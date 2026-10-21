<?php
/**
 * Recurrence rules for nets. Pure PHP (no WordPress calls) so it can be unit tested.
 *
 * A rule is an array with:
 * - recurrence     weekly | biweekly | monthly | once | none
 * - days_of_week   int[] 0 (Sunday) .. 6 (Saturday)
 * - weeks_of_month array of 1..5 and/or 'last' (monthly only)
 * - months         int[] 1..12, empty for every month
 * - start_date     Y-m-d or '' (first possible date; anchor for biweekly; the date for "once")
 * - end_date       Y-m-d or '' (last possible date)
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Recurrence expansion.
 */
class ARNL_Recurrence {

	const WEEKLY   = 'weekly';
	const BIWEEKLY = 'biweekly';
	const MONTHLY  = 'monthly';
	const ONCE     = 'once';
	const NONE     = 'none';

	/**
	 * Longest range expanded in one call, to keep requests cheap.
	 */
	const MAX_SPAN_DAYS = 800;

	/**
	 * All dates (Y-m-d) between $from and $to (inclusive) that match the rule.
	 *
	 * @param array  $rule Rule.
	 * @param string $from Y-m-d.
	 * @param string $to   Y-m-d.
	 * @return string[]
	 */
	public static function dates( array $rule, $from, $to ) {
		$rule  = self::normalize( $rule );
		$start = self::date( $from );
		$end   = self::date( $to );
		if ( ! $start || ! $end || $end < $start || self::NONE === $rule['recurrence'] ) {
			return array();
		}

		if ( '' !== $rule['start_date'] && $start->format( 'Y-m-d' ) < $rule['start_date'] ) {
			$start = self::date( $rule['start_date'] );
		}
		if ( '' !== $rule['end_date'] && $end->format( 'Y-m-d' ) > $rule['end_date'] ) {
			$end = self::date( $rule['end_date'] );
		}
		$limit = $start->modify( '+' . self::MAX_SPAN_DAYS . ' days' );
		if ( $end > $limit ) {
			$end = $limit;
		}

		$dates = array();
		if ( self::ONCE === $rule['recurrence'] ) {
			$once = $rule['start_date'];
			if ( '' !== $once && $once >= $start->format( 'Y-m-d' ) && $once <= $end->format( 'Y-m-d' ) ) {
				$dates[] = $once;
			}
			return $dates;
		}

		for ( $day = $start; $day <= $end; $day = $day->modify( '+1 day' ) ) {
			if ( self::matches( $rule, $day ) ) {
				$dates[] = $day->format( 'Y-m-d' );
			}
		}
		return $dates;
	}

	/**
	 * Whether a single date matches the rule.
	 *
	 * @param array                    $rule Rule.
	 * @param DateTimeImmutable|string $day  Date.
	 * @return bool
	 */
	public static function matches( array $rule, $day ) {
		$rule = self::normalize( $rule );
		if ( is_string( $day ) ) {
			$day = self::date( $day );
		}
		if ( ! $day ) {
			return false;
		}
		$ymd = $day->format( 'Y-m-d' );

		if ( ( '' !== $rule['start_date'] && $ymd < $rule['start_date'] ) || ( '' !== $rule['end_date'] && $ymd > $rule['end_date'] ) ) {
			return false;
		}

		switch ( $rule['recurrence'] ) {
			case self::ONCE:
				return $ymd === $rule['start_date'];
			case self::WEEKLY:
			case self::BIWEEKLY:
			case self::MONTHLY:
				break;
			default:
				return false;
		}

		if ( $rule['months'] && ! in_array( (int) $day->format( 'n' ), $rule['months'], true ) ) {
			return false;
		}
		if ( ! in_array( (int) $day->format( 'w' ), $rule['days_of_week'], true ) ) {
			return false;
		}

		if ( self::BIWEEKLY === $rule['recurrence'] ) {
			// Count whole weeks from the Sunday of the anchor week.
			$anchor = self::date( '' !== $rule['start_date'] ? $rule['start_date'] : '1970-01-04' );
			$anchor = $anchor->modify( '-' . (int) $anchor->format( 'w' ) . ' days' );
			$weeks  = (int) floor( (int) $anchor->diff( $day )->format( '%r%a' ) / 7 );
			return 0 === abs( $weeks % 2 );
		}

		if ( self::MONTHLY === $rule['recurrence'] ) {
			$dom     = (int) $day->format( 'j' );
			$nth     = intdiv( $dom - 1, 7 ) + 1;
			$is_last = $dom + 7 > (int) $day->format( 't' );
			return in_array( $nth, $rule['weeks_of_month'], true ) || ( $is_last && in_array( 'last', $rule['weeks_of_month'], true ) );
		}

		return true;
	}

	/**
	 * Fill in missing keys and coerce types.
	 *
	 * @param array $rule Rule.
	 * @return array
	 */
	public static function normalize( array $rule ) {
		$rule = array_merge(
			array(
				'recurrence'     => self::WEEKLY,
				'days_of_week'   => array(),
				'weeks_of_month' => array(),
				'months'         => array(),
				'start_date'     => '',
				'end_date'       => '',
			),
			$rule
		);

		$rule['days_of_week'] = array_values( array_filter( array_map( 'intval', (array) $rule['days_of_week'] ), array( __CLASS__, 'is_weekday' ) ) );
		$rule['months']       = array_values(
			array_filter(
				array_map( 'intval', (array) $rule['months'] ),
				static function ( $m ) {
					return $m >= 1 && $m <= 12;
				}
			)
		);

		$weeks = array();
		foreach ( (array) $rule['weeks_of_month'] as $w ) {
			if ( 'last' === $w ) {
				$weeks[] = 'last';
			} elseif ( is_numeric( $w ) && (int) $w >= 1 && (int) $w <= 5 ) {
				$weeks[] = (int) $w;
			}
		}
		$rule['weeks_of_month'] = $weeks;
		$rule['start_date']     = (string) $rule['start_date'];
		$rule['end_date']       = (string) $rule['end_date'];

		return $rule;
	}

	/**
	 * Weekday filter callback.
	 *
	 * @param int $d Day.
	 * @return bool
	 */
	public static function is_weekday( $d ) {
		return $d >= 0 && $d <= 6;
	}

	/**
	 * Parse Y-m-d as a timezone-neutral date.
	 *
	 * @param string $ymd Date.
	 * @return DateTimeImmutable|null
	 */
	protected static function date( $ymd ) {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $ymd, new DateTimeZone( 'UTC' ) );
		return $date && $date->format( 'Y-m-d' ) === $ymd ? $date : null;
	}
}
