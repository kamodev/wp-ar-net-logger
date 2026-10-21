<?php
/**
 * Admin bootstrap: menus, assets, notices and shared helpers.
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin core.
 */
class ARNL_Admin {

	/**
	 * Page hook suffixes of the plugin screens.
	 *
	 * @var string[]
	 */
	protected static $hooks = array();

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ARNL_FILE ), array( __CLASS__, 'action_links' ) );

		ARNL_Admin_Nets::init();
		ARNL_Admin_Schedule::init();
		ARNL_Admin_Log::init();
		ARNL_Admin_Settings::init();
	}

	/**
	 * Admin menu.
	 */
	public static function menu() {
		self::$hooks[] = add_menu_page(
			__( 'Net Logger', 'ar-net-logger' ),
			__( 'Net Logger', 'ar-net-logger' ),
			ARNL_Install::CAP_LOG,
			'arnl-schedule',
			array( 'ARNL_Admin_Schedule', 'render' ),
			'dashicons-microphone',
			26
		);
		self::$hooks[] = add_submenu_page( 'arnl-schedule', __( 'Net Schedule', 'ar-net-logger' ), __( 'Schedule', 'ar-net-logger' ), ARNL_Install::CAP_LOG, 'arnl-schedule', array( 'ARNL_Admin_Schedule', 'render' ) );
		self::$hooks[] = add_submenu_page( 'arnl-schedule', __( 'Net Calendar', 'ar-net-logger' ), __( 'Calendar', 'ar-net-logger' ), ARNL_Install::CAP_LOG, 'arnl-calendar', array( 'ARNL_Admin_Schedule', 'render_calendar' ) );
		$nets_hook     = add_submenu_page( 'arnl-schedule', __( 'Nets', 'ar-net-logger' ), __( 'Nets', 'ar-net-logger' ), ARNL_Install::CAP_MANAGE_NETS, 'arnl-nets', array( 'ARNL_Admin_Nets', 'render' ) );
		self::$hooks[] = $nets_hook;
		self::$hooks[] = add_submenu_page( 'arnl-schedule', __( 'Net Logger Settings', 'ar-net-logger' ), __( 'Settings', 'ar-net-logger' ), 'manage_options', 'arnl-settings', array( 'ARNL_Admin_Settings', 'render' ) );

		add_action( 'load-' . $nets_hook, array( 'ARNL_Admin_Nets', 'load' ) );
	}

	/**
	 * Admin CSS / JS on plugin screens.
	 *
	 * @param string $hook Current screen hook.
	 */
	public static function assets( $hook ) {
		if ( ! in_array( $hook, self::$hooks, true ) ) {
			return;
		}
		wp_enqueue_style( 'arnl-public', ARNL_URL . 'assets/css/arnl-public.css', array(), ARNL_VERSION );
		wp_enqueue_style( 'arnl-admin', ARNL_URL . 'assets/css/arnl-admin.css', array( 'arnl-public' ), ARNL_VERSION );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'arnl-admin', ARNL_URL . 'assets/js/arnl-admin.js', array( 'jquery', 'wp-color-picker' ), ARNL_VERSION, true );
		wp_localize_script(
			'arnl-admin',
			'arnlAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'arnl_lookup' ),
				'refresh' => array(
					'nonce'    => wp_create_nonce( 'arnl_refresh_log' ),
					/**
					 * Filters how often (in seconds) the Net Log checks for new check-ins.
					 *
					 * @param int $seconds Interval.
					 */
					'interval' => max( 2, (int) apply_filters( 'arnl_log_refresh_interval', 15 ) ),
				),
				'i18n'    => array(
					'confirm'     => __( 'Are you sure?', 'ar-net-logger' ),
					'found'       => __( 'Filled in from a previous check-in.', 'ar-net-logger' ),
					'foundOnline' => __( 'Filled in from the license database.', 'ar-net-logger' ),
					/* translators: %d: number of previous check-ins. */
					'checkins'    => __( '%d check-ins', 'ar-net-logger' ),
					'online'      => __( 'License lookup', 'ar-net-logger' ),
					'copied'      => __( 'Copied.', 'ar-net-logger' ),
					/* translators: %s: time of the last update. */
					'updated'     => __( 'Updated %s', 'ar-net-logger' ),
					/* translators: %d: number of new check-ins. */
					'newOne'      => __( '%d new check-in', 'ar-net-logger' ),
					/* translators: %d: number of new check-ins. */
					'newMany'     => __( '%d new check-ins', 'ar-net-logger' ),
					'paused'      => __( 'Automatic updates paused.', 'ar-net-logger' ),
					'retrying'    => __( 'Could not reach the server, retrying…', 'ar-net-logger' ),
					'expired'     => __( 'Automatic updates stopped. Reload the page to resume.', 'ar-net-logger' ),
				),
			)
		);
	}

	/**
	 * Plugins screen links.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( self::url( 'arnl-nets' ) ) . '">' . esc_html__( 'Nets', 'ar-net-logger' ) . '</a>',
			'<a href="' . esc_url( self::url( 'arnl-settings' ) ) . '">' . esc_html__( 'Settings', 'ar-net-logger' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Admin page URL.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function url( $page, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * URL of a nonce-protected admin-post.php GET action.
	 *
	 * @param string $action Action name.
	 * @param array  $args   Query args.
	 * @param string $nonce  Nonce action (defaults to the action).
	 * @return string
	 */
	public static function action_url( $action, array $args = array(), $nonce = '' ) {
		$url = add_query_arg( array_merge( array( 'action' => $action ), $args ), admin_url( 'admin-post.php' ) );
		return wp_nonce_url( $url, $nonce ? $nonce : $action );
	}

	/**
	 * Redirect with a notice code and stop.
	 *
	 * @param string $url    URL.
	 * @param string $notice Notice code.
	 */
	public static function redirect( $url, $notice = '' ) {
		if ( '' !== $notice ) {
			$url = add_query_arg( 'arnl_notice', $notice, $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Remember an error and submitted values for the next screen, then redirect.
	 *
	 * @param string          $url   URL.
	 * @param WP_Error|string $error Error.
	 * @param array           $data  Submitted data to re-populate the form.
	 */
	public static function fail( $url, $error, array $data = array() ) {
		set_transient(
			'arnl_form_' . get_current_user_id(),
			array(
				'errors' => is_wp_error( $error ) ? $error->get_error_messages() : array( (string) $error ),
				'data'   => $data,
			),
			5 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( add_query_arg( 'arnl_error', 1, $url ) );
		exit;
	}

	/**
	 * Submitted values from a failed save (once).
	 *
	 * @return array|null
	 */
	public static function failed_data() {
		static $state = null;
		if ( null === $state ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			$state = empty( $_GET['arnl_error'] ) ? false : get_transient( 'arnl_form_' . get_current_user_id() );
		}
		return $state && ! empty( $state['data'] ) ? $state['data'] : null;
	}

	/**
	 * Show notices on plugin screens.
	 */
	public static function notices() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, self::$hooks, true ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		if ( ! empty( $_GET['arnl_error'] ) ) {
			$state = get_transient( 'arnl_form_' . get_current_user_id() );
			if ( $state && ! empty( $state['errors'] ) ) {
				echo '<div class="notice notice-error"><p>' . implode( '<br>', array_map( 'esc_html', $state['errors'] ) ) . '</p></div>';
			}
			// Keep the data for the form on this request; drop it for the next one.
			self::failed_data();
			delete_transient( 'arnl_form_' . get_current_user_id() );
		}

		$code = isset( $_GET['arnl_notice'] ) ? sanitize_key( wp_unslash( $_GET['arnl_notice'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$messages = array(
			'net_created'       => __( 'Net created.', 'ar-net-logger' ),
			'net_saved'         => __( 'Net saved.', 'ar-net-logger' ),
			'net_deleted'       => __( 'Net deleted.', 'ar-net-logger' ),
			'nets_deleted'      => __( 'Nets deleted.', 'ar-net-logger' ),
			'nets_updated'      => __( 'Nets updated.', 'ar-net-logger' ),
			'event_saved'       => __( 'Session saved.', 'ar-net-logger' ),
			'event_created'     => __( 'Session added to the schedule.', 'ar-net-logger' ),
			'event_deleted'     => __( 'Session record deleted. Regular schedule restored for that date.', 'ar-net-logger' ),
			'event_cancelled'   => __( 'Session cancelled.', 'ar-net-logger' ),
			'event_scheduled'   => __( 'Session restored to scheduled.', 'ar-net-logger' ),
			'event_active'      => __( 'Net opened. Online check-ins are accepted until you close the net.', 'ar-net-logger' ),
			'event_completed'   => __( 'Net closed.', 'ar-net-logger' ),
			'checkin_saved'     => __( 'Check-in logged.', 'ar-net-logger' ),
			'checkin_updated'   => __( 'Check-in updated.', 'ar-net-logger' ),
			'checkin_deleted'   => __( 'Check-in deleted.', 'ar-net-logger' ),
			'checkins_approved' => __( 'Pending check-ins approved.', 'ar-net-logger' ),
			'checkin_changed'   => __( 'Check-in updated.', 'ar-net-logger' ),
			'discord_test_sent' => __( 'Test message sent to Discord. Check the channel.', 'ar-net-logger' ),
			'facebook_posted'   => __( 'Session posted to the Facebook Page.', 'ar-net-logger' ),
		);
		if ( 'facebook_connected' === $code ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			$page = isset( $_GET['arnl_page'] ) ? sanitize_text_field( wp_unslash( $_GET['arnl_page'] ) ) : '';
			/* translators: %s: Facebook Page name. */
			$messages[ $code ] = sprintf( __( 'Connected to the Facebook Page "%s".', 'ar-net-logger' ), $page );
		}
		if ( isset( $messages[ $code ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $code ] ) . '</p></div>';
		}
	}

	/**
	 * Checkbox-list helper.
	 *
	 * @param string $name     Field name (without []).
	 * @param array  $options  value => label.
	 * @param array  $selected Selected values.
	 */
	public static function checkboxes( $name, array $options, array $selected ) {
		$selected = array_map( 'strval', $selected );
		echo '<span class="arnl-checkboxes">';
		foreach ( $options as $value => $label ) {
			printf(
				'<label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> %4$s</label>',
				esc_attr( $name ),
				esc_attr( $value ),
				checked( in_array( (string) $value, $selected, true ), true, false ),
				esc_html( $label )
			);
		}
		echo '</span>';
	}

	/**
	 * <select> helper.
	 *
	 * @param string $name     Name.
	 * @param array  $options  value => label.
	 * @param string $selected Selected value.
	 * @param array  $attrs    Extra attributes (id, class, ...).
	 */
	public static function select( $name, array $options, $selected, array $attrs = array() ) {
		$extra = '';
		foreach ( $attrs as $key => $value ) {
			$extra .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}
		printf( '<select name="%1$s"%2$s>', esc_attr( $name ), $extra ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		foreach ( $options as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( (string) $selected, (string) $value, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	/**
	 * Status badge.
	 *
	 * @param string $status Session status.
	 * @return string
	 */
	public static function status_badge( $status ) {
		return '<span class="arnl-badge arnl-badge--' . esc_attr( $status ) . '">' . esc_html( arnl_label( arnl_event_statuses(), $status ) ) . '</span>';
	}
}
