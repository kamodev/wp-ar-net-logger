<?php
/**
 * Discord: posts check-ins (and net open / close announcements) to a channel
 * through a Discord webhook.
 *
 * Messages are queued while the request runs and sent at shutdown, after the
 * response has been handed to the browser where the server allows it, so
 * logging a check-in is never slowed down by Discord.
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Discord webhook integration.
 */
class ARNL_Discord {

	const ERROR_OPTION = 'arnl_discord_last_error';

	/**
	 * Messages waiting to be sent: array( webhook, payload ).
	 *
	 * @var array[]
	 */
	protected static $queue = array();

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'arnl_checkin_created', array( __CLASS__, 'checkin_created' ), 10, 2 );
		add_action( 'arnl_checkin_approved', array( __CLASS__, 'checkin_approved' ) );
		add_action( 'arnl_event_status_changed', array( __CLASS__, 'status_changed' ), 10, 3 );
		add_action( 'shutdown', array( __CLASS__, 'flush' ), 1000 );
	}

	/**
	 * Whether a URL is a Discord webhook URL.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function valid_webhook( $url ) {
		return (bool) preg_match( '#^https://(?:(?:ptb|canary)\.)?discord(?:app)?\.com/api(?:/v\d+)?/webhooks/\d+/[A-Za-z0-9_-]+$#', (string) $url );
	}

	/**
	 * Webhook a net posts to ('' when it does not post).
	 *
	 * @param array $net Net.
	 * @return string
	 */
	public static function webhook_for( array $net ) {
		$mode = isset( $net['discord_mode'] ) ? $net['discord_mode'] : 'default';
		if ( 'off' === $mode ) {
			return '';
		}
		if ( 'custom' === $mode && ! empty( $net['discord_webhook'] ) ) {
			return (string) $net['discord_webhook'];
		}
		return (string) ARNL_Settings::get( 'discord_webhook' );
	}

	/**
	 * A check-in was saved: post it when it is approved.
	 *
	 * @param int   $id  Check-in ID.
	 * @param array $row Saved fields.
	 */
	public static function checkin_created( $id, $row ) {
		if ( isset( $row['status'] ) && 'approved' === $row['status'] ) {
			self::queue_checkin( (int) $id );
		}
	}

	/**
	 * A pending check-in was approved by Net Control: post it now.
	 *
	 * @param int $id Check-in ID.
	 */
	public static function checkin_approved( $id ) {
		self::queue_checkin( (int) $id );
	}

	/**
	 * Queue the message for one check-in.
	 *
	 * @param int $id Check-in ID.
	 */
	protected static function queue_checkin( $id ) {
		if ( ! ARNL_Settings::get( 'discord_checkins' ) ) {
			return;
		}
		$checkin = ARNL_Checkins::get( $id );
		$o       = $checkin ? ARNL_Schedule::find( 'e' . $checkin['event_id'] ) : null;
		if ( ! $checkin || ! $o ) {
			return;
		}
		$webhook = self::webhook_for( $o->net );
		if ( '' === $webhook ) {
			return;
		}
		$number = 0;
		foreach ( ARNL_Checkins::for_event( $o->event_id, 'approved' ) as $i => $c ) {
			if ( (int) $c['id'] === (int) $id ) {
				$number = $i + 1;
			}
		}
		self::queue( $webhook, self::checkin_payload( $checkin, $o, $number ) );
	}

	/**
	 * Net opened / closed announcements.
	 *
	 * @param int    $event_id   Session ID.
	 * @param string $old_status Previous status.
	 * @param string $new_status New status.
	 */
	public static function status_changed( $event_id, $old_status, $new_status ) {
		if ( ! ARNL_Settings::get( 'discord_open_close' ) || ! in_array( $new_status, array( 'active', 'completed' ), true ) ) {
			return;
		}
		$o = ARNL_Schedule::find( 'e' . (int) $event_id );
		if ( ! $o ) {
			return;
		}
		$webhook = self::webhook_for( $o->net );
		if ( '' !== $webhook ) {
			self::queue( $webhook, 'active' === $new_status ? self::open_payload( $o ) : self::close_payload( $o ) );
		}
	}

	/**
	 * Add a message to the queue.
	 *
	 * @param string $webhook Webhook URL.
	 * @param array  $payload Message.
	 */
	public static function queue( $webhook, array $payload ) {
		if ( self::valid_webhook( $webhook ) ) {
			/**
			 * Filters a Discord message before it is sent. Return false to skip it.
			 *
			 * @param array|false $payload Message (Discord "Execute Webhook" JSON).
			 * @param string      $webhook Webhook URL.
			 */
			$payload = apply_filters( 'arnl_discord_payload', $payload, $webhook );
			if ( is_array( $payload ) ) {
				self::$queue[] = array( $webhook, $payload );
			}
		}
	}

	/**
	 * Send queued messages (runs at shutdown).
	 */
	public static function flush() {
		if ( ! self::$queue ) {
			return;
		}
		$queue       = self::$queue;
		self::$queue = array();
		if ( doing_action( 'shutdown' ) && function_exists( 'fastcgi_finish_request' ) ) {
			// Hand the page to the browser first; Discord can take a moment.
			ignore_user_abort( true );
			fastcgi_finish_request();
		}
		foreach ( $queue as $message ) {
			self::send( $message[0], $message[1] );
		}
	}

	/**
	 * Send one message now.
	 *
	 * @param string $webhook Webhook URL.
	 * @param array  $payload Message.
	 * @return true|WP_Error
	 */
	public static function send( $webhook, array $payload ) {
		$username = trim( (string) ARNL_Settings::get( 'discord_username' ) );
		if ( '' !== $username && ! isset( $payload['username'] ) ) {
			$payload['username'] = substr( $username, 0, 80 );
		}
		// Never ping @everyone / roles / users from user-entered text.
		$payload['allowed_mentions'] = array( 'parse' => array() );

		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			$response = wp_remote_post(
				$webhook,
				array(
					'timeout' => 8,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode( $payload ),
				)
			);
			if ( is_wp_error( $response ) ) {
				return self::failed( $response->get_error_message() );
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code >= 200 && $code < 300 ) {
				delete_option( self::ERROR_OPTION );
				return true;
			}
			$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			// Rate limited: wait as asked (briefly) and try once more.
			if ( 429 === $code && 0 === $attempt && isset( $body['retry_after'] ) && (float) $body['retry_after'] <= 5 ) {
				usleep( (int) ( (float) $body['retry_after'] * 1000000 ) + 100000 );
				continue;
			}
			$message = isset( $body['message'] ) ? (string) $body['message'] : 'HTTP ' . $code;
			return self::failed( $message );
		}
		return self::failed( 'HTTP 429' );
	}

	/**
	 * Remember a delivery error for the settings screen.
	 *
	 * @param string $message Error.
	 * @return WP_Error
	 */
	protected static function failed( $message ) {
		update_option(
			self::ERROR_OPTION,
			array(
				'time'    => current_time( 'mysql' ),
				'message' => (string) $message,
			),
			false
		);
		return new WP_Error( 'arnl_discord', (string) $message );
	}

	/**
	 * Message for a check-in.
	 *
	 * @param array           $c      Check-in.
	 * @param ARNL_Occurrence $o      Session.
	 * @param int             $number Position among approved check-ins (0 if unknown).
	 * @return array
	 */
	public static function checkin_payload( array $c, ARNL_Occurrence $o, $number = 0 ) {
		$title = $c['callsign'];
		if ( ARNL_Settings::get( 'discord_include_name' ) && '' !== $c['name'] ) {
			$title .= ' – ' . self::escape( $c['name'] );
		}

		$lines = array();
		if ( ARNL_Settings::get( 'discord_include_location' ) && '' !== $c['location'] ) {
			$lines[] = '📍 ' . self::escape( $c['location'] );
		}
		$station = array();
		if ( 'fixed' !== $c['station_type'] ) {
			$station[] = arnl_label( arnl_station_types(), $c['station_type'] );
		}
		if ( $c['short_time'] ) {
			$station[] = __( 'Short time', 'ar-net-logger' );
		}
		if ( $station ) {
			$lines[] = '📻 ' . self::escape( implode( ' · ', $station ) );
		}
		if ( $c['has_traffic'] ) {
			/* translators: 1: number of messages, 2: precedence. */
			$lines[] = '📨 ' . self::escape( sprintf( __( 'Traffic: %1$d %2$s', 'ar-net-logger' ), max( 1, $c['traffic_count'] ), arnl_label( arnl_precedences(), $c['traffic_precedence'] ) ) );
		}
		if ( '' !== $c['relayed_by'] ) {
			/* translators: %s: relay station callsign. */
			$lines[] = '🔁 ' . self::escape( sprintf( __( 'Relayed by %s', 'ar-net-logger' ), $c['relayed_by'] ) );
		}
		if ( ARNL_Settings::get( 'discord_include_comments' ) && '' !== $c['comments'] ) {
			$lines[] = '💬 ' . self::escape( $c['comments'] );
		}
		if ( 'online' === $c['source'] ) {
			$lines[] = '🌐 ' . __( 'Checked in online', 'ar-net-logger' );
		}

		$footer = $o->title();
		if ( $number ) {
			/* translators: 1: net name, 2: check-in number. */
			$footer = sprintf( __( '%1$s · Check-in #%2$d', 'ar-net-logger' ), $o->title(), $number );
		}

		$embed = array(
			'title'       => self::truncate( $title, 256 ),
			'description' => self::truncate( implode( "\n", $lines ), 4000 ),
			'color'       => self::color( $o->net['color'] ),
			'footer'      => array( 'text' => self::truncate( $footer, 2000 ) ),
			'timestamp'   => self::iso( $c['checkin_time'] ),
		);
		$url   = self::session_url( $o );
		if ( '' !== $url ) {
			$embed['url'] = $url;
		}
		return array( 'embeds' => array( self::clean( $embed ) ) );
	}

	/**
	 * "Net is on the air" message.
	 *
	 * @param ARNL_Occurrence $o Session.
	 * @return array
	 */
	public static function open_payload( ARNL_Occurrence $o ) {
		$lines = array();
		$freq  = arnl_describe_frequency( $o->net, $o->frequency );
		if ( '' !== $freq ) {
			$lines[] = '📡 ' . self::escape( $freq );
		}
		if ( '' !== $o->ncs_callsign ) {
			$lines[] = '🎙 ' . self::escape( ARNL_Calendar::ncs_label( $o ) );
		}
		if ( '' !== $o->topic ) {
			/* translators: %s: topic. */
			$lines[] = '🗒 ' . self::escape( sprintf( __( 'Topic: %s', 'ar-net-logger' ), $o->topic ) );
		}
		$url = self::session_url( $o );
		if ( '' !== $url && $o->checkins_open() ) {
			/* translators: %s: URL. */
			$lines[] = sprintf( __( 'Check in online: %s', 'ar-net-logger' ), $url );
		}
		$embed = array(
			/* translators: %s: net name. */
			'title'       => self::truncate( '🟢 ' . sprintf( __( '%s is on the air', 'ar-net-logger' ), $o->title() ), 256 ),
			'description' => self::truncate( implode( "\n", $lines ), 4000 ),
			'color'       => self::color( $o->net['color'] ),
			'timestamp'   => gmdate( 'c' ),
		);
		if ( '' !== $url ) {
			$embed['url'] = $url;
		}
		return array( 'embeds' => array( self::clean( $embed ) ) );
	}

	/**
	 * Closing summary: counts and the stations that checked in.
	 *
	 * @param ARNL_Occurrence $o Session.
	 * @return array
	 */
	public static function close_payload( ARNL_Occurrence $o ) {
		$checkins = ARNL_Checkins::for_event( $o->event_id, 'approved' );
		$stats    = ARNL_Checkins::stats( $checkins );
		$lines    = array(
			/* translators: 1: check-ins, 2: traffic count. */
			sprintf( __( 'Check-ins (QNI): %1$d · Traffic (QTC): %2$d', 'ar-net-logger' ), $stats['approved'], $stats['traffic_count'] ),
		);
		if ( $checkins ) {
			$lines[] = self::escape( implode( ', ', wp_list_pluck( $checkins, 'callsign' ) ) );
		}
		$lines[] = __( 'Thanks to everyone who checked in. 73!', 'ar-net-logger' );
		$embed   = array(
			/* translators: %s: net name. */
			'title'       => self::truncate( '🔴 ' . sprintf( __( '%s is closed', 'ar-net-logger' ), $o->title() ), 256 ),
			'description' => self::truncate( implode( "\n", $lines ), 4000 ),
			'color'       => self::color( $o->net['color'] ),
			'timestamp'   => gmdate( 'c' ),
		);
		$url     = self::session_url( $o );
		if ( '' !== $url ) {
			$embed['url'] = $url;
		}
		return array( 'embeds' => array( self::clean( $embed ) ) );
	}

	/**
	 * Test message for the settings screen.
	 *
	 * @return array
	 */
	public static function test_payload() {
		return array(
			'embeds' => array(
				array(
					'title'       => __( 'AR Net Logger is connected', 'ar-net-logger' ),
					/* translators: %s: site name. */
					'description' => sprintf( __( 'Check-ins from %s will be posted in this channel.', 'ar-net-logger' ), self::escape( get_bloginfo( 'name' ) ) ),
					'color'       => self::color( '#2271b1' ),
				),
			),
		);
	}

	/**
	 * Drop empty embed fields: Discord rejects empty strings (e.g. a check-in with no details).
	 *
	 * @param array $embed Embed.
	 * @return array
	 */
	public static function clean( array $embed ) {
		foreach ( $embed as $key => $value ) {
			if ( '' === $value || ( is_array( $value ) && isset( $value['text'] ) && '' === $value['text'] ) ) {
				unset( $embed[ $key ] );
			}
		}
		return $embed;
	}

	/**
	 * Escape Discord markdown in user-entered text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function escape( $text ) {
		return preg_replace( '/([\\\\*_~`|>\[\]()#-])/', '\\\\$1', (string) $text );
	}

	/**
	 * Hex color to Discord's integer color.
	 *
	 * @param string $hex "#rrggbb".
	 * @return int
	 */
	public static function color( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		return preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ? (int) hexdec( $hex ) : 0x2271b1;
	}

	/**
	 * Cut text to a Discord length limit.
	 *
	 * @param string $text  Text.
	 * @param int    $limit Characters.
	 * @return string
	 */
	public static function truncate( $text, $limit ) {
		$text = (string) $text;
		return mb_strlen( $text ) > $limit ? mb_substr( $text, 0, $limit - 1 ) . '…' : $text;
	}

	/**
	 * Site-local "Y-m-d H:i:s" to ISO 8601 UTC.
	 *
	 * @param string $datetime Datetime.
	 * @return string
	 */
	protected static function iso( $datetime ) {
		try {
			$dt = new DateTimeImmutable( (string) $datetime, wp_timezone() );
		} catch ( Exception $e ) {
			$dt = new DateTimeImmutable( 'now', wp_timezone() );
		}
		return $dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
	}

	/**
	 * Public URL of a session, if net pages or a session page exist.
	 *
	 * @param ARNL_Occurrence $o Session.
	 * @return string
	 */
	protected static function session_url( ARNL_Occurrence $o ) {
		if ( 'active' !== $o->net['status'] || ( ! ARNL_Permalinks::available() && ! (int) ARNL_Settings::get( 'event_page_id' ) ) ) {
			return '';
		}
		$url = arnl_event_url( $o );
		return 0 === strpos( $url, 'http' ) ? $url : '';
	}
}
