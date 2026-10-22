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
	$page_id = (int) get_option( 'arnl_net_page_id' );
	if ( $page_id && 'page' === get_post_type( $page_id ) ) {
		wp_delete_post( $page_id, true );
	}
	wp_clear_scheduled_hook( 'arnl_hourly' );
	foreach ( array( 'arnl_settings', 'arnl_db_version', 'arnl_net_page_id', 'arnl_flush_rewrite', 'arnl_create_page', 'arnl_fb_posts', 'arnl_fb_last_error', 'arnl_discord_last_error' ) as $option ) {
		delete_option( $option );
	}
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
