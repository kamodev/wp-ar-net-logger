<?php
/**
 * Plugin Name:       AR Net Logger
 * Plugin URI:        https://github.com/kamodev/wp-ar-net-logger
 * Description:       Schedule, publish and log Amateur (ham) Radio nets: recurring net schedules, per-session overrides, a public calendar with shortcodes, iCalendar export, Net Control Station assignments and online / NCS-logged check-ins.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            kamodev
 * Author URI:        https://github.com/kamodev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ar-net-logger
 * Domain Path:       /languages
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

define( 'ARNL_VERSION', '1.1.0' );
define( 'ARNL_DB_VERSION', '2' );
define( 'ARNL_FILE', __FILE__ );
define( 'ARNL_DIR', plugin_dir_path( __FILE__ ) );
define( 'ARNL_URL', plugin_dir_url( __FILE__ ) );

require_once ARNL_DIR . 'includes/functions.php';
require_once ARNL_DIR . 'includes/class-arnl-settings.php';
require_once ARNL_DIR . 'includes/class-arnl-install.php';
require_once ARNL_DIR . 'includes/class-arnl-recurrence.php';
require_once ARNL_DIR . 'includes/class-arnl-nets.php';
require_once ARNL_DIR . 'includes/class-arnl-events.php';
require_once ARNL_DIR . 'includes/class-arnl-checkins.php';
require_once ARNL_DIR . 'includes/class-arnl-occurrence.php';
require_once ARNL_DIR . 'includes/class-arnl-schedule.php';
require_once ARNL_DIR . 'includes/class-arnl-permalinks.php';
require_once ARNL_DIR . 'includes/class-arnl-callsign-lookup.php';
require_once ARNL_DIR . 'includes/class-arnl-calendar.php';
require_once ARNL_DIR . 'includes/class-arnl-ical.php';
require_once ARNL_DIR . 'includes/class-arnl-checkin-handler.php';
require_once ARNL_DIR . 'includes/class-arnl-shortcodes.php';
require_once ARNL_DIR . 'includes/class-arnl-plugin.php';

if ( is_admin() ) {
	require_once ARNL_DIR . 'includes/admin/class-arnl-admin.php';
	require_once ARNL_DIR . 'includes/admin/class-arnl-nets-list-table.php';
	require_once ARNL_DIR . 'includes/admin/class-arnl-admin-nets.php';
	require_once ARNL_DIR . 'includes/admin/class-arnl-admin-schedule.php';
	require_once ARNL_DIR . 'includes/admin/class-arnl-admin-log.php';
	require_once ARNL_DIR . 'includes/admin/class-arnl-admin-settings.php';
}

register_activation_hook( __FILE__, array( 'ARNL_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ARNL_Install', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'ARNL_Plugin', 'init' ) );
