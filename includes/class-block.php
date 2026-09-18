<?php
/**
 * The SuperQuest block.
 *
 * @package SuperQuest
 */

namespace SuperQuest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the block and renders it.
 */
final class Block {

	/**
	 * Block name.
	 */
	public const NAME = 'superquest/quest';

	/**
	 * Hooks the block registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_block' ) );
	}

	/**
	 * Registers the block from its built metadata.
	 *
	 * @return void
	 */
	public static function register_block(): void {
		register_block_type( SUPERQUEST_PATH . 'build/quest' );
	}

	/**
	 * Prints the block on the front end.
	 *
	 * The markup is the external loader's contract: it looks for `.jquest-app`
	 * elements and reads `data-org-id`, `data-game-id`, `data-version` and
	 * `data-new-styles`. These names come from the SuperQuest loader and cannot
	 * be renamed here.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 *
	 * @return void
	 */
	public static function output( array $attributes ): void {
		$quest_id     = isset( $attributes['questId'] ) && is_scalar( $attributes['questId'] )
			? trim( (string) $attributes['questId'] )
			: '';
		$organization = Options::organization_id();

		if ( '' === $quest_id || '' === $organization ) {
			return;
		}

		// `has_block()` only sees the queried post, so a block inside a template
		// part, synced pattern or Query Loop item is invisible to the head
		// enqueue in Loader. Enqueuing here prints the loader in the footer
		// instead; it waits for DOMContentLoaded, so that still works.
		Loader::enqueue();

		$wrapper = get_block_wrapper_attributes(
			array(
				'class'           => 'jquest-app',
				'data-org-id'     => $organization,
				'data-game-id'    => $quest_id,
				'data-version'    => Loader::SCRIPT_VERSION,
				'data-new-styles' => 'true',
			)
		);

		// get_block_wrapper_attributes() escapes every attribute it returns.
		echo '<div ' . $wrapper . '></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
