<?php
/**
 * Facebook: announces net sessions on a Facebook Page.
 *
 * Sessions are published as Page posts through the Graph API, automatically a
 * set number of hours before they start (hourly WP-Cron check) or on demand
 * from the schedule. Cancelling a posted session edits the post.
 *
 * Facebook does not let apps create Page or Group events, and it removed
 * posting to Groups in 2024, so the Net Log offers ready-to-paste event
 * details for creating those by hand.
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Facebook Page integration.
 */
class ARNL_Facebook {

	const POSTS_OPTION = 'arnl_fb_posts';
	const ERROR_OPTION = 'arnl_fb_last_error';
	const GRAPH        = 'https://graph.facebook.com/';
	const CRON_HOOK    = 'arnl_hourly';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'auto_post' ) );
		add_action( 'arnl_event_status_changed', array( __CLASS__, 'status_changed' ), 10, 3 );
		add_action( 'init', array( __CLASS__, 'schedule_cron' ) );
	}

	/**
	 * Make sure the hourly check is scheduled.
	 */
	public static function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * Default post text.
	 *
	 * @return string
	 */
	public static function default_template() {
		return __( "📻 {net}\n🗓 {date}, {time} – {end_time} {timezone}\n📡 {frequency}\n🎙 Net Control: {ncs}\n{topic}\nAll licensed amateurs are welcome to check in. Details and online check-in: {url}", 'ar-net-logger' );
	}

	/**
	 * Whether a Page and access token are set.
	 *
	 * @return bool
	 */
	public static function configured() {
		return '' !== (string) ARNL_Settings::get( 'facebook_page_id' ) && '' !== (string) ARNL_Settings::get( 'facebook_token' );
	}

	/**
	 * Whether a net's sessions are posted.
	 *
	 * @param array $net Net.
	 * @return bool
	 */
	public static function enabled_for( array $net ) {
		return self::configured() && 'active' === $net['status'] && ( ! isset( $net['facebook_mode'] ) || 'off' !== $net['facebook_mode'] );
	}

	/**
	 * Fill a template's {placeholders}. Lines whose placeholders are all empty are dropped.
	 *
	 * @param string $template Template.
	 * @param array  $values   Placeholder => value.
	 * @return string
	 */
	public static function render_template( $template, array $values ) {
		$lines = array();
		foreach ( preg_split( '/\R/', (string) $template ) as $line ) {
			$used  = preg_match_all( '/\{([a-z_]+)\}/', $line, $m );
			$empty = $used > 0;
			foreach ( $m[1] as $key ) {
				if ( isset( $values[ $key ] ) && '' !== (string) $values[ $key ] ) {
					$empty = false;
				}
			}
			if ( $empty ) {
				continue;
			}
			$lines[] = preg_replace_callback(
				'/\{([a-z_]+)\}/',
				static function ( $found ) use ( $values ) {
					return isset( $values[ $found[1] ] ) ? (string) $values[ $found[1] ] : $found[0];
				},
				$line
			);
		}
		return trim( implode( "\n", $lines ) );
	}

	/**
	 * Placeholder values for a session.
	 *
	 * @param ARNL_Occurrence $o Session.
	 * @return array
	 */
	public static function values( ARNL_Occurrence $o ) {
		$url = arnl_event_url( $o );
		return array(
			'net'         => $o->title(),
			'date'        => arnl_format_date( $o->date, 'l, ' . get_option( 'date_format' ) ),
			'time'        => arnl_format_time( $o->start_time ),
			'end_time'    => arnl_format_time( $o->end_time ),
			'timezone'    => arnl_timezone_label( $o->start()->getTimestamp() ),
			'frequency'   => arnl_describe_frequency( $o->net, $o->frequency ),
			'ncs'         => '' !== $o->ncs_callsign ? trim( $o->ncs_callsign . ( '' !== $o->ncs_name ? ' (' . $o->ncs_name . ')' : '' ) ) : '',
			/* translators: %s: topic. */
			'topic'       => '' !== $o->topic ? sprintf( __( 'Topic: %s', 'ar-net-logger' ), $o->topic ) : '',
			'type'        => arnl_label( arnl_net_types(), $o->net['net_type'] ),
			'description' => trim( html_entity_decode( wp_strip_all_tags( $o->net['description'] ), ENT_QUOTES, 'UTF-8' ) ),
			'url'         => 0 === strpos( $url, 'http' ) ? $url : '',
		);
	}

	/**
	 * Post text for a session.
	 *
	 * @param ARNL_Occurrence $o Session.
	 * @return string
	 */
	public static function message( ARNL_Occurrence $o ) {
		$template = (string) ARNL_Settings::get( 'facebook_template' );
		$message  = self::render_template( '' !== trim( $template ) ? $template : self::default_template(), self::values( $o ) );
		/**
		 * Filters the Facebook post text of a session.
		 *
		 * @param string          $message Text.
		 * @param ARNL_Occurrence $o       Session.
		 */
		return (string) apply_filters( 'arnl_facebook_message', $message, $o );
	}

	/**
	 * Posts made so far: session UID => array( id, date, cancelled ).
	 *
	 * @return array
	 */
	protected static function posts() {
		$posts = get_option( self::POSTS_OPTION, array() );
		return is_array( $posts ) ? $posts : array();
	}

	/**
	 * Facebook post ID of a session, or ''.
	 *
	 * @param ARNL_Occurrence $o Session.
	 * @return string
	 */
	public static function posted( ARNL_Occurrence $o ) {
		$posts = self::posts();
		return isset( $posts[ $o->uid() ]['id'] ) ? (string) $posts[ $o->uid() ]['id'] : '';
	}

	/**
	 * Link to a Facebook post.
	 *
	 * @param string $post_id "pageid_postid".
	 * @return string
	 */
	public static function post_url( $post_id ) {
		return 'https://www.facebook.com/' . rawurlencode( (string) $post_id );
	}

	/**
	 * Publish a session on the Page.
	 *
	 * @param ARNL_Occurrence $o Session.
	 * @return string|WP_Error Post ID.
	 */
	public static function post( ARNL_Occurrence $o ) {
		if ( ! self::configured() ) {
			return new WP_Error( 'arnl_facebook', __( 'Set the Facebook Page ID and access token in the settings first.', 'ar-net-logger' ) );
		}
		$existing = self::posted( $o );
		if ( '' !== $existing ) {
			return $existing;
		}
		$params = array( 'message' => self::message( $o ) );
		$values = self::values( $o );
		if ( '' !== $values['url'] ) {
			$params['link'] = $values['url'];
		}
		$result = self::api( 'POST', rawurlencode( (string) ARNL_Settings::get( 'facebook_page_id' ) ) . '/feed', $params );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( empty( $result['id'] ) ) {
			return self::failed( __( 'Facebook did not return a post ID.', 'ar-net-logger' ) );
		}
		$posts              = self::posts();
		$posts[ $o->uid() ] = array(
			'id'        => (string) $result['id'],
			'date'      => $o->date,
			'cancelled' => false,
		);
		update_option( self::POSTS_OPTION, $posts, false );
		do_action( 'arnl_facebook_posted', (string) $result['id'], $o );
		return (string) $result['id'];
	}

	/**
	 * Hourly: post sessions starting within the configured lead time.
	 *
	 * @return int Number of posts made.
	 */
	public static function auto_post() {
		$lead = (int) ARNL_Settings::get( 'facebook_lead_hours' );
		if ( ! self::configured() || $lead <= 0 ) {
			return 0;
		}
		$now   = arnl_now();
		$until = $now->modify( '+' . $lead . ' hours' );
		$made  = 0;
		foreach ( ARNL_Schedule::occurrences(
			array(
				'from'              => $now->format( 'Y-m-d' ),
				'to'                => $until->format( 'Y-m-d' ),
				'include_cancelled' => false,
			)
		) as $o ) {
			if ( 'completed' === $o->status || $o->start() <= $now || $o->start() > $until || ! self::enabled_for( $o->net ) || '' !== self::posted( $o ) ) {
				continue;
			}
			if ( ! is_wp_error( self::post( $o ) ) ) {
				++$made;
			}
		}
		self::prune();
		return $made;
	}

	/**
	 * When a posted session is cancelled, mark the post as cancelled.
	 *
	 * @param int    $event_id   Session ID.
	 * @param string $old_status Previous status.
	 * @param string $new_status New status.
	 */
	public static function status_changed( $event_id, $old_status, $new_status ) {
		if ( 'cancelled' !== $new_status || ! self::configured() ) {
			return;
		}
		$o = ARNL_Schedule::find( 'e' . (int) $event_id );
		if ( ! $o ) {
			return;
		}
		$post_id = self::posted( $o );
		$posts   = self::posts();
		if ( '' === $post_id || ! empty( $posts[ $o->uid() ]['cancelled'] ) ) {
			return;
		}
		/* translators: %s: original post text. */
		$result = self::api( 'POST', rawurlencode( $post_id ), array( 'message' => sprintf( __( "❌ CANCELLED\n\n%s", 'ar-net-logger' ), self::message( $o ) ) ) );
		if ( ! is_wp_error( $result ) ) {
			$posts[ $o->uid() ]['cancelled'] = true;
			update_option( self::POSTS_OPTION, $posts, false );
		}
	}

	/**
	 * Check the Page ID and token: returns the Page name.
	 *
	 * @return string|WP_Error
	 */
	public static function test() {
		if ( ! self::configured() ) {
			return new WP_Error( 'arnl_facebook', __( 'Set the Facebook Page ID and access token first.', 'ar-net-logger' ) );
		}
		$result = self::api( 'GET', rawurlencode( (string) ARNL_Settings::get( 'facebook_page_id' ) ), array( 'fields' => 'name' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return isset( $result['name'] ) ? (string) $result['name'] : (string) ARNL_Settings::get( 'facebook_page_id' );
	}

	/**
	 * Call the Graph API.
	 *
	 * @param string $method GET or POST.
	 * @param string $path   Path after graph.facebook.com/.
	 * @param array  $params Parameters (the access token is added).
	 * @return array|WP_Error Decoded response.
	 */
	public static function api( $method, $path, array $params ) {
		$params['access_token'] = (string) ARNL_Settings::get( 'facebook_token' );
		$url                    = self::GRAPH . $path;
		$args                   = array( 'timeout' => 15 );
		if ( 'GET' === $method ) {
			$response = wp_remote_get( add_query_arg( array_map( 'rawurlencode', $params ), $url ), $args );
		} else {
			$response = wp_remote_post( $url, array_merge( $args, array( 'body' => $params ) ) );
		}
		if ( is_wp_error( $response ) ) {
			return self::failed( $response->get_error_message() );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) || isset( $data['error'] ) ) {
			$message = isset( $data['error']['message'] ) ? (string) $data['error']['message'] : 'HTTP ' . $code;
			return self::failed( $message );
		}
		delete_option( self::ERROR_OPTION );
		return $data;
	}

	/**
	 * Remember an error for the settings screen.
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
		/* translators: %s: error from Facebook. */
		return new WP_Error( 'arnl_facebook', sprintf( __( 'Facebook: %s', 'ar-net-logger' ), $message ) );
	}

	/**
	 * Forget posts of sessions more than 60 days old.
	 */
	protected static function prune() {
		$cutoff = arnl_now()->modify( '-60 days' )->format( 'Y-m-d' );
		$posts  = self::posts();
		$kept   = array_filter(
			$posts,
			static function ( $post ) use ( $cutoff ) {
				return isset( $post['date'] ) && $post['date'] >= $cutoff;
			}
		);
		if ( count( $kept ) !== count( $posts ) ) {
			update_option( self::POSTS_OPTION, $kept, false );
		}
	}

	/**
	 * Ready-to-paste details for creating a Facebook event by hand (Pages and Groups).
	 *
	 * @param ARNL_Occurrence $o Session.
	 * @return array<string,string> Label => value.
	 */
	public static function event_details( ARNL_Occurrence $o ) {
		$values  = self::values( $o );
		$details = array_filter(
			array(
				$values['description'],
				'' !== $values['frequency'] ? __( 'Frequency:', 'ar-net-logger' ) . ' ' . $values['frequency'] : '',
				'' !== $values['ncs'] ? __( 'Net Control:', 'ar-net-logger' ) . ' ' . $values['ncs'] : '',
				$values['topic'],
				'' !== $values['url'] ? __( 'Details and online check-in:', 'ar-net-logger' ) . ' ' . $values['url'] : '',
			)
		);
		return array(
			__( 'Event name', 'ar-net-logger' ) => $o->title() . ( '' !== $o->topic ? ' – ' . $o->topic : '' ),
			__( 'Start', 'ar-net-logger' )      => $values['date'] . ', ' . $values['time'] . ' ' . $values['timezone'],
			__( 'End', 'ar-net-logger' )        => arnl_format_date( $o->end()->format( 'Y-m-d' ), 'l, ' . get_option( 'date_format' ) ) . ', ' . $values['end_time'] . ' ' . $values['timezone'],
			__( 'Location', 'ar-net-logger' )   => '' !== $values['frequency'] ? __( 'On the air:', 'ar-net-logger' ) . ' ' . $values['frequency'] : __( 'On the air', 'ar-net-logger' ),
			__( 'Details', 'ar-net-logger' )    => implode( "\n", $details ),
		);
	}
}
