<?php
/**
 * Loads the SuperQuest loader script and the hints that speed it up.
 *
 * @package SuperQuest
 */

namespace SuperQuest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one external script every quest on the page runs on.
 */
final class Loader {

	/**
	 * Script handle.
	 */
	public const HANDLE = 'superquest-loader';

	/**
	 * The SuperQuest build the loader fetches. Set on `window.__JQUEST_VERSION`
	 * before the loader runs and written to every quest element.
	 */
	public const SCRIPT_VERSION = 'v2';

	/**
	 * Origin the loader and bundle are served from.
	 */
	public const CDN_ORIGIN = 'https://files.jquest.fi';

	/**
	 * The loader script. It picks the build named by `window.__JQUEST_VERSION`.
	 */
	public const LOADER_URL = self::CDN_ORIGIN . '/jquest/jquest-loader.js';

	/**
	 * Directory holding the build's manifest.json and the chunks it lists.
	 */
	public const BUNDLE_BASE_URL = self::CDN_ORIGIN . '/jquest/' . self::SCRIPT_VERSION;

	/**
	 * Transient caching the bundle manifest.
	 */
	public const MANIFEST_TRANSIENT = 'superquest_manifest';

	/**
	 * How long a fetched manifest is reused. The CDN caches it for a minute, so
	 * hints can lag a deploy by a few minutes; the loader reads the live
	 * manifest itself, so a stale hint costs a wasted request and nothing else.
	 */
	private const MANIFEST_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Whether the bundle is worth fetching before the visitor does anything,
	 * i.e. a quest is known to be on the page.
	 *
	 * @var bool
	 */
	private static bool $preload = false;

	/**
	 * Hooks registration, conditional enqueue, tag attributes and hints.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_script' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'maybe_enqueue' ) );
		add_filter( 'wp_script_attributes', array( self::class, 'script_attributes' ) );
		add_filter( 'wp_inline_script_attributes', array( self::class, 'inline_script_attributes' ) );
		add_filter( 'wp_resource_hints', array( self::class, 'resource_hints' ), 10, 2 );
		add_filter( 'wp_preload_resources', array( self::class, 'preload_resources' ) );
		add_action( 'wp_head', array( self::class, 'print_module_preloads' ), 3 );
	}

	/**
	 * Registers the loader with its version global, so every later enqueue,
	 * including the footer fallback from a block render, prints both.
	 *
	 * The loader is a classic IIFE that waits for DOMContentLoaded, so it goes
	 * out async: it never blocks parsing and runs as soon as it arrives.
	 *
	 * @return void
	 */
	public static function register_script(): void {
		wp_register_script(
			self::HANDLE,
			self::LOADER_URL,
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the vendor's loader is unversioned by design.
			array(
				'strategy'  => 'async',
				'in_footer' => false,
			)
		);

		// A `before` inline script is a plain blocking tag printed right ahead
		// of the async one, and an async script cannot run before the parser
		// has reached its own tag, so the global is always set first.
		wp_add_inline_script(
			self::HANDLE,
			sprintf( 'window.__JQUEST_VERSION = %s;', wp_json_encode( self::SCRIPT_VERSION ) ),
			'before'
		);
	}

	/**
	 * Enqueues the loader on pages that need it: a quest block in the queried
	 * post, an active popup quest, or the always-load setting.
	 *
	 * @return void
	 */
	public static function maybe_enqueue(): void {
		$has_block = has_block( Block::NAME );
		$has_popup = array() !== Popup::active_ids();

		if ( ! $has_block && ! $has_popup && ! Options::always_load() ) {
			return;
		}

		// Only preload the bundle when a quest is known to be on the page. The
		// always-load setting alone may never need it, and a megabyte of vendor
		// code fetched for nothing would only slow the host page down.
		self::enqueue( $has_block || $has_popup );
	}

	/**
	 * Enqueues the loader. Safe to call more than once.
	 *
	 * @param bool $preload Whether to also hint the bundle for early fetching.
	 *
	 * @return void
	 */
	public static function enqueue( bool $preload = false ): void {
		wp_enqueue_script( self::HANDLE );

		if ( $preload ) {
			self::$preload = true;
		}
	}

	/**
	 * Adds the CORS and consent attributes to the loader tag.
	 *
	 * @param array<string, mixed> $attributes Tag attributes.
	 *
	 * @return array<string, mixed>
	 */
	public static function script_attributes( array $attributes ): array {
		if ( self::HANDLE . '-js' !== ( $attributes['id'] ?? '' ) ) {
			return $attributes;
		}

		return array_merge( $attributes, array( 'crossorigin' => 'anonymous' ), self::consent_attributes() );
	}

	/**
	 * Adds the consent attributes to the inline version tag as well, so a
	 * consent manager that blocks one does not leave the other dangling.
	 *
	 * @param array<string, mixed> $attributes Tag attributes.
	 *
	 * @return array<string, mixed>
	 */
	public static function inline_script_attributes( array $attributes ): array {
		if ( self::HANDLE . '-js-before' !== ( $attributes['id'] ?? '' ) ) {
			return $attributes;
		}

		return array_merge( $attributes, self::consent_attributes() );
	}

	/**
	 * Attributes telling Cookiebot, OneTrust and CookieYes not to gate the
	 * loader. Off unless the site owner opts in.
	 *
	 * @return array<string, string|bool>
	 */
	private static function consent_attributes(): array {
		$attributes = array();

		if ( Options::consent_bypass() ) {
			$attributes = array(
				'data-cookieconsent' => 'ignore',
				'data-ot-ignore'     => true,
				'data-cookieyes'     => 'ignore',
			);
		}

		/**
		 * Filters the extra attributes printed on the SuperQuest loader tags.
		 *
		 * @param array<string, string|bool> $attributes Attribute map. A `true` value prints a bare attribute.
		 */
		return (array) apply_filters( 'superquest_loader_attributes', $attributes );
	}

	/**
	 * Opens the CDN connection early. Every request after the loader itself is
	 * anonymous CORS, hence the crossorigin hint.
	 *
	 * @param array<int, string|array<string, string>> $urls          Hints.
	 * @param string                                   $relation_type Hint type.
	 *
	 * @return array<int, string|array<string, string>>
	 */
	public static function resource_hints( array $urls, string $relation_type ): array {
		if ( 'preconnect' === $relation_type && self::$preload ) {
			$urls[] = array(
				'href'        => self::CDN_ORIGIN,
				'crossorigin' => 'anonymous',
			);
		}

		return $urls;
	}

	/**
	 * Preloads the manifest the loader fetches first.
	 *
	 * @param array<int, array<string, string>> $resources Preload resources.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function preload_resources( array $resources ): array {
		if ( self::$preload ) {
			$resources[] = array(
				'href'        => self::BUNDLE_BASE_URL . '/manifest.json',
				'as'          => 'fetch',
				'crossorigin' => 'anonymous',
			);
		}

		return $resources;
	}

	/**
	 * Hints the module chunks so the browser fetches the whole bundle in one
	 * round trip instead of manifest, then entry, then vendors. Core has no
	 * API for modulepreload, so the links are printed here.
	 *
	 * @return void
	 */
	public static function print_module_preloads(): void {
		if ( ! self::$preload ) {
			return;
		}

		foreach ( self::module_preload_urls() as $url ) {
			printf( '<link rel="modulepreload" href="%s" crossorigin>' . "\n", esc_url( $url ) );
		}
	}

	/**
	 * The chunks worth preloading: the entry chunk plus what it imports
	 * statically. A manifest may name them in `preload`; without one, every
	 * vendor chunk except Sentry is assumed to be a static import, which
	 * matches the current bundle. Chunks loaded on demand are skipped.
	 *
	 * @return string[] Absolute URLs, entry first.
	 */
	private static function module_preload_urls(): array {
		$manifest = self::manifest();
		$app      = $manifest['app'] ?? '';
		if ( ! is_string( $app ) || '' === $app ) {
			return array();
		}

		$files = $manifest['preload'] ?? null;
		if ( ! is_array( $files ) ) {
			$files = array_filter(
				(array) ( $manifest['files'] ?? array() ),
				static function ( $file ): bool {
					return is_string( $file )
						&& str_contains( $file, '-vendor-' )
						&& ! str_contains( $file, '-vendor-sentry-' );
				}
			);
		}

		$urls = array( self::BUNDLE_BASE_URL . '/' . $app );
		foreach ( $files as $file ) {
			if ( is_string( $file ) && '' !== $file && $app !== $file ) {
				$urls[] = self::BUNDLE_BASE_URL . '/' . $file;
			}
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Fetches and caches the bundle manifest. Failures are cached too, so an
	 * unreachable CDN costs one attempt per TTL rather than one per page view.
	 *
	 * @return array<string, mixed> Decoded manifest, or an empty array.
	 */
	private static function manifest(): array {
		$cached = get_transient( self::MANIFEST_TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$manifest = array();
		$response = wp_remote_get( self::BUNDLE_BASE_URL . '/manifest.json', array( 'timeout' => 2 ) );

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $decoded ) ) {
				$manifest = $decoded;
			}
		}

		set_transient( self::MANIFEST_TRANSIENT, $manifest, self::MANIFEST_TTL );

		return $manifest;
	}
}
