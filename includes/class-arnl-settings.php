<?php
/**
 * Plugin settings stored in a single option.
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings accessor.
 */
class ARNL_Settings {

	const OPTION = 'arnl_settings';

	/**
	 * Request-level cache.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'public_checkins'   => 1,
			'require_login'     => 0,
			'auto_approve'      => 1,
			'checkin_before'    => 15,
			'checkin_after'     => 15,
			'public_log'        => 'callsign_name',
			'validate_callsign' => 1,
			'online_lookup'     => 1,
			'rate_limit'        => 30,
			'ics_enabled'       => 1,
			'ics_past_days'     => 30,
			'ics_future_days'   => 180,
			'event_page_id'     => 0,
			'delete_data'       => 0,
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * Single setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Forget cached values (after an update).
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Sanitize the settings array submitted from the settings screen.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$out      = array();

		foreach ( array( 'public_checkins', 'require_login', 'auto_approve', 'validate_callsign', 'online_lookup', 'ics_enabled', 'delete_data' ) as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$ranges = array(
			'checkin_before'  => array( 0, 1440 ),
			'checkin_after'   => array( 0, 1440 ),
			'rate_limit'      => array( 0, 3600 ),
			'ics_past_days'   => array( 0, 3650 ),
			'ics_future_days' => array( 1, 730 ),
		);
		foreach ( $ranges as $key => $range ) {
			$value       = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : $defaults[ $key ];
			$out[ $key ] = max( $range[0], min( $range[1], $value ) );
		}

		$public_log        = isset( $input['public_log'] ) ? sanitize_key( $input['public_log'] ) : $defaults['public_log'];
		$out['public_log'] = in_array( $public_log, array( 'none', 'callsign', 'callsign_name' ), true ) ? $public_log : $defaults['public_log'];

		$out['event_page_id'] = isset( $input['event_page_id'] ) ? absint( $input['event_page_id'] ) : 0;

		// The net permalink base is the slug of the "Nets" page, not a stored setting.
		if ( isset( $input['net_base'] ) && current_user_can( 'manage_options' ) ) {
			$base = sanitize_title( wp_unslash( $input['net_base'] ) );
			if ( '' !== $base && ( ARNL_Permalinks::slug() !== $base || ! ARNL_Permalinks::available() ) ) {
				ARNL_Permalinks::set_base( $base );
				if ( ARNL_Permalinks::slug() !== $base && function_exists( 'add_settings_error' ) ) {
					add_settings_error(
						self::OPTION,
						'arnl_net_base',
						sprintf(
							/* translators: 1: requested slug, 2: slug in use. */
							__( '"%1$s" is already used by another page or post, so net pages use "%2$s" instead.', 'ar-net-logger' ),
							$base,
							ARNL_Permalinks::slug()
						),
						'warning'
					);
				}
			}
		}

		self::flush();
		return $out;
	}
}
