<?php
/**
 * Plugin bootstrap.
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires up all components.
 */
class ARNL_Plugin {

	/**
	 * Hook everything up (plugins_loaded).
	 */
	public static function init() {
		load_plugin_textdomain( 'ar-net-logger', false, dirname( plugin_basename( ARNL_FILE ) ) . '/languages' );

		ARNL_Install::maybe_upgrade();

		add_action( 'add_option_' . ARNL_Settings::OPTION, array( 'ARNL_Settings', 'flush' ) );
		add_action( 'update_option_' . ARNL_Settings::OPTION, array( 'ARNL_Settings', 'flush' ) );
		add_action( 'wp_initialize_site', array( __CLASS__, 'new_site' ), 200 );

		ARNL_Permalinks::init();
		ARNL_ICal::init();
		ARNL_Checkin_Handler::init();
		ARNL_Shortcodes::init();

		if ( is_admin() ) {
			ARNL_Admin::init();
		}
	}

	/**
	 * Install on sites created after network activation.
	 *
	 * @param WP_Site $site New site.
	 */
	public static function new_site( $site ) {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( is_plugin_active_for_network( plugin_basename( ARNL_FILE ) ) ) {
			switch_to_blog( $site->blog_id );
			ARNL_Install::install();
			restore_current_blog();
		}
	}
}
