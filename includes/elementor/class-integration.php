<?php
/**
 * Elementor support.
 *
 * @package SuperQuest
 */

namespace SuperQuest\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the quest widget with Elementor, when Elementor is there.
 *
 * This class extends nothing and touches no Elementor symbol outside a hook
 * Elementor itself fires, so it is safe to load on every request. It also
 * holds the widget's name, because reading that off the widget class would
 * load a file extending `\Elementor\Widget_Base` and fatal without Elementor.
 */
final class Integration {

	/**
	 * Widget name, as it appears in `_elementor_data`.
	 */
	public const WIDGET_NAME = 'superquest-quest';

	/**
	 * The quest picker's setting key.
	 */
	public const SETTING_QUEST_ID = 'quest_id';

	/**
	 * The manual override's setting key.
	 */
	public const SETTING_QUEST_ID_MANUAL = 'quest_id_manual';

	/**
	 * The post meta Elementor keeps its tree in.
	 */
	public const META_KEY = '_elementor_data';

	/**
	 * The first Elementor with `$widgets_manager->register()`; before 3.5 it
	 * was `register_widget_type()`, which we do not support.
	 */
	public const MINIMUM_VERSION = '3.5.0';

	/**
	 * Style handle for the editor placeholder.
	 */
	public const STYLE_HANDLE = 'superquest-elementor-preview';

	/**
	 * Hooks registration once Elementor is known to be loaded.
	 *
	 * Elementor fires `elementor/loaded` on `plugins_loaded` at the same
	 * priority as this plugin's own boot, so which runs first depends on the
	 * order of `active_plugins`. Both orders are covered.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( did_action( 'elementor/loaded' ) ) {
			self::init();
			return;
		}

		add_action( 'elementor/loaded', array( self::class, 'init' ) );
	}

	/**
	 * Hooks the widget and its editor styles.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( ! self::is_supported() ) {
			return;
		}

		add_action( 'elementor/widgets/register', array( self::class, 'register_widget' ) );
		add_action( 'elementor/preview/enqueue_styles', array( self::class, 'enqueue_preview_styles' ) );
	}

	/**
	 * Whether the installed Elementor is new enough to register widgets the
	 * way this integration does.
	 *
	 * @return bool
	 */
	public static function is_supported(): bool {
		return defined( 'ELEMENTOR_VERSION' )
			&& version_compare( (string) ELEMENTOR_VERSION, self::MINIMUM_VERSION, '>=' );
	}

	/**
	 * Registers the widget.
	 *
	 * The widget class is only named here, inside a hook Elementor fires, so
	 * the autoloader never reaches it on a site without Elementor.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor's widget manager.
	 *
	 * @return void
	 */
	public static function register_widget( $widgets_manager ): void {
		$widgets_manager->register( new Quest_Widget() );
	}

	/**
	 * Loads the styles for the editor placeholder.
	 *
	 * This hook only fires inside Elementor's preview iframe, so the stylesheet
	 * never reaches a visitor.
	 *
	 * @return void
	 */
	public static function enqueue_preview_styles(): void {
		wp_enqueue_style(
			self::STYLE_HANDLE,
			SUPERQUEST_URL . 'assets/css/elementor-preview.css',
			array(),
			SUPERQUEST_VERSION
		);
	}

	/**
	 * The text that marks a quest widget in an `_elementor_data` blob.
	 *
	 * Just the quoted widget name: the key order of re-encoded JSON is not a
	 * contract, so anything longer would be brittle. Callers that need
	 * certainty decode the JSON and confirm.
	 *
	 * @return string
	 */
	public static function needle(): string {
		return '"' . self::WIDGET_NAME . '"';
	}

	/**
	 * Whether Elementor is rendering into its own editor rather than a page.
	 *
	 * Three contexts reach the widget's render(): the preview iframe, the
	 * editor screen, and the ajax re-render Elementor fires on every settings
	 * change. Missing the last one is what lets a live quest appear the moment
	 * an editor touches a control, so all three are covered.
	 *
	 * @return bool
	 */
	public static function is_editing(): bool {
		if ( ! class_exists( '\Elementor\Plugin' ) || null === \Elementor\Plugin::$instance ) {
			return false;
		}

		$elementor = \Elementor\Plugin::$instance;

		if ( isset( $elementor->preview ) && $elementor->preview->is_preview_mode() ) {
			return true;
		}

		if ( isset( $elementor->editor ) && $elementor->editor->is_edit_mode() ) {
			return true;
		}

		if ( ! wp_doing_ajax() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reads which screen is asking, changes nothing.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		return str_starts_with( $action, 'elementor' );
	}
}
