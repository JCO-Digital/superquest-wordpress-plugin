<?php
/**
 * Option names, registration and typed accessors.
 *
 * @package SuperQuest
 */

namespace SuperQuest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every option the plugin stores, in one place.
 */
final class Options {

	/**
	 * The SuperQuest organisation whose quests are offered.
	 */
	public const ORGANIZATION_ID = 'superquest_organization_id';

	/**
	 * Cached quest list fetched from the SuperQuest API. Not a setting.
	 */
	public const QUESTS = 'superquest_quests';

	/**
	 * Load the loader on every front-end page, quest or not.
	 */
	public const ALWAYS_LOAD = 'superquest_always_load';

	/**
	 * Mark the loader as essential for consent managers.
	 */
	public const CONSENT_BYPASS = 'superquest_consent_bypass';

	/**
	 * Quests inserted above the footer on every page.
	 */
	public const POPUP_QUESTS = 'superquest_popup_quests';

	/**
	 * Post IDs no popup quest is inserted on.
	 */
	public const POPUP_EXCLUDED_IDS = 'superquest_popup_excluded_ids';

	/**
	 * Settings group of the General screen.
	 */
	public const GROUP_GENERAL = 'superquest_general';

	/**
	 * Settings group of the Popup screen.
	 */
	public const GROUP_POPUP = 'superquest_popup';

	/**
	 * Registers every setting the admin screens save.
	 *
	 * Runs on `init` rather than `admin_init` so the defaults also apply to
	 * front-end reads.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_setting(
			self::GROUP_GENERAL,
			self::ORGANIZATION_ID,
			array(
				'type'              => 'string',
				'default'           => '',
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			self::GROUP_POPUP,
			self::ALWAYS_LOAD,
			array(
				'type'              => 'boolean',
				'default'           => false,
				'show_in_rest'      => false,
				'sanitize_callback' => array( self::class, 'sanitize_bool' ),
			)
		);

		register_setting(
			self::GROUP_POPUP,
			self::CONSENT_BYPASS,
			array(
				'type'              => 'boolean',
				'default'           => false,
				'show_in_rest'      => false,
				'sanitize_callback' => array( self::class, 'sanitize_bool' ),
			)
		);

		register_setting(
			self::GROUP_POPUP,
			self::POPUP_EXCLUDED_IDS,
			array(
				'type'              => 'array',
				'default'           => array(),
				'show_in_rest'      => false,
				'sanitize_callback' => array( self::class, 'sanitize_id_list' ),
			)
		);

		register_setting(
			self::GROUP_POPUP,
			self::POPUP_QUESTS,
			array(
				'type'              => 'array',
				'default'           => array(),
				'show_in_rest'      => false,
				'sanitize_callback' => array( self::class, 'sanitize_popup_quests' ),
			)
		);
	}

	/**
	 * The configured organisation ID, trimmed.
	 *
	 * @return string
	 */
	public static function organization_id(): string {
		$value = get_option( self::ORGANIZATION_ID, '' );

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Whether the loader is loaded on every page.
	 *
	 * @return bool
	 */
	public static function always_load(): bool {
		return (bool) get_option( self::ALWAYS_LOAD, false );
	}

	/**
	 * Whether the loader is marked as essential for consent managers.
	 *
	 * @return bool
	 */
	public static function consent_bypass(): bool {
		return (bool) get_option( self::CONSENT_BYPASS, false );
	}

	/**
	 * The popup quests, in configured order.
	 *
	 * @return array<int, array{quest_id: string, enabled: bool}>
	 */
	public static function popup_quests(): array {
		return self::sanitize_popup_quests( get_option( self::POPUP_QUESTS, array() ) );
	}

	/**
	 * Post IDs excluded from popup quests.
	 *
	 * @return int[]
	 */
	public static function popup_excluded_ids(): array {
		return self::sanitize_id_list( get_option( self::POPUP_EXCLUDED_IDS, array() ) );
	}

	/**
	 * Sanitises a checkbox value. An unchecked box arrives as null.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return bool
	 */
	public static function sanitize_bool( $value ): bool {
		return rest_sanitize_boolean( $value );
	}

	/**
	 * Sanitises a list of post IDs typed as text (commas or whitespace) or
	 * already stored as an array.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return int[] Unique positive IDs.
	 */
	public static function sanitize_id_list( $value ): array {
		if ( is_scalar( $value ) ) {
			$value = (string) $value;
		} elseif ( ! is_array( $value ) ) {
			$value = array();
		}

		return array_values( array_filter( wp_parse_id_list( $value ) ) );
	}

	/**
	 * Sanitises the popup quest list posted from the settings screen.
	 *
	 * A row is deleted by ticking its remove box or emptying its quest; both
	 * are dropped here. Duplicates collapse into one entry, enabled if any of
	 * them was.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return array<int, array{quest_id: string, enabled: bool}>
	 */
	public static function sanitize_popup_quests( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$quests = array();
		foreach ( $value as $entry ) {
			if ( ! is_array( $entry ) || ! empty( $entry['remove'] ) ) {
				continue;
			}

			$quest_id = isset( $entry['quest_id'] ) && is_scalar( $entry['quest_id'] )
				? sanitize_text_field( (string) $entry['quest_id'] )
				: '';
			if ( '' === $quest_id ) {
				continue;
			}

			$enabled = ! empty( $entry['enabled'] );

			$quests[ $quest_id ] = array(
				'quest_id' => $quest_id,
				'enabled'  => $enabled || ! empty( $quests[ $quest_id ]['enabled'] ),
			);
		}

		return array_values( $quests );
	}
}
