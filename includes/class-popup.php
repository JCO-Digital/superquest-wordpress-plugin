<?php
/**
 * Site-wide popup quests inserted above the footer.
 *
 * @package SuperQuest
 */

namespace SuperQuest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the configured popup quests on every page but the excluded ones.
 */
final class Popup {

	/**
	 * Hooks the footer output.
	 *
	 * @return void
	 */
	public static function register(): void {
		// Priority 0: the quest elements go out ahead of everything else
		// hooked to the footer.
		add_action( 'wp_footer', array( self::class, 'render' ), 0 );
	}

	/**
	 * The quest IDs to insert on the page being rendered.
	 *
	 * @return string[]
	 */
	public static function active_ids(): array {
		if ( self::is_excluded() ) {
			return array();
		}

		$ids = array();
		foreach ( Options::popup_quests() as $quest ) {
			if ( $quest['enabled'] ) {
				$ids[] = $quest['quest_id'];
			}
		}

		return $ids;
	}

	/**
	 * Whether the queried post is on the exclusion list.
	 *
	 * @return bool
	 */
	public static function is_excluded(): bool {
		$excluded = Options::popup_excluded_ids();
		if ( array() === $excluded ) {
			return false;
		}

		$current = (int) get_queried_object_id();

		return $current > 0 && in_array( $current, $excluded, true );
	}

	/**
	 * The language slug handed to the loader.
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
	 * Prints one quest element per active popup quest.
	 *
	 * The class and data attributes are the external loader's contract and
	 * cannot be renamed. `data-jq-load="eager"` opts these out of the loader's
	 * viewport gate: they sit at the bottom of the document but render
	 * floating popups, so they must load without the visitor scrolling down.
	 *
	 * @return void
	 */
	public static function render(): void {
		$quest_ids = self::active_ids();
		if ( array() === $quest_ids ) {
			return;
		}

		if ( '' === Options::organization_id() ) {
			return;
		}

		$organization = Options::organization_id();
		$locale       = self::locale();

		foreach ( $quest_ids as $quest_id ) {
			printf(
				'<div class="jquest-app" data-new-styles="true" data-locale="%1$s" data-org-id="%2$s" data-game-id="%3$s" data-version="%4$s" data-jq-load="eager"></div>' . "\n",
				esc_attr( $locale ),
				esc_attr( $organization ),
				esc_attr( $quest_id ),
				esc_attr( Loader::SCRIPT_VERSION )
			);
		}
	}
}
