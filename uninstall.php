<?php
/**
 * Uninstall: removes data only when "Delete all data" is enabled in the settings.
 *
 * @package AR_Net_Logger
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove the plugin's data from the current site.
 */
function arnl_uninstall_site() {
	global $wpdb;
	$settings = get_option( 'arnl_settings', array() );
	if ( empty( $settings['delete_data'] ) ) {
		return;
	}
	foreach ( array( 'arnl_checkins', 'arnl_events', 'arnl_nets' ) as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}
	delete_option( 'arnl_settings' );
	delete_option( 'arnl_db_version' );
	delete_transient( 'arnl_event_page' );

	foreach ( array( 'administrator', 'editor' ) as $role_name ) {
		$role = get_role( $role_name );
		if ( $role ) {
			$role->remove_cap( 'arnl_manage_nets' );
			$role->remove_cap( 'arnl_log_checkins' );
		}
	}
	remove_role( 'arnl_net_control' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $arnl_site_id ) {
		switch_to_blog( $arnl_site_id );
		arnl_uninstall_site();
		restore_current_blog();
	}
} else {
	arnl_uninstall_site();
}
