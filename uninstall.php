<?php
/**
 * Removes everything the plugin stored when it is deleted from the Plugins screen.
 *
 * @package SimpleDataLogs
 */

// Run only when WordPress itself uninstalls the plugin.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// The settings option, on this site.
delete_option( 'simpledatalogs_settings' );

// On a network, the option of every site, since each site has its own settings.
if ( is_multisite() ) {
	$simpledatalogs_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $simpledatalogs_site_ids as $simpledatalogs_site_id ) {
		switch_to_blog( $simpledatalogs_site_id );
		delete_option( 'simpledatalogs_settings' );
		restore_current_blog();
	}
}
