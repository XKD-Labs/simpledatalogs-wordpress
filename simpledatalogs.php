<?php
/**
 * Plugin Name:       simpledatalogs
 * Plugin URI:        https://simpledatalogs.com/docs
 * Description:       Page views, opt-in autocapture and server-side events for simpledatalogs, with no cookies.
 * Version:           0.1.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            XKD Labs
 * Author URI:        https://simpledatalogs.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simpledatalogs
 *
 * @package SimpleDataLogs
 */

// Stop when loaded outside WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The plugin's own version, sent in the User-Agent of server-side events.
define( 'SIMPLEDATALOGS_VERSION', '0.1.0' );

// The version of the bundled browser SDK in assets/js/simpledatalogs.min.js.
define( 'SIMPLEDATALOGS_SDK_VERSION', '0.3.0' );

// The main plugin file, used to build asset URLs and the settings link.
define( 'SIMPLEDATALOGS_PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-simpledatalogs-settings.php';
require_once __DIR__ . '/includes/class-simpledatalogs-sender.php';
require_once __DIR__ . '/includes/class-simpledatalogs-frontend.php';
require_once __DIR__ . '/includes/class-simpledatalogs-server-events.php';
require_once __DIR__ . '/includes/class-simpledatalogs-admin.php';
require_once __DIR__ . '/includes/class-simpledatalogs-plugin-bootstrap.php';

// Wire every part of the plugin once WordPress has loaded the other plugins.
add_action( 'plugins_loaded', array( 'SimpleDataLogs_Plugin_Bootstrap', 'boot' ) );

// Declare WooCommerce order tables (HPOS) support before WooCommerce starts.
add_action( 'before_woocommerce_init', array( 'SimpleDataLogs_Plugin_Bootstrap', 'declare_woocommerce_compatibility' ) );
