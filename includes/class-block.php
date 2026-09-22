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
	 * The attributes come from Embed, which every embed path shares, but they
	 * go out through `get_block_wrapper_attributes()` so the block also picks
	 * up the alignment and style classes the editor gives it.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 *
	 * @return void
	 */
	public static function output( array $attributes ): void {
		$quest_id = isset( $attributes['questId'] ) && is_scalar( $attributes['questId'] )
			? (string) $attributes['questId']
			: '';

		$embed = Embed::attributes( $quest_id );

		if ( array() === $embed ) {
			return;
		}

		// `has_block()` only sees the queried post, so a block inside a template
		// part, synced pattern or Query Loop item is invisible to the head
		// enqueue in Loader. Enqueuing here prints the loader in the footer
		// instead; it waits for DOMContentLoaded, so that still works.
		Loader::enqueue();

		// get_block_wrapper_attributes() escapes every attribute it returns.
		echo '<div ' . get_block_wrapper_attributes( $embed ) . '></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
