<?php
/**
 * A single net session on the calendar, generated from a net's recurrence
 * ("virtual") or backed by a session row (override, one-off or logged).
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Occurrence value object.
 */
class ARNL_Occurrence {

	/**
	 * Session row ID, 0 when virtual.
	 *
	 * @var int
	 */
	public $event_id = 0;

	/**
	 * Net ID.
	 *
	 * @var int
	 */
	public $net_id = 0;

	/**
	 * Net record.
	 *
	 * @var array
	 */
	public $net = array();

	/**
	 * Session row, when not virtual.
	 *
	 * @var array|null
	 */
	public $event = null;

	/**
	 * Recurring date this session belongs to ('' for one-off sessions).
	 *
	 * @var string
	 */
	public $occurrence_date = '';

	/**
	 * Actual date (Y-m-d).
	 *
	 * @var string
	 */
	public $date = '';

	/**
	 * Start time (H:i:s).
	 *
	 * @var string
	 */
	public $start_time = '';

	/**
	 * End time (H:i:s). Earlier than the start time means the net runs past midnight.
	 *
	 * @var string
	 */
	public $end_time = '';

	/**
	 * Topic.
	 *
	 * @var string
	 */
	public $topic = '';

	/**
	 * Net Control Station callsign.
	 *
	 * @var string
	 */
	public $ncs_callsign = '';

	/**
	 * Net Control Station operator name.
	 *
	 * @var string
	 */
	public $ncs_name = '';

	/**
	 * Frequency (session override or the net's).
	 *
	 * @var string
	 */
	public $frequency = '';

	/**
	 * Public notes.
	 *
	 * @var string
	 */
	public $notes = '';

	/**
	 * Status: scheduled | active | completed | cancelled.
	 *
	 * @var string
	 */
	public $status = 'scheduled';

	/**
	 * Build a virtual occurrence from a net's recurrence.
	 *
	 * @param array  $net  Net.
	 * @param string $date Date (Y-m-d).
	 * @return self
	 */
	public static function from_net( array $net, $date ) {
		$o                  = new self();
		$o->net             = $net;
		$o->net_id          = (int) $net['id'];
		$o->occurrence_date = $date;
		$o->date            = $date;
		$o->start_time      = $net['start_time'];
		$o->end_time        = $net['end_time'];
		$o->ncs_callsign    = $net['ncs_callsign'];
		$o->ncs_name        = $net['ncs_name'];
		$o->frequency       = $net['frequency'];
		return $o;
	}

	/**
	 * Build an occurrence from a session row. Blank NCS / frequency fall back to the net's.
	 *
	 * @param array $event Session row.
	 * @param array $net   Net.
	 * @return self
	 */
	public static function from_event( array $event, array $net ) {
		$o                  = new self();
		$o->net             = $net;
		$o->event           = $event;
		$o->event_id        = (int) $event['id'];
		$o->net_id          = (int) $net['id'];
		$o->occurrence_date = $event['occurrence_date'];
		$o->date            = $event['event_date'];
		$o->start_time      = $event['start_time'];
		$o->end_time        = $event['end_time'];
		$o->topic           = $event['topic'];
		$o->ncs_callsign    = '' !== $event['ncs_callsign'] ? $event['ncs_callsign'] : $net['ncs_callsign'];
		$o->ncs_name        = '' !== $event['ncs_callsign'] ? $event['ncs_name'] : $net['ncs_name'];
		$o->frequency       = '' !== $event['frequency'] ? $event['frequency'] : $net['frequency'];
		$o->notes           = $event['notes'];
		$o->status          = $event['status'];
		return $o;
	}

	/**
	 * Stable identifier used in URLs: "e{id}" for stored sessions, "n{net}-{Ymd}" for virtual ones.
	 *
	 * @return string
	 */
	public function key() {
		if ( $this->event_id ) {
			return 'e' . $this->event_id;
		}
		return 'n' . $this->net_id . '-' . str_replace( '-', '', $this->date );
	}

	/**
	 * Whether this occurrence has no session row yet.
	 *
	 * @return bool
	 */
	public function is_virtual() {
		return 0 === $this->event_id;
	}

	/**
	 * Whether this is an extra session outside the recurrence.
	 *
	 * @return bool
	 */
	public function is_one_off() {
		return ! $this->is_virtual() && '' === $this->occurrence_date;
	}

	/**
	 * Whether the date or time differs from the regular schedule.
	 *
	 * @return bool
	 */
	public function is_rescheduled() {
		return '' !== $this->occurrence_date && ( $this->date !== $this->occurrence_date || $this->start_time !== $this->net['start_time'] );
	}

	/**
	 * Whether the session was cancelled.
	 *
	 * @return bool
	 */
	public function is_cancelled() {
		return 'cancelled' === $this->status;
	}

	/**
	 * Start moment in the site timezone.
	 *
	 * @return DateTimeImmutable
	 */
	public function start() {
		return new DateTimeImmutable( $this->date . ' ' . $this->start_time, wp_timezone() );
	}

	/**
	 * End moment; nets ending at or before their start time end the next day.
	 *
	 * @return DateTimeImmutable
	 */
	public function end() {
		$end = new DateTimeImmutable( $this->date . ' ' . $this->end_time, wp_timezone() );
		return $end <= $this->start() ? $end->modify( '+1 day' ) : $end;
	}

	/**
	 * Online check-in window.
	 *
	 * @return DateTimeImmutable[] Open and close moments.
	 */
	public function checkin_window() {
		$before = (int) ARNL_Settings::get( 'checkin_before' );
		$after  = (int) ARNL_Settings::get( 'checkin_after' );
		return array(
			$this->start()->modify( '-' . $before . ' minutes' ),
			$this->end()->modify( '+' . $after . ' minutes' ),
		);
	}

	/**
	 * Whether visitors can check in online right now.
	 *
	 * Open while the NCS has the net "In Session", or during the window
	 * around the scheduled time; never for cancelled or closed sessions.
	 *
	 * @param DateTimeImmutable|null $now Moment to test.
	 * @return bool
	 */
	public function checkins_open( $now = null ) {
		if ( ! ARNL_Settings::get( 'public_checkins' ) || empty( $this->net['allow_checkins'] ) || 'active' !== $this->net['status'] ) {
			$open = false;
		} elseif ( 'active' === $this->status ) {
			$open = true;
		} elseif ( 'scheduled' !== $this->status ) {
			$open = false;
		} else {
			$now           = $now ? $now : arnl_now();
			list( $o, $c ) = $this->checkin_window();
			$open          = $now >= $o && $now <= $c;
		}
		/**
		 * Filters whether online check-ins are open for a session.
		 *
		 * @param bool            $open       Open.
		 * @param ARNL_Occurrence $occurrence Session.
		 */
		return (bool) apply_filters( 'arnl_checkins_open', $open, $this );
	}

	/**
	 * Whether check-ins have not opened yet.
	 *
	 * @return bool
	 */
	public function checkins_upcoming() {
		list( $open ) = $this->checkin_window();
		return 'scheduled' === $this->status && arnl_now() < $open;
	}

	/**
	 * Display title: net name.
	 *
	 * @return string
	 */
	public function title() {
		return $this->net['name'];
	}

	/**
	 * Calendar UID: stable across reschedules of the same recurring date.
	 *
	 * @return string
	 */
	public function uid() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( '' !== $this->occurrence_date ) {
			return 'arnl-' . $this->net_id . '-' . str_replace( '-', '', $this->occurrence_date ) . '@' . $host;
		}
		return 'arnl-session-' . $this->event_id . '@' . $host;
	}
}
