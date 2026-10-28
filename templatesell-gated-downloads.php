<?php
/**
 * Gated Downloads – Lead Capture & Content Locker
 *
 * @package           TemplateSell_Gated_Downloads
 * @author            TemplateSell
 * @copyright         2026 TemplateSell
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Gated Downloads – Lead Capture & Content Locker
 * Plugin URI:        https://templatesell.net/gated-downloads/
 * Description:       Gate any download behind a lead-capture form. Collect name, email & phone, then deliver the file — accessible, translatable, and compatible with any theme.
 * Version:           1.3.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            TemplateSell
 * Author URI:        https://templatesell.net/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       templatesell-gated-downloads
 * Domain Path:       /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * ---------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------------
 */
define( 'TSLGD_VERSION', '1.3.0' );
define( 'TSLGD_FILE', __FILE__ );
define( 'TSLGD_PATH', plugin_dir_path( __FILE__ ) );
define( 'TSLGD_URL', plugin_dir_url( __FILE__ ) );
define( 'TSLGD_BASENAME', plugin_basename( __FILE__ ) );
define( 'TSLGD_MIN_PHP', '7.4' );
define( 'TSLGD_MIN_WP', '6.2' );

/*
 * ---------------------------------------------------------------------------
 * Environment guard — fail gracefully on unsupported PHP/WP.
 * ---------------------------------------------------------------------------
 */
if ( version_compare( PHP_VERSION, TSLGD_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			printf(
				/* translators: 1: required PHP version, 2: current PHP version */
				esc_html__( 'Gated Downloads requires PHP %1$s or higher. You are running %2$s.', 'templatesell-gated-downloads' ),
				esc_html( TSLGD_MIN_PHP ),
				esc_html( PHP_VERSION )
			);
			echo '</p></div>';
		}
	);
	return;
}

/*
 * ---------------------------------------------------------------------------
 * Autoloader — maps the Gated Downloads\ namespace to includes/ (WPCS file names).
 * ---------------------------------------------------------------------------
 */
require_once TSLGD_PATH . 'includes/class-autoloader.php';
TemplateSell\GatedDownloads\Autoloader::register();

/*
 * ---------------------------------------------------------------------------
 * Activation / Deactivation hooks.
 * ---------------------------------------------------------------------------
 */
register_activation_hook( __FILE__, array( 'TemplateSell\\GatedDownloads\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TemplateSell\\GatedDownloads\\Deactivator', 'deactivate' ) );

/*
 * ---------------------------------------------------------------------------
 * Boot the plugin once all plugins are loaded.
 * ---------------------------------------------------------------------------
 */
add_action(
	'plugins_loaded',
	function () {
		TemplateSell\GatedDownloads\Plugin::instance()->run();
	}
);
