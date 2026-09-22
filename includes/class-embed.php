<?php
/**
 * The quest element every embed renders.
 *
 * @package SuperQuest
 */

namespace SuperQuest;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the element the external loader hydrates, and spots one on a post.
 *
 * The class and data attributes are the loader's contract: it scans for
 * `.jquest-app` elements and reads `data-jq-load`, and the app then reads
 * `data-org-id` and `data-game-id` off each one. These names come from
 * SuperQuest and cannot be renamed here, which is exactly why they are
 * written in one place: the block, the popup, the shortcode and the
 * Elementor widget all come through here.
 */
final class Embed {

	/**
	 * The class the loader scans for.
	 */
	public const CLASS_NAME = 'jquest-app';

	/**
	 * The load triggers the loader understands. It logs and ignores any other
	 * value, so an unrecognised one is left off the element altogether rather
	 * than written and discarded.
	 */
	public const LOAD_TRIGGERS = array( 'eager', 'hover', 'click' );

	/**
	 * A plain HTML attribute name. Anything else is dropped before printing,
	 * so no value can carry an attribute out of its quotes.
	 */
	private const ATTRIBUTE_NAME = '/^[a-zA-Z][a-zA-Z0-9_:-]*$/';

	/**
	 * The attributes a quest element carries.
	 *
	 * The order is the block's. `data-locale` and `data-jq-load` are only
	 * written when asked for, and follow the ones the app always needs.
	 *
	 * @param string               $quest_id Quest ID.
	 * @param array<string, mixed> $args {
	 *     Optional arguments.
	 *
	 *     @type string $class  Extra class names, appended after the loader's own.
	 *     @type string $load   One of `eager`, `hover` or `click`. Anything else is left off.
	 *     @type string $locale Value for `data-locale`. Omitted unless the key is given.
	 * }
	 *
	 * @return array<string, string> Attributes, or an empty array when there is nothing to render.
	 */
	public static function attributes( string $quest_id, array $args = array() ): array {
		$quest_id     = trim( $quest_id );
		$organization = Options::organization_id();

		if ( '' === $quest_id || '' === $organization ) {
			return array();
		}

		$class = self::CLASS_NAME;
		$extra = isset( $args['class'] ) ? self::sanitize_classes( (string) $args['class'] ) : '';

		if ( '' !== $extra ) {
			$class .= ' ' . $extra;
		}

		$attributes = array(
			'class'        => $class,
			'data-org-id'  => $organization,
			'data-game-id' => $quest_id,
			'data-version' => Loader::SCRIPT_VERSION,
		);

		// Presence decides: no key omits the attribute, an empty string still
		// writes an empty one, which is what the popup has always printed.
		if ( isset( $args['locale'] ) && is_string( $args['locale'] ) ) {
			$attributes['data-locale'] = $args['locale'];
		}

		$load = isset( $args['load'] ) ? (string) $args['load'] : '';

		if ( in_array( $load, self::LOAD_TRIGGERS, true ) ) {
			$attributes['data-jq-load'] = $load;
		}

		return $attributes;
	}

	/**
	 * The quest element, ready to print.
	 *
	 * @param string               $quest_id Quest ID.
	 * @param array<string, mixed> $args     See attributes().
	 *
	 * @return string Escaped HTML, or an empty string when there is nothing to render.
	 */
	public static function markup( string $quest_id, array $args = array() ): string {
		$attributes = self::attributes( $quest_id, $args );

		if ( array() === $attributes ) {
			return '';
		}

		return '<div' . self::attribute_string( $attributes ) . '></div>';
	}

	/**
	 * Prints the quest element.
	 *
	 * @param string               $quest_id Quest ID.
	 * @param array<string, mixed> $args     See attributes().
	 *
	 * @return void
	 */
	public static function render( string $quest_id, array $args = array() ): void {
		// markup() escapes every value and drops any attribute name that is not
		// a plain HTML name, so the string is safe to print as it stands.
		echo self::markup( $quest_id, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Escaped ` name="value"` pairs, each preceded by a space.
	 *
	 * @param array<string, string> $attributes Attribute map.
	 *
	 * @return string
	 */
	public static function attribute_string( array $attributes ): string {
		$html = '';

		foreach ( $attributes as $name => $value ) {
			if ( 1 !== preg_match( self::ATTRIBUTE_NAME, (string) $name ) ) {
				continue;
			}

			$html .= ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
		}

		return $html;
	}

	/**
	 * The language slug written to popup quests as `data-locale`.
	 *
	 * @return string
	 */
	public static function locale(): string {
		$locale = function_exists( 'pll_current_language' ) ? (string) pll_current_language() : '';

		/**
		 * Filters the language slug written to popup quests as `data-locale`.
		 *
		 * Polylang is read automatically; other multilingual plugins can hook
		 * here. An empty string lets the loader use its default.
		 *
		 * @param string $locale Language slug, e.g. `fi`.
		 */
		return (string) apply_filters( 'superquest_locale', $locale );
	}

	/**
	 * Whether a post holds a quest, in any of the ways one can be embedded.
	 *
	 * @param WP_Post|null $post Post to inspect.
	 *
	 * @return bool
	 */
	public static function in_post( ?WP_Post $post ): bool {
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		if ( has_block( Block::NAME, $post ) ) {
			return true;
		}

		if ( has_shortcode( $post->post_content, Shortcode::TAG ) ) {
			return true;
		}

		return self::in_elementor_data( $post->ID );
	}

	/**
	 * Whether a post's Elementor tree holds the quest widget.
	 *
	 * Elementor keeps its tree as JSON in post meta, where neither
	 * `has_block()` nor `has_shortcode()` can see it. Sites without Elementor
	 * read nothing at all, and the meta of the post being viewed is already in
	 * the object cache, so this costs no extra query.
	 *
	 * The needle is the widget name alone rather than the whole `widgetType`
	 * pair, because the key order of re-encoded JSON is not a contract. A
	 * false positive costs one unnecessary preload hint and nothing else.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool
	 */
	private static function in_elementor_data( int $post_id ): bool {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return false;
		}

		$data = get_post_meta( $post_id, Elementor\Integration::META_KEY, true );

		if ( ! is_string( $data ) || '' === $data ) {
			return false;
		}

		// The widget, or a shortcode typed into one of Elementor's own.
		return str_contains( $data, Elementor\Integration::needle() )
			|| str_contains( $data, '[' . Shortcode::TAG );
	}

	/**
	 * Class names reduced to the ones HTML allows.
	 *
	 * @param string $classes Space-separated class names.
	 *
	 * @return string
	 */
	private static function sanitize_classes( string $classes ): string {
		$names = preg_split( '/\s+/', trim( $classes ) );

		if ( ! is_array( $names ) ) {
			return '';
		}

		return implode( ' ', array_filter( array_map( 'sanitize_html_class', $names ) ) );
	}
}
