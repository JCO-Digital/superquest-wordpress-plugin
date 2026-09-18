<?php
/**
 * Plugin bootstrap.
 *
 * @package SuperQuest
 */

namespace SuperQuest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires every component to its hooks.
 */
final class Plugin {

	/**
	 * The single instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Returns the single instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers every hook. Called once on `plugins_loaded`.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'init', array( Options::class, 'register' ) );

		Quests::register();
		Block::register();
		Loader::register();
		Popup::register();

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		if ( is_admin() ) {
			Admin\Menu::register();
			add_filter( 'plugin_action_links_' . plugin_basename( SUPERQUEST_FILE ), array( $this, 'action_links' ) );
		}
	}

	/**
	 * Registers the REST routes the block editor uses.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		( new Rest\Quests_Controller() )->register_routes();
	}

	/**
	 * Adds a Settings link on the Plugins screen.
	 *
	 * @param string[] $links Existing action links.
	 *
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( Admin\Menu::url() ),
			esc_html__( 'Settings', 'superquest' )
		);

		return $links;
	}
}
