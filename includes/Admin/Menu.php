<?php
/**
 * Admin menu, settings fields and screens.
 *
 * @package SuperQuest
 */

namespace SuperQuest\Admin;

use SuperQuest\Options;
use SuperQuest\Quests;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The SuperQuest admin menu and its three screens.
 */
final class Menu {

	/**
	 * Slug of the top-level menu and the General screen.
	 */
	public const SLUG = 'superquest';

	/**
	 * Slug of the Popup screen.
	 */
	public const POPUP_SLUG = 'superquest-popup';

	/**
	 * Slug of the Usage screen.
	 */
	public const USAGE_SLUG = 'superquest-usage';

	/**
	 * The admin-post action refreshing the quest list.
	 */
	public const REFRESH_ACTION = 'superquest_refresh_quests';

	/**
	 * Transient prefix carrying a refresh result to the next page load.
	 */
	private const REFRESH_RESULT_TRANSIENT = 'superquest_refresh_result_';

	/**
	 * The SuperQuest mark as a data URI, pre-encoded. Drawn in black so the
	 * admin colour scheme can recolour it.
	 */
	private const MENU_ICON = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMTggMTM3Ij48cGF0aCBmaWxsPSJibGFjayIgZD0iTTE5LjYyLDQ1Ljk2djE5LjA1YzAsMS4yLTEuMywxLjk1LTIuMzQsMS4zNUwuNzgsNTYuODRjLS40OC0uMjgtLjc4LS43OS0uNzgtMS4zNXYtMjAuODVjMC0uNTYuMy0xLjA3Ljc4LTEuMzVMNTguMDcuMjFjLjQ4LS4yOCwxLjA4LS4yOCwxLjU2LDBsMTYuNSw5LjUzYzEuMDQuNiwxLjA0LDIuMSwwLDIuN0wyMC40LDQ0LjYxYy0uNDguMjgtLjc4Ljc5LS43OCwxLjM1Wk01OC4xNSwxMTQuOEwyLjQxLDgyLjYyYy0xLjA0LS42LTIuMzQuMTUtMi4zNCwxLjM1djE5LjA1YzAsLjU2LjI5LDEuMDcuNzgsMS4zNWw1Ny4yOSwzMy4wOGMuNDguMjgsMS4wOC4yOCwxLjU2LDBsMTguMDYtMTAuNDNjLjQ4LS4yOC43OC0uNzkuNzgtMS4zNXYtMTkuMDVjMC0xLjItMS4zLTEuOTUtMi4zNC0xLjM1bC0xNi41LDkuNTNjLS40OC4yOC0xLjA4LjI4LTEuNTYsMFpNOTguMDgsNDUuNzJ2NjQuMzVjMCwxLjIsMS4zLDEuOTUsMi4zNCwxLjM1bDE2LjUtOS41MmMuNDgtLjI4Ljc4LS43OS43OC0xLjM1VjM0LjM5YzAtLjU2LS4zLTEuMDctLjc4LTEuMzVsLTE4LjA2LTEwLjQzYy0uNDgtLjI4LTEuMDgtLjI4LTEuNTYsMGwtMTYuNSw5LjUzYy0xLjA0LjYtMS4wNCwyLjEsMCwyLjdsMTYuNSw5LjUzYy40OC4yOC43OC43OS43OCwxLjM1Wk03Ny45NCw4MC41NGMuMzgtLjMuNjEtLjc1LjYxLTEuMjR2LTIwLjk0YzAtLjQ5LS4yMy0uOTQtLjYxLTEuMjRsLTE4LjMxLTEwLjU4Yy0uNDktLjI4LTEuMDgtLjI4LTEuNTYsMGwtMTguMzEsMTAuNThjLS4zOC4zLS42MS43NS0uNjEsMS4yNHYyMC45NGMwLC40OS4yMy45NC42MSwxLjI0bDE4LjMxLDEwLjU4Yy40Ny4yOCwxLjA3LjI4LDEuNTYsMGwxOC4zMS0xMC41OFoiLz48L3N2Zz4=';

	/**
	 * Hook suffixes of the plugin's screens, filled by add_pages().
	 *
	 * @var string[]
	 */
	private static array $hooks = array();

	/**
	 * Hooks the menu, settings and handlers.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_pages' ) );
		add_action( 'admin_init', array( self::class, 'add_fields' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::REFRESH_ACTION, array( self::class, 'handle_refresh' ) );
	}

	/**
	 * URL of one of the plugin's screens.
	 *
	 * @param string $page Screen slug.
	 *
	 * @return string
	 */
	public static function url( string $page = self::SLUG ): string {
		return add_query_arg( 'page', $page, admin_url( 'admin.php' ) );
	}

	/**
	 * Adds the menu and its screens.
	 *
	 * @return void
	 */
	public static function add_pages(): void {
		self::$hooks[] = (string) add_menu_page(
			__( 'SuperQuest', 'superquest' ),
			__( 'SuperQuest', 'superquest' ),
			'manage_options',
			self::SLUG,
			array( self::class, 'render_general' ),
			self::MENU_ICON,
			80
		);

		self::$hooks[] = (string) add_submenu_page(
			self::SLUG,
			__( 'SuperQuest settings', 'superquest' ),
			__( 'General', 'superquest' ),
			'manage_options',
			self::SLUG,
			array( self::class, 'render_general' )
		);

		self::$hooks[] = (string) add_submenu_page(
			self::SLUG,
			__( 'SuperQuest popup', 'superquest' ),
			__( 'Popup', 'superquest' ),
			'manage_options',
			self::POPUP_SLUG,
			array( self::class, 'render_popup' )
		);

		self::$hooks[] = (string) add_submenu_page(
			self::SLUG,
			__( 'SuperQuest usage', 'superquest' ),
			__( 'Usage', 'superquest' ),
			'manage_options',
			self::USAGE_SLUG,
			array( self::class, 'render_usage' )
		);
	}

	/**
	 * Adds the settings sections and fields. The settings themselves are
	 * registered on init by Options.
	 *
	 * @return void
	 */
	public static function add_fields(): void {
		add_settings_section(
			'superquest_organization',
			__( 'Organisation', 'superquest' ),
			array( self::class, 'render_organization_section' ),
			self::SLUG
		);

		add_settings_field(
			Options::ORGANIZATION_ID,
			__( 'Organisation ID', 'superquest' ),
			array( self::class, 'render_organization_field' ),
			self::SLUG,
			'superquest_organization',
			array( 'label_for' => Options::ORGANIZATION_ID )
		);

		add_settings_section(
			'superquest_loader',
			__( 'Loader', 'superquest' ),
			array( self::class, 'render_loader_section' ),
			self::POPUP_SLUG
		);

		add_settings_field(
			Options::ALWAYS_LOAD,
			__( 'Always load', 'superquest' ),
			array( self::class, 'render_always_load_field' ),
			self::POPUP_SLUG,
			'superquest_loader'
		);

		add_settings_field(
			Options::CONSENT_BYPASS,
			__( 'Cookie consent', 'superquest' ),
			array( self::class, 'render_consent_field' ),
			self::POPUP_SLUG,
			'superquest_loader'
		);

		add_settings_section(
			'superquest_popup',
			__( 'Popup quests', 'superquest' ),
			array( self::class, 'render_popup_section' ),
			self::POPUP_SLUG
		);

		add_settings_field(
			Options::POPUP_EXCLUDED_IDS,
			__( 'Excluded pages', 'superquest' ),
			array( self::class, 'render_excluded_field' ),
			self::POPUP_SLUG,
			'superquest_popup',
			array( 'label_for' => Options::POPUP_EXCLUDED_IDS )
		);

		add_settings_field(
			Options::POPUP_QUESTS,
			__( 'Quests', 'superquest' ),
			array( self::class, 'render_popup_quests_field' ),
			self::POPUP_SLUG,
			'superquest_popup'
		);
	}

	/**
	 * Loads the admin stylesheet on the plugin's screens only.
	 *
	 * @param string $hook_suffix Current screen hook.
	 *
	 * @return void
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, self::$hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'superquest-admin',
			SUPERQUEST_URL . 'assets/css/admin.css',
			array(),
			SUPERQUEST_VERSION
		);
	}

	/**
	 * Renders the General screen.
	 *
	 * @return void
	 */
	public static function render_general(): void {
		self::refresh_notice();
		require SUPERQUEST_PATH . 'views/admin/general.php';
	}

	/**
	 * Renders the Popup screen.
	 *
	 * @return void
	 */
	public static function render_popup(): void {
		require SUPERQUEST_PATH . 'views/admin/popup.php';
	}

	/**
	 * Renders the Usage screen.
	 *
	 * @return void
	 */
	public static function render_usage(): void {
		require SUPERQUEST_PATH . 'views/admin/usage.php';
	}

	/**
	 * Intro of the Organisation section.
	 *
	 * @return void
	 */
	public static function render_organization_section(): void {
		echo '<p>' . esc_html__( 'The organisation whose quests can be inserted. You find the ID in the SuperQuest dashboard.', 'superquest' ) . '</p>';
	}

	/**
	 * Organisation ID field.
	 *
	 * @return void
	 */
	public static function render_organization_field(): void {
		printf(
			'<input type="text" class="regular-text code" id="%1$s" name="%1$s" value="%2$s" autocomplete="off">',
			esc_attr( Options::ORGANIZATION_ID ),
			esc_attr( Options::organization_id() )
		);
		echo '<p class="description">' . esc_html__( 'Saving a new ID fetches the organisation\'s quests right away.', 'superquest' ) . '</p>';
	}

	/**
	 * Intro of the Loader section.
	 *
	 * @return void
	 */
	public static function render_loader_section(): void {
		echo '<p>' . esc_html__( 'The SuperQuest loader is a small script from files.jquest.fi that renders quests. It is added to pages that hold a quest block or a popup quest.', 'superquest' ) . '</p>';
	}

	/**
	 * Always-load checkbox.
	 *
	 * @return void
	 */
	public static function render_always_load_field(): void {
		self::render_checkbox(
			Options::ALWAYS_LOAD,
			Options::always_load(),
			__( 'Load the SuperQuest loader on every page', 'superquest' ),
			__( 'Use this when quests are inserted by other means than the block or the popup, for example from a theme template.', 'superquest' )
		);
	}

	/**
	 * Consent-bypass checkbox.
	 *
	 * @return void
	 */
	public static function render_consent_field(): void {
		self::render_checkbox(
			Options::CONSENT_BYPASS,
			Options::consent_bypass(),
			__( 'Load SuperQuest before cookie consent is given', 'superquest' ),
			__( 'Marks the loader as essential for Cookiebot, OneTrust and CookieYes so quests work before the visitor answers the consent banner. Only enable this if your privacy assessment allows it.', 'superquest' )
		);
	}

	/**
	 * Intro of the Popup quests section.
	 *
	 * @return void
	 */
	public static function render_popup_section(): void {
		echo '<p>' . esc_html__( 'Every enabled quest below is inserted at the top of the footer on every page of the site, apart from the excluded pages.', 'superquest' ) . '</p>';
	}

	/**
	 * Excluded page IDs field.
	 *
	 * @return void
	 */
	public static function render_excluded_field(): void {
		require SUPERQUEST_PATH . 'views/admin/popup-excluded-field.php';
	}

	/**
	 * Popup quest list field.
	 *
	 * @return void
	 */
	public static function render_popup_quests_field(): void {
		require SUPERQUEST_PATH . 'views/admin/popup-quests-field.php';
	}

	/**
	 * Renders a checkbox with a label and description.
	 *
	 * @param string $name        Option name.
	 * @param bool   $checked     Current value.
	 * @param string $label       Label text.
	 * @param string $description Description text.
	 *
	 * @return void
	 */
	private static function render_checkbox( string $name, bool $checked, string $label, string $description ): void {
		printf(
			'<label for="%1$s"><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s> %3$s</label>',
			esc_attr( $name ),
			checked( $checked, true, false ),
			esc_html( $label )
		);
		echo '<p class="description">' . esc_html( $description ) . '</p>';
	}

	/**
	 * Refreshes the quest list from the General screen's button.
	 *
	 * @return void
	 */
	public static function handle_refresh(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to refresh the quest list.', 'superquest' ), 403 );
		}
		check_admin_referer( self::REFRESH_ACTION );

		$result = Quests::refresh();

		set_transient(
			self::REFRESH_RESULT_TRANSIENT . get_current_user_id(),
			is_wp_error( $result ) ? $result->get_error_message() : true,
			MINUTE_IN_SECONDS
		);

		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Turns a stored refresh result into a settings notice, once.
	 *
	 * @return void
	 */
	private static function refresh_notice(): void {
		$key    = self::REFRESH_RESULT_TRANSIENT . get_current_user_id();
		$result = get_transient( $key );
		if ( false === $result ) {
			return;
		}
		delete_transient( $key );

		if ( true === $result ) {
			add_settings_error(
				'superquest_refresh',
				'superquest_refreshed',
				__( 'Quest list refreshed.', 'superquest' ),
				'success'
			);
			return;
		}

		add_settings_error(
			'superquest_refresh',
			'superquest_refresh_failed',
			sprintf(
				/* translators: %s: error message. */
				__( 'Could not refresh the quest list: %s', 'superquest' ),
				(string) $result
			)
		);
	}
}
