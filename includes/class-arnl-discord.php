<?php
/**
 * Discord: posts check-ins (and net open / close announcements) to a channel
 * through a Discord webhook. Each net has its own webhook and options.
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

	/**
	 * Last delivery error of each net: net ID => array( time, message ).
	 */
	const ERRORS_OPTION = 'arnl_discord_errors';

	/**
	 * Messages waiting to be sent: array( webhook, payload, net ).
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
	 * What a net can post: key => label.
	 *
	 * @return array<string,string>
	 */
	public static function post_options() {
		return array(
			'checkins'   => __( 'Each check-in (website check-ins once approved)', 'ar-net-logger' ),
			'open_close' => __( 'When the NCS opens the net, and a summary when it closes', 'ar-net-logger' ),
		);
	}

	/**
	 * Optional check-in details: key => label.
	 *
	 * @return array<string,string>
	 */
	public static function include_options() {
		return array(
			'name'          => __( 'Name', 'ar-net-logger' ),
			'location'      => __( 'Location', 'ar-net-logger' ),
			'announcements' => __( 'Announcements', 'ar-net-logger' ),
			'comments'      => __( 'Comments', 'ar-net-logger' ),
		);
	}

	/**
	 * Webhook a net posts to ('' when it does not post to Discord).
	 *
	 * @param array $net Net.
	 * @return string
	 */
	public static function webhook_for( array $net ) {
		$webhook = isset( $net['discord_webhook'] ) ? (string) $net['discord_webhook'] : '';
		return isset( $net['discord_mode'] ) && 'on' === $net['discord_mode'] && self::valid_webhook( $webhook ) ? $webhook : '';
	}

	/**
	 * Whether a net posts a kind of message ("checkins", "open_close").
	 *
	 * @param array  $net  Net.
	 * @param string $kind Kind.
	 * @return bool
	 */
	public static function posts( array $net, $kind ) {
		return '' !== self::webhook_for( $net ) && in_array( $kind, (array) $net['discord_post'], true );
	}

	/**
	 * Whether a net's check-in posts include a detail ("name", "location", "announcements", "comments").
	 *
	 * @param array  $net   Net.
	 * @param string $field Detail.
	 * @return bool
	 */
	public static function includes( array $net, $field ) {
		return isset( $net['discord_include'] ) && in_array( $field, (array) $net['discord_include'], true );
	}

	/**
	 * Last delivery error of a net, or null.
	 *
	 * @param int $net_id Net ID.
	 * @return array{time:string,message:string}|null
	 */
	public static function last_error( $net_id ) {
		$errors = get_option( self::ERRORS_OPTION, array() );
		return is_array( $errors ) && isset( $errors[ (int) $net_id ]['message'] ) ? $errors[ (int) $net_id ] : null;
	}

	/**
	 * Send a test message to a net's channel.
	 *
	 * @param array $net Net.
	 * @return true|WP_Error
	 */
	public static function test( array $net ) {
		$webhook = self::webhook_for( $net );
		if ( '' === $webhook ) {
			return new WP_Error( 'arnl_discord', __( 'Turn Discord on for this net and save its webhook URL first.', 'ar-net-logger' ) );
		}
		return self::send( $webhook, self::test_payload( $net ), $net );
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
		$checkin = ARNL_Checkins::get( $id );
		$o       = $checkin ? ARNL_Schedule::find( 'e' . $checkin['event_id'] ) : null;
		if ( ! $checkin || ! $o || ! self::posts( $o->net, 'checkins' ) ) {
			return;
		}
		$number = 0;
		foreach ( ARNL_Checkins::for_event( $o->event_id, 'approved' ) as $i => $c ) {
			if ( (int) $c['id'] === (int) $id ) {
				$number = $i + 1;
			}
		}
		self::queue( self::webhook_for( $o->net ), self::checkin_payload( $checkin, $o, $number ), $o->net );
	}

	/**
	 * Net opened / closed announcements.
	 *
	 * @param int    $event_id   Session ID.
	 * @param string $old_status Previous status.
	 * @param string $new_status New status.
	 */
	public static function status_changed( $event_id, $old_status, $new_status ) {
		if ( ! in_array( $new_status, array( 'active', 'completed' ), true ) ) {
			return;
		}
		$o = ARNL_Schedule::find( 'e' . (int) $event_id );
		if ( $o && self::posts( $o->net, 'open_close' ) ) {
			self::queue( self::webhook_for( $o->net ), 'active' === $new_status ? self::open_payload( $o ) : self::close_payload( $o ), $o->net );
		}
	}

	/**
	 * Add a message to the queue.
	 *
	 * @param string $webhook Webhook URL.
	 * @param array  $payload Message.
	 * @param array  $net     Net the message is about.
	 */
	public static function queue( $webhook, array $payload, array $net = array() ) {
		if ( self::valid_webhook( $webhook ) ) {
			/**
			 * Filters a Discord message before it is sent. Return false to skip it.
			 *
			 * @param array|false $payload Message (Discord "Execute Webhook" JSON).
			 * @param string      $webhook Webhook URL.
			 */
			$payload = apply_filters( 'arnl_discord_payload', $payload, $webhook );
			if ( is_array( $payload ) ) {
				self::$queue[] = array( $webhook, $payload, $net );
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
			self::send( $message[0], $message[1], $message[2] );
		}
	}

	/**
	 * Send one message now.
	 *
	 * @param string $webhook Webhook URL.
	 * @param array  $payload Message.
	 * @param array  $net     Net (its "post as" name; errors are noted on it).
	 * @return true|WP_Error
	 */
	public static function send( $webhook, array $payload, array $net = array() ) {
		$net_id   = isset( $net['id'] ) ? (int) $net['id'] : 0;
		$username = isset( $net['discord_username'] ) ? trim( (string) $net['discord_username'] ) : '';
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
				return self::failed( $response->get_error_message(), $net_id );
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code >= 200 && $code < 300 ) {
				self::note_error( $net_id, null );
				return true;
			}
			$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			// Rate limited: wait as asked (briefly) and try once more.
			if ( 429 === $code && 0 === $attempt && isset( $body['retry_after'] ) && (float) $body['retry_after'] <= 5 ) {
				usleep( (int) ( (float) $body['retry_after'] * 1000000 ) + 100000 );
				continue;
			}
			$message = isset( $body['message'] ) ? (string) $body['message'] : 'HTTP ' . $code;
			return self::failed( $message, $net_id );
		}
		return self::failed( 'HTTP 429', $net_id );
	}

	/**
	 * Remember a delivery error for the net's edit screen.
	 *
	 * @param string $message Error.
	 * @param int    $net_id  Net ID.
	 * @return WP_Error
	 */
	protected static function failed( $message, $net_id ) {
		self::note_error(
			$net_id,
			array(
				'time'    => current_time( 'mysql' ),
				'message' => (string) $message,
			)
		);
		return new WP_Error( 'arnl_discord', (string) $message );
	}

	/**
	 * Store (or clear, with null) a net's last delivery error.
	 *
	 * @param int        $net_id Net ID.
	 * @param array|null $error  Error.
	 */
	protected static function note_error( $net_id, $error ) {
		if ( ! $net_id ) {
			return;
		}
		$errors = get_option( self::ERRORS_OPTION, array() );
		$errors = is_array( $errors ) ? $errors : array();
		if ( null === $error && ! isset( $errors[ $net_id ] ) ) {
			return;
		}
		if ( null === $error ) {
			unset( $errors[ $net_id ] );
		} else {
			$errors[ $net_id ] = $error;
		}
		update_option( self::ERRORS_OPTION, $errors, false );
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
		if ( self::includes( $o->net, 'name' ) && '' !== $c['name'] ) {
			$title .= ' – ' . self::escape( $c['name'] );
		}

		$lines = array();
		if ( self::includes( $o->net, 'location' ) && '' !== $c['location'] ) {
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
		if ( $c['has_announcement'] && self::includes( $o->net, 'announcements' ) ) {
			$lines[] = '📢 ' . ( '' !== $c['announcement']
				/* translators: %s: announcement text. */
				? self::escape( sprintf( __( 'Announcement: %s', 'ar-net-logger' ), $c['announcement'] ) )
				: __( 'Has an announcement for the net', 'ar-net-logger' ) );
		}
		if ( self::includes( $o->net, 'comments' ) && '' !== $c['comments'] ) {
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
		return array( 'embeds' => array( self::clean( self::with_logo( $embed, $o->net ) ) ) );
	}

	/**
	 * Add the net's logo to an embed as its thumbnail (Discord needs a public http(s) URL).
	 *
	 * @param array $embed Embed.
	 * @param array $net   Net.
	 * @return array
	 */
	public static function with_logo( array $embed, array $net ) {
		$logo = arnl_net_logo_url( $net, 'thumbnail' );
		if ( 0 === strpos( $logo, 'http' ) ) {
			$embed['thumbnail'] = array( 'url' => $logo );
		}
		return $embed;
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
		$digital = arnl_describe_digital( $o->net );
		if ( '' !== $digital ) {
			$lines[] = '💻 ' . self::escape( $digital );
		}
		$system = arnl_linked_system( $o->net );
		if ( $system ) {
			$lines[] = '🔗 ' . ( '' !== $system['url'] ? '[' . self::escape( $system['name'] ) . '](' . esc_url_raw( $system['url'] ) . ')' : self::escape( $system['name'] ) );
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
		return array( 'embeds' => array( self::clean( self::with_logo( $embed, $o->net ) ) ) );
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
		if ( $stats['announcements'] ) {
			/* translators: %d: number of announcements. */
			$lines[] = '📢 ' . sprintf( _n( '%d announcement', '%d announcements', $stats['announcements'], 'ar-net-logger' ), $stats['announcements'] );
		}
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
		return array( 'embeds' => array( self::clean( self::with_logo( $embed, $o->net ) ) ) );
	}

	/**
	 * Test message for a net's channel.
	 *
	 * @param array $net Net.
	 * @return array
	 */
	public static function test_payload( array $net = array() ) {
		$name  = isset( $net['name'] ) ? (string) $net['name'] : get_bloginfo( 'name' );
		$embed = array(
			'title'       => __( 'AR Net Logger is connected', 'ar-net-logger' ),
			/* translators: %s: net name. */
			'description' => sprintf( __( 'Check-ins of the %s will be posted in this channel.', 'ar-net-logger' ), self::escape( $name ) ),
			'color'       => self::color( isset( $net['color'] ) ? $net['color'] : '#2271b1' ),
		);
		return array( 'embeds' => array( $net ? self::with_logo( $embed, $net ) : $embed ) );
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
