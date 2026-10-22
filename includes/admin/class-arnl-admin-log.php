<?php
/**
 * Net Log screen: what the Net Control Station uses while running a net.
 *
 * Shows the session, its net script, a quick check-in form, and the running
 * list of check-ins (from the air and from the website) with approve /
 * acknowledge / edit / delete actions, plus CSV export.
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Net Log.
 */
class ARNL_Admin_Log {

	/**
	 * Register handlers.
	 */
	public static function init() {
		add_action( 'admin_post_arnl_save_checkin', array( __CLASS__, 'handle_save_checkin' ) );
		add_action( 'admin_post_arnl_checkin_action', array( __CLASS__, 'handle_checkin_action' ) );
		add_action( 'admin_post_arnl_update_session', array( __CLASS__, 'handle_update_session' ) );
		add_action( 'admin_post_arnl_export_checkins', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_arnl_script', array( __CLASS__, 'handle_script' ) );
		add_action( 'wp_ajax_arnl_lookup_callsign', array( __CLASS__, 'ajax_lookup' ) );
		add_action( 'wp_ajax_arnl_search_callsigns', array( __CLASS__, 'ajax_search' ) );
		add_action( 'wp_ajax_arnl_refresh_log', array( __CLASS__, 'ajax_refresh' ) );
	}

	/**
	 * Session from the request, or die.
	 *
	 * @param string $key Session key.
	 * @return ARNL_Occurrence
	 */
	protected static function occurrence( $key ) {
		$o = ARNL_Schedule::find( $key );
		if ( ! $o ) {
			wp_die( esc_html__( 'Session not found. It may have been rescheduled or deleted.', 'ar-net-logger' ) );
		}
		return $o;
	}

	/**
	 * Check-in action URL.
	 *
	 * @param array  $checkin Check-in.
	 * @param string $op      Operation.
	 * @return string
	 */
	protected static function checkin_action_url( array $checkin, $op ) {
		return ARNL_Admin::action_url(
			'arnl_checkin_action',
			array(
				'checkin' => $checkin['id'],
				'op'      => $op,
			),
			'arnl_checkin_action_' . $checkin['id']
		);
	}

	/**
	 * Render the Net Log.
	 */
	public static function render() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- routing only.
		$key        = isset( $_GET['event'] ) ? sanitize_text_field( wp_unslash( $_GET['event'] ) ) : '';
		$editing_id = isset( $_GET['checkin'] ) ? absint( $_GET['checkin'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$o = self::occurrence( $key );

		$checkins = $o->is_virtual() ? array() : ARNL_Checkins::for_event( $o->event_id );
		$stats    = ARNL_Checkins::stats( $checkins );
		$editing  = null;
		foreach ( $checkins as $c ) {
			if ( $c['id'] === $editing_id ) {
				$editing = $c;
			}
		}
		$net        = $o->net;
		$can_manage = current_user_can( ARNL_Install::CAP_MANAGE_NETS );
		$public_url = '';
		if ( 'active' === $net['status'] ) {
			if ( (int) ARNL_Settings::get( 'event_page_id' ) || ARNL_Permalinks::available() ) {
				$public_url = arnl_event_url( $o );
			} else {
				$page       = ARNL_ICal::event_page_url();
				$public_url = '' !== $page ? arnl_event_url( $o, $page ) : '';
			}
		}
		?>
		<div class="wrap arnl-wrap arnl-log" data-arnl-refresh="<?php echo esc_attr( $o->key() ); ?>" data-arnl-signature="<?php echo esc_attr( self::signature( $o, $checkins ) ); ?>">
			<p class="arnl-breadcrumb"><a href="<?php echo esc_url( ARNL_Admin::url( 'arnl-schedule', array( 'net' => $o->net_id ) ) ); ?>">&larr; <?php esc_html_e( 'Net Schedule', 'ar-net-logger' ); ?></a></p>
			<h1 class="wp-heading-inline">
				<span class="arnl-swatch" style="--arnl-color: <?php echo esc_attr( $net['color'] ); ?>" aria-hidden="true"></span>
				<?php echo esc_html( $o->title() ); ?>
			</h1>
			<?php echo ARNL_Admin::status_badge( $o->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<hr class="wp-header-end">

			<div class="arnl-log-header">
				<div class="arnl-log-when">
					<?php echo arnl_net_logo( $net ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(). ?>
					<div>
					<strong><?php echo esc_html( arnl_format_date( $o->date, 'l, ' . get_option( 'date_format' ) ) ); ?></strong>
					&middot; <?php echo esc_html( ARNL_Calendar::time_range( $o ) ); ?>
					<?php $freq = arnl_describe_frequency( $net, $o->frequency ); ?>
					<?php if ( '' !== $freq ) : ?>
						<br><?php echo esc_html( $freq ); ?>
					<?php endif; ?>
					<?php $digital = arnl_describe_digital( $net ); ?>
					<?php if ( '' !== $digital ) : ?>
						<br><?php echo esc_html( $digital ); ?>
					<?php endif; ?>
					<?php $system = arnl_linked_system( $net ); ?>
					<?php if ( $system ) : ?>
						<br><?php esc_html_e( 'Linked system:', 'ar-net-logger' ); ?>
						<?php if ( '' !== $system['url'] ) : ?>
							<a href="<?php echo esc_url( $system['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $system['name'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $system['name'] ); ?>
						<?php endif; ?>
					<?php endif; ?>
					<?php if ( $o->is_rescheduled() ) : ?>
						<br><small class="arnl-muted">
							<?php
							/* translators: %s: original date. */
							echo esc_html( sprintf( __( 'Rescheduled from %s', 'ar-net-logger' ), arnl_format_date( $o->occurrence_date ) . ' ' . arnl_format_time( $net['start_time'] ) ) );
							?>
						</small>
					<?php endif; ?>
					<?php if ( $o->event && '' !== $o->event['opened_at'] ) : ?>
						<br><small class="arnl-muted">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: open time, 2: close time. */
									__( 'Net opened %1$s, closed %2$s', 'ar-net-logger' ),
									mysql2date( get_option( 'time_format' ), $o->event['opened_at'] ),
									'' !== $o->event['closed_at'] ? mysql2date( get_option( 'time_format' ), $o->event['closed_at'] ) : '—'
								)
							);
							?>
						</small>
					<?php endif; ?>
					</div>
				</div>
				<div class="arnl-log-buttons">
					<?php if ( 'scheduled' === $o->status ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( ARNL_Admin_Schedule::status_url( $o, 'active' ) ); ?>"><?php esc_html_e( 'Open Net', 'ar-net-logger' ); ?></a>
					<?php elseif ( 'active' === $o->status ) : ?>
						<a class="button button-primary arnl-confirm" data-arnl-confirm="<?php esc_attr_e( 'Close the net? Online check-ins will stop.', 'ar-net-logger' ); ?>" href="<?php echo esc_url( ARNL_Admin_Schedule::status_url( $o, 'completed' ) ); ?>"><?php esc_html_e( 'Close Net', 'ar-net-logger' ); ?></a>
					<?php elseif ( 'completed' === $o->status ) : ?>
						<a class="button" href="<?php echo esc_url( ARNL_Admin_Schedule::status_url( $o, 'active' ) ); ?>"><?php esc_html_e( 'Re-open Net', 'ar-net-logger' ); ?></a>
					<?php else : ?>
						<a class="button" href="<?php echo esc_url( ARNL_Admin_Schedule::status_url( $o, 'scheduled' ) ); ?>"><?php esc_html_e( 'Restore Session', 'ar-net-logger' ); ?></a>
					<?php endif; ?>
					<?php if ( $can_manage ) : ?>
						<a class="button" href="<?php echo esc_url( ARNL_Admin_Schedule::edit_url( $o ) ); ?>"><?php esc_html_e( 'Edit / Reschedule', 'ar-net-logger' ); ?></a>
					<?php endif; ?>
					<?php if ( $checkins ) : ?>
						<a class="button" href="<?php echo esc_url( ARNL_Admin::action_url( 'arnl_export_checkins', array( 'event' => $o->event_id ), 'arnl_export_' . $o->event_id ) ); ?>"><?php esc_html_e( 'Export CSV', 'ar-net-logger' ); ?></a>
						<button type="button" class="button arnl-print"><?php esc_html_e( 'Print Log', 'ar-net-logger' ); ?></button>
					<?php endif; ?>
					<?php if ( '' !== trim( wp_strip_all_tags( $net['preamble'] ) ) ) : ?>
						<a class="button" href="<?php echo esc_url( self::script_url( $o ) ); ?>" target="arnl-script-<?php echo esc_attr( $o->key() ); ?>"><?php esc_html_e( 'Net Script', 'ar-net-logger' ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== $public_url ) : ?>
						<a class="button" href="<?php echo esc_url( $public_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Public Page', 'ar-net-logger' ); ?></a>
					<?php endif; ?>
				</div>
			</div>

			<?php self::render_stats( $stats ); ?>

			<div class="arnl-log-columns">
				<div class="arnl-log-main">
					<?php self::render_checkin_form( $o, $editing ); ?>
					<p class="arnl-refresh arnl-noprint">
						<label><input type="checkbox" id="arnl-auto-refresh" checked> <?php esc_html_e( 'Update the check-in list automatically', 'ar-net-logger' ); ?></label>
						<span class="arnl-refresh-status arnl-muted" role="status" aria-live="polite"></span>
					</p>
					<?php self::render_checkins_table( $o, $checkins, $stats ); ?>
				</div>
				<div class="arnl-log-side">
					<?php self::render_script_box( $o, $can_manage ); ?>
					<?php self::render_session_box( $o ); ?>
					<?php self::render_share_box( $o ); ?>
					<details class="arnl-box">
						<summary><?php esc_html_e( 'NCS quick reference', 'ar-net-logger' ); ?></summary>
						<ol class="arnl-ncs-tips">
							<li><?php esc_html_e( 'Open the net on time: identify, read the preamble, state it is a directed net.', 'ar-net-logger' ); ?></li>
							<li><?php esc_html_e( 'Ask for emergency or priority traffic first.', 'ar-net-logger' ); ?></li>
							<li><?php esc_html_e( 'Call for short-time, mobile / portable, then general check-ins; take calls in small groups and acknowledge each.', 'ar-net-logger' ); ?></li>
							<li><?php esc_html_e( 'Ask for relays of weak stations and for any website check-ins listed below.', 'ar-net-logger' ); ?></li>
							<li><?php esc_html_e( 'Handle listed traffic, then go around the list for comments.', 'ar-net-logger' ); ?></li>
							<li><?php esc_html_e( 'Call for late check-ins and last traffic, then close the net and identify.', 'ar-net-logger' ); ?></li>
						</ol>
					</details>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Quick check-in form (also used to edit one).
	 *
	 * @param ARNL_Occurrence $o       Session.
	 * @param array|null      $editing Check-in being edited.
	 */
	protected static function render_checkin_form( ARNL_Occurrence $o, $editing ) {
		$c      = $editing ? $editing : ARNL_Checkins::defaults();
		$failed = ARNL_Admin::failed_data();
		if ( $failed ) {
			$c = array_merge( $c, $failed );
		}
		$disabled = $o->is_cancelled();
		// Fields the callsign type-ahead fills in.
		$fill = array(
			'name'         => '#arnl-ci-name',
			'location'     => '#arnl-ci-location',
			'station_type' => '#arnl-ci-type',
		);
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="arnl-box arnl-checkin-admin" id="arnl-checkin-form">
			<h2><?php echo esc_html( $editing ? __( 'Edit check-in', 'ar-net-logger' ) : __( 'Log a check-in', 'ar-net-logger' ) ); ?></h2>
			<?php if ( $disabled ) : ?>
				<p class="arnl-muted"><?php esc_html_e( 'This session is cancelled. Restore it to log check-ins.', 'ar-net-logger' ); ?></p>
			<?php endif; ?>
			<input type="hidden" name="action" value="arnl_save_checkin">
			<input type="hidden" name="event" value="<?php echo esc_attr( $o->key() ); ?>">
			<input type="hidden" name="checkin_id" value="<?php echo (int) $c['id']; ?>">
			<?php wp_nonce_field( 'arnl_save_checkin_' . $o->key() ); ?>
			<fieldset <?php disabled( $disabled ); ?>>
				<div class="arnl-grid">
					<p>
						<label for="arnl-ci-callsign"><?php esc_html_e( 'Callsign', 'ar-net-logger' ); ?> <span class="arnl-required">*</span></label>
						<input type="text" id="arnl-ci-callsign" name="callsign" class="arnl-callsign-input" value="<?php echo esc_attr( $c['callsign'] ); ?>" maxlength="20" required autocomplete="off" spellcheck="false" <?php echo $editing ? '' : 'autofocus'; ?> data-arnl-lookup="<?php echo esc_attr( wp_json_encode( $fill ) ); ?>">
					</p>
					<p>
						<label for="arnl-ci-name"><?php esc_html_e( 'Name', 'ar-net-logger' ); ?></label>
						<input type="text" id="arnl-ci-name" name="name" value="<?php echo esc_attr( $c['name'] ); ?>" maxlength="100" autocomplete="off">
					</p>
					<p>
						<label for="arnl-ci-location"><?php esc_html_e( 'Location (QTH)', 'ar-net-logger' ); ?></label>
						<input type="text" id="arnl-ci-location" name="location" value="<?php echo esc_attr( $c['location'] ); ?>" maxlength="190" autocomplete="off">
					</p>
					<p>
						<label for="arnl-ci-type"><?php esc_html_e( 'Station', 'ar-net-logger' ); ?></label>
						<?php ARNL_Admin::select( 'station_type', arnl_station_types(), $c['station_type'], array( 'id' => 'arnl-ci-type' ) ); ?>
					</p>
					<p>
						<label for="arnl-ci-relay"><?php esc_html_e( 'Relayed by', 'ar-net-logger' ); ?></label>
						<input type="text" id="arnl-ci-relay" name="relayed_by" class="arnl-callsign-input" value="<?php echo esc_attr( $c['relayed_by'] ); ?>" maxlength="20" autocomplete="off" data-arnl-lookup="{}">
					</p>
					<?php if ( $editing ) : ?>
						<p>
							<label for="arnl-ci-status"><?php esc_html_e( 'Status', 'ar-net-logger' ); ?></label>
							<?php ARNL_Admin::select( 'status', arnl_checkin_statuses(), $c['status'], array( 'id' => 'arnl-ci-status' ) ); ?>
						</p>
					<?php endif; ?>
				</div>
				<div class="arnl-inline-options">
					<label><input type="checkbox" name="short_time" value="1" <?php checked( $c['short_time'] ); ?>> <?php esc_html_e( 'Short time', 'ar-net-logger' ); ?></label>
					<label><input type="checkbox" name="acknowledged" value="1" <?php checked( $editing ? $c['acknowledged'] : 1 ); ?>> <?php esc_html_e( 'Acknowledged on air', 'ar-net-logger' ); ?></label>
					<label><input type="checkbox" name="has_traffic" value="1" <?php checked( $c['has_traffic'] ); ?> data-arnl-toggle="arnl-ci-traffic"> <?php esc_html_e( 'Has traffic', 'ar-net-logger' ); ?></label>
					<span id="arnl-ci-traffic" class="arnl-traffic-fields">
						<label class="screen-reader-text" for="arnl-ci-precedence"><?php esc_html_e( 'Precedence', 'ar-net-logger' ); ?></label>
						<?php ARNL_Admin::select( 'traffic_precedence', arnl_precedences(), '' !== $c['traffic_precedence'] ? $c['traffic_precedence'] : 'routine', array( 'id' => 'arnl-ci-precedence' ) ); ?>
						<label for="arnl-ci-count"><?php esc_html_e( 'Count', 'ar-net-logger' ); ?></label>
						<input type="number" id="arnl-ci-count" name="traffic_count" value="<?php echo (int) max( 1, $c['traffic_count'] ); ?>" min="0" max="999" class="small-text">
					</span>
					<label><input type="checkbox" name="has_announcement" value="1" <?php checked( $c['has_announcement'] ); ?> data-arnl-toggle="arnl-ci-announcement-row"> <?php esc_html_e( 'Has an announcement', 'ar-net-logger' ); ?></label>
				</div>
				<p id="arnl-ci-announcement-row">
					<label for="arnl-ci-announcement"><?php esc_html_e( 'Announcement', 'ar-net-logger' ); ?></label>
					<textarea id="arnl-ci-announcement" name="announcement" rows="2" class="large-text" maxlength="500" placeholder="<?php esc_attr_e( 'Optional: the announcement, if the station gave it here or you want to note it.', 'ar-net-logger' ); ?>"><?php echo esc_textarea( $c['announcement'] ); ?></textarea>
				</p>
				<p>
					<label for="arnl-ci-comments"><?php esc_html_e( 'Comments / traffic details', 'ar-net-logger' ); ?></label>
					<textarea id="arnl-ci-comments" name="comments" rows="2" class="large-text"><?php echo esc_textarea( $c['comments'] ); ?></textarea>
				</p>
				<p class="arnl-form-actions">
					<?php submit_button( $editing ? __( 'Update Check-in', 'ar-net-logger' ) : __( 'Log Check-in', 'ar-net-logger' ), 'primary', 'submit', false ); ?>
					<?php if ( $editing ) : ?>
						<a class="button" href="<?php echo esc_url( ARNL_Admin_Schedule::log_url( $o ) ); ?>"><?php esc_html_e( 'Cancel', 'ar-net-logger' ); ?></a>
					<?php endif; ?>
					<span class="arnl-lookup-status" aria-live="polite"></span>
				</p>
			</fieldset>
		</form>
		<?php
	}

	/**
	 * QNI / QTC counts bar.
	 *
	 * @param array $stats Stats from ARNL_Checkins::stats().
	 */
	protected static function render_stats( array $stats ) {
		?>
		<ul class="arnl-stats" id="arnl-stats">
			<li><span class="arnl-stat-num"><?php echo (int) $stats['approved']; ?></span> <abbr title="<?php esc_attr_e( 'Stations checked in', 'ar-net-logger' ); ?>"><?php esc_html_e( 'Check-ins (QNI)', 'ar-net-logger' ); ?></abbr></li>
			<li><span class="arnl-stat-num"><?php echo (int) $stats['traffic_count']; ?></span> <abbr title="<?php esc_attr_e( 'Messages / traffic listed', 'ar-net-logger' ); ?>"><?php esc_html_e( 'Traffic (QTC)', 'ar-net-logger' ); ?></abbr></li>
			<li><span class="arnl-stat-num"><?php echo (int) $stats['short_time']; ?></span> <?php esc_html_e( 'Short time', 'ar-net-logger' ); ?></li>
			<li><span class="arnl-stat-num"><?php echo (int) $stats['mobile']; ?></span> <?php esc_html_e( 'Mobile / portable', 'ar-net-logger' ); ?></li>
			<li><span class="arnl-stat-num"><?php echo (int) $stats['relayed']; ?></span> <?php esc_html_e( 'Relayed', 'ar-net-logger' ); ?></li>
			<li><span class="arnl-stat-num"><?php echo (int) $stats['online']; ?></span> <?php esc_html_e( 'Online', 'ar-net-logger' ); ?></li>
			<li><span class="arnl-stat-num"><?php echo (int) $stats['announcements']; ?></span> <?php esc_html_e( 'Announcements', 'ar-net-logger' ); ?></li>
			<?php if ( $stats['pending'] ) : ?>
				<li class="arnl-stat-pending"><span class="arnl-stat-num"><?php echo (int) $stats['pending']; ?></span> <?php esc_html_e( 'Pending', 'ar-net-logger' ); ?></li>
			<?php endif; ?>
		</ul>
		<?php
	}

	/**
	 * Fingerprint of a session's check-ins and status, so the Net Log only
	 * re-renders when something changed.
	 *
	 * @param ARNL_Occurrence $o        Session.
	 * @param array[]         $checkins Check-ins.
	 * @return string
	 */
	protected static function signature( ARNL_Occurrence $o, array $checkins ) {
		$parts = array( $o->key(), $o->status );
		foreach ( $checkins as $c ) {
			$parts[] = $c['id'] . ':' . $c['status'] . ':' . $c['acknowledged'] . ':' . $c['updated_at'];
		}
		return md5( implode( '|', $parts ) );
	}

	/**
	 * Check-ins table.
	 *
	 * @param ARNL_Occurrence $o        Session.
	 * @param array[]         $checkins Check-ins.
	 * @param array           $stats    Stats.
	 */
	protected static function render_checkins_table( ARNL_Occurrence $o, array $checkins, array $stats ) {
		$types = arnl_station_types();
		?>
		<div class="arnl-box arnl-checkins" id="arnl-checkins">
			<h2>
				<?php esc_html_e( 'Check-ins', 'ar-net-logger' ); ?>
				<?php if ( $stats['pending'] ) : ?>
					<a class="button button-small" href="
					<?php
					echo esc_url(
						ARNL_Admin::action_url(
							'arnl_checkin_action',
							array(
								'event' => $o->event_id,
								'op'    => 'approve_all',
							),
							'arnl_approve_all_' . $o->event_id
						)
					);
					?>
															"><?php esc_html_e( 'Approve all pending', 'ar-net-logger' ); ?></a>
				<?php endif; ?>
			</h2>
			<table class="widefat striped arnl-checkins-table">
				<thead>
					<tr>
						<th scope="col" class="num">#</th>
						<th scope="col"><?php esc_html_e( 'Time', 'ar-net-logger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Callsign', 'ar-net-logger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Name', 'ar-net-logger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Location', 'ar-net-logger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Station', 'ar-net-logger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Traffic / notes', 'ar-net-logger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Source', 'ar-net-logger' ); ?></th>
						<th scope="col" class="arnl-noprint"><?php esc_html_e( 'Actions', 'ar-net-logger' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $checkins ) : ?>
						<tr><td colspan="9"><?php esc_html_e( 'No check-ins yet.', 'ar-net-logger' ); ?></td></tr>
					<?php endif; ?>
					<?php
					$n = 0;
					foreach ( $checkins as $c ) :
						$classes = array( 'arnl-ci-' . $c['status'] );
						if ( $c['acknowledged'] ) {
							$classes[] = 'arnl-ci-ack';
						}
						?>
						<tr class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-checkin="<?php echo (int) $c['id']; ?>">
							<td class="num"><?php echo 'rejected' === $c['status'] ? '–' : (int) ++$n; ?></td>
							<td><?php echo esc_html( mysql2date( get_option( 'time_format' ), $c['checkin_time'] ) ); ?></td>
							<td>
								<strong class="arnl-callsign"><?php echo esc_html( $c['callsign'] ); ?></strong>
								<?php if ( 'pending' === $c['status'] ) : ?>
									<span class="arnl-badge arnl-badge--pending"><?php esc_html_e( 'Pending', 'ar-net-logger' ); ?></span>
								<?php elseif ( 'rejected' === $c['status'] ) : ?>
									<span class="arnl-badge arnl-badge--cancelled"><?php esc_html_e( 'Rejected', 'ar-net-logger' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $c['name'] ); ?></td>
							<td><?php echo esc_html( $c['location'] ); ?></td>
							<td>
								<?php echo esc_html( arnl_label( $types, $c['station_type'] ) ); ?>
								<?php if ( $c['short_time'] ) : ?>
									<span class="arnl-badge arnl-badge--short"><?php esc_html_e( 'Short time', 'ar-net-logger' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $c['has_traffic'] ) : ?>
									<span class="arnl-badge arnl-badge--traffic-<?php echo esc_attr( $c['traffic_precedence'] ); ?>">
										<?php
										/* translators: 1: precedence, 2: count. */
										echo esc_html( sprintf( __( 'QTC %2$d %1$s', 'ar-net-logger' ), arnl_label( arnl_precedences(), $c['traffic_precedence'] ), max( 1, $c['traffic_count'] ) ) );
										?>
									</span>
								<?php endif; ?>
								<?php if ( '' !== $c['relayed_by'] ) : ?>
									<span class="arnl-muted">
										<?php
										/* translators: %s: relay station callsign. */
										echo esc_html( sprintf( __( 'via %s', 'ar-net-logger' ), $c['relayed_by'] ) );
										?>
									</span>
								<?php endif; ?>
								<?php if ( $c['has_announcement'] ) : ?>
									<div class="arnl-announcement">
										<span class="arnl-announce-badge"><span aria-hidden="true">📢</span> <?php esc_html_e( 'Announcement', 'ar-net-logger' ); ?></span>
										<?php if ( '' !== $c['announcement'] ) : ?>
											<span class="arnl-announce-text"><?php echo esc_html( $c['announcement'] ); ?></span>
										<?php endif; ?>
									</div>
								<?php endif; ?>
								<?php if ( '' !== $c['comments'] ) : ?>
									<div class="arnl-comment"><?php echo esc_html( $c['comments'] ); ?></div>
								<?php endif; ?>
							</td>
							<td><?php echo 'online' === $c['source'] ? esc_html__( 'Website', 'ar-net-logger' ) : esc_html__( 'NCS', 'ar-net-logger' ); ?></td>
							<td class="arnl-actions arnl-noprint">
								<?php if ( 'approved' !== $c['status'] ) : ?>
									<a href="<?php echo esc_url( self::checkin_action_url( $c, 'approve' ) ); ?>"><?php esc_html_e( 'Approve', 'ar-net-logger' ); ?></a> |
								<?php endif; ?>
								<?php if ( 'pending' === $c['status'] ) : ?>
									<a href="<?php echo esc_url( self::checkin_action_url( $c, 'reject' ) ); ?>"><?php esc_html_e( 'Reject', 'ar-net-logger' ); ?></a> |
								<?php endif; ?>
								<?php if ( 'approved' === $c['status'] ) : ?>
									<a href="<?php echo esc_url( self::checkin_action_url( $c, $c['acknowledged'] ? 'unack' : 'ack' ) ); ?>" title="<?php esc_attr_e( 'Acknowledged on the air by Net Control', 'ar-net-logger' ); ?>">
										<?php echo $c['acknowledged'] ? esc_html__( 'Un-ack', 'ar-net-logger' ) : esc_html__( 'Ack', 'ar-net-logger' ); ?>
									</a> |
								<?php endif; ?>
								<a href="<?php echo esc_url( add_query_arg( 'checkin', $c['id'], ARNL_Admin_Schedule::log_url( $o ) ) . '#arnl-checkin-form' ); ?>"><?php esc_html_e( 'Edit', 'ar-net-logger' ); ?></a> |
								<a class="arnl-confirm arnl-delete-link" data-arnl-confirm="<?php esc_attr_e( 'Delete this check-in?', 'ar-net-logger' ); ?>" href="<?php echo esc_url( self::checkin_action_url( $c, 'delete' ) ); ?>"><?php esc_html_e( 'Delete', 'ar-net-logger' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Sharing box: Discord status, Facebook Page post, Facebook event details to paste.
	 *
	 * @param ARNL_Occurrence $o Session.
	 */
	protected static function render_share_box( ARNL_Occurrence $o ) {
		$webhook = ARNL_Discord::webhook_for( $o->net );
		$details = ARNL_Facebook::event_details( $o );
		$text    = '';
		foreach ( $details as $label => $value ) {
			$text .= $label . ': ' . $value . "\n";
		}
		?>
		<details class="arnl-box arnl-share-box">
			<summary><?php esc_html_e( 'Share', 'ar-net-logger' ); ?></summary>
			<p>
				<strong><?php esc_html_e( 'Discord:', 'ar-net-logger' ); ?></strong>
				<?php
				if ( '' === $webhook ) {
					esc_html_e( 'not posting this net.', 'ar-net-logger' );
				} elseif ( ARNL_Settings::get( 'discord_checkins' ) ) {
					esc_html_e( 'check-ins are posted to the channel as they are logged.', 'ar-net-logger' );
				} else {
					esc_html_e( 'check-in posts are turned off in the settings.', 'ar-net-logger' );
				}
				?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Facebook Page:', 'ar-net-logger' ); ?></strong>
				<?php
				$button = ARNL_Admin_Schedule::facebook_button( $o );
				if ( '' !== $button ) {
					echo $button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
				} elseif ( ! ARNL_Facebook::configured() ) {
					esc_html_e( 'not connected.', 'ar-net-logger' );
				} else {
					esc_html_e( 'not posted.', 'ar-net-logger' );
				}
				?>
			</p>
			<p class="arnl-muted"><?php esc_html_e( 'Facebook event (Page or Group): Facebook does not let apps create events, so copy these details into "Create event".', 'ar-net-logger' ); ?></p>
			<label class="screen-reader-text" for="arnl-fb-event"><?php esc_html_e( 'Facebook event details', 'ar-net-logger' ); ?></label>
			<textarea id="arnl-fb-event" class="widefat arnl-fb-event" rows="9" readonly><?php echo esc_textarea( trim( $text ) ); ?></textarea>
			<p><button type="button" class="button arnl-copy-button" data-arnl-copy="#arnl-fb-event"><?php esc_html_e( 'Copy event details', 'ar-net-logger' ); ?></button> <span class="arnl-copy-status" aria-live="polite"></span></p>
		</details>
		<?php
	}

	/**
	 * The net's script, formatted and read-only, with this session's details filled in.
	 *
	 * @param ARNL_Occurrence $o          Session.
	 * @param bool            $can_manage Whether the user may edit the net.
	 */
	protected static function render_script_box( ARNL_Occurrence $o, $can_manage ) {
		$script = arnl_render_script( $o );
		$edit   = ARNL_Admin::url(
			'arnl-nets',
			array(
				'action' => 'edit',
				'id'     => $o->net_id,
			)
		) . '#arnl-script-row';
		if ( '' === $script ) {
			if ( $can_manage ) {
				echo '<div class="arnl-box arnl-script-box arnl-script-box--empty"><h2>' . esc_html__( 'Net script', 'ar-net-logger' ) . '</h2><p class="arnl-muted">' . esc_html__( 'This net has no script yet.', 'ar-net-logger' ) . ' <a href="' . esc_url( $edit ) . '">' . esc_html__( 'Write one, or start from the sample', 'ar-net-logger' ) . '</a></p></div>';
			}
			return;
		}
		?>
		<details class="arnl-box arnl-script-box" id="arnl-script" open>
			<summary>
				<?php esc_html_e( 'Net script', 'ar-net-logger' ); ?>
				<span class="arnl-script-links">
					<a href="<?php echo esc_url( self::script_url( $o ) ); ?>" target="arnl-script-<?php echo esc_attr( $o->key() ); ?>"><?php esc_html_e( 'Open in new window', 'ar-net-logger' ); ?></a>
					<?php if ( $can_manage ) : ?>
						| <a href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Edit', 'ar-net-logger' ); ?></a>
					<?php endif; ?>
				</span>
			</summary>
			<div class="arnl-script"><?php echo $script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by arnl_render_script(). ?></div>
		</details>
		<?php
	}

	/**
	 * URL of a session's net script on its own page (second screen or print).
	 *
	 * @param ARNL_Occurrence $o Session.
	 * @return string
	 */
	public static function script_url( ARNL_Occurrence $o ) {
		return add_query_arg(
			array(
				'action' => 'arnl_script',
				'event'  => $o->key(),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * A session's net script as a standalone, print-friendly page.
	 */
	public static function handle_script() {
		if ( ! current_user_can( ARNL_Install::CAP_LOG ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'ar-net-logger' ), 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view.
		$o      = self::occurrence( isset( $_GET['event'] ) ? sanitize_text_field( wp_unslash( $_GET['event'] ) ) : '' );
		$script = arnl_render_script( $o );
		$freq   = arnl_describe_frequency( $o->net, $o->frequency );
		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php echo esc_attr( get_option( 'blog_charset' ) ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( sprintf( /* translators: %s: net name. */ __( 'Net script: %s', 'ar-net-logger' ), $o->title() ) ); ?></title>
<style>
	body { margin: 0; padding: 1.5em; font: 18px/1.65 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1e1e1e; background: #fff; }
	.arnl-sheet { max-width: 46em; margin: 0 auto; }
	header { border-bottom: 2px solid #dcdcde; margin-bottom: 1em; }
	h1 { font-size: 1.5em; margin: 0 0 0.2em; }
	.arnl-meta { margin: 0 0 0.75em; color: #50575e; }
	.arnl-tools { float: right; display: flex; gap: 0.4em; }
	.arnl-tools button { font: inherit; font-size: 0.75em; padding: 0.2em 0.7em; cursor: pointer; }
	.arnl-script h2, .arnl-script h3, .arnl-script h4 { margin: 1.2em 0 0.4em; }
	.arnl-script blockquote { margin: 1em 0; padding: 0.5em 1em; border-left: 4px solid #2271b1; background: #f0f6fc; }
	.arnl-script-blank { display: inline-block; min-width: 4em; border-bottom: 2px dotted #996800; color: transparent; }
	@media print { .arnl-tools { display: none; } body { padding: 0; font-size: 13pt; } }
	@media (prefers-color-scheme: dark) { body { background: #1d2327; color: #f0f0f1; } .arnl-meta { color: #c3c4c7; } .arnl-script blockquote { background: #2c3338; } }
</style>
</head>
<body>
<div class="arnl-sheet">
	<header>
		<div class="arnl-tools">
			<button type="button" data-size="-1" aria-label="<?php esc_attr_e( 'Smaller text', 'ar-net-logger' ); ?>">A&minus;</button>
			<button type="button" data-size="1" aria-label="<?php esc_attr_e( 'Larger text', 'ar-net-logger' ); ?>">A+</button>
			<button type="button" onclick="window.print()"><?php esc_html_e( 'Print', 'ar-net-logger' ); ?></button>
		</div>
		<h1><?php echo esc_html( $o->title() ); ?></h1>
		<p class="arnl-meta">
			<?php echo esc_html( arnl_format_date( $o->date, 'l, ' . get_option( 'date_format' ) ) . ' · ' . ARNL_Calendar::time_range( $o ) ); ?>
			<?php if ( '' !== $freq ) : ?>
				<br><?php echo esc_html( $freq ); ?>
			<?php endif; ?>
			<?php if ( '' !== $o->ncs_callsign ) : ?>
				<br><?php echo esc_html( ARNL_Calendar::ncs_label( $o ) ); ?>
			<?php endif; ?>
		</p>
	</header>
	<main class="arnl-script">
		<?php
		if ( '' !== $script ) {
			echo $script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by arnl_render_script().
		} else {
			echo '<p>' . esc_html__( 'This net has no script yet.', 'ar-net-logger' ) . '</p>';
		}
		?>
	</main>
</div>
<script>
( function () {
	var size = 18;
	try {
		size = parseFloat( localStorage.getItem( 'arnlScriptSize' ) ) || size;
	} catch ( e ) {}
	function apply() {
		document.body.style.fontSize = size + 'px';
	}
	apply();
	Array.prototype.forEach.call( document.querySelectorAll( '[data-size]' ), function ( button ) {
		button.addEventListener( 'click', function () {
			size = Math.min( 40, Math.max( 12, size + 2 * parseInt( button.getAttribute( 'data-size' ), 10 ) ) );
			apply();
			try {
				localStorage.setItem( 'arnlScriptSize', size );
			} catch ( e ) {}
		} );
	} );
}() );
</script>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Session details box (NCS, topic, log notes).
	 *
	 * @param ARNL_Occurrence $o Session.
	 */
	protected static function render_session_box( ARNL_Occurrence $o ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="arnl-box arnl-session-box">
			<h2><?php esc_html_e( 'This session', 'ar-net-logger' ); ?></h2>
			<input type="hidden" name="action" value="arnl_update_session">
			<input type="hidden" name="event" value="<?php echo esc_attr( $o->key() ); ?>">
			<?php wp_nonce_field( 'arnl_update_session_' . $o->key() ); ?>
			<p>
				<label for="arnl-s-ncs"><?php esc_html_e( 'Net Control Station', 'ar-net-logger' ); ?></label>
				<span class="arnl-pair">
					<input type="text" id="arnl-s-ncs" name="ncs_callsign" class="arnl-callsign-input" value="<?php echo esc_attr( $o->ncs_callsign ); ?>" maxlength="20" placeholder="<?php esc_attr_e( 'Callsign', 'ar-net-logger' ); ?>" autocomplete="off" data-arnl-lookup="<?php echo esc_attr( wp_json_encode( array( 'name' => '#arnl-s-ncs-name' ) ) ); ?>">
					<label class="screen-reader-text" for="arnl-s-ncs-name"><?php esc_html_e( 'NCS name', 'ar-net-logger' ); ?></label>
					<input type="text" id="arnl-s-ncs-name" name="ncs_name" value="<?php echo esc_attr( $o->ncs_name ); ?>" maxlength="100" placeholder="<?php esc_attr_e( 'Name', 'ar-net-logger' ); ?>">
				</span>
			</p>
			<p>
				<label for="arnl-s-topic"><?php esc_html_e( 'Topic', 'ar-net-logger' ); ?></label>
				<input type="text" id="arnl-s-topic" name="topic" class="widefat" value="<?php echo esc_attr( $o->topic ); ?>" maxlength="255">
			</p>
			<p>
				<label for="arnl-s-notes"><?php esc_html_e( 'Log notes (private)', 'ar-net-logger' ); ?></label>
				<textarea id="arnl-s-notes" name="log_notes" class="widefat" rows="4" placeholder="<?php esc_attr_e( 'Conditions, traffic passed, announcements, problems...', 'ar-net-logger' ); ?>"><?php echo esc_textarea( $o->event ? $o->event['log_notes'] : '' ); ?></textarea>
			</p>
			<?php submit_button( __( 'Save Session Details', 'ar-net-logger' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Save a check-in from the NCS form.
	 */
	public static function handle_save_checkin() {
		if ( ! current_user_can( ARNL_Install::CAP_LOG ) ) {
			wp_die( esc_html__( 'You are not allowed to log check-ins.', 'ar-net-logger' ), 403 );
		}
		$key = isset( $_POST['event'] ) ? sanitize_text_field( wp_unslash( $_POST['event'] ) ) : '';
		check_admin_referer( 'arnl_save_checkin_' . $key );

		$o = self::occurrence( $key );
		$o = ARNL_Schedule::materialize( $o );
		if ( is_wp_error( $o ) ) {
			wp_die( esc_html( $o->get_error_message() ) );
		}
		$log_url = ARNL_Admin_Schedule::log_url( $o );

		$data = array();
		foreach ( array( 'callsign', 'name', 'location', 'station_type', 'relayed_by', 'traffic_precedence', 'traffic_count', 'status' ) as $field ) {
			$data[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		$data['comments'] = isset( $_POST['comments'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comments'] ) ) : '';
		foreach ( array( 'short_time', 'has_traffic', 'has_announcement', 'acknowledged' ) as $flag ) {
			$data[ $flag ] = empty( $_POST[ $flag ] ) ? 0 : 1;
		}
		$data['announcement'] = $data['has_announcement'] && isset( $_POST['announcement'] ) ? sanitize_textarea_field( wp_unslash( $_POST['announcement'] ) ) : '';
		$checkin_id           = isset( $_POST['checkin_id'] ) ? absint( $_POST['checkin_id'] ) : 0;

		$duplicate = ARNL_Checkins::find_duplicate( $o->event_id, $data['callsign'], $checkin_id );
		if ( $duplicate ) {
			ARNL_Admin::fail(
				$checkin_id ? add_query_arg( 'checkin', $checkin_id, $log_url ) : $log_url,
				/* translators: %s: callsign. */
				sprintf( __( '%s is already on the log for this session.', 'ar-net-logger' ), $duplicate['callsign'] ),
				$data
			);
		}

		if ( $checkin_id ) {
			$existing = ARNL_Checkins::get( $checkin_id );
			if ( ! $existing || $existing['event_id'] !== $o->event_id ) {
				wp_die( esc_html__( 'Check-in not found.', 'ar-net-logger' ) );
			}
			$result = ARNL_Checkins::update( $checkin_id, $data );
			$notice = 'checkin_updated';
		} else {
			unset( $data['status'] );
			$data   = self::fill_from_lookup( $data );
			$result = ARNL_Checkins::insert(
				array_merge(
					$data,
					array(
						'event_id' => $o->event_id,
						'source'   => 'admin',
						'status'   => 'approved',
						'user_id'  => get_current_user_id(),
					)
				)
			);
			$notice = 'checkin_saved';
		}
		if ( is_wp_error( $result ) ) {
			ARNL_Admin::fail( $checkin_id ? add_query_arg( 'checkin', $checkin_id, $log_url ) : $log_url, $result, $data );
		}
		ARNL_Admin::redirect( $log_url . '#arnl-checkin-form', $notice );
	}

	/**
	 * Fill an empty name / location from the station's previous check-in or the
	 * online lookup, for check-ins logged without picking a suggestion.
	 *
	 * @param array $data Check-in fields.
	 * @return array
	 */
	protected static function fill_from_lookup( array $data ) {
		if ( '' !== $data['name'] && '' !== $data['location'] ) {
			return $data;
		}
		$callsign = arnl_sanitize_callsign( $data['callsign'] );
		$station  = '' !== $callsign ? ARNL_Checkins::last_by_callsign( $callsign ) : null;
		if ( ! $station && '' !== $callsign ) {
			$station = ARNL_Callsign_Lookup::lookup( $callsign );
		}
		foreach ( array( 'name', 'location' ) as $field ) {
			if ( '' === $data[ $field ] && $station && ! empty( $station[ $field ] ) ) {
				$data[ $field ] = $station[ $field ];
			}
		}
		return $data;
	}

	/**
	 * Approve / reject / acknowledge / delete check-ins.
	 */
	public static function handle_checkin_action() {
		if ( ! current_user_can( ARNL_Install::CAP_LOG ) ) {
			wp_die( esc_html__( 'You are not allowed to log check-ins.', 'ar-net-logger' ), 403 );
		}
		$op = isset( $_GET['op'] ) ? sanitize_key( wp_unslash( $_GET['op'] ) ) : '';

		if ( 'approve_all' === $op ) {
			$event_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0;
			check_admin_referer( 'arnl_approve_all_' . $event_id );
			foreach ( ARNL_Checkins::for_event( $event_id, 'pending' ) as $c ) {
				ARNL_Checkins::update( $c['id'], array( 'status' => 'approved' ) );
			}
			$o = ARNL_Schedule::find( 'e' . $event_id );
			ARNL_Admin::redirect( $o ? ARNL_Admin_Schedule::log_url( $o ) : ARNL_Admin::url( 'arnl-schedule' ), 'checkins_approved' );
		}

		$id = isset( $_GET['checkin'] ) ? absint( $_GET['checkin'] ) : 0;
		check_admin_referer( 'arnl_checkin_action_' . $id );
		$checkin = ARNL_Checkins::get( $id );
		if ( ! $checkin ) {
			wp_die( esc_html__( 'Check-in not found.', 'ar-net-logger' ) );
		}
		$o      = ARNL_Schedule::find( 'e' . $checkin['event_id'] );
		$notice = 'checkin_changed';
		switch ( $op ) {
			case 'approve':
				ARNL_Checkins::update( $id, array( 'status' => 'approved' ) );
				break;
			case 'reject':
				ARNL_Checkins::update( $id, array( 'status' => 'rejected' ) );
				break;
			case 'ack':
			case 'unack':
				ARNL_Checkins::update( $id, array( 'acknowledged' => 'ack' === $op ? 1 : 0 ) );
				break;
			case 'delete':
				ARNL_Checkins::delete( $id );
				$notice = 'checkin_deleted';
				break;
		}
		ARNL_Admin::redirect( $o ? ARNL_Admin_Schedule::log_url( $o ) : ARNL_Admin::url( 'arnl-schedule' ), $notice );
	}

	/**
	 * Save NCS / topic / log notes from the Net Log.
	 */
	public static function handle_update_session() {
		if ( ! current_user_can( ARNL_Install::CAP_LOG ) ) {
			wp_die( esc_html__( 'You are not allowed to run nets.', 'ar-net-logger' ), 403 );
		}
		$key = isset( $_POST['event'] ) ? sanitize_text_field( wp_unslash( $_POST['event'] ) ) : '';
		check_admin_referer( 'arnl_update_session_' . $key );
		$o = ARNL_Schedule::materialize( self::occurrence( $key ) );
		if ( is_wp_error( $o ) ) {
			wp_die( esc_html( $o->get_error_message() ) );
		}
		$ncs      = isset( $_POST['ncs_callsign'] ) ? arnl_sanitize_callsign( sanitize_text_field( wp_unslash( $_POST['ncs_callsign'] ) ) ) : '';
		$ncs_name = isset( $_POST['ncs_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ncs_name'] ) ) : '';
		// Store blanks when the NCS matches the net default, so later changes to the default still apply.
		if ( '' === $ncs || ( $ncs === $o->net['ncs_callsign'] && $ncs_name === $o->net['ncs_name'] ) ) {
			$ncs      = '';
			$ncs_name = '';
		}
		$data   = array(
			'ncs_callsign' => $ncs,
			'ncs_name'     => $ncs_name,
			'topic'        => isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '',
			'log_notes'    => isset( $_POST['log_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['log_notes'] ) ) : '',
		);
		$result = ARNL_Events::update( $o->event_id, $data );
		if ( is_wp_error( $result ) ) {
			ARNL_Admin::fail( ARNL_Admin_Schedule::log_url( $o ), $result );
		}
		ARNL_Admin::redirect( ARNL_Admin_Schedule::log_url( $o ), 'event_saved' );
	}

	/**
	 * CSV export of a session's log.
	 */
	public static function handle_export() {
		if ( ! current_user_can( ARNL_Install::CAP_LOG ) ) {
			wp_die( esc_html__( 'You are not allowed to export logs.', 'ar-net-logger' ), 403 );
		}
		$event_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0;
		check_admin_referer( 'arnl_export_' . $event_id );
		$o = ARNL_Schedule::find( 'e' . $event_id );
		if ( ! $o ) {
			wp_die( esc_html__( 'Session not found.', 'ar-net-logger' ) );
		}

		$filename = sanitize_file_name( 'net-log-' . $o->net['slug'] . '-' . $o->date . '.csv' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM for spreadsheet apps.
		$types = arnl_station_types();
		$prec  = arnl_precedences();
		self::csv_row( $out, array( __( 'Net', 'ar-net-logger' ), $o->title(), __( 'Date', 'ar-net-logger' ), $o->date, __( 'NCS', 'ar-net-logger' ), $o->ncs_callsign, $o->ncs_name, __( 'Topic', 'ar-net-logger' ), $o->topic ) );
		self::csv_row(
			$out,
			array(
				'#',
				__( 'Time', 'ar-net-logger' ),
				__( 'Callsign', 'ar-net-logger' ),
				__( 'Name', 'ar-net-logger' ),
				__( 'Location', 'ar-net-logger' ),
				__( 'Station', 'ar-net-logger' ),
				__( 'Short time', 'ar-net-logger' ),
				__( 'Traffic', 'ar-net-logger' ),
				__( 'Precedence', 'ar-net-logger' ),
				__( 'Count', 'ar-net-logger' ),
				__( 'Relayed by', 'ar-net-logger' ),
				__( 'Announcement', 'ar-net-logger' ),
				__( 'Announcement text', 'ar-net-logger' ),
				__( 'Comments', 'ar-net-logger' ),
				__( 'Source', 'ar-net-logger' ),
				__( 'Status', 'ar-net-logger' ),
				__( 'Acknowledged', 'ar-net-logger' ),
			)
		);
		$n = 0;
		foreach ( ARNL_Checkins::for_event( $event_id ) as $c ) {
			self::csv_row(
				$out,
				array(
					'rejected' === $c['status'] ? '' : ++$n,
					$c['checkin_time'],
					$c['callsign'],
					$c['name'],
					$c['location'],
					arnl_label( $types, $c['station_type'] ),
					$c['short_time'] ? 'Y' : '',
					$c['has_traffic'] ? 'Y' : '',
					$c['has_traffic'] ? arnl_label( $prec, $c['traffic_precedence'] ) : '',
					$c['has_traffic'] ? max( 1, $c['traffic_count'] ) : '',
					$c['relayed_by'],
					$c['has_announcement'] ? 'Y' : '',
					$c['announcement'],
					$c['comments'],
					$c['source'],
					$c['status'],
					$c['acknowledged'] ? 'Y' : '',
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Write a CSV row, neutralising spreadsheet formulas.
	 *
	 * @param resource $out    Handle.
	 * @param array    $fields Fields.
	 */
	protected static function csv_row( $out, array $fields ) {
		foreach ( $fields as $i => $value ) {
			$value        = (string) $value;
			$fields[ $i ] = '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ? "'" . $value : $value;
		}
		fputcsv( $out, $fields, ',', '"', '\\' );
	}

	/**
	 * AJAX: previous name / location for a callsign, or the online lookup for new stations.
	 */
	public static function ajax_lookup() {
		check_ajax_referer( 'arnl_lookup', 'nonce' );
		if ( ! current_user_can( ARNL_Install::CAP_LOG ) ) {
			wp_send_json_error( null, 403 );
		}
		$callsign = isset( $_GET['callsign'] ) ? arnl_sanitize_callsign( sanitize_text_field( wp_unslash( $_GET['callsign'] ) ) ) : '';
		$last     = '' !== $callsign ? ARNL_Checkins::last_by_callsign( $callsign ) : null;
		if ( $last ) {
			wp_send_json_success(
				array(
					'callsign'     => $last['callsign'],
					'name'         => $last['name'],
					'location'     => $last['location'],
					'station_type' => $last['station_type'],
					'source'       => 'log',
				)
			);
		}
		wp_send_json_success( '' !== $callsign ? ARNL_Callsign_Lookup::lookup( $callsign ) : null );
	}

	/**
	 * AJAX: background refresh of the Net Log's check-in list and counts.
	 *
	 * The page sends the fingerprint it shows; the list is only re-rendered
	 * and returned when check-ins (e.g. from the website) changed.
	 */
	public static function ajax_refresh() {
		check_ajax_referer( 'arnl_refresh_log', 'nonce' );
		if ( ! current_user_can( ARNL_Install::CAP_LOG ) ) {
			wp_send_json_error( null, 403 );
		}
		$key = isset( $_GET['event'] ) ? sanitize_text_field( wp_unslash( $_GET['event'] ) ) : '';
		$o   = ARNL_Schedule::find( $key );
		if ( ! $o ) {
			wp_send_json_error( array( 'message' => __( 'Session not found.', 'ar-net-logger' ) ), 404 );
		}
		$checkins  = $o->is_virtual() ? array() : ARNL_Checkins::for_event( $o->event_id );
		$signature = self::signature( $o, $checkins );
		$since     = isset( $_GET['since'] ) ? sanitize_key( wp_unslash( $_GET['since'] ) ) : '';
		if ( $since === $signature ) {
			wp_send_json_success(
				array(
					'changed'   => false,
					'signature' => $signature,
				)
			);
		}

		$stats = ARNL_Checkins::stats( $checkins );
		ob_start();
		self::render_stats( $stats );
		$stats_html = ob_get_clean();
		ob_start();
		self::render_checkins_table( $o, $checkins, $stats );
		$table_html = ob_get_clean();

		wp_send_json_success(
			array(
				'changed'   => true,
				'signature' => $signature,
				'event'     => $o->key(),
				'stats'     => $stats_html,
				'table'     => $table_html,
			)
		);
	}

	/**
	 * AJAX: type-ahead suggestions for a callsign field.
	 *
	 * Stations from previous check-ins (callsign or name match), plus the
	 * online lookup when the text is a complete callsign never logged before.
	 */
	public static function ajax_search() {
		check_ajax_referer( 'arnl_lookup', 'nonce' );
		if ( ! current_user_can( ARNL_Install::CAP_LOG ) ) {
			wp_send_json_error( null, 403 );
		}
		$term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( array() );
		}

		$results = array();
		foreach ( ARNL_Checkins::search_stations( $term, 8 ) as $station ) {
			$station['source'] = 'log';
			$results[]         = $station;
		}

		$base = arnl_base_callsign( $term );
		if ( strlen( $base ) >= 4 && arnl_looks_like_callsign( $base ) && ! in_array( $base, wp_list_pluck( $results, 'callsign' ), true ) ) {
			$online = ARNL_Callsign_Lookup::lookup( $base );
			if ( $online ) {
				array_unshift( $results, $online );
			}
		}
		wp_send_json_success( $results );
	}
}
