<?php
/**
 * Net sessions repository (CRUD).
 *
 * A row exists only for a session that differs from, or adds to, its net's
 * recurring schedule: a date / time / topic / NCS override, a cancellation,
 * a special one-off session, or any session that has check-ins. Plain
 * recurring sessions are generated on the fly (see ARNL_Schedule).
 *
 * occurrence_date holds the recurring date a row replaces; it is NULL for
 * extra sessions that are not part of the recurrence.
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sessions data access.
 */
class ARNL_Events {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		$tables = ARNL_Install::tables();
		return $tables['events'];
	}

	/**
	 * Default field values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'id'              => 0,
			'net_id'          => 0,
			'occurrence_date' => '',
			'event_date'      => '',
			'start_time'      => '',
			'end_time'        => '',
			'topic'           => '',
			'ncs_callsign'    => '',
			'ncs_name'        => '',
			'frequency'       => '',
			'notes'           => '',
			'log_notes'       => '',
			'status'          => 'scheduled',
			'opened_at'       => '',
			'closed_at'       => '',
			'created_at'      => '',
			'updated_at'      => '',
		);
	}

	/**
	 * Get a session by ID.
	 *
	 * @param int $id Session ID.
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
	 * Get the row that overrides a recurring date of a net.
	 *
	 * @param int    $net_id Net ID.
	 * @param string $date   Recurring date (Y-m-d).
	 * @return array|null
	 */
	public static function get_by_occurrence( $net_id, $date ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE net_id = %d AND occurrence_date = %s", (int) $net_id, $date ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Query sessions.
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type int|int[] $net_id            Net ID(s).
	 *     @type string    $from              Earliest date (Y-m-d).
	 *     @type string    $to                Latest date (Y-m-d).
	 *     @type bool      $match_occurrence  Also match rows whose original recurring date is in range.
	 *     @type string[]  $status            Statuses.
	 *     @type string    $order             ASC | DESC by date and time.
	 *     @type int       $number            Limit.
	 * }
	 * @return array[]
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'net_id'           => array(),
				'from'             => '',
				'to'               => '',
				'match_occurrence' => false,
				'status'           => array(),
				'order'            => 'ASC',
				'number'           => 0,
			)
		);

		$where  = array( '1=1' );
		$params = array();

		$net_ids = array_filter( array_map( 'intval', (array) $args['net_id'] ) );
		if ( $net_ids ) {
			$where[] = 'net_id IN (' . implode( ',', array_fill( 0, count( $net_ids ), '%d' ) ) . ')';
			$params  = array_merge( $params, $net_ids );
		}

		if ( '' !== $args['from'] && '' !== $args['to'] ) {
			if ( $args['match_occurrence'] ) {
				$where[] = '((event_date BETWEEN %s AND %s) OR (occurrence_date BETWEEN %s AND %s))';
				array_push( $params, $args['from'], $args['to'], $args['from'], $args['to'] );
			} else {
				$where[] = 'event_date BETWEEN %s AND %s';
				array_push( $params, $args['from'], $args['to'] );
			}
		} elseif ( '' !== $args['from'] ) {
			$where[]  = 'event_date >= %s';
			$params[] = $args['from'];
		} elseif ( '' !== $args['to'] ) {
			$where[]  = 'event_date <= %s';
			$params[] = $args['to'];
		}

		$statuses = arnl_parse_list( $args['status'] );
		if ( $statuses ) {
			$where[] = 'status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')';
			$params  = array_merge( $params, $statuses );
		}

		$order = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
		$table = self::table();
		$sql   = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY event_date {$order}, start_time {$order}, id {$order}";
		if ( $args['number'] > 0 ) {
			$sql     .= ' LIMIT %d';
			$params[] = (int) $args['number'];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $params ? $wpdb->prepare( $sql, $params ) : $sql, ARRAY_A );
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	/**
	 * Create a session.
	 *
	 * @param array $data Field values; net defaults fill blanks.
	 * @return int|WP_Error New ID.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$row = self::prepare( array_merge( self::defaults(), $data ) );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		if ( $row['occurrence_date'] ) {
			$existing = self::get_by_occurrence( $row['net_id'], $row['occurrence_date'] );
			if ( $existing ) {
				return new WP_Error( 'arnl_exists', __( 'That scheduled session already has its own record.', 'ar-net-logger' ), array( 'id' => $existing['id'] ) );
			}
		}
		$now               = current_time( 'mysql' );
		$row['created_at'] = $now;
		$row['updated_at'] = $now;

		// A concurrent request may have created the same occurrence; the unique key rejects the duplicate.
		$suppress = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert( self::table(), $row );
		$wpdb->suppress_errors( $suppress );

		if ( ! $ok ) {
			$existing = $row['occurrence_date'] ? self::get_by_occurrence( $row['net_id'], $row['occurrence_date'] ) : null;
			if ( $existing ) {
				return new WP_Error( 'arnl_exists', __( 'That scheduled session already has its own record.', 'ar-net-logger' ), array( 'id' => $existing['id'] ) );
			}
			return new WP_Error( 'arnl_db_error', __( 'The session could not be saved.', 'ar-net-logger' ) );
		}
		$id = (int) $wpdb->insert_id;
		do_action( 'arnl_event_saved', $id, true );
		return $id;
	}

	/**
	 * Update a session. Missing fields keep their value.
	 *
	 * @param int   $id   Session ID.
	 * @param array $data Field values.
	 * @return true|WP_Error
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$current = self::get( $id );
		if ( ! $current ) {
			return new WP_Error( 'arnl_not_found', __( 'Session not found.', 'ar-net-logger' ) );
		}
		$row = self::prepare( array_merge( $current, $data ) );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$row['updated_at'] = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( false === $wpdb->update( self::table(), $row, array( 'id' => (int) $id ) ) ) {
			return new WP_Error( 'arnl_db_error', __( 'The session could not be saved.', 'ar-net-logger' ) );
		}
		do_action( 'arnl_event_saved', (int) $id, false );
		return true;
	}

	/**
	 * Delete a session and its check-ins.
	 *
	 * @param int $id Session ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		do_action( 'arnl_before_delete_event', $id );
		ARNL_Checkins::delete_for_event( $id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Sanitize and validate fields into a database row.
	 *
	 * @param array $data Field values.
	 * @return array|WP_Error
	 */
	public static function prepare( array $data ) {
		$net = ARNL_Nets::get( (int) $data['net_id'] );
		if ( ! $net ) {
			return new WP_Error( 'arnl_net', __( 'Please choose a net.', 'ar-net-logger' ) );
		}

		$event_date = arnl_sanitize_date( $data['event_date'] );
		if ( '' === $event_date ) {
			return new WP_Error( 'arnl_date', __( 'Please enter a valid date.', 'ar-net-logger' ) );
		}
		$occurrence = arnl_sanitize_date( $data['occurrence_date'] );

		$start_time = arnl_sanitize_time( $data['start_time'], $net['start_time'] );
		$end_time   = arnl_sanitize_time( $data['end_time'], $net['end_time'] );

		$status = array_key_exists( (string) $data['status'], arnl_event_statuses() ) ? (string) $data['status'] : 'scheduled';

		return array(
			'net_id'          => $net['id'],
			'occurrence_date' => '' !== $occurrence ? $occurrence : null,
			'event_date'      => $event_date,
			'start_time'      => $start_time,
			'end_time'        => $end_time,
			'topic'           => substr( sanitize_text_field( (string) $data['topic'] ), 0, 255 ),
			'ncs_callsign'    => arnl_sanitize_callsign( $data['ncs_callsign'] ),
			'ncs_name'        => substr( sanitize_text_field( (string) $data['ncs_name'] ), 0, 100 ),
			'frequency'       => substr( sanitize_text_field( (string) $data['frequency'] ), 0, 100 ),
			'notes'           => wp_kses_post( (string) $data['notes'] ),
			'log_notes'       => sanitize_textarea_field( (string) $data['log_notes'] ),
			'status'          => $status,
			'opened_at'       => self::sanitize_datetime( $data['opened_at'] ),
			'closed_at'       => self::sanitize_datetime( $data['closed_at'] ),
		);
	}

	/**
	 * Y-m-d H:i:s or null.
	 *
	 * @param string $value Value.
	 * @return string|null
	 */
	protected static function sanitize_datetime( $value ) {
		$value = trim( (string) $value );
		$dt    = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $value );
		return $dt && $dt->format( 'Y-m-d H:i:s' ) === $value ? $value : null;
	}

	/**
	 * Typed record from a row.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function hydrate( array $row ) {
		$event = array_merge( self::defaults(), $row );
		foreach ( array( 'occurrence_date', 'notes', 'log_notes', 'opened_at', 'closed_at' ) as $key ) {
			$event[ $key ] = (string) $event[ $key ];
		}
		$event['id']     = (int) $event['id'];
		$event['net_id'] = (int) $event['net_id'];
		return $event;
	}
}
