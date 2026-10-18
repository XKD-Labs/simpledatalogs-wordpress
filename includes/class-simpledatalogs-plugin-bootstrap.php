<?php
/**
 * Wires the plugin's parts together.
 *
 * @package SimpleDataLogs
 */

// Stop when loaded outside WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the plugin's parts and registers their hooks.
 */
final class SimpleDataLogs_Plugin_Bootstrap {

	/**
	 * Registers the front-end, server-side and admin hooks.
	 *
	 * @return void
	 */
	public static function boot() {
		$sender = new SimpleDataLogs_Sender();

		// The browser SDK on public pages.
		( new SimpleDataLogs_Frontend() )->register_hooks();

		// Server-side events from WordPress and WooCommerce actions.
		( new SimpleDataLogs_Server_Events( $sender ) )->register_hooks();

		// The settings page and the test event button, only in wp-admin.
		if ( is_admin() ) {
			( new SimpleDataLogs_Admin( $sender ) )->register_hooks();
		}
	}
}
