<?php
/**
 * Front-end handling of menu items opening a popup quest.
 *
 * @package SuperQuest
 */

namespace SuperQuest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles `#superquest-ID` menu items: labels them "SuperQuest" in the menu
 * editor, adds a CSS class to them on the front end, and hides them on pages
 * where their quest is not inserted, so no link is shown that would do nothing.
 */
final class Menu_Items {

	/**
	 * CSS class added to the `<li>` of every SuperQuest menu item.
	 */
	public const CSS_CLASS = 'menu-item-superquest';

	/**
	 * Hooks the menu filters.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_setup_nav_menu_item', array( self::class, 'set_type_label' ) );
		add_filter( 'nav_menu_css_class', array( self::class, 'add_css_class' ), 10, 2 );
		add_filter( 'wp_nav_menu_objects', array( self::class, 'filter' ) );
	}

	/**
	 * Shows "SuperQuest" instead of "Custom Link" as the item type in the menu
	 * editor.
	 *
	 * @param object $item Menu item.
	 *
	 * @return object
	 */
	public static function set_type_label( $item ) {
		if ( is_object( $item ) && null !== self::item_quest_id( $item ) ) {
			$item->type_label = __( 'SuperQuest', 'superquest' );
		}

		return $item;
	}

	/**
	 * Adds the SuperQuest class to the menu item's `<li>` on the front end.
	 *
	 * @param string[] $classes Item classes.
	 * @param object   $item    Menu item.
	 *
	 * @return string[]
	 */
	public static function add_css_class( $classes, $item ) {
		$classes = is_array( $classes ) ? $classes : array();

		if ( is_object( $item ) && null !== self::item_quest_id( $item ) ) {
			$classes[] = self::CSS_CLASS;
		}

		return $classes;
	}

	/**
	 * The quest a menu item opens, as quest_id() reports it, or null when the
	 * item is not a custom link to a quest.
	 *
	 * @param object $item Menu item.
	 *
	 * @return string|null
	 */
	private static function item_quest_id( object $item ): ?string {
		if ( 'custom' !== ( $item->type ?? '' ) ) {
			return null;
		}

		return self::quest_id( (string) ( $item->url ?? '' ) );
	}

	/**
	 * The quest a menu item URL opens: the quest ID, an empty string for the
	 * bare `#superquest` (every popup quest), or null for any other URL.
	 *
	 * @param string $url Menu item URL.
	 *
	 * @return string|null
	 */
	public static function quest_id( string $url ): ?string {
		$fragment = wp_parse_url( $url, PHP_URL_FRAGMENT );
		if ( ! is_string( $fragment ) ) {
			return null;
		}

		if ( 'superquest' === $fragment ) {
			return '';
		}

		if ( ! str_starts_with( $fragment, 'superquest-' ) ) {
			return null;
		}

		$quest_id = rawurldecode( substr( $fragment, strlen( 'superquest-' ) ) );

		return '' !== $quest_id ? $quest_id : null;
	}

	/**
	 * Drops quest items whose popup is not on the page, with their submenus.
	 *
	 * @param array<int, object> $items Sorted menu items.
	 *
	 * @return array<int, object>
	 */
	public static function filter( array $items ): array {
		$active  = null;
		$removed = array();

		foreach ( $items as $key => $item ) {
			$parent = (int) ( $item->menu_item_parent ?? 0 );
			if ( $parent > 0 && isset( $removed[ $parent ] ) ) {
				$removed[ (int) $item->ID ] = true;
				unset( $items[ $key ] );
				continue;
			}

			$quest_id = self::item_quest_id( $item );
			if ( null === $quest_id ) {
				continue;
			}

			$active ??= Popup::active_ids();

			$shown = '' === $quest_id
				? array() !== $active
				: in_array( $quest_id, $active, true );

			if ( ! $shown ) {
				$removed[ (int) $item->ID ] = true;
				unset( $items[ $key ] );
			}
		}

		return $items;
	}
}
