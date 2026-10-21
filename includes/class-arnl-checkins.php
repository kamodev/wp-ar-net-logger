<?php
/**
 * Check-ins repository (CRUD).
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Check-ins data access.
 */
class ARNL_Checkins {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		$tables = ARNL_Install::tables();
		return $tables['checkins'];
	}

	/**
	 * Default field values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'id'                 => 0,
			'event_id'           => 0,
			'callsign'           => '',
			'name'               => '',
			'location'           => '',
			'station_type'       => 'fixed',
			'short_time'         => 0,
			'has_traffic'        => 0,
			'traffic_precedence' => '',
			'traffic_count'      => 0,
			'relayed_by'         => '',
			'comments'           => '',
			'source'             => 'admin',
			'status'             => 'approved',
			'acknowledged'       => 0,
			'user_id'            => 0,
			'checkin_time'       => '',
			'created_at'         => '',
			'updated_at'         => '',
		);
	}

	/**
	 * Get a check-in.
	 *
	 * @param int $id Check-in ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Check-ins of a session, in check-in order.
	 *
	 * @param int             $event_id Session ID.
	 * @param string|string[] $status   Optional status filter.
	 * @return array[]
	 */
	public static function for_event( $event_id, $status = array() ) {
		global $wpdb;
		$table    = self::table();
		$sql      = "SELECT * FROM {$table} WHERE event_id = %d";
		$params   = array( (int) $event_id );
		$statuses = arnl_parse_list( $status );
		if ( $statuses ) {
			$sql   .= ' AND status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')';
			$params = array_merge( $params, $statuses );
		}
		$sql .= ' ORDER BY checkin_time ASC, id ASC';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	/**
	 * Approved / pending check-in counts for several sessions.
	 *
	 * @param int[] $event_ids Session IDs.
	 * @return array<int,array{approved:int,pending:int}>
	 */
	public static function counts( array $event_ids ) {
		global $wpdb;
		$event_ids = array_values( array_filter( array_map( 'intval', $event_ids ) ) );
		$counts    = array();
		if ( ! $event_ids ) {
			return $counts;
		}
		$table = self::table();
		$sql   = "SELECT event_id, status, COUNT(*) AS total FROM {$table} WHERE event_id IN (" . implode( ',', array_fill( 0, count( $event_ids ), '%d' ) ) . ') GROUP BY event_id, status';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( $sql, $event_ids ), ARRAY_A ) as $row ) {
			$id = (int) $row['event_id'];
			if ( ! isset( $counts[ $id ] ) ) {
				$counts[ $id ] = array(
					'approved' => 0,
					'pending'  => 0,
				);
			}
			if ( isset( $counts[ $id ][ $row['status'] ] ) ) {
				$counts[ $id ][ $row['status'] ] = (int) $row['total'];
			}
		}
		return $counts;
	}

	/**
	 * Net report figures for a list of check-ins (QNI / QTC style).
	 *
	 * @param array[] $checkins Check-ins.
	 * @return array{total:int,approved:int,pending:int,traffic_stations:int,traffic_count:int,short_time:int,mobile:int,relayed:int,online:int}
	 */
	public static function stats( array $checkins ) {
		$stats = array(
			'total'            => 0,
			'approved'         => 0,
			'pending'          => 0,
			'traffic_stations' => 0,
			'traffic_count'    => 0,
			'short_time'       => 0,
			'mobile'           => 0,
			'relayed'          => 0,
			'online'           => 0,
		);
		foreach ( $checkins as $c ) {
			if ( 'rejected' === $c['status'] ) {
				continue;
			}
			++$stats['total'];
			if ( 'pending' === $c['status'] ) {
				++$stats['pending'];
				continue;
			}
			++$stats['approved'];
			if ( $c['has_traffic'] ) {
				++$stats['traffic_stations'];
				$stats['traffic_count'] += max( 1, $c['traffic_count'] );
			}
			$stats['short_time'] += $c['short_time'];
			$stats['mobile']     += in_array( $c['station_type'], array( 'mobile', 'portable', 'handheld' ), true ) ? 1 : 0;
			$stats['relayed']    += '' !== $c['relayed_by'] ? 1 : 0;
			$stats['online']     += 'online' === $c['source'] ? 1 : 0;
		}
		return $stats;
	}

	/**
	 * Find an existing (non-rejected) check-in of the same station for a session.
	 *
	 * Portable / mobile designators are ignored, so W1AW/M matches W1AW.
	 *
	 * @param int    $event_id   Session ID.
	 * @param string $callsign   Callsign.
	 * @param int    $exclude_id Check-in to ignore (when editing).
	 * @return array|null
	 */
	public static function find_duplicate( $event_id, $callsign, $exclude_id = 0 ) {
		$base = arnl_base_callsign( $callsign );
		foreach ( self::for_event( $event_id, array( 'approved', 'pending' ) ) as $checkin ) {
			if ( (int) $checkin['id'] !== (int) $exclude_id && arnl_base_callsign( $checkin['callsign'] ) === $base ) {
				return $checkin;
			}
		}
		return null;
	}

	/**
	 * Most recent check-in of a callsign on any session (name / location look-up).
	 *
	 * @param string $callsign Callsign.
	 * @return array|null
	 */
	public static function last_by_callsign( $callsign ) {
		global $wpdb;
		$table = self::table();
		$base  = arnl_base_callsign( $callsign );
		if ( '' === $base ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE callsign LIKE %s AND status <> 'rejected' ORDER BY checkin_time DESC, id DESC LIMIT 25", '%' . $wpdb->esc_like( $base ) . '%' ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			if ( arnl_base_callsign( $row['callsign'] ) === $base ) {
				return self::hydrate( $row );
			}
		}
		return null;
	}

	/**
	 * Stations from previous check-ins matching what the NCS is typing:
	 * callsigns containing the text (prefix matches first) or names starting
	 * with it. One entry per station, with its most recent details.
	 *
	 * @param string $term  Search text.
	 * @param int    $limit Maximum number of stations.
	 * @return array[] Each: callsign, name, location, station_type, last_seen, checkins.
	 */
	public static function search_stations( $term, $limit = 8 ) {
		global $wpdb;
		$term = trim( (string) $term );
		if ( '' === $term ) {
			return array();
		}
		$call  = arnl_sanitize_callsign( $term );
		$table = self::table();
		$where = array();
		$args  = array();
		if ( '' !== $call ) {
			$where[] = 'callsign LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $call ) . '%';
		}
		$where[] = 'name LIKE %s';
		$args[]  = $wpdb->esc_like( $term ) . '%';
		$sql     = "SELECT callsign, name, location, station_type, checkin_time FROM {$table} WHERE (" . implode( ' OR ', $where ) . ") AND status <> 'rejected' ORDER BY checkin_time DESC, id DESC LIMIT 300";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );

		$stations = array();
		foreach ( (array) $rows as $row ) {
			$base = arnl_base_callsign( $row['callsign'] );
			if ( isset( $stations[ $base ] ) ) {
				++$stations[ $base ]['checkins'];
				foreach ( array( 'name', 'location' ) as $field ) {
					if ( '' === $stations[ $base ][ $field ] && '' !== $row[ $field ] ) {
						$stations[ $base ][ $field ] = $row[ $field ];
					}
				}
				continue;
			}
			$stations[ $base ] = array(
				'callsign'     => $base,
				'name'         => (string) $row['name'],
				'location'     => (string) $row['location'],
				'station_type' => (string) $row['station_type'],
				'last_seen'    => (string) $row['checkin_time'],
				'checkins'     => 1,
			);
		}

		// Callsigns starting with the typed text first, then other matches; most recent first within each.
		$starts = array();
		$others = array();
		foreach ( $stations as $base => $station ) {
			if ( '' !== $call && 0 === strpos( $base, $call ) ) {
				$starts[] = $station;
			} else {
				$others[] = $station;
			}
		}
		return array_slice( array_merge( $starts, $others ), 0, max( 1, (int) $limit ) );
	}

	/**
	 * Create a check-in.
	 *
	 * @param array $data Field values.
	 * @return int|WP_Error
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$row = self::prepare( array_merge( self::defaults(), $data ) );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$now               = current_time( 'mysql' );
		$row['created_at'] = $now;
		$row['updated_at'] = $now;
		if ( null === $row['checkin_time'] ) {
			$row['checkin_time'] = $now;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $wpdb->insert( self::table(), $row ) ) {
			return new WP_Error( 'arnl_db_error', __( 'The check-in could not be saved.', 'ar-net-logger' ) );
		}
		$id = (int) $wpdb->insert_id;
		/**
		 * Fires after a check-in is created.
		 *
		 * @param int   $id  Check-in ID.
		 * @param array $row Saved row.
		 */
		do_action( 'arnl_checkin_created', $id, $row );
		return $id;
	}

	/**
	 * Update a check-in. Missing fields keep their value.
	 *
	 * @param int   $id   Check-in ID.
	 * @param array $data Field values.
	 * @return true|WP_Error
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$current = self::get( $id );
		if ( ! $current ) {
			return new WP_Error( 'arnl_not_found', __( 'Check-in not found.', 'ar-net-logger' ) );
		}
		$row = self::prepare( array_merge( $current, $data ) );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		if ( null === $row['checkin_time'] ) {
			unset( $row['checkin_time'] );
		}
		$row['updated_at'] = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( false === $wpdb->update( self::table(), $row, array( 'id' => (int) $id ) ) ) {
			return new WP_Error( 'arnl_db_error', __( 'The check-in could not be saved.', 'ar-net-logger' ) );
		}
		do_action( 'arnl_checkin_updated', (int) $id );
		return true;
	}

	/**
	 * Delete a check-in.
	 *
	 * @param int $id Check-in ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Delete every check-in of a session.
	 *
	 * @param int $event_id Session ID.
	 * @return int Rows deleted.
	 */
	public static function delete_for_event( $event_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->delete( self::table(), array( 'event_id' => (int) $event_id ), array( '%d' ) );
	}

	/**
	 * Sanitize and validate fields into a database row.
	 *
	 * @param array $data Field values.
	 * @return array|WP_Error
	 */
	public static function prepare( array $data ) {
		$event_id = (int) $data['event_id'];
		if ( $event_id <= 0 || ! ARNL_Events::get( $event_id ) ) {
			return new WP_Error( 'arnl_event', __( 'Unknown net session.', 'ar-net-logger' ) );
		}

		$callsign = arnl_sanitize_callsign( $data['callsign'] );
		if ( ! arnl_is_valid_callsign( $callsign ) ) {
			return new WP_Error( 'arnl_callsign', __( 'Please enter a valid callsign.', 'ar-net-logger' ) );
		}

		$has_traffic = empty( $data['has_traffic'] ) ? 0 : 1;
		$precedence  = (string) $data['traffic_precedence'];
		$precedence  = $has_traffic && array_key_exists( $precedence, arnl_precedences() ) ? $precedence : ( $has_traffic ? 'routine' : '' );

		$checkin_time = trim( (string) $data['checkin_time'] );
		$parsed       = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $checkin_time );

		return array(
			'event_id'           => $event_id,
			'callsign'           => $callsign,
			'name'               => substr( sanitize_text_field( (string) $data['name'] ), 0, 100 ),
			'location'           => substr( sanitize_text_field( (string) $data['location'] ), 0, 190 ),
			'station_type'       => array_key_exists( (string) $data['station_type'], arnl_station_types() ) ? (string) $data['station_type'] : 'fixed',
			'short_time'         => empty( $data['short_time'] ) ? 0 : 1,
			'has_traffic'        => $has_traffic,
			'traffic_precedence' => $precedence,
			'traffic_count'      => $has_traffic ? min( 999, absint( $data['traffic_count'] ) ) : 0,
			'relayed_by'         => arnl_sanitize_callsign( $data['relayed_by'] ),
			'comments'           => substr( sanitize_textarea_field( (string) $data['comments'] ), 0, 1000 ),
			'source'             => 'online' === $data['source'] ? 'online' : 'admin',
			'status'             => array_key_exists( (string) $data['status'], arnl_checkin_statuses() ) ? (string) $data['status'] : 'approved',
			'acknowledged'       => empty( $data['acknowledged'] ) ? 0 : 1,
			'user_id'            => absint( $data['user_id'] ),
			'checkin_time'       => $parsed && $parsed->format( 'Y-m-d H:i:s' ) === $checkin_time ? $checkin_time : null,
		);
	}

	/**
	 * Typed record from a row.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function hydrate( array $row ) {
		$c = array_merge( self::defaults(), $row );
		foreach ( array( 'id', 'event_id', 'short_time', 'has_traffic', 'traffic_count', 'acknowledged', 'user_id' ) as $key ) {
			$c[ $key ] = (int) $c[ $key ];
		}
		$c['comments'] = (string) $c['comments'];
		return $c;
	}
}
