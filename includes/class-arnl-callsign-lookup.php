<?php
/**
 * Online callsign lookup for stations that have not checked in before.
 *
 * Uses callook.info (US amateur licenses from the FCC ULS) by default.
 * Other services can be plugged in with the "arnl_callsign_lookup" filter.
 * Results are cached: found stations for 30 days, unknown ones for a day.
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Callsign lookup.
 */
class ARNL_Callsign_Lookup {

	/**
	 * Look up a callsign.
	 *
	 * @param string $callsign Callsign (portable designators are ignored).
	 * @return array|null array( callsign, name, full_name, location, grid, source ) or null.
	 */
	public static function lookup( $callsign ) {
		$callsign = arnl_base_callsign( $callsign );
		if ( '' === $callsign || ! ARNL_Settings::get( 'online_lookup' ) || ! arnl_looks_like_callsign( $callsign ) ) {
			return null;
		}

		$key    = 'arnl_cl_' . md5( $callsign );
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached ? $cached : null;
		}

		/**
		 * Short-circuits the online lookup, e.g. to use another service.
		 *
		 * Return an array with callsign, name, full_name, location, grid and
		 * source keys, false when the callsign is unknown, or null to use the
		 * built-in callook.info lookup.
		 *
		 * @param array|false|null $result   Result.
		 * @param string           $callsign Base callsign.
		 */
		$result = apply_filters( 'arnl_callsign_lookup', null, $callsign );

		if ( null === $result ) {
			// After a failure, give the service a break instead of slowing every keystroke.
			if ( get_transient( 'arnl_cl_unavailable' ) ) {
				return null;
			}
			$response = wp_remote_get(
				'https://callook.info/' . rawurlencode( $callsign ) . '/json',
				array(
					'timeout'    => 4,
					'user-agent' => 'AR Net Logger/' . ARNL_VERSION . '; ' . home_url( '/' ),
				)
			);
			$data     = is_wp_error( $response ) ? null : json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) || ! is_array( $data ) || ( isset( $data['status'] ) && 'UPDATING' === $data['status'] ) ) {
				set_transient( 'arnl_cl_unavailable', 1, 10 * MINUTE_IN_SECONDS );
				return null;
			}
			$result = self::parse_callook( $data );
		}

		$result = is_array( $result ) ? $result : null;
		set_transient( $key, $result ? $result : array(), $result ? 30 * DAY_IN_SECONDS : DAY_IN_SECONDS );
		return $result;
	}

	/**
	 * Turn a callook.info JSON response into station fields.
	 *
	 * @param mixed $data Decoded JSON.
	 * @return array|null
	 */
	public static function parse_callook( $data ) {
		if ( ! is_array( $data ) || ! isset( $data['status'] ) || 'VALID' !== $data['status'] ) {
			return null;
		}
		$callsign = isset( $data['current']['callsign'] ) ? strtoupper( trim( (string) $data['current']['callsign'] ) ) : '';
		if ( '' === $callsign ) {
			return null;
		}

		$full = isset( $data['name'] ) ? trim( preg_replace( '/\s+/', ' ', (string) $data['name'] ) ) : '';
		// FCC records may be "LAST, FIRST M" or "FIRST M LAST".
		if ( false !== strpos( $full, ',' ) ) {
			list( $last, $first ) = array_map( 'trim', explode( ',', $full, 2 ) );
			$full                 = trim( $first . ' ' . $last );
		}
		$full   = self::title_case( $full );
		$person = ! isset( $data['type'] ) || 'PERSON' === strtoupper( (string) $data['type'] );
		$name   = $person && '' !== $full ? strtok( $full, ' ' ) : $full;

		$location = '';
		$line     = isset( $data['address']['line2'] ) ? trim( (string) $data['address']['line2'] ) : '';
		if ( preg_match( '/^(.+?),\s*([A-Za-z]{2})(?:\s+[\d-]+)?$/', $line, $m ) ) {
			$location = self::title_case( $m[1] ) . ', ' . strtoupper( $m[2] );
		} elseif ( '' !== $line ) {
			$location = self::title_case( trim( preg_replace( '/\s+[\d-]+$/', '', $line ) ) );
		}

		return array(
			'callsign'  => $callsign,
			'name'      => $name,
			'full_name' => $full,
			'location'  => $location,
			'grid'      => isset( $data['location']['gridsquare'] ) ? (string) $data['location']['gridsquare'] : '',
			'source'    => 'callook',
		);
	}

	/**
	 * "JOHN Q PUBLIC" -> "John Q Public"; mixed-case input is kept as is.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function title_case( $text ) {
		$text = (string) $text;
		if ( strtoupper( $text ) !== $text ) {
			return $text;
		}
		return ucwords( strtolower( $text ), " -'" );
	}
}
