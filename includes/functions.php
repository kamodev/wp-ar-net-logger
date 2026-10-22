<?php
/**
 * Shared helper functions: option lists, sanitizers and formatters.
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Net types (directed, traffic, ARES, ...).
 *
 * @return array<string,string> key => label.
 */
function arnl_net_types() {
	return apply_filters(
		'arnl_net_types',
		array(
			'directed'  => __( 'Directed', 'ar-net-logger' ),
			'open'      => __( 'Open / Informal', 'ar-net-logger' ),
			'traffic'   => __( 'Traffic (NTS)', 'ar-net-logger' ),
			'emergency' => __( 'Emergency / ARES / RACES', 'ar-net-logger' ),
			'weather'   => __( 'Weather / SKYWARN', 'ar-net-logger' ),
			'technical' => __( 'Technical', 'ar-net-logger' ),
			'ragchew'   => __( 'Rag Chew / Social', 'ar-net-logger' ),
			'training'  => __( 'Training / Practice', 'ar-net-logger' ),
			'swap'      => __( 'Swap / Trade', 'ar-net-logger' ),
			'other'     => __( 'Other', 'ar-net-logger' ),
		)
	);
}

/**
 * Operating modes.
 *
 * @return array<string,string>
 */
function arnl_modes() {
	return apply_filters(
		'arnl_modes',
		array(
			'fm'       => __( 'FM', 'ar-net-logger' ),
			'ssb'      => __( 'SSB', 'ar-net-logger' ),
			'cw'       => __( 'CW', 'ar-net-logger' ),
			'am'       => __( 'AM', 'ar-net-logger' ),
			'digital'  => __( 'Digital (FT8, JS8, PSK, RTTY, Winlink...)', 'ar-net-logger' ),
			'dmr'      => __( 'DMR', 'ar-net-logger' ),
			'dstar'    => __( 'D-STAR', 'ar-net-logger' ),
			'ysf'      => __( 'System Fusion (YSF)', 'ar-net-logger' ),
			'p25'      => __( 'P25', 'ar-net-logger' ),
			'echolink' => __( 'EchoLink', 'ar-net-logger' ),
			'allstar'  => __( 'AllStar', 'ar-net-logger' ),
			'irlp'     => __( 'IRLP', 'ar-net-logger' ),
			'mixed'    => __( 'Mixed / Linked', 'ar-net-logger' ),
			'other'    => __( 'Other', 'ar-net-logger' ),
		)
	);
}

/**
 * Amateur bands.
 *
 * @return array<string,string>
 */
function arnl_bands() {
	return apply_filters(
		'arnl_bands',
		array(
			'160m'     => '160m',
			'80m'      => '80m',
			'60m'      => '60m',
			'40m'      => '40m',
			'30m'      => '30m',
			'20m'      => '20m',
			'17m'      => '17m',
			'15m'      => '15m',
			'12m'      => '12m',
			'10m'      => '10m',
			'6m'       => '6m',
			'2m'       => '2m',
			'1.25m'    => '1.25m',
			'70cm'     => '70cm',
			'33cm'     => '33cm',
			'23cm'     => '23cm',
			'multi'    => __( 'Multiple bands', 'ar-net-logger' ),
			'internet' => __( 'Internet linked', 'ar-net-logger' ),
		)
	);
}

/**
 * Recurrence patterns a net can use.
 *
 * @return array<string,string>
 */
function arnl_recurrence_types() {
	return array(
		ARNL_Recurrence::WEEKLY   => __( 'Weekly', 'ar-net-logger' ),
		ARNL_Recurrence::BIWEEKLY => __( 'Every other week', 'ar-net-logger' ),
		ARNL_Recurrence::MONTHLY  => __( 'Monthly (by week of the month)', 'ar-net-logger' ),
		ARNL_Recurrence::ONCE     => __( 'One time only (on the start date)', 'ar-net-logger' ),
		ARNL_Recurrence::NONE     => __( 'No automatic schedule (add sessions manually)', 'ar-net-logger' ),
	);
}

/**
 * Station types for check-ins.
 *
 * @return array<string,string>
 */
function arnl_station_types() {
	return apply_filters(
		'arnl_station_types',
		array(
			'fixed'    => __( 'Fixed / Home', 'ar-net-logger' ),
			'mobile'   => __( 'Mobile', 'ar-net-logger' ),
			'portable' => __( 'Portable', 'ar-net-logger' ),
			'handheld' => __( 'Handheld', 'ar-net-logger' ),
			'remote'   => __( 'Remote / Internet link', 'ar-net-logger' ),
		)
	);
}

/**
 * Radiogram (NTS) traffic precedences, lowest to highest.
 *
 * @return array<string,string>
 */
function arnl_precedences() {
	return array(
		'routine'   => __( 'Routine', 'ar-net-logger' ),
		'welfare'   => __( 'Welfare', 'ar-net-logger' ),
		'priority'  => __( 'Priority', 'ar-net-logger' ),
		'emergency' => __( 'Emergency', 'ar-net-logger' ),
	);
}

/**
 * Net session statuses.
 *
 * @return array<string,string>
 */
function arnl_event_statuses() {
	return array(
		'scheduled' => __( 'Scheduled', 'ar-net-logger' ),
		'active'    => __( 'In Session', 'ar-net-logger' ),
		'completed' => __( 'Closed', 'ar-net-logger' ),
		'cancelled' => __( 'Cancelled', 'ar-net-logger' ),
	);
}

/**
 * Check-in moderation statuses.
 *
 * @return array<string,string>
 */
function arnl_checkin_statuses() {
	return array(
		'approved' => __( 'Approved', 'ar-net-logger' ),
		'pending'  => __( 'Pending review', 'ar-net-logger' ),
		'rejected' => __( 'Rejected', 'ar-net-logger' ),
	);
}

/**
 * Week-of-month choices for monthly nets.
 *
 * @return array<string,string>
 */
function arnl_week_of_month_options() {
	return array(
		'1'    => __( '1st', 'ar-net-logger' ),
		'2'    => __( '2nd', 'ar-net-logger' ),
		'3'    => __( '3rd', 'ar-net-logger' ),
		'4'    => __( '4th', 'ar-net-logger' ),
		'5'    => __( '5th', 'ar-net-logger' ),
		'last' => __( 'Last', 'ar-net-logger' ),
	);
}

/**
 * Localized weekday names indexed 0 (Sunday) to 6 (Saturday).
 *
 * @param bool $short Use abbreviations.
 * @return string[]
 */
function arnl_weekday_names( $short = false ) {
	global $wp_locale;
	$names = array();
	for ( $i = 0; $i < 7; $i++ ) {
		$name        = $wp_locale->get_weekday( $i );
		$names[ $i ] = $short ? $wp_locale->get_weekday_abbrev( $name ) : $name;
	}
	return $names;
}

/**
 * Localized month names indexed 1 to 12.
 *
 * @param bool $short Use abbreviations.
 * @return string[]
 */
function arnl_month_names( $short = false ) {
	global $wp_locale;
	$names = array();
	for ( $i = 1; $i <= 12; $i++ ) {
		$name        = $wp_locale->get_month( $i );
		$names[ $i ] = $short ? $wp_locale->get_month_abbrev( $name ) : $name;
	}
	return $names;
}

/**
 * Label for a key in an option list, falling back to the key itself.
 *
 * @param array<string,string> $options Options.
 * @param string               $key     Key.
 * @return string
 */
function arnl_label( $options, $key ) {
	return isset( $options[ $key ] ) ? $options[ $key ] : (string) $key;
}

/**
 * Normalize a callsign: upper case, only A-Z, 0-9 and "/".
 *
 * @param string $value Raw value.
 * @return string
 */
function arnl_sanitize_callsign( $value ) {
	$value = strtoupper( trim( (string) $value ) );
	$value = preg_replace( '#[^A-Z0-9/]#', '', $value );
	return substr( trim( $value, '/' ), 0, 20 );
}

/**
 * Base callsign without portable prefixes / suffixes (VE3/W1AW/M -> W1AW).
 *
 * @param string $callsign Callsign.
 * @return string
 */
function arnl_base_callsign( $callsign ) {
	$base = '';
	foreach ( explode( '/', arnl_sanitize_callsign( $callsign ) ) as $part ) {
		if ( strlen( $part ) > strlen( $base ) ) {
			$base = $part;
		}
	}
	return $base;
}

/**
 * Whether a callsign looks like a valid amateur callsign.
 *
 * Accepts an optional portable prefix ("VE3/") and suffix ("/M", "/P", "/4").
 * Format checking can be disabled in the settings, e.g. for nets that also
 * accept GMRS or SWL check-ins.
 *
 * @param string $callsign Sanitized callsign.
 * @return bool
 */
function arnl_is_valid_callsign( $callsign ) {
	$callsign = (string) $callsign;
	if ( '' === $callsign ) {
		return false;
	}
	$valid = true;
	if ( ARNL_Settings::get( 'validate_callsign' ) ) {
		$valid = arnl_looks_like_callsign( $callsign );
	}
	/**
	 * Filters whether a callsign is accepted.
	 *
	 * @param bool   $valid    Validation result.
	 * @param string $callsign Callsign.
	 */
	return (bool) apply_filters( 'arnl_is_valid_callsign', $valid, $callsign );
}

/**
 * Whether a string has the format of an amateur callsign, with an optional
 * portable prefix / suffix (W1AW, 2E0ABC, VE3/W1AW, W1AW/M, K4X).
 *
 * @param string $callsign Upper-case callsign.
 * @return bool
 */
function arnl_looks_like_callsign( $callsign ) {
	return (bool) preg_match( '#^(?:[A-Z0-9]{1,4}/)?[A-Z0-9]{1,3}[0-9][A-Z0-9]{0,3}[A-Z](?:/[A-Z0-9]{1,4})?$#', (string) $callsign );
}

/**
 * Sanitize a time of day into H:i:s. Accepts "19:00", "19:00:00", "7:00 pm".
 *
 * @param string $value    Raw value.
 * @param string $fallback Returned when the value cannot be parsed.
 * @return string
 */
function arnl_sanitize_time( $value, $fallback = '' ) {
	$value = strtolower( trim( (string) $value ) );
	if ( preg_match( '/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([ap]\.?m\.?)?$/', $value, $m ) ) {
		$hour   = (int) $m[1];
		$minute = (int) $m[2];
		$second = isset( $m[3] ) && '' !== $m[3] ? (int) $m[3] : 0;
		if ( ! empty( $m[4] ) ) {
			if ( $hour < 1 || $hour > 12 ) {
				return $fallback;
			}
			$pm   = 'p' === $m[4][0];
			$hour = ( 12 === $hour ? 0 : $hour ) + ( $pm ? 12 : 0 );
		}
		if ( $hour <= 23 && $minute <= 59 && $second <= 59 ) {
			return sprintf( '%02d:%02d:%02d', $hour, $minute, $second );
		}
	}
	return $fallback;
}

/**
 * Sanitize a date into Y-m-d, or '' if invalid.
 *
 * @param string $value Raw value.
 * @return string
 */
function arnl_sanitize_date( $value ) {
	$value = trim( (string) $value );
	$date  = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
	if ( $date && $date->format( 'Y-m-d' ) === $value ) {
		return $value;
	}
	return '';
}

/**
 * Turn a CSV string or array into a list of trimmed, non-empty strings.
 *
 * @param string|array $value     Value.
 * @param bool         $lowercase Lowercase every item.
 * @return string[]
 */
function arnl_parse_list( $value, $lowercase = true ) {
	if ( is_string( $value ) ) {
		$value = explode( ',', $value );
	}
	$out = array();
	foreach ( (array) $value as $item ) {
		$item = trim( (string) $item );
		if ( '' !== $item ) {
			$out[] = $lowercase ? strtolower( $item ) : $item;
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * Current time in the site timezone.
 *
 * @return DateTimeImmutable
 */
function arnl_now() {
	/**
	 * Filters "now" (used by tests and previews).
	 *
	 * @param DateTimeImmutable $now Current site time.
	 */
	return apply_filters( 'arnl_now', new DateTimeImmutable( 'now', wp_timezone() ) );
}

/**
 * Today's date (Y-m-d) in the site timezone.
 *
 * @return string
 */
function arnl_today() {
	return arnl_now()->format( 'Y-m-d' );
}

/**
 * Format a H:i:s time using the site time format.
 *
 * @param string $time Time of day.
 * @return string
 */
function arnl_format_time( $time ) {
	$dt = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', '2000-01-01 ' . $time, wp_timezone() );
	return $dt ? wp_date( get_option( 'time_format' ), $dt->getTimestamp(), wp_timezone() ) : (string) $time;
}

/**
 * Format a Y-m-d date.
 *
 * @param string $date   Date.
 * @param string $format PHP date format, defaults to the site date format.
 * @return string
 */
function arnl_format_date( $date, $format = '' ) {
	$dt = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' 12:00', wp_timezone() );
	if ( ! $dt ) {
		return (string) $date;
	}
	return wp_date( $format ? $format : get_option( 'date_format' ), $dt->getTimestamp(), wp_timezone() );
}

/**
 * Human readable description of a net's recurring schedule.
 *
 * @param array $net      Net record.
 * @param bool  $with_time Append the time range.
 * @return string
 */
function arnl_describe_schedule( array $net, $with_time = true ) {
	$days  = arnl_weekday_names();
	$names = array();
	foreach ( $net['days_of_week'] as $d ) {
		$names[] = $days[ $d ];
	}
	$day_list = wp_sprintf( '%l', $names );

	switch ( $net['recurrence'] ) {
		case ARNL_Recurrence::WEEKLY:
			/* translators: %s: list of weekdays. */
			$text = 7 === count( $names ) ? __( 'Daily', 'ar-net-logger' ) : sprintf( __( 'Every %s', 'ar-net-logger' ), $day_list );
			break;
		case ARNL_Recurrence::BIWEEKLY:
			/* translators: %s: list of weekdays. */
			$text = sprintf( __( 'Every other %s', 'ar-net-logger' ), $day_list );
			break;
		case ARNL_Recurrence::MONTHLY:
			$weeks   = arnl_week_of_month_options();
			$ordinal = array();
			foreach ( $net['weeks_of_month'] as $w ) {
				$ordinal[] = arnl_label( $weeks, (string) $w );
			}
			/* translators: 1: list of ordinals such as "1st and 3rd", 2: list of weekdays. */
			$text = sprintf( __( '%1$s %2$s of the month', 'ar-net-logger' ), wp_sprintf( '%l', $ordinal ), $day_list );
			break;
		case ARNL_Recurrence::ONCE:
			/* translators: %s: date. */
			$text = sprintf( __( 'One time: %s', 'ar-net-logger' ), $net['start_date'] ? arnl_format_date( $net['start_date'] ) : __( 'date not set', 'ar-net-logger' ) );
			break;
		default:
			$text = __( 'Sessions scheduled individually', 'ar-net-logger' );
	}

	if ( in_array( $net['recurrence'], array( ARNL_Recurrence::WEEKLY, ARNL_Recurrence::BIWEEKLY, ARNL_Recurrence::MONTHLY ), true ) && ! empty( $net['months'] ) && count( $net['months'] ) < 12 ) {
		$months = arnl_month_names( true );
		$list   = array();
		foreach ( $net['months'] as $m ) {
			$list[] = $months[ $m ];
		}
		/* translators: 1: schedule, 2: list of months. */
		$text = sprintf( __( '%1$s (%2$s)', 'ar-net-logger' ), $text, implode( ', ', $list ) );
	}

	if ( $with_time && ARNL_Recurrence::NONE !== $net['recurrence'] ) {
		/* translators: 1: schedule, 2: start time, 3: end time. */
		$text = sprintf( __( '%1$s, %2$s – %3$s', 'ar-net-logger' ), $text, arnl_format_time( $net['start_time'] ), arnl_format_time( $net['end_time'] ) );
	}

	return apply_filters( 'arnl_describe_schedule', $text, $net );
}

/**
 * Frequency / access line for a net, e.g. "146.940 MHz FM (-0.600, PL 100.0) · 2m".
 *
 * @param array  $net       Net record.
 * @param string $frequency Optional session frequency override.
 * @return string
 */
function arnl_describe_frequency( array $net, $frequency = '' ) {
	$parts = array();
	$freq  = '' !== $frequency ? $frequency : $net['frequency'];
	if ( '' !== $freq ) {
		$parts[] = $freq;
	}
	if ( '' !== $net['mode'] ) {
		$parts[] = arnl_label( arnl_modes(), $net['mode'] );
	}
	if ( '' !== $net['band'] ) {
		$parts[] = arnl_label( arnl_bands(), $net['band'] );
	}
	$text = implode( ' · ', $parts );
	if ( '' !== $net['access_info'] ) {
		$text .= ( '' !== $text ? ' — ' : '' ) . $net['access_info'];
	}
	return $text;
}

/**
 * Short timezone label for the site, e.g. "EDT" or "UTC+2".
 *
 * @param int|null $timestamp Moment to evaluate (for DST).
 * @return string
 */
function arnl_timezone_label( $timestamp = null ) {
	return wp_date( 'T', $timestamp ? $timestamp : time(), wp_timezone() );
}

/**
 * Public URL of an occurrence's detail view.
 *
 * By default this is the session's net permalink (/net/{net-slug}/?p={id}).
 * A "Session details page" chosen in the settings, or an explicit base URL,
 * shows the session on that page instead (?arnl_event=...).
 *
 * @param ARNL_Occurrence $occurrence Occurrence.
 * @param string          $base       Base URL; defaults to the configured event page, the net permalink or the current page.
 * @return string
 */
function arnl_event_url( ARNL_Occurrence $occurrence, $base = '' ) {
	if ( '' === $base ) {
		$page_id = (int) ARNL_Settings::get( 'event_page_id' );
		$base    = $page_id ? (string) get_permalink( $page_id ) : '';
		if ( '' === $base && ARNL_Permalinks::available() ) {
			return ARNL_Permalinks::session_url( $occurrence );
		}
	}
	// Without a dedicated event page, link to the current page (the shortcode renders the details).
	$base = '' !== $base ? remove_query_arg( array( 'arnl_event', 'arnl_msg' ), $base ) : remove_query_arg( array( 'arnl_event', 'arnl_msg' ) );
	return add_query_arg( 'arnl_event', $occurrence->key(), $base );
}

/**
 * Public URL of a net's page (/net/{net-slug}/), or '' when net pages are unavailable.
 *
 * @param array $net Net.
 * @return string
 */
function arnl_net_url( array $net ) {
	return ARNL_Permalinks::net_url( $net );
}

/**
 * Absolute URL of the current front-end request.
 *
 * @return string
 */
function arnl_current_url() {
	$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
	return set_url_scheme( 'http://' . $host . $uri );
}

/**
 * URL of the iCalendar feed / download.
 *
 * @param array $args Query args: net, type, mode, band, event, download.
 * @return string
 */
function arnl_ics_url( array $args = array() ) {
	$query = array( 'arnl_ics' => isset( $args['event'] ) ? $args['event'] : 'feed' );
	foreach ( array( 'net', 'type', 'mode', 'band' ) as $key ) {
		if ( ! empty( $args[ $key ] ) ) {
			$query[ $key ] = is_array( $args[ $key ] ) ? implode( ',', $args[ $key ] ) : $args[ $key ];
		}
	}
	if ( ! empty( $args['download'] ) ) {
		$query['download'] = 1;
	}
	return add_query_arg( array_map( 'rawurlencode', $query ), home_url( '/' ) );
}

/**
 * Turn an http(s) URL into a webcal:// URL for calendar subscriptions.
 *
 * @param string $url URL.
 * @return string
 */
function arnl_webcal_url( $url ) {
	return preg_replace( '#^https?://#i', 'webcal://', $url );
}

/**
 * Placeholders a net script can use, filled in for each session: key => description.
 *
 * @return array<string,string>
 */
function arnl_script_placeholders() {
	return array(
		'ncs_callsign' => __( 'Net Control callsign', 'ar-net-logger' ),
		'ncs_name'     => __( 'Net Control name', 'ar-net-logger' ),
		'ncs'          => __( 'Callsign and name', 'ar-net-logger' ),
		'net'          => __( 'Net name', 'ar-net-logger' ),
		'date'         => __( 'Date', 'ar-net-logger' ),
		'time'         => __( 'Start time', 'ar-net-logger' ),
		'end_time'     => __( 'End time', 'ar-net-logger' ),
		'timezone'     => __( 'Time zone', 'ar-net-logger' ),
		'frequency'    => __( 'Frequency / repeater', 'ar-net-logger' ),
		'topic'        => __( 'Topic', 'ar-net-logger' ),
		'type'         => __( 'Net type', 'ar-net-logger' ),
		'url'          => __( 'Session web page', 'ar-net-logger' ),
		'site'         => __( 'Site / club name', 'ar-net-logger' ),
	);
}

/**
 * Placeholder values of a session for its net script.
 *
 * @param ARNL_Occurrence $o Session.
 * @return array<string,string>
 */
function arnl_script_values( ARNL_Occurrence $o ) {
	$url    = arnl_event_url( $o );
	$values = array(
		'ncs_callsign' => $o->ncs_callsign,
		'ncs_name'     => $o->ncs_name,
		'ncs'          => '' !== $o->ncs_callsign ? trim( $o->ncs_callsign . ( '' !== $o->ncs_name ? ' (' . $o->ncs_name . ')' : '' ) ) : '',
		'net'          => $o->title(),
		'date'         => arnl_format_date( $o->date, 'l, ' . get_option( 'date_format' ) ),
		'time'         => arnl_format_time( $o->start_time ),
		'end_time'     => arnl_format_time( $o->end_time ),
		'timezone'     => arnl_timezone_label( $o->start()->getTimestamp() ),
		'frequency'    => arnl_describe_frequency( $o->net, $o->frequency ),
		'topic'        => $o->topic,
		'type'         => arnl_label( arnl_net_types(), $o->net['net_type'] ),
		'url'          => 0 === strpos( $url, 'http' ) ? $url : '',
		'site'         => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
	);
	/**
	 * Filters the placeholder values of a net script.
	 *
	 * @param array           $values Placeholder => value.
	 * @param ARNL_Occurrence $o      Session.
	 */
	return array_map( 'strval', (array) apply_filters( 'arnl_script_values', $values, $o ) );
}

/**
 * A net's script (rich text) for one session, read-only, with its placeholders
 * filled in. Placeholders without a value show as a blank to fill in on the air.
 *
 * @param ARNL_Occurrence $o Session.
 * @return string Safe HTML, or '' when the net has no script.
 */
function arnl_render_script( ARNL_Occurrence $o ) {
	$script = (string) $o->net['preamble'];
	if ( '' === trim( wp_strip_all_tags( $script ) ) ) {
		return '';
	}
	$values = arnl_script_values( $o );
	$html   = '';
	foreach ( wp_html_split( wpautop( $script ) ) as $part ) {
		$in_tag = '' !== $part && '<' === $part[0];
		$html  .= preg_replace_callback(
			'/\{([a-z_]+)\}/',
			static function ( $found ) use ( $values, $in_tag ) {
				if ( ! isset( $values[ $found[1] ] ) ) {
					return $found[0];
				}
				if ( $in_tag ) {
					return esc_attr( $values[ $found[1] ] );
				}
				if ( '' === $values[ $found[1] ] ) {
					return '<span class="arnl-script-blank" title="' . esc_attr( $found[0] ) . '">______</span>';
				}
				return esc_html( $values[ $found[1] ] );
			},
			$part
		);
	}
	/**
	 * Filters a session's rendered net script.
	 *
	 * @param string          $html Script HTML.
	 * @param ARNL_Occurrence $o    Session.
	 */
	return (string) apply_filters( 'arnl_net_script', wp_kses_post( $html ), $o );
}

/**
 * Example directed-net script (HTML for the editor), following common ARRL / NTS
 * net practice. Placeholders are filled in for each session.
 *
 * @return string
 */
function arnl_sample_script() {
	$bold = static function ( $text ) {
		return str_replace(
			array( '{ncs_callsign}', '{net}' ),
			array( '<strong>{ncs_callsign}</strong>', '<strong>{net}</strong>' ),
			esc_html( $text )
		);
	};
	$cue  = static function ( $text ) {
		return '<p><em>' . esc_html( $text ) . '</em></p>';
	};
	$html = array(
		'<h3>' . esc_html__( 'Opening', 'ar-net-logger' ) . '</h3>',
		'<p><strong>' . esc_html__( 'QST, QST, QST.', 'ar-net-logger' ) . '</strong> ' . $bold( __( 'This is {ncs_callsign}, my name is {ncs_name}, Net Control for the {net}.', 'ar-net-logger' ) ) . '</p>',
		'<p>' . esc_html__( 'The net meets {date} at {time} {timezone} on {frequency}. This is a directed net: all transmissions go through Net Control. Please wait to be called, except for emergency or priority traffic.', 'ar-net-logger' ) . '</p>',
		'<h3>' . esc_html__( 'Emergency and priority traffic', 'ar-net-logger' ) . '</h3>',
		'<p><span style="color: #d63638"><strong>' . esc_html__( 'Is there any emergency or priority traffic? Please call now.', 'ar-net-logger' ) . '</strong></span></p>',
		$cue( __( '(Pause. Handle emergency or priority traffic before anything else.)', 'ar-net-logger' ) ),
		'<h3>' . esc_html__( 'Check-ins', 'ar-net-logger' ) . '</h3>',
		'<p>' . esc_html__( 'Net Control will now take check-ins. Please give your callsign phonetically, your name and location, and say if you are mobile or portable or have traffic for the net.', 'ar-net-logger' ) . '</p>',
		'<ul><li>' . esc_html__( 'Short-time and mobile stations, please call now.', 'ar-net-logger' ) . '</li><li>' . esc_html__( 'All other stations, please call now.', 'ar-net-logger' ) . '</li><li>' . esc_html__( 'Are there any relays for stations Net Control could not hear?', 'ar-net-logger' ) . '</li></ul>',
		$cue( __( '(Acknowledge each station by callsign, and acknowledge the website check-ins in the Net Log. Identify at least every 10 minutes.)', 'ar-net-logger' ) ),
		'<h3>' . esc_html__( 'Topic and traffic', 'ar-net-logger' ) . '</h3>',
		'<p>' . esc_html__( 'Topic for this session: {topic}', 'ar-net-logger' ) . '</p>',
		'<p>' . esc_html__( 'Stations with traffic for the net, please call now. Net Control will then go down the list for comments.', 'ar-net-logger' ) . '</p>',
		'<h3>' . esc_html__( 'Closing', 'ar-net-logger' ) . '</h3>',
		'<p>' . esc_html__( 'Are there any late check-ins, comments or traffic before the net closes?', 'ar-net-logger' ) . '</p>',
		$cue( __( '(Pause.)', 'ar-net-logger' ) ),
		'<p>' . $bold( __( 'Thank you to everyone who checked in. This is {ncs_callsign} closing the {net} and returning the frequency to normal amateur use. 73.', 'ar-net-logger' ) ) . '</p>',
	);
	/**
	 * Filters the sample net script offered in the net editor.
	 *
	 * @param string $html Script HTML.
	 */
	return (string) apply_filters( 'arnl_sample_script', implode( "\n", $html ) );
}
