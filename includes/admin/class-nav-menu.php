<?php
/**
 * SuperQuest box on Appearance → Menus.
 *
 * @package SuperQuest
 */

namespace SuperQuest\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds popup quests to classic menus as custom links to `#superquest-ID`.
 *
 * The links are plain custom links, so core saves them, their labels stay
 * editable, and they keep opening the popup without this plugin: the
 * SuperQuest loader reacts to the hash by itself.
 */
final class Nav_Menu {

	/**
	 * Meta box ID.
	 */
	public const BOX_ID = 'superquest-nav-menu';

	/**
	 * Hooks the meta box to the Menus screen.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'load-nav-menus.php', array( self::class, 'add_meta_box' ) );
		add_filter( 'get_user_option_metaboxhidden_nav-menus', array( self::class, 'show_meta_box' ) );
	}

	/**
	 * Adds the meta box.
	 *
	 * @return void
	 */
	public static function add_meta_box(): void {
		add_meta_box(
			self::BOX_ID,
			__( 'SuperQuest', 'superquest' ),
			array( self::class, 'render' ),
			'nav-menus',
			'side',
			'low'
		);
	}

	/**
	 * Keeps the box visible. Core hides meta boxes it has not seen before on
	 * the Menus screen, which would leave the box behind Screen Options.
	 *
	 * @param mixed $hidden Hidden meta box IDs, or false when never saved.
	 *
	 * @return mixed
	 */
	public static function show_meta_box( $hidden ) {
		if ( ! is_array( $hidden ) ) {
			return $hidden;
		}

		return array_values( array_diff( $hidden, array( self::BOX_ID ) ) );
	}

	/**
	 * Renders the meta box.
	 *
	 * @return void
	 */
	public static function render(): void {
		require SUPERQUEST_PATH . 'views/admin/nav-menu.php';
	}
}
