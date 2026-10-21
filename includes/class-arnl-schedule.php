<?php
/**
 * Builds the schedule: expands net recurrences and merges session rows
 * (overrides, cancellations, one-off sessions) into a list of occurrences.
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Schedule service.
 */
class ARNL_Schedule {

	/**
	 * Occurrences in a date range, sorted by start time.
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type string       $from              First date (Y-m-d). Default today.
	 *     @type string       $to                Last date (Y-m-d). Default +30 days.
	 *     @type string|array $net               Net IDs and/or slugs.
	 *     @type string|array $net_type          Net type keys.
	 *     @type string|array $mode              Mode keys.
	 *     @type string|array $band              Band keys.
	 *     @type string       $net_status        active (public) | any (admin: also show history of inactive nets).
	 *     @type bool         $include_cancelled Include cancelled sessions.
	 *     @type string|array $status            Only these session statuses.
	 *     @type int          $limit             Maximum number returned (0 = all).
	 * }
	 * @return ARNL_Occurrence[]
	 */
	public static function occurrences( array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'from'              => arnl_today(),
				'to'                => '',
				'net'               => array(),
				'net_type'          => array(),
				'mode'              => array(),
				'band'              => array(),
				'net_status'        => 'active',
				'include_cancelled' => true,
				'status'            => array(),
				'limit'             => 0,
			)
		);
		$from = arnl_sanitize_date( $args['from'] );
		$from = '' !== $from ? $from : arnl_today();
		$to   = arnl_sanitize_date( $args['to'] );
		$to   = '' !== $to ? $to : gmdate( 'Y-m-d', strtotime( $from . ' +30 days' ) );
		if ( $to < $from ) {
			return array();
		}

		$nets = ARNL_Nets::query(
			array(
				'status'   => 'any' === $args['net_status'] ? 'any' : 'active',
				'include'  => $args['net'],
				'net_type' => $args['net_type'],
				'mode'     => $args['mode'],
				'band'     => $args['band'],
			)
		);
		if ( ! $nets ) {
			return array();
		}

		$events = ARNL_Events::query(
			array(
				'net_id'           => array_keys( $nets ),
				'from'             => $from,
				'to'               => $to,
				'match_occurrence' => true,
			)
		);

		$overridden = array();
		foreach ( $events as $event ) {
			if ( '' !== $event['occurrence_date'] ) {
				$overridden[ $event['net_id'] . '|' . $event['occurrence_date'] ] = true;
			}
		}

		$out = array();
		foreach ( $nets as $net ) {
			if ( 'active' !== $net['status'] ) {
				continue;
			}
			foreach ( ARNL_Recurrence::dates( ARNL_Nets::rule( $net ), $from, $to ) as $date ) {
				if ( ! isset( $overridden[ $net['id'] . '|' . $date ] ) ) {
					$out[] = ARNL_Occurrence::from_net( $net, $date );
				}
			}
		}
		foreach ( $events as $event ) {
			if ( $event['event_date'] >= $from && $event['event_date'] <= $to && isset( $nets[ $event['net_id'] ] ) ) {
				$out[] = ARNL_Occurrence::from_event( $event, $nets[ $event['net_id'] ] );
			}
		}

		$statuses = arnl_parse_list( $args['status'] );
		$out      = array_values(
			array_filter(
				$out,
				static function ( ARNL_Occurrence $o ) use ( $args, $statuses ) {
					if ( ! $args['include_cancelled'] && $o->is_cancelled() ) {
						return false;
					}
					return ! $statuses || in_array( $o->status, $statuses, true );
				}
			)
		);

		usort( $out, array( __CLASS__, 'compare' ) );

		if ( $args['limit'] > 0 ) {
			$out = array_slice( $out, 0, (int) $args['limit'] );
		}

		/**
		 * Filters the generated occurrences.
		 *
		 * @param ARNL_Occurrence[] $out  Occurrences.
		 * @param array             $args Query arguments.
		 */
		return apply_filters( 'arnl_occurrences', $out, $args );
	}

	/**
	 * Sort callback: by start, then net name.
	 *
	 * @param ARNL_Occurrence $a A.
	 * @param ARNL_Occurrence $b B.
	 * @return int
	 */
	public static function compare( ARNL_Occurrence $a, ARNL_Occurrence $b ) {
		$cmp = strcmp( $a->date . ' ' . $a->start_time, $b->date . ' ' . $b->start_time );
		return 0 !== $cmp ? $cmp : strcasecmp( $a->net['name'], $b->net['name'] );
	}

	/**
	 * Resolve an occurrence key ("e12" or "n3-20261021").
	 *
	 * A virtual key whose recurring date already has a session row resolves
	 * to that row, so old links keep working after an override.
	 *
	 * @param string $key Key.
	 * @return ARNL_Occurrence|null
	 */
	public static function find( $key ) {
		$key = (string) $key;
		if ( preg_match( '/^e(\d+)$/', $key, $m ) ) {
			$event = ARNL_Events::get( (int) $m[1] );
			$net   = $event ? ARNL_Nets::get( $event['net_id'] ) : null;
			return $event && $net ? ARNL_Occurrence::from_event( $event, $net ) : null;
		}
		if ( preg_match( '/^n(\d+)-(\d{4})(\d{2})(\d{2})$/', $key, $m ) ) {
			return self::find_by_date( (int) $m[1], $m[2] . '-' . $m[3] . '-' . $m[4] );
		}
		return null;
	}

	/**
	 * Occurrence of a net on a recurring date, or null if the net does not meet that day.
	 *
	 * @param int    $net_id Net ID.
	 * @param string $date   Y-m-d.
	 * @return ARNL_Occurrence|null
	 */
	public static function find_by_date( $net_id, $date ) {
		$net = ARNL_Nets::get( $net_id );
		if ( ! $net || '' === arnl_sanitize_date( $date ) ) {
			return null;
		}
		$event = ARNL_Events::get_by_occurrence( $net_id, $date );
		if ( $event ) {
			return ARNL_Occurrence::from_event( $event, $net );
		}
		if ( 'active' === $net['status'] && ARNL_Recurrence::matches( ARNL_Nets::rule( $net ), $date ) ) {
			return ARNL_Occurrence::from_net( $net, $date );
		}
		return null;
	}

	/**
	 * Make sure an occurrence has a session row (needed before logging check-ins
	 * or overriding details) and return it refreshed.
	 *
	 * @param ARNL_Occurrence $occurrence Occurrence.
	 * @return ARNL_Occurrence|WP_Error
	 */
	public static function materialize( ARNL_Occurrence $occurrence ) {
		if ( ! $occurrence->is_virtual() ) {
			return $occurrence;
		}
		$id = ARNL_Events::insert(
			array(
				'net_id'          => $occurrence->net_id,
				'occurrence_date' => $occurrence->occurrence_date,
				'event_date'      => $occurrence->date,
				'start_time'      => $occurrence->start_time,
				'end_time'        => $occurrence->end_time,
				'status'          => 'scheduled',
			)
		);
		if ( is_wp_error( $id ) ) {
			$data = $id->get_error_data();
			if ( 'arnl_exists' !== $id->get_error_code() || empty( $data['id'] ) ) {
				return $id;
			}
			$id = (int) $data['id'];
		}
		$found = self::find( 'e' . $id );
		return $found ? $found : new WP_Error( 'arnl_not_found', __( 'Session not found.', 'ar-net-logger' ) );
	}

	/**
	 * The session of a net that is running now, or the next one.
	 *
	 * @param int $net_id Net ID.
	 * @return ARNL_Occurrence|null
	 */
	public static function current_or_next( $net_id ) {
		$now   = arnl_now();
		$list  = self::occurrences(
			array(
				'net'               => array( (int) $net_id ),
				'from'              => $now->modify( '-1 day' )->format( 'Y-m-d' ),
				'to'                => $now->modify( '+400 days' )->format( 'Y-m-d' ),
				'include_cancelled' => false,
			)
		);
		$after = (int) ARNL_Settings::get( 'checkin_after' );
		foreach ( $list as $occurrence ) {
			if ( 'completed' === $occurrence->status ) {
				continue;
			}
			if ( 'active' === $occurrence->status || $occurrence->end()->modify( '+' . $after . ' minutes' ) >= $now ) {
				return $occurrence;
			}
		}
		return null;
	}
}
