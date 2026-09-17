<?php
/**
 * The organisation's quest list: fetched from the SuperQuest API and cached.
 *
 * @package SuperQuest
 */

namespace SuperQuest;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches, caches and reads the quest list.
 */
final class Quests {

	/**
	 * SuperQuest API endpoint listing an organisation's quests. The path is the
	 * vendor's name for it and is not ours to rename.
	 */
	public const API_URL = 'https://api.jquest.fi/organizationgames-getorganizationgames';

	/**
	 * SuperQuest API request timeout in seconds. Sized for cold starts on
	 * serverless endpoints.
	 */
	public const REQUEST_TIMEOUT = 25;

	/**
	 * Transient held while a refresh is in flight or was just done.
	 */
	public const LOCK_TRANSIENT = 'superquest_refresh_lock';

	/**
	 * Seconds between two refreshes.
	 */
	private const LOCK_TTL = 30;

	/**
	 * Hooks the cache to the organisation setting.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'add_option_' . Options::ORGANIZATION_ID, array( self::class, 'on_organization_added' ), 10, 2 );
		add_action( 'update_option_' . Options::ORGANIZATION_ID, array( self::class, 'on_organization_updated' ), 10, 2 );
	}

	/**
	 * Fetches the quest list when the organisation is first set.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Saved value.
	 *
	 * @return void
	 */
	public static function on_organization_added( string $option, $value ): void {
		self::on_organization_updated( '', $value );
	}

	/**
	 * Refetches the quest list when the organisation changes, or clears it when
	 * the organisation is removed.
	 *
	 * @param mixed $old_value Previous value.
	 * @param mixed $value     New value.
	 *
	 * @return void
	 */
	public static function on_organization_updated( $old_value, $value ): void {
		if ( $old_value === $value ) {
			return;
		}

		if ( '' === Options::organization_id() ) {
			self::clear();
			return;
		}

		delete_transient( self::LOCK_TRANSIENT );
		self::refresh();
	}

	/**
	 * Fetches the organisation's quests and stores them.
	 *
	 * @return true|WP_Error True on success, otherwise the reason. A failed fetch
	 *                       also empties the cache and stores the API message.
	 */
	public static function refresh() {
		$organization = Options::organization_id();
		if ( '' === $organization ) {
			return new WP_Error(
				'superquest_no_organization',
				__( 'Set an Organisation ID before fetching quests.', 'superquest' ),
				array( 'status' => 400 )
			);
		}

		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			return new WP_Error(
				'superquest_refresh_locked',
				__( 'The quest list was refreshed a moment ago. Try again shortly.', 'superquest' ),
				array( 'status' => 429 )
			);
		}
		set_transient( self::LOCK_TRANSIENT, time(), self::LOCK_TTL );

		$response = wp_remote_get(
			add_query_arg(
				array(
					'v2'    => 'true',
					'orgId' => rawurlencode( $organization ),
				),
				self::API_URL
			),
			array(
				'timeout'    => self::REQUEST_TIMEOUT,
				'user-agent' => 'SuperQuest/' . SUPERQUEST_VERSION . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::store( array(), $response->get_error_message() );

			return new WP_Error(
				'superquest_api_unreachable',
				__( 'Could not reach the SuperQuest API.', 'superquest' ),
				array( 'status' => 502 )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			self::store( array(), __( 'The SuperQuest API returned an unexpected response.', 'superquest' ) );

			return new WP_Error(
				'superquest_api_invalid',
				__( 'The SuperQuest API returned an unexpected response.', 'superquest' ),
				array( 'status' => 502 )
			);
		}

		$message = isset( $body['message'] ) && is_scalar( $body['message'] ) ? (string) $body['message'] : '';

		if ( empty( $body['success'] ) ) {
			self::store( array(), $message );

			return new WP_Error(
				'superquest_api_failed',
				'' !== $message ? $message : __( 'The SuperQuest API rejected the request.', 'superquest' ),
				array( 'status' => 502 )
			);
		}

		// The API still returns a retired quest generation under `data`; only
		// `quests` holds the current one.
		self::store( self::normalize( $body['quests'] ?? array() ), $message );

		return true;
	}

	/**
	 * The cached quests.
	 *
	 * @return array<int, array{id: string, title: string}>
	 */
	public static function items(): array {
		return self::cache()['items'];
	}

	/**
	 * Quest titles keyed by quest ID.
	 *
	 * @return array<string, string>
	 */
	public static function titles(): array {
		$titles = array();
		foreach ( self::items() as $quest ) {
			$titles[ $quest['id'] ] = $quest['title'];
		}

		return $titles;
	}

	/**
	 * The message the API sent with the last fetch. Untrusted; escape on output.
	 *
	 * @return string
	 */
	public static function message(): string {
		return self::cache()['message'];
	}

	/**
	 * Unix timestamp of the last fetch, or 0 when never fetched.
	 *
	 * @return int
	 */
	public static function updated(): int {
		return self::cache()['updated'];
	}

	/**
	 * Empties the cache.
	 *
	 * @return void
	 */
	public static function clear(): void {
		delete_option( Options::QUESTS );
		delete_transient( self::LOCK_TRANSIENT );
	}

	/**
	 * Reads the cache in a predictable shape.
	 *
	 * @return array{items: array<int, array{id: string, title: string}>, message: string, updated: int}
	 */
	private static function cache(): array {
		$stored = get_option( Options::QUESTS, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'items'   => self::normalize( $stored['items'] ?? array() ),
			'message' => isset( $stored['message'] ) && is_scalar( $stored['message'] ) ? (string) $stored['message'] : '',
			'updated' => isset( $stored['updated'] ) ? (int) $stored['updated'] : 0,
		);
	}

	/**
	 * Writes the cache. The option is not autoloaded: it is only read in the
	 * admin and by the popup settings.
	 *
	 * @param array<int, array{id: string, title: string}> $items   Quests.
	 * @param string                                       $message API message.
	 *
	 * @return void
	 */
	private static function store( array $items, string $message ): void {
		$value = array(
			'items'   => $items,
			'message' => sanitize_text_field( $message ),
			'updated' => time(),
		);

		if ( false === get_option( Options::QUESTS, false ) ) {
			add_option( Options::QUESTS, $value, '', false );
			return;
		}

		update_option( Options::QUESTS, $value, false );
	}

	/**
	 * Reduces whatever the API (or an old cache) holds to a list of quests with
	 * a string ID and title, dropping anything else.
	 *
	 * @param mixed $quests Raw quest collection.
	 *
	 * @return array<int, array{id: string, title: string}>
	 */
	private static function normalize( $quests ): array {
		if ( ! is_array( $quests ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $quests as $quest ) {
			$quest = is_object( $quest ) ? get_object_vars( $quest ) : $quest;
			if ( ! is_array( $quest ) || ! isset( $quest['id'] ) || ! is_scalar( $quest['id'] ) ) {
				continue;
			}

			$id = sanitize_text_field( (string) $quest['id'] );
			if ( '' === $id ) {
				continue;
			}

			$normalized[] = array(
				'id'    => $id,
				'title' => isset( $quest['title'] ) && is_scalar( $quest['title'] )
					? sanitize_text_field( (string) $quest['title'] )
					: '',
			);
		}

		return $normalized;
	}
}
