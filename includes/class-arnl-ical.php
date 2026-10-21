<?php
/**
 * Calendar export (iCalendar, RFC 5545): subscribable feeds and single-session downloads.
 *
 * Feed:     /?arnl_ics=feed[&net=slug,12][&type=traffic][&mode=fm][&band=2m]
 * Session:  /?arnl_ics=e12  or  /?arnl_ics=n3-20261021
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Calendar (.ics) export.
 */
class ARNL_ICal {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ), 1 );
		add_action( 'save_post', array( __CLASS__, 'forget_event_page' ) );
	}

	/**
	 * Public query vars.
	 *
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'arnl_ics';
		return $vars;
	}

	/**
	 * Serve a feed or a single session when requested.
	 */
	public static function maybe_serve() {
		$which = (string) get_query_var( 'arnl_ics' );
		if ( '' === $which ) {
			return;
		}
		if ( ! ARNL_Settings::get( 'ics_enabled' ) ) {
			status_header( 404 );
			exit;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public, read-only feed.
		$download = ! empty( $_GET['download'] );

		if ( 'feed' === $which ) {
			$filters = array();
			foreach ( array(
				'net'  => 'net',
				'type' => 'net_type',
				'mode' => 'mode',
				'band' => 'band',
			) as $param => $arg ) {
				$filters[ $arg ] = isset( $_GET[ $param ] ) ? arnl_parse_list( sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) ) : array();
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			$now         = arnl_now();
			$occurrences = ARNL_Schedule::occurrences(
				array_merge(
					$filters,
					array(
						'from' => $now->modify( '-' . (int) ARNL_Settings::get( 'ics_past_days' ) . ' days' )->format( 'Y-m-d' ),
						'to'   => $now->modify( '+' . (int) ARNL_Settings::get( 'ics_future_days' ) . ' days' )->format( 'Y-m-d' ),
					)
				)
			);

			$name = get_bloginfo( 'name' ) . ' – ' . __( 'Nets', 'ar-net-logger' );
			if ( 1 === count( $filters['net'] ) ) {
				$net  = ARNL_Nets::find( $filters['net'][0] );
				$name = $net ? $net['name'] : $name;
			}
			self::send( self::build( $occurrences, $name ), sanitize_title( $name ) . '.ics', $download );
		}

		$occurrence = ARNL_Schedule::find( $which );
		if ( ! $occurrence || 'active' !== $occurrence->net['status'] ) {
			status_header( 404 );
			exit;
		}
		self::send( self::build( array( $occurrence ), $occurrence->title() ), $occurrence->net['slug'] . '-' . $occurrence->date . '.ics', true );
	}

	/**
	 * Build a VCALENDAR document.
	 *
	 * @param ARNL_Occurrence[] $occurrences Sessions.
	 * @param string            $name        Calendar name.
	 * @return string
	 */
	public static function build( array $occurrences, $name ) {
		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//AR Net Logger//' . ARNL_VERSION . '//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . self::escape( $name ),
			'REFRESH-INTERVAL;VALUE=DURATION:PT6H',
			'X-PUBLISHED-TTL:PT6H',
		);
		$tz    = wp_timezone_string();
		if ( false !== strpos( $tz, '/' ) || 'UTC' === $tz ) {
			$lines[] = 'X-WR-TIMEZONE:' . $tz;
		}

		$base = self::event_page_url();
		foreach ( $occurrences as $o ) {
			$lines = array_merge( $lines, self::vevent( $o, $base ) );
		}
		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", array_map( array( __CLASS__, 'fold' ), $lines ) ) . "\r\n";
	}

	/**
	 * VEVENT lines for one session.
	 *
	 * @param ARNL_Occurrence $o    Session.
	 * @param string          $base Event page URL ('' to omit URLs).
	 * @return string[]
	 */
	public static function vevent( ARNL_Occurrence $o, $base = '' ) {
		$utc      = new DateTimeZone( 'UTC' );
		$url      = '' !== $base ? arnl_event_url( $o, $base ) : '';
		$location = arnl_describe_frequency( $o->net, $o->frequency );
		$summary  = $o->title() . ( '' !== $o->topic ? ' – ' . $o->topic : '' );
		if ( $o->is_cancelled() ) {
			/* translators: %s: session title. */
			$summary = sprintf( __( 'CANCELLED: %s', 'ar-net-logger' ), $summary );
		}

		$modified = $o->event ? $o->event['updated_at'] : $o->net['updated_at'];
		$modified = $modified ? new DateTimeImmutable( $modified, wp_timezone() ) : new DateTimeImmutable( 'now', $utc );

		$lines = array(
			'BEGIN:VEVENT',
			'UID:' . $o->uid(),
			'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
			'LAST-MODIFIED:' . $modified->setTimezone( $utc )->format( 'Ymd\THis\Z' ),
			'DTSTART:' . $o->start()->setTimezone( $utc )->format( 'Ymd\THis\Z' ),
			'DTEND:' . $o->end()->setTimezone( $utc )->format( 'Ymd\THis\Z' ),
			'SUMMARY:' . self::escape( $summary ),
			'DESCRIPTION:' . self::escape( self::description( $o, $url ) ),
			'STATUS:' . ( $o->is_cancelled() ? 'CANCELLED' : 'CONFIRMED' ),
			'TRANSP:TRANSPARENT',
			'CATEGORIES:' . self::escape( arnl_label( arnl_net_types(), $o->net['net_type'] ) ),
		);
		if ( '' !== $location ) {
			$lines[] = 'LOCATION:' . self::escape( $location );
		}
		if ( '' !== $url ) {
			$lines[] = 'URL:' . $url;
		}
		$lines[] = 'END:VEVENT';

		/**
		 * Filters the VEVENT lines of a session.
		 *
		 * @param string[]        $lines Lines (unfolded, already escaped).
		 * @param ARNL_Occurrence $o     Session.
		 */
		return apply_filters( 'arnl_ics_vevent', $lines, $o );
	}

	/**
	 * Plain-text description of a session.
	 *
	 * @param ARNL_Occurrence $o   Session.
	 * @param string          $url Details URL.
	 * @return string
	 */
	public static function description( ARNL_Occurrence $o, $url = '' ) {
		$parts = array();
		if ( '' !== $o->topic ) {
			/* translators: %s: topic. */
			$parts[] = sprintf( __( 'Topic: %s', 'ar-net-logger' ), $o->topic );
		}
		$freq = arnl_describe_frequency( $o->net, $o->frequency );
		if ( '' !== $freq ) {
			/* translators: %s: frequency / mode. */
			$parts[] = sprintf( __( 'Frequency: %s', 'ar-net-logger' ), $freq );
		}
		if ( '' !== $o->ncs_callsign ) {
			$parts[] = ARNL_Calendar::ncs_label( $o );
		}
		/* translators: %s: net type. */
		$parts[] = sprintf( __( 'Net type: %s', 'ar-net-logger' ), arnl_label( arnl_net_types(), $o->net['net_type'] ) );
		foreach ( array( $o->notes, $o->net['description'] ) as $text ) {
			$text = trim( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) );
			if ( '' !== $text ) {
				$parts[] = $text;
			}
		}
		if ( '' !== $url ) {
			/* translators: %s: URL. */
			$parts[] = sprintf( __( 'Details and online check-in: %s', 'ar-net-logger' ), $url );
		}
		return implode( "\n", $parts );
	}

	/**
	 * Google Calendar "add event" link.
	 *
	 * @param ARNL_Occurrence $o   Session.
	 * @param string          $url Details URL.
	 * @return string
	 */
	public static function google_url( ARNL_Occurrence $o, $url = '' ) {
		$utc = new DateTimeZone( 'UTC' );
		return add_query_arg(
			array_map(
				'rawurlencode',
				array(
					'action'   => 'TEMPLATE',
					'text'     => $o->title() . ( '' !== $o->topic ? ' – ' . $o->topic : '' ),
					'dates'    => $o->start()->setTimezone( $utc )->format( 'Ymd\THis\Z' ) . '/' . $o->end()->setTimezone( $utc )->format( 'Ymd\THis\Z' ),
					'details'  => self::description( $o, $url ),
					'location' => arnl_describe_frequency( $o->net, $o->frequency ),
				)
			),
			'https://calendar.google.com/calendar/render'
		);
	}

	/**
	 * Outlook.com "add event" link.
	 *
	 * @param ARNL_Occurrence $o   Session.
	 * @param string          $url Details URL.
	 * @return string
	 */
	public static function outlook_url( ARNL_Occurrence $o, $url = '' ) {
		return add_query_arg(
			array_map(
				'rawurlencode',
				array(
					'path'     => '/calendar/action/compose',
					'rru'      => 'addevent',
					'subject'  => $o->title() . ( '' !== $o->topic ? ' – ' . $o->topic : '' ),
					'startdt'  => $o->start()->format( DATE_ATOM ),
					'enddt'    => $o->end()->format( DATE_ATOM ),
					'body'     => self::description( $o, $url ),
					'location' => arnl_describe_frequency( $o->net, $o->frequency ),
				)
			),
			'https://outlook.live.com/calendar/0/deeplink/compose'
		);
	}

	/**
	 * Page used for "details" links in feeds: the configured event page, or the
	 * first published page that contains one of the plugin's shortcodes.
	 *
	 * @return string
	 */
	public static function event_page_url() {
		$page_id = (int) ARNL_Settings::get( 'event_page_id' );
		if ( ! $page_id ) {
			$page_id = get_transient( 'arnl_event_page' );
			if ( false === $page_id ) {
				global $wpdb;
				$page_id = 0;
				foreach ( array( '[arnl_event', '[arnl_calendar', '[arnl_upcoming' ) as $tag ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$page_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('page','post') AND post_content LIKE %s ORDER BY post_type = 'page' DESC, ID ASC LIMIT 1", '%' . $wpdb->esc_like( $tag ) . '%' ) );
					if ( $page_id ) {
						break;
					}
				}
				set_transient( 'arnl_event_page', $page_id, DAY_IN_SECONDS );
			}
		}
		$url = $page_id ? get_permalink( (int) $page_id ) : '';
		return $url ? $url : '';
	}

	/**
	 * Forget the detected event page when content changes.
	 */
	public static function forget_event_page() {
		delete_transient( 'arnl_event_page' );
	}

	/**
	 * Escape a TEXT value.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function escape( $text ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		return str_replace( array( '\\', ';', ',', "\n" ), array( '\\\\', '\\;', '\\,', '\\n' ), $text );
	}

	/**
	 * Fold a content line to 75 octets without splitting UTF-8 characters.
	 *
	 * @param string $line Line.
	 * @return string
	 */
	public static function fold( $line ) {
		$out    = '';
		$limit  = 75;
		$length = strlen( $line );
		while ( $length > $limit ) {
			$chunk  = mb_strcut( $line, 0, $limit, 'UTF-8' );
			$out   .= $chunk . "\r\n ";
			$line   = (string) substr( $line, strlen( $chunk ) );
			$length = strlen( $line );
			$limit  = 74; // Continuation lines start with a space.
		}
		return $out . $line;
	}

	/**
	 * Output and stop.
	 *
	 * @param string $ics      Document.
	 * @param string $filename File name.
	 * @param bool   $download Force a download.
	 */
	protected static function send( $ics, $filename, $download ) {
		status_header( 200 );
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: ' . ( $download ? 'attachment' : 'inline' ) . '; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Cache-Control: public, max-age=900' );
		header( 'X-Robots-Tag: noindex' );
		echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCalendar document.
		exit;
	}
}
