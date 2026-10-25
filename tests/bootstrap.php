<?php
/**
 * Loads Composer, the WordPress stubs and the plugin classes for unit tests.
 *
 * @package SimpleDataLogs
 */

// Composer's autoloader, the class stubs and the base test case.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/stubs.php';
require_once __DIR__ . '/stub-woocommerce-features-util.php';
require_once __DIR__ . '/class-simpledatalogs-test-case.php';

// The constants the main plugin file defines.
define( 'ABSPATH', '/tmp/wordpress/' );
define( 'SIMPLEDATALOGS_VERSION', '0.1.0' );
define( 'SIMPLEDATALOGS_SDK_VERSION', '0.3.0' );
define( 'SIMPLEDATALOGS_PLUGIN_FILE', dirname( __DIR__ ) . '/simpledatalogs.php' );
define( 'MINUTE_IN_SECONDS', 60 );

// The plugin classes, without the main file's add_action call.
$simpledatalogs_include_dir = dirname( __DIR__ ) . '/includes/';

require_once $simpledatalogs_include_dir . 'class-simpledatalogs-settings.php';
require_once $simpledatalogs_include_dir . 'class-simpledatalogs-sender.php';
require_once $simpledatalogs_include_dir . 'class-simpledatalogs-frontend.php';
require_once $simpledatalogs_include_dir . 'class-simpledatalogs-server-events.php';
require_once $simpledatalogs_include_dir . 'class-simpledatalogs-admin.php';
require_once $simpledatalogs_include_dir . 'class-simpledatalogs-plugin-bootstrap.php';
