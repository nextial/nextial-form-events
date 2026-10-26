<?php
/**
 * Plugin Name:       Nextial Form Events
 * Description:       MCP Events for WordPress forms. ChatGPT and other MCP clients get a signed notification for every new Contact Form 7, WPForms, Fluent Forms or Gravity Forms submission and react automatically.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Nextial
 * Author URI:        https://nextial.it/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nextial-form-events
 *
 * @package NextialFormEvents
 */

defined( 'ABSPATH' ) || exit;

define( 'NEXTIAL_FE_VERSION', '0.1.0' );
define( 'NEXTIAL_FE_FILE', __FILE__ );
define( 'NEXTIAL_FE_DIR', plugin_dir_path( __FILE__ ) );
define( 'NEXTIAL_FE_DB_VERSION', '1' );

require_once NEXTIAL_FE_DIR . 'includes/class-input.php';
require_once NEXTIAL_FE_DIR . 'includes/class-signer.php';
require_once NEXTIAL_FE_DIR . 'includes/class-store.php';
require_once NEXTIAL_FE_DIR . 'includes/class-http.php';
require_once NEXTIAL_FE_DIR . 'includes/class-forms.php';
require_once NEXTIAL_FE_DIR . 'includes/class-events.php';
require_once NEXTIAL_FE_DIR . 'includes/class-tools.php';
require_once NEXTIAL_FE_DIR . 'includes/class-mcp-server.php';
require_once NEXTIAL_FE_DIR . 'includes/class-oauth.php';
require_once NEXTIAL_FE_DIR . 'includes/class-dispatcher.php';
require_once NEXTIAL_FE_DIR . 'includes/class-endpoints.php';
require_once NEXTIAL_FE_DIR . 'includes/class-admin.php';

register_activation_hook( __FILE__, array( 'Nextial_FE_Store', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Nextial_FE_Store', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		Nextial_FE_Store::maybe_upgrade();
		Nextial_FE_Endpoints::init();
		Nextial_FE_Dispatcher::init();
		Nextial_FE_Events::init();
		add_action( Nextial_FE_Dispatcher::CLEANUP_HOOK, array( 'Nextial_FE_Events', 'purge_recent' ) );
		if ( is_admin() ) {
			Nextial_FE_Admin::init();
		}
	}
);
