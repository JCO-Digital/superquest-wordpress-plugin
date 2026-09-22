<?php
/**
 * Plugin Name:       SuperQuest
 * Plugin URI:        https://superquest.ai
 * Description:       Embed SuperQuest quests, quizzes and polls in your content with a block or as a site-wide popup.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            J&Co Digital Oy
 * Author URI:        https://jco.fi
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       superquest
 *
 * @package SuperQuest
 */

namespace SuperQuest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SUPERQUEST_VERSION', '1.1.0' );
define( 'SUPERQUEST_FILE', __FILE__ );
define( 'SUPERQUEST_PATH', plugin_dir_path( __FILE__ ) );
define( 'SUPERQUEST_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoloads classes from the SuperQuest namespace.
 *
 * Maps `SuperQuest\Foo\Bar_Baz` to `includes/foo/class-bar-baz.php`, the file
 * naming the WordPress coding standards ask for.
 *
 * @param string $class_name Fully qualified class name.
 *
 * @return void
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$parts = explode( '\\', substr( $class_name, strlen( $prefix ) ) );
		$parts = array_map(
			static function ( string $part ): string {
				return strtolower( str_replace( '_', '-', $part ) );
			},
			$parts
		);

		$parts[ array_key_last( $parts ) ] = 'class-' . end( $parts );

		$file = SUPERQUEST_PATH . 'includes/' . implode( '/', $parts ) . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance()->boot();
	}
);
