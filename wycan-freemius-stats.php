<?php
/**
 * Plugin Name:          Wycan Freemius Stats
 * Plugin URI:           https://wycan.fr
 * Description:          Display the number of active installations, the latest version and the changelog of your plugins via the Freemius API.
 * Version:              2.1.0
 * Author:               Wycan
 * Author URI:           https://wycan.fr
 * License:              GPL v2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          wycan-freemius-stats
 * Domain Path:          /languages
 * Requires at least:    6.7
 * Requires PHP:         8.1
 * Elementor tested up to: 4.3.5
 * Update URI:           https://github.com/yeswycan/wycan-freemius-stats
 *
 * @package Wycan\FreemiusStats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WFS_VERSION', '2.1.0' );
define( 'WFS_FILE', __FILE__ );
define( 'WFS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WFS_URL', plugin_dir_url( __FILE__ ) );
define( 'WFS_BASENAME', plugin_basename( __FILE__ ) );
define( 'WFS_MIN_PHP', '8.1' );

// Syntaxe compatible PHP 7 jusqu'ici : la vérification doit s'exécuter avant tout fichier en 8.1.
if ( version_compare( PHP_VERSION, WFS_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			printf(
				/* translators: 1: required PHP version, 2: current PHP version */
				esc_html__( 'Wycan Freemius Stats requires PHP %1$s or later. This site runs PHP %2$s: the plugin is inactive.', 'wycan-freemius-stats' ),
				esc_html( WFS_MIN_PHP ),
				esc_html( PHP_VERSION )
			);
			echo '</p></div>';
		}
	);
	return;
}

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'Wycan\\FreemiusStats\\';
		if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
			return;
		}
		$file = WFS_PATH . 'src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_file( $file ) ) {
			require $file;
		}
	}
);

/**
 * Public API kept from 1.1.0.
 *
 * @param string $product_slug Product slug as configured in the settings.
 * @param bool   $active_only  Count active installations only.
 * @return int|false
 */
function wycan_get_freemius_installs( $product_slug, $active_only = true ) {
	return \Wycan\FreemiusStats\Plugin::instance()->get_install_count( $product_slug, $active_only );
}

// 1.1.0 exposed this class name; templates may still call Wycan_Freemius_Stats::get_instance().
class_alias( \Wycan\FreemiusStats\Plugin::class, 'Wycan_Freemius_Stats' );

add_action( 'plugins_loaded', [ \Wycan\FreemiusStats\Plugin::class, 'boot' ] );
register_activation_hook( __FILE__, [ \Wycan\FreemiusStats\Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \Wycan\FreemiusStats\Plugin::class, 'deactivate' ] );
