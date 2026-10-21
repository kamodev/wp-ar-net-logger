<?php
/**
 * Activation, database schema and capabilities.
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class ARNL_Install {

	/**
	 * Capability to create / edit / delete nets, sessions and settings.
	 */
	const CAP_MANAGE_NETS = 'arnl_manage_nets';

	/**
	 * Capability to run a net: log check-ins and update a session's NCS / topic / status.
	 */
	const CAP_LOG = 'arnl_log_checkins';

	/**
	 * Activation hook.
	 *
	 * @param bool $network_wide Network activation.
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) {
				switch_to_blog( $site_id );
				self::install();
				restore_current_blog();
			}
			return;
		}
		self::install();
	}

	/**
	 * Run the installer when the stored schema version is out of date.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'arnl_db_version' ) !== ARNL_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Create tables, roles, default options and the "Nets" page for net permalinks.
	 */
	public static function install() {
		self::create_tables();
		self::add_caps();
		add_option( ARNL_Settings::OPTION, ARNL_Settings::defaults() );
		// The page is created on "init", once WordPress has registered its post types.
		update_option( 'arnl_create_page', 1 );
		ARNL_Permalinks::schedule_flush();
		update_option( 'arnl_db_version', ARNL_DB_VERSION );
	}

	/**
	 * Deactivation: drop the net page rewrite rule.
	 */
	public static function deactivate() {
		delete_option( 'rewrite_rules' );
	}

	/**
	 * Table names keyed by short name.
	 *
	 * @return array<string,string>
	 */
	public static function tables() {
		global $wpdb;
		return array(
			'nets'     => $wpdb->prefix . 'arnl_nets',
			'events'   => $wpdb->prefix . 'arnl_events',
			'checkins' => $wpdb->prefix . 'arnl_checkins',
		);
	}

	/**
	 * Create / update the custom tables with dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$t       = self::tables();

		dbDelta(
			"CREATE TABLE {$t['nets']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL,
  slug varchar(190) NOT NULL,
  description longtext NULL,
  net_type varchar(32) NOT NULL DEFAULT 'directed',
  mode varchar(32) NOT NULL DEFAULT '',
  band varchar(32) NOT NULL DEFAULT '',
  frequency varchar(100) NOT NULL DEFAULT '',
  access_info varchar(255) NOT NULL DEFAULT '',
  start_time time NOT NULL DEFAULT '19:00:00',
  end_time time NOT NULL DEFAULT '20:00:00',
  recurrence varchar(20) NOT NULL DEFAULT 'weekly',
  days_of_week varchar(20) NOT NULL DEFAULT '',
  weeks_of_month varchar(30) NOT NULL DEFAULT '',
  months varchar(40) NOT NULL DEFAULT '',
  start_date date NULL,
  end_date date NULL,
  ncs_callsign varchar(20) NOT NULL DEFAULT '',
  ncs_name varchar(100) NOT NULL DEFAULT '',
  preamble longtext NULL,
  color varchar(7) NOT NULL DEFAULT '',
  allow_checkins tinyint(1) NOT NULL DEFAULT 1,
  status varchar(20) NOT NULL DEFAULT 'active',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY slug (slug),
  KEY status (status)
) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$t['events']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  net_id bigint(20) unsigned NOT NULL,
  occurrence_date date NULL,
  event_date date NOT NULL,
  start_time time NOT NULL,
  end_time time NOT NULL,
  topic varchar(255) NOT NULL DEFAULT '',
  ncs_callsign varchar(20) NOT NULL DEFAULT '',
  ncs_name varchar(100) NOT NULL DEFAULT '',
  frequency varchar(100) NOT NULL DEFAULT '',
  notes longtext NULL,
  log_notes longtext NULL,
  status varchar(20) NOT NULL DEFAULT 'scheduled',
  opened_at datetime NULL,
  closed_at datetime NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY net_occurrence (net_id,occurrence_date),
  KEY event_date (event_date),
  KEY net_event_date (net_id,event_date)
) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$t['checkins']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  event_id bigint(20) unsigned NOT NULL,
  callsign varchar(20) NOT NULL,
  name varchar(100) NOT NULL DEFAULT '',
  location varchar(190) NOT NULL DEFAULT '',
  station_type varchar(20) NOT NULL DEFAULT 'fixed',
  short_time tinyint(1) NOT NULL DEFAULT 0,
  has_traffic tinyint(1) NOT NULL DEFAULT 0,
  traffic_precedence varchar(20) NOT NULL DEFAULT '',
  traffic_count smallint(5) unsigned NOT NULL DEFAULT 0,
  relayed_by varchar(20) NOT NULL DEFAULT '',
  comments text NULL,
  source varchar(20) NOT NULL DEFAULT 'admin',
  status varchar(20) NOT NULL DEFAULT 'approved',
  acknowledged tinyint(1) NOT NULL DEFAULT 0,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  checkin_time datetime NOT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY event_id (event_id),
  KEY callsign (callsign)
) $charset;"
		);
	}

	/**
	 * Grant capabilities and register the Net Control Operator role.
	 */
	public static function add_caps() {
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				$role->add_cap( self::CAP_MANAGE_NETS );
				$role->add_cap( self::CAP_LOG );
			}
		}
		if ( ! get_role( 'arnl_net_control' ) ) {
			add_role(
				'arnl_net_control',
				__( 'Net Control Operator', 'ar-net-logger' ),
				array(
					'read'        => true,
					self::CAP_LOG => true,
				)
			);
		}
	}
}
