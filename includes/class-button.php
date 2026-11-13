<?php
/**
 * Front-end handling of core Button blocks opening a popup quest.
 *
 * @package SuperQuest
 */

namespace SuperQuest;

use WP_HTML_Tag_Processor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Marks Button blocks linking to `#superquest-ID` with a CSS class.
 *
 * The editor side, a quest picker in the Button block's sidebar, only sets
 * the link URL; the button stays a core Button, so it keeps the theme's
 * button styles and still works if this plugin is turned off.
 */
final class Button {

	/**
	 * CSS class added to the wrapper of every SuperQuest button.
	 */
	public const CSS_CLASS = 'superquest-button';

	/**
	 * Hooks the block render filter.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'render_block_core/button', array( self::class, 'add_css_class' ), 10, 2 );
	}

	/**
	 * Adds the SuperQuest class to the button's wrapper `<div>`.
	 *
	 * @param string               $content Rendered block.
	 * @param array<string, mixed> $block   Parsed block.
	 *
	 * @return string
	 */
	public static function add_css_class( $content, $block ) {
		if ( ! is_string( $content ) || '' === $content || ! is_array( $block ) ) {
			return $content;
		}

		$url = $block['attrs']['url'] ?? '';
		if ( ! is_string( $url ) || null === Popup::link_quest_id( $url ) ) {
			return $content;
		}

		$tags = new WP_HTML_Tag_Processor( $content );
		if ( ! $tags->next_tag() ) {
			return $content;
		}

		$tags->add_class( self::CSS_CLASS );

		return $tags->get_updated_html();
	}
}
