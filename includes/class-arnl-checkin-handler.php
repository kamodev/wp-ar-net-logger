<?php
/**
 * Public (front-end) online check-in form and submission handler.
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Online check-ins.
 */
class ARNL_Checkin_Handler {

	const ACTION = 'arnl_checkin';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Process a submitted check-in, then redirect back with a status code.
	 */
	public static function handle() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified below, after the redirect target is known.
		$key      = isset( $_POST['arnl_event'] ) ? sanitize_text_field( wp_unslash( $_POST['arnl_event'] ) ) : '';
		$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
		$redirect = wp_validate_redirect( $redirect, home_url( '/' ) );
		$redirect = remove_query_arg( array( 'arnl_msg', 'arnl_cs' ), $redirect );

		$nonce = isset( $_POST['_arnl_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_arnl_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::ACTION . '_' . $key ) ) {
			self::back( $redirect, 'expired' );
		}
		if ( ! empty( $_POST['arnl_hp'] ) ) {
			self::back( $redirect, 'error' );
		}

		$occurrence = ARNL_Schedule::find( $key );
		if ( ! $occurrence ) {
			self::back( $redirect, 'error' );
		}
		if ( ARNL_Settings::get( 'require_login' ) && ! is_user_logged_in() ) {
			self::back( $redirect, 'login' );
		}
		if ( ! $occurrence->checkins_open() ) {
			self::back( $redirect, 'closed' );
		}

		$callsign = isset( $_POST['callsign'] ) ? arnl_sanitize_callsign( sanitize_text_field( wp_unslash( $_POST['callsign'] ) ) ) : '';
		if ( ! arnl_is_valid_callsign( $callsign ) ) {
			self::back( $redirect, 'callsign' );
		}

		if ( ! $occurrence->is_virtual() && ARNL_Checkins::find_duplicate( $occurrence->event_id, $callsign ) ) {
			self::back( $redirect, 'duplicate', $callsign );
		}

		$throttle_key = 'arnl_rl_' . md5( wp_salt( 'nonce' ) . ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ) );
		$rate_limit   = (int) ARNL_Settings::get( 'rate_limit' );
		if ( $rate_limit > 0 && get_transient( $throttle_key ) ) {
			self::back( $redirect, 'slow' );
		}

		$occurrence = ARNL_Schedule::materialize( $occurrence );
		if ( is_wp_error( $occurrence ) ) {
			self::back( $redirect, 'error' );
		}

		$fields = array( 'name', 'location', 'station_type', 'traffic_precedence', 'traffic_count', 'comments' );
		$data   = array();
		foreach ( $fields as $field ) {
			$data[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		$data['comments'] = isset( $_POST['comments'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comments'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$status = ARNL_Settings::get( 'auto_approve' ) ? 'approved' : 'pending';
		$data   = array_merge(
			$data,
			array(
				'event_id'    => $occurrence->event_id,
				'callsign'    => $callsign,
				'short_time'  => ! empty( $_POST['short_time'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'has_traffic' => ! empty( $_POST['has_traffic'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'source'      => 'online',
				'status'      => $status,
				'user_id'     => get_current_user_id(),
			)
		);

		/**
		 * Filters an online check-in before it is saved. Return a WP_Error to reject it.
		 *
		 * @param array|WP_Error  $data       Check-in fields.
		 * @param ARNL_Occurrence $occurrence Session.
		 */
		$data = apply_filters( 'arnl_pre_online_checkin', $data, $occurrence );
		if ( is_wp_error( $data ) ) {
			self::back( $redirect, 'error' );
		}

		$id = ARNL_Checkins::insert( $data );
		if ( is_wp_error( $id ) ) {
			self::back( $redirect, 'arnl_callsign' === $id->get_error_code() ? 'callsign' : 'error' );
		}
		if ( $rate_limit > 0 ) {
			set_transient( $throttle_key, 1, $rate_limit );
		}

		self::back( $redirect, 'approved' === $status ? 'checked_in' : 'pending', $callsign );
	}

	/**
	 * Redirect back to the form.
	 *
	 * @param string $url      URL.
	 * @param string $code     Message code.
	 * @param string $callsign Callsign to mention in the message.
	 */
	protected static function back( $url, $code, $callsign = '' ) {
		$args = array( 'arnl_msg' => $code );
		if ( '' !== $callsign ) {
			$args['arnl_cs'] = $callsign;
		}
		wp_safe_redirect( add_query_arg( $args, $url ) . '#arnl-checkin' );
		exit;
	}

	/**
	 * Feedback message for the current request, if any.
	 *
	 * @return array|null array( type, text ).
	 */
	public static function message() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		if ( empty( $_GET['arnl_msg'] ) ) {
			return null;
		}
		$code     = sanitize_key( wp_unslash( $_GET['arnl_msg'] ) );
		$callsign = isset( $_GET['arnl_cs'] ) ? arnl_sanitize_callsign( sanitize_text_field( wp_unslash( $_GET['arnl_cs'] ) ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		switch ( $code ) {
			case 'checked_in':
				/* translators: %s: callsign. */
				return array( 'success', sprintf( __( 'Thanks %s, you are checked in! 73.', 'ar-net-logger' ), $callsign ) );
			case 'pending':
				return array( 'success', __( 'Thanks! Your check-in was received and will be listed once Net Control confirms it.', 'ar-net-logger' ) );
			case 'duplicate':
				/* translators: %s: callsign. */
				return array( 'info', sprintf( __( '%s is already checked in to this session.', 'ar-net-logger' ), $callsign ) );
			case 'closed':
				return array( 'error', __( 'Online check-in is not open for this session.', 'ar-net-logger' ) );
			case 'callsign':
				return array( 'error', __( 'Please enter a valid amateur radio callsign.', 'ar-net-logger' ) );
			case 'expired':
				return array( 'error', __( 'The form expired. Please reload the page and try again.', 'ar-net-logger' ) );
			case 'slow':
				return array( 'error', __( 'Please wait a moment before submitting another check-in.', 'ar-net-logger' ) );
			case 'login':
				return array( 'error', __( 'Please log in to check in.', 'ar-net-logger' ) );
			case 'error':
				return array( 'error', __( 'Something went wrong. Please try again.', 'ar-net-logger' ) );
		}
		return null;
	}

	/**
	 * Check-in form (or the reason it is unavailable).
	 *
	 * @param ARNL_Occurrence $o        Session.
	 * @param string          $redirect URL to return to after submitting.
	 * @return string
	 */
	public static function form( ARNL_Occurrence $o, $redirect ) {
		if ( ! ARNL_Settings::get( 'public_checkins' ) || empty( $o->net['allow_checkins'] ) ) {
			return '';
		}

		ob_start();
		echo '<div class="arnl-checkin" id="arnl-checkin">';
		echo '<h3 class="arnl-checkin-title">' . esc_html__( 'Online check-in', 'ar-net-logger' ) . '</h3>';

		$message = self::message();
		if ( $message ) {
			printf( '<div class="arnl-notice arnl-notice--%1$s" role="status">%2$s</div>', esc_attr( $message[0] ), esc_html( $message[1] ) );
		}

		if ( $o->is_cancelled() ) {
			echo '<p class="arnl-checkin-closed">' . esc_html__( 'This session has been cancelled.', 'ar-net-logger' ) . '</p>';
		} elseif ( 'completed' === $o->status ) {
			echo '<p class="arnl-checkin-closed">' . esc_html__( 'This net has closed. Thanks to everyone who checked in!', 'ar-net-logger' ) . '</p>';
		} elseif ( ! $o->checkins_open() ) {
			if ( $o->checkins_upcoming() ) {
				list( $opens ) = $o->checkin_window();
				printf(
					'<p class="arnl-checkin-closed">%s</p>',
					esc_html(
						sprintf(
							/* translators: 1: date, 2: time. */
							__( 'Online check-in opens %1$s at %2$s.', 'ar-net-logger' ),
							wp_date( get_option( 'date_format' ), $opens->getTimestamp() ),
							wp_date( get_option( 'time_format' ) . ' T', $opens->getTimestamp() )
						)
					)
				);
			} else {
				echo '<p class="arnl-checkin-closed">' . esc_html__( 'Online check-in for this session is closed.', 'ar-net-logger' ) . '</p>';
			}
		} elseif ( ARNL_Settings::get( 'require_login' ) && ! is_user_logged_in() ) {
			printf(
				'<p class="arnl-checkin-closed"><a href="%1$s">%2$s</a></p>',
				esc_url( wp_login_url( $redirect ) ),
				esc_html__( 'Log in to check in to this net.', 'ar-net-logger' )
			);
		} else {
			self::form_fields( $o, $redirect );
		}

		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * The form itself.
	 *
	 * @param ARNL_Occurrence $o        Session.
	 * @param string          $redirect Return URL.
	 */
	protected static function form_fields( ARNL_Occurrence $o, $redirect ) {
		$prefill = array(
			'callsign' => '',
			'name'     => '',
		);
		$user    = wp_get_current_user();
		if ( $user->exists() ) {
			$prefill['callsign'] = arnl_sanitize_callsign( get_user_meta( $user->ID, 'callsign', true ) );
			$prefill['name']     = $user->first_name ? $user->first_name : $user->display_name;
		}
		/**
		 * Filters the values pre-filled in the public check-in form.
		 *
		 * @param array           $prefill Values (callsign, name).
		 * @param ARNL_Occurrence $o       Session.
		 */
		$prefill = apply_filters( 'arnl_checkin_prefill', $prefill, $o );
		$uid     = 'arnl-' . substr( md5( $o->key() ), 0, 6 );
		?>
		<p class="arnl-checkin-intro">
			<?php esc_html_e( 'Listening in? Let Net Control know you are here. Stations heard on the air are logged by the NCS.', 'ar-net-logger' ); ?>
		</p>
		<form class="arnl-checkin-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
			<input type="hidden" name="arnl_event" value="<?php echo esc_attr( $o->key() ); ?>">
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect ); ?>">
			<?php wp_nonce_field( self::ACTION . '_' . $o->key(), '_arnl_nonce', false ); ?>
			<div class="arnl-hp" aria-hidden="true">
				<label for="<?php echo esc_attr( $uid ); ?>-hp"><?php esc_html_e( 'Leave this field empty', 'ar-net-logger' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-hp" name="arnl_hp" value="" tabindex="-1" autocomplete="off">
			</div>
			<div class="arnl-field-row">
				<p class="arnl-field arnl-field--callsign">
					<label for="<?php echo esc_attr( $uid ); ?>-callsign"><?php esc_html_e( 'Callsign', 'ar-net-logger' ); ?> <span class="arnl-required" aria-hidden="true">*</span></label>
					<input type="text" id="<?php echo esc_attr( $uid ); ?>-callsign" name="callsign" value="<?php echo esc_attr( $prefill['callsign'] ); ?>" required maxlength="20" autocapitalize="characters" autocomplete="off" spellcheck="false" data-arnl-remember="callsign">
				</p>
				<p class="arnl-field">
					<label for="<?php echo esc_attr( $uid ); ?>-name"><?php esc_html_e( 'Name', 'ar-net-logger' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $uid ); ?>-name" name="name" value="<?php echo esc_attr( $prefill['name'] ); ?>" maxlength="100" autocomplete="given-name" data-arnl-remember="name">
				</p>
				<p class="arnl-field">
					<label for="<?php echo esc_attr( $uid ); ?>-location"><?php esc_html_e( 'Location (QTH)', 'ar-net-logger' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $uid ); ?>-location" name="location" value="" maxlength="190" data-arnl-remember="location">
				</p>
				<p class="arnl-field">
					<label for="<?php echo esc_attr( $uid ); ?>-type"><?php esc_html_e( 'Station', 'ar-net-logger' ); ?></label>
					<select id="<?php echo esc_attr( $uid ); ?>-type" name="station_type" data-arnl-remember="station_type">
						<?php foreach ( arnl_station_types() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			</div>
			<fieldset class="arnl-field arnl-field--options">
				<legend class="screen-reader-text"><?php esc_html_e( 'Check-in options', 'ar-net-logger' ); ?></legend>
				<label><input type="checkbox" name="short_time" value="1"> <?php esc_html_e( 'Short time (checking in and out)', 'ar-net-logger' ); ?></label>
				<label><input type="checkbox" name="has_traffic" value="1" data-arnl-toggle="<?php echo esc_attr( $uid ); ?>-traffic"> <?php esc_html_e( 'I have traffic / an announcement for the net', 'ar-net-logger' ); ?></label>
			</fieldset>
			<p class="arnl-field arnl-field--traffic" id="<?php echo esc_attr( $uid ); ?>-traffic">
				<label for="<?php echo esc_attr( $uid ); ?>-precedence"><?php esc_html_e( 'Traffic precedence', 'ar-net-logger' ); ?></label>
				<select id="<?php echo esc_attr( $uid ); ?>-precedence" name="traffic_precedence">
					<?php foreach ( arnl_precedences() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="arnl-field arnl-field--comments">
				<label for="<?php echo esc_attr( $uid ); ?>-comments"><?php esc_html_e( 'Comments for Net Control (optional)', 'ar-net-logger' ); ?></label>
				<textarea id="<?php echo esc_attr( $uid ); ?>-comments" name="comments" rows="2" maxlength="1000"></textarea>
			</p>
			<p class="arnl-submit">
				<button type="submit" class="arnl-button"><?php esc_html_e( 'Check in', 'ar-net-logger' ); ?></button>
			</p>
		</form>
		<?php
	}

	/**
	 * Public list of approved check-ins (per the "public log" setting).
	 *
	 * @param ARNL_Occurrence $o Session.
	 * @return string
	 */
	public static function public_log( ARNL_Occurrence $o ) {
		$mode = ARNL_Settings::get( 'public_log' );
		if ( 'none' === $mode || $o->is_virtual() ) {
			return '';
		}
		$checkins = ARNL_Checkins::for_event( $o->event_id, 'approved' );
		if ( ! $checkins ) {
			return '';
		}
		ob_start();
		?>
		<div class="arnl-public-log">
			<h3>
				<?php
				/* translators: %d: number of check-ins. */
				echo esc_html( sprintf( _n( '%d check-in', '%d check-ins', count( $checkins ), 'ar-net-logger' ), count( $checkins ) ) );
				?>
			</h3>
			<ol class="arnl-public-log-list">
				<?php foreach ( $checkins as $c ) : ?>
					<li>
						<strong class="arnl-callsign"><?php echo esc_html( $c['callsign'] ); ?></strong>
						<?php if ( 'callsign_name' === $mode && '' !== $c['name'] ) : ?>
							<span class="arnl-name"><?php echo esc_html( $c['name'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
