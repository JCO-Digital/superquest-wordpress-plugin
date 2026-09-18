<?php
/**
 * The `[superquest]` shortcode.
 *
 * @package SuperQuest
 */

namespace SuperQuest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Places a quest anywhere content is run through `do_shortcode()`.
 *
 * This is the embed path for everything that is not the block editor: page
 * builders, the classic editor, widgets and theme templates. Elementor has a
 * widget of its own, but its Shortcode widget works through here too.
 */
final class Shortcode {

	/**
	 * Shortcode tag.
	 */
	public const TAG = 'superquest';

	/**
	 * Hooks the registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_shortcode' ) );
	}

	/**
	 * Registers the shortcode.
	 *
	 * @return void
	 */
	public static function register_shortcode(): void {
		add_shortcode( self::TAG, array( self::class, 'render' ) );
	}

	/**
	 * Renders the shortcode.
	 *
	 * Nothing is printed and no script is enqueued when the quest is missing
	 * or the site has no organisation: a shortcode can turn up in an excerpt
	 * or a widget, where a visible error would be worse than silence.
	 *
	 * @param array<int|string, string>|string $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public static function render( $atts ): string {
		$raw    = is_array( $atts ) ? $atts : array();
		$parsed = shortcode_atts(
			array(
				'id'    => '',
				'load'  => '',
				'class' => '',
			),
			$raw,
			self::TAG
		);

		// `[superquest abc123]` arrives as a positional attribute, which
		// shortcode_atts() drops, so the bare form is read back by hand.
		$quest_id = (string) $parsed['id'];
		if ( '' === $quest_id && isset( $raw[0] ) && is_scalar( $raw[0] ) ) {
			$quest_id = (string) $raw[0];
		}

		$markup = Embed::markup(
			sanitize_text_field( $quest_id ),
			array(
				'load'  => strtolower( trim( (string) $parsed['load'] ) ),
				'class' => (string) $parsed['class'],
			)
		);

		if ( '' === $markup ) {
			return '';
		}

		// The head enqueue only sees the queried post, so a shortcode in a
		// widget, a template or a Query Loop item needs the footer fallback,
		// exactly as the block does.
		Loader::enqueue();

		return $markup;
	}
}
