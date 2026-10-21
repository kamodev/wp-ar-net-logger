<?php
/**
 * Unit tests for ARNL_Recurrence (no WordPress needed).
 *
 * @package AR_Net_Logger
 */

use PHPUnit\Framework\TestCase;

/**
 * Recurrence rule expansion.
 */
class RecurrenceTest extends TestCase {

	public function test_weekly_single_day() {
		$rule = array(
			'recurrence'   => 'weekly',
			'days_of_week' => array( 2 ), // Tuesday.
		);
		$this->assertSame(
			array( '2026-10-06', '2026-10-13', '2026-10-20', '2026-10-27' ),
			ARNL_Recurrence::dates( $rule, '2026-10-01', '2026-10-31' )
		);
	}

	public function test_weekly_multiple_days() {
		$rule = array(
			'recurrence'   => 'weekly',
			'days_of_week' => array( 1, 3, 5 ),
		);
		$this->assertSame(
			array( '2026-10-19', '2026-10-21', '2026-10-23' ),
			ARNL_Recurrence::dates( $rule, '2026-10-19', '2026-10-25' )
		);
	}

	public function test_weekly_without_days_never_matches() {
		$this->assertSame( array(), ARNL_Recurrence::dates( array( 'recurrence' => 'weekly' ), '2026-10-01', '2026-10-31' ) );
	}

	public function test_biweekly_counts_from_start_date() {
		$rule = array(
			'recurrence'   => 'biweekly',
			'days_of_week' => array( 1 ), // Monday.
			'start_date'   => '2026-10-05',
		);
		$this->assertSame(
			array( '2026-10-05', '2026-10-19', '2026-11-02', '2026-11-16', '2026-11-30' ),
			ARNL_Recurrence::dates( $rule, '2026-09-01', '2026-11-30' )
		);
	}

	public function test_biweekly_multiple_days_share_the_same_week() {
		$rule = array(
			'recurrence'   => 'biweekly',
			'days_of_week' => array( 0, 6 ), // Sunday and Saturday of the same (Sunday-based) week.
			'start_date'   => '2026-10-04',
		);
		$this->assertSame(
			array( '2026-10-04', '2026-10-10', '2026-10-18', '2026-10-24' ),
			ARNL_Recurrence::dates( $rule, '2026-10-01', '2026-10-31' )
		);
	}

	public function test_monthly_nth_weekday() {
		$rule = array(
			'recurrence'     => 'monthly',
			'days_of_week'   => array( 4 ), // Thursday.
			'weeks_of_month' => array( 1, 3 ),
		);
		$this->assertSame(
			array( '2026-10-01', '2026-10-15', '2026-11-05', '2026-11-19' ),
			ARNL_Recurrence::dates( $rule, '2026-10-01', '2026-11-30' )
		);
	}

	public function test_monthly_last_weekday() {
		$rule = array(
			'recurrence'     => 'monthly',
			'days_of_week'   => array( 6 ), // Saturday.
			'weeks_of_month' => array( 'last' ),
		);
		// October 2026 has five Saturdays (the 31st is last); November's last is the 28th.
		$this->assertSame(
			array( '2026-10-31', '2026-11-28' ),
			ARNL_Recurrence::dates( $rule, '2026-10-01', '2026-11-30' )
		);
	}

	public function test_monthly_fifth_week_skips_short_months() {
		$rule = array(
			'recurrence'     => 'monthly',
			'days_of_week'   => array( 6 ),
			'weeks_of_month' => array( '5' ),
		);
		$this->assertSame( array( '2026-10-31' ), ARNL_Recurrence::dates( $rule, '2026-10-01', '2026-11-30' ) );
	}

	public function test_months_filter() {
		$rule = array(
			'recurrence'   => 'weekly',
			'days_of_week' => array( 3 ),
			'months'       => array( 6, 7, 8 ),
		);
		$dates = ARNL_Recurrence::dates( $rule, '2026-05-01', '2026-09-30' );
		$this->assertSame( '2026-06-03', $dates[0] );
		$this->assertSame( '2026-08-26', end( $dates ) );
		$this->assertCount( 13, $dates );
	}

	public function test_start_and_end_dates_bound_the_series() {
		$rule = array(
			'recurrence'   => 'weekly',
			'days_of_week' => array( 2 ),
			'start_date'   => '2026-10-10',
			'end_date'     => '2026-10-25',
		);
		$this->assertSame( array( '2026-10-13', '2026-10-20' ), ARNL_Recurrence::dates( $rule, '2026-10-01', '2026-10-31' ) );
	}

	public function test_once() {
		$rule = array(
			'recurrence' => 'once',
			'start_date' => '2026-06-27',
		);
		$this->assertSame( array( '2026-06-27' ), ARNL_Recurrence::dates( $rule, '2026-06-01', '2026-06-30' ) );
		$this->assertSame( array(), ARNL_Recurrence::dates( $rule, '2026-07-01', '2026-07-31' ) );
		$this->assertTrue( ARNL_Recurrence::matches( $rule, '2026-06-27' ) );
	}

	public function test_none_never_matches() {
		$rule = array(
			'recurrence'   => 'none',
			'days_of_week' => array( 0, 1, 2, 3, 4, 5, 6 ),
		);
		$this->assertSame( array(), ARNL_Recurrence::dates( $rule, '2026-10-01', '2026-10-31' ) );
		$this->assertFalse( ARNL_Recurrence::matches( $rule, '2026-10-21' ) );
	}

	public function test_matches_rejects_invalid_dates() {
		$rule = array(
			'recurrence'   => 'weekly',
			'days_of_week' => array( 2 ),
		);
		$this->assertFalse( ARNL_Recurrence::matches( $rule, '2026-02-31' ) );
		$this->assertFalse( ARNL_Recurrence::matches( $rule, 'not-a-date' ) );
	}

	public function test_inverted_range_is_empty() {
		$rule = array(
			'recurrence'   => 'weekly',
			'days_of_week' => array( 2 ),
		);
		$this->assertSame( array(), ARNL_Recurrence::dates( $rule, '2026-10-31', '2026-10-01' ) );
	}

	public function test_span_is_capped() {
		$rule = array(
			'recurrence'   => 'weekly',
			'days_of_week' => array( 0, 1, 2, 3, 4, 5, 6 ),
		);
		$this->assertCount( ARNL_Recurrence::MAX_SPAN_DAYS + 1, ARNL_Recurrence::dates( $rule, '2020-01-01', '2030-12-31' ) );
	}

	public function test_normalize_coerces_input() {
		$rule = ARNL_Recurrence::normalize(
			array(
				'days_of_week'   => array( '2', '9', '-1' ),
				'weeks_of_month' => array( '1', 'last', '7', 'x' ),
				'months'         => array( '12', '0' ),
			)
		);
		$this->assertSame( array( 2 ), $rule['days_of_week'] );
		$this->assertSame( array( 1, 'last' ), $rule['weeks_of_month'] );
		$this->assertSame( array( 12 ), $rule['months'] );
	}
}
