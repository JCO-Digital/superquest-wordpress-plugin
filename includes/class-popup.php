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
	 * Hash prefix of a link opening a popup quest. The SuperQuest loader opens
	 * the popup for `#superquest-ID`, or every popup quest for a bare
	 * `#superquest`.
	 */
	public const LINK_PREFIX = 'superquest';

	/**
	 * The link URL opening a quest's popup.
	 *
	 * @param string $quest_id Quest ID.
	 *
	 * @return string
	 */
	public static function link_url( string $quest_id ): string {
		return '#' . self::LINK_PREFIX . '-' . rawurlencode( $quest_id );
	}

	/**
	 * The quest a link URL opens: the quest ID, an empty string for the bare
	 * `#superquest` (every popup quest), or null for any other URL.
	 *
	 * @param string $url Link URL.
	 *
	 * @return string|null
	 */
	public static function link_quest_id( string $url ): ?string {
		$fragment = wp_parse_url( $url, PHP_URL_FRAGMENT );
		if ( ! is_string( $fragment ) ) {
			return null;
		}

		if ( self::LINK_PREFIX === $fragment ) {
			return '';
		}

		$prefix = self::LINK_PREFIX . '-';
		if ( ! str_starts_with( $fragment, $prefix ) ) {
			return null;
		}

		$quest_id = rawurldecode( substr( $fragment, strlen( $prefix ) ) );

		return '' !== $quest_id ? $quest_id : null;
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
	 * Kept as a way in for anything that already called it; the work itself
	 * now lives in Embed, alongside the rest of the element's contract.
	 *
	 * @return string
	 */
	public static function locale(): string {
		return Embed::locale();
	}

	/**
	 * Prints one quest element per active popup quest.
	 *
	 * `data-jq-load="eager"` opts these out of the loader's viewport gate:
	 * they sit at the bottom of the document but render floating popups, so
	 * they must load without the visitor scrolling down.
	 *
	 * @return void
	 */
	public static function render(): void {
		$quest_ids = self::active_ids();
		if ( array() === $quest_ids ) {
			return;
		}

		$locale = Embed::locale();

		foreach ( $quest_ids as $quest_id ) {
			Embed::render(
				$quest_id,
				array(
					'locale' => $locale,
					'load'   => 'eager',
				)
			);

			echo "\n";
		}
	}
}
