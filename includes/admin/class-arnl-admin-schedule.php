<?php
/**
 * Schedule admin: session list, calendar, session overrides / special sessions.
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Schedule screens.
 */
class ARNL_Admin_Schedule {

	/**
	 * Register handlers.
	 */
	public static function init() {
		add_action( 'admin_post_arnl_save_event', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_arnl_event_status', array( __CLASS__, 'handle_status' ) );
		add_action( 'admin_post_arnl_delete_event', array( __CLASS__, 'handle_delete' ) );
	}

	/**
	 * Net Log URL of a session.
	 *
	 * @param ARNL_Occurrence $o Session.
	 * @return string
	 */
	public static function log_url( ARNL_Occurrence $o ) {
		return ARNL_Admin::url(
			'arnl-schedule',
			array(
				'action' => 'log',
				'event'  => $o->key(),
			)
		);
	}

	/**
	 * Edit URL of a session.
	 *
	 * @param ARNL_Occurrence $o Session.
	 * @return string
	 */
	public static function edit_url( ARNL_Occurrence $o ) {
		return ARNL_Admin::url(
			'arnl-schedule',
			array(
				'action' => 'edit',
				'event'  => $o->key(),
			)
		);
	}

	/**
	 * Status change URL.
	 *
	 * @param ARNL_Occurrence $o      Session.
	 * @param string          $status New status.
	 * @return string
	 */
	public static function status_url( ARNL_Occurrence $o, $status ) {
		return ARNL_Admin::action_url(
			'arnl_event_status',
			array(
				'event'  => $o->key(),
				'status' => $status,
			),
			'arnl_event_status_' . $o->key()
		);
	}

	/**
	 * Screen router.
	 */
	public static function render() {
		if ( ! current_user_can( ARNL_Install::CAP_LOG ) ) {
			wp_die( esc_html__( 'You are not allowed to view the net schedule.', 'ar-net-logger' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( 'log' === $action ) {
			ARNL_Admin_Log::render();
			return;
		}
		if ( in_array( $action, array( 'edit', 'new' ), true ) ) {
			if ( ! current_user_can( ARNL_Install::CAP_MANAGE_NETS ) ) {
				wp_die( esc_html__( 'You are not allowed to edit the schedule.', 'ar-net-logger' ) );
			}
			self::render_form();
			return;
		}
		self::render_list();
	}

	/**
	 * Session list.
	 */
	protected static function render_list() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- list filters.
		$net_id = isset( $_GET['net'] ) ? absint( $_GET['net'] ) : 0;
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$from   = isset( $_GET['from'] ) ? arnl_sanitize_date( sanitize_text_field( wp_unslash( $_GET['from'] ) ) ) : '';
		$to     = isset( $_GET['to'] ) ? arnl_sanitize_date( sanitize_text_field( wp_unslash( $_GET['to'] ) ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$today = arnl_now();
		$from  = '' !== $from ? $from : $today->format( 'Y-m-d' );
		$to    = '' !== $to && $to >= $from ? $to : ( new DateTimeImmutable( $from, wp_timezone() ) )->modify( '+30 days' )->format( 'Y-m-d' );

		$status      = array_key_exists( $status, arnl_event_statuses() ) ? $status : '';
		$occurrences = ARNL_Schedule::occurrences(
			array(
				'from'       => $from,
				'to'         => $to,
				'net'        => $net_id ? array( $net_id ) : array(),
				'net_status' => 'any',
				'status'     => $status ? array( $status ) : array(),
			)
		);
		$past        = $to < $today->format( 'Y-m-d' );
		if ( $past ) {
			$occurrences = array_reverse( $occurrences );
		}

		$ids = array();
		foreach ( $occurrences as $o ) {
			if ( $o->event_id ) {
				$ids[] = $o->event_id;
			}
		}
		$counts     = ARNL_Checkins::counts( $ids );
		$nets       = ARNL_Nets::query( array( 'status' => 'any' ) );
		$can_manage = current_user_can( ARNL_Install::CAP_MANAGE_NETS );
		$base       = array_filter( array( 'net' => $net_id ) );
		?>
		<div class="wrap arnl-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Net Schedule', 'ar-net-logger' ); ?></h1>
			<?php if ( $can_manage ) : ?>
				<a href="<?php echo esc_url( ARNL_Admin::url( 'arnl-schedule', array_merge( $base, array( 'action' => 'new' ) ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add Special Session', 'ar-net-logger' ); ?></a>
				<a href="<?php echo esc_url( ARNL_Admin::url( 'arnl-nets', array( 'action' => 'new' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Net', 'ar-net-logger' ); ?></a>
			<?php endif; ?>
			<?php if ( ARNL_Settings::get( 'ics_enabled' ) ) : ?>
				<a href="
				<?php
				echo esc_url(
					arnl_ics_url(
						array(
							'net'      => $net_id ? (string) $net_id : '',
							'download' => 1,
						)
					)
				);
				?>
							" class="page-title-action"><?php esc_html_e( 'Export .ics', 'ar-net-logger' ); ?></a>
			<?php endif; ?>
			<hr class="wp-header-end">

			<?php if ( ! $nets ) : ?>
				<div class="notice notice-info inline"><p>
					<?php esc_html_e( 'No nets yet. Create a net with its regular day and time, and its sessions will appear here and on the calendar.', 'ar-net-logger' ); ?>
					<?php if ( $can_manage ) : ?>
						<a href="<?php echo esc_url( ARNL_Admin::url( 'arnl-nets', array( 'action' => 'new' ) ) ); ?>"><?php esc_html_e( 'Add your first net', 'ar-net-logger' ); ?></a>
					<?php endif; ?>
				</p></div>
			<?php endif; ?>

			<form method="get" class="arnl-filters">
				<input type="hidden" name="page" value="arnl-schedule">
				<label class="screen-reader-text" for="arnl-filter-net"><?php esc_html_e( 'Net', 'ar-net-logger' ); ?></label>
				<select name="net" id="arnl-filter-net">
					<option value=""><?php esc_html_e( 'All nets', 'ar-net-logger' ); ?></option>
					<?php foreach ( $nets as $net ) : ?>
						<option value="<?php echo (int) $net['id']; ?>" <?php selected( $net_id, $net['id'] ); ?>><?php echo esc_html( $net['name'] . ( 'active' === $net['status'] ? '' : ' (' . __( 'inactive', 'ar-net-logger' ) . ')' ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="arnl-filter-from"><?php esc_html_e( 'From', 'ar-net-logger' ); ?></label>
				<input type="date" name="from" id="arnl-filter-from" value="<?php echo esc_attr( $from ); ?>">
				<label for="arnl-filter-to"><?php esc_html_e( 'to', 'ar-net-logger' ); ?></label>
				<input type="date" name="to" id="arnl-filter-to" value="<?php echo esc_attr( $to ); ?>">
				<label class="screen-reader-text" for="arnl-filter-status"><?php esc_html_e( 'Status', 'ar-net-logger' ); ?></label>
				<?php ARNL_Admin::select( 'status', array( '' => __( 'Any status', 'ar-net-logger' ) ) + arnl_event_statuses(), $status, array( 'id' => 'arnl-filter-status' ) ); ?>
				<?php submit_button( __( 'Filter', 'ar-net-logger' ), 'secondary', '', false ); ?>
				<span class="arnl-quick-ranges">
					<a href="<?php echo esc_url( ARNL_Admin::url( 'arnl-schedule', $base ) ); ?>"><?php esc_html_e( 'Upcoming', 'ar-net-logger' ); ?></a> |
					<a href="
					<?php
					echo esc_url(
						ARNL_Admin::url(
							'arnl-schedule',
							array_merge(
								$base,
								array(
									'from' => $today->modify( '-30 days' )->format( 'Y-m-d' ),
									'to'   => $today->modify( '-1 day' )->format( 'Y-m-d' ),
								)
							)
						)
					);
					?>
								"><?php esc_html_e( 'Past 30 days', 'ar-net-logger' ); ?></a>
				</span>
			</form>

			<table class="widefat striped arnl-schedule-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Date', 'ar-net-logger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Time', 'ar-net-logger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Net', 'ar-net-logger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Topic', 'ar-net-logger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Net Control', 'ar-net-logger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'ar-net-logger' ); ?></th>
						<th scope="col" class="num"><?php esc_html_e( 'Check-ins', 'ar-net-logger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'ar-net-logger' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $occurrences ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No sessions in this period.', 'ar-net-logger' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $occurrences as $o ) : ?>
					<?php
					$count    = isset( $counts[ $o->event_id ] ) ? $counts[ $o->event_id ] : array(
						'approved' => 0,
						'pending'  => 0,
					);
					$is_today = $o->date === $today->format( 'Y-m-d' );
					?>
					<tr class="<?php echo $is_today ? 'arnl-row-today' : ''; ?> <?php echo $o->is_cancelled() ? 'arnl-row-cancelled' : ''; ?>">
						<td>
							<strong><?php echo esc_html( arnl_format_date( $o->date, 'D, ' . get_option( 'date_format' ) ) ); ?></strong>
							<?php if ( $is_today ) : ?>
								<span class="arnl-badge arnl-badge--today"><?php esc_html_e( 'Today', 'ar-net-logger' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( arnl_format_time( $o->start_time ) . ' – ' . arnl_format_time( $o->end_time ) ); ?></td>
						<td>
							<span class="arnl-swatch" style="--arnl-color: <?php echo esc_attr( $o->net['color'] ); ?>" aria-hidden="true"></span>
							<?php echo esc_html( $o->title() ); ?>
							<?php if ( $o->is_rescheduled() ) : ?>
								<br><small class="arnl-muted">
									<?php
									/* translators: %s: original date. */
									echo esc_html( sprintf( __( 'Rescheduled from %s', 'ar-net-logger' ), arnl_format_date( $o->occurrence_date ) . ' ' . arnl_format_time( $o->net['start_time'] ) ) );
									?>
								</small>
							<?php elseif ( $o->is_one_off() ) : ?>
								<br><small class="arnl-muted"><?php esc_html_e( 'Special session', 'ar-net-logger' ); ?></small>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $o->topic ); ?></td>
						<td><?php echo esc_html( trim( $o->ncs_callsign . ( '' !== $o->ncs_name ? ' – ' . $o->ncs_name : '' ) ) ); ?></td>
						<td><?php echo ARNL_Admin::status_badge( $o->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></td>
						<td class="num">
							<?php echo (int) $count['approved']; ?>
							<?php if ( $count['pending'] ) : ?>
								<span class="arnl-badge arnl-badge--pending" title="<?php esc_attr_e( 'Pending review', 'ar-net-logger' ); ?>">+<?php echo (int) $count['pending']; ?></span>
							<?php endif; ?>
						</td>
						<td class="arnl-actions">
							<a class="button button-small button-primary" href="<?php echo esc_url( self::log_url( $o ) ); ?>"><?php esc_html_e( 'Net Log', 'ar-net-logger' ); ?></a>
							<?php if ( $can_manage ) : ?>
								<a class="button button-small" href="<?php echo esc_url( self::edit_url( $o ) ); ?>"><?php esc_html_e( 'Edit', 'ar-net-logger' ); ?></a>
							<?php endif; ?>
							<?php if ( $o->is_cancelled() ) : ?>
								<a class="button button-small" href="<?php echo esc_url( self::status_url( $o, 'scheduled' ) ); ?>"><?php esc_html_e( 'Restore', 'ar-net-logger' ); ?></a>
							<?php elseif ( 'scheduled' === $o->status ) : ?>
								<a class="button button-small arnl-confirm" data-arnl-confirm="<?php esc_attr_e( 'Cancel this session? It will show as cancelled on the calendar.', 'ar-net-logger' ); ?>" href="<?php echo esc_url( self::status_url( $o, 'cancelled' ) ); ?>"><?php esc_html_e( 'Cancel', 'ar-net-logger' ); ?></a>
							<?php endif; ?>
							<?php if ( $can_manage && ! $o->is_virtual() ) : ?>
								<a class="button button-small button-link-delete arnl-confirm" data-arnl-confirm="<?php esc_attr_e( 'Delete this session record and all of its check-ins? Regular sessions return to the net\'s default details.', 'ar-net-logger' ); ?>" href="<?php echo esc_url( ARNL_Admin::action_url( 'arnl_delete_event', array( 'event' => $o->event_id ), 'arnl_delete_event_' . $o->event_id ) ); ?>"><?php esc_html_e( 'Delete', 'ar-net-logger' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">
				<?php esc_html_e( 'Sessions are generated from each net\'s regular schedule. Edit a session to change its date, time, topic or Net Control for that date only; open its Net Log to record check-ins.', 'ar-net-logger' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Admin calendar.
	 */
	public static function render_calendar() {
		if ( ! current_user_can( ARNL_Install::CAP_LOG ) ) {
			wp_die( esc_html__( 'You are not allowed to view the net schedule.', 'ar-net-logger' ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation.
		$month  = isset( $_GET['arnl_month'] ) ? sanitize_text_field( wp_unslash( $_GET['arnl_month'] ) ) : '';
		$net_id = isset( $_GET['net'] ) ? absint( $_GET['net'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! preg_match( '/^(\d{4})-(\d{2})$/', $month, $m ) || (int) $m[2] < 1 || (int) $m[2] > 12 ) {
			$month = arnl_now()->format( 'Y-m' );
		}
		$first = new DateTimeImmutable( $month . '-01', wp_timezone() );
		$args  = array_filter( array( 'net' => $net_id ) );

		$occurrences = ARNL_Schedule::occurrences(
			array(
				'from'       => $first->format( 'Y-m-d' ),
				'to'         => $first->format( 'Y-m-t' ),
				'net'        => $net_id ? array( $net_id ) : array(),
				'net_status' => 'any',
			)
		);
		$nets        = ARNL_Nets::query( array( 'status' => 'any' ) );
		?>
		<div class="wrap arnl-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Net Calendar', 'ar-net-logger' ); ?></h1>
			<hr class="wp-header-end">
			<form method="get" class="arnl-filters">
				<input type="hidden" name="page" value="arnl-calendar">
				<input type="hidden" name="arnl_month" value="<?php echo esc_attr( $month ); ?>">
				<label class="screen-reader-text" for="arnl-cal-net"><?php esc_html_e( 'Net', 'ar-net-logger' ); ?></label>
				<select name="net" id="arnl-cal-net" onchange="this.form.submit()">
					<option value=""><?php esc_html_e( 'All nets', 'ar-net-logger' ); ?></option>
					<?php foreach ( $nets as $net ) : ?>
						<option value="<?php echo (int) $net['id']; ?>" <?php selected( $net_id, $net['id'] ); ?>><?php echo esc_html( $net['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<noscript><?php submit_button( __( 'Filter', 'ar-net-logger' ), 'secondary', '', false ); ?></noscript>
			</form>
			<?php
			$calendar = ARNL_Calendar::month(
				$occurrences,
				(int) $first->format( 'Y' ),
				(int) $first->format( 'n' ),
				array(
					'link'     => array( __CLASS__, 'log_url' ),
					'prev_url' => ARNL_Admin::url( 'arnl-calendar', array_merge( $args, array( 'arnl_month' => $first->modify( '-1 month' )->format( 'Y-m' ) ) ) ),
					'next_url' => ARNL_Admin::url( 'arnl-calendar', array_merge( $args, array( 'arnl_month' => $first->modify( '+1 month' )->format( 'Y-m' ) ) ) ),
				)
			);
			echo $calendar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the renderer.
			?>
			<p class="description"><?php esc_html_e( 'Click a session to open its Net Log. Add the calendar to any page with the [arnl_calendar] shortcode.', 'ar-net-logger' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Add / edit a session (override for one date, or a special session).
	 */
	protected static function render_form() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- routing only.
		$key    = isset( $_GET['event'] ) ? sanitize_text_field( wp_unslash( $_GET['event'] ) ) : '';
		$net_id = isset( $_GET['net'] ) ? absint( $_GET['net'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$o = null;
		if ( '' !== $key ) {
			$o = ARNL_Schedule::find( $key );
			if ( ! $o ) {
				wp_die( esc_html__( 'Session not found.', 'ar-net-logger' ) );
			}
		}
		$nets = ARNL_Nets::query( array( 'status' => 'any' ) );
		if ( ! $nets ) {
			wp_die( esc_html__( 'Create a net first.', 'ar-net-logger' ) );
		}

		$values = array(
			'net_id'       => $o ? $o->net_id : ( $net_id ? $net_id : (int) key( $nets ) ),
			'event_date'   => $o ? $o->date : arnl_today(),
			'start_time'   => $o ? $o->start_time : '',
			'end_time'     => $o ? $o->end_time : '',
			'topic'        => $o ? $o->topic : '',
			'ncs_callsign' => $o && $o->event ? $o->event['ncs_callsign'] : '',
			'ncs_name'     => $o && $o->event ? $o->event['ncs_name'] : '',
			'frequency'    => $o && $o->event ? $o->event['frequency'] : '',
			'notes'        => $o ? $o->notes : '',
			'status'       => $o ? $o->status : 'scheduled',
		);
		$failed = ARNL_Admin::failed_data();
		if ( $failed ) {
			$values = array_merge( $values, $failed );
		}
		$net = isset( $nets[ $values['net_id'] ] ) ? $nets[ $values['net_id'] ] : reset( $nets );
		?>
		<div class="wrap arnl-wrap">
			<h1><?php echo esc_html( $o ? __( 'Edit Session', 'ar-net-logger' ) : __( 'Add Special Session', 'ar-net-logger' ) ); ?></h1>
			<?php if ( $o && '' !== $o->occurrence_date ) : ?>
				<div class="notice notice-info inline"><p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: net name, 2: date, 3: regular schedule. */
							__( 'This is the %1$s session regularly scheduled for %2$s (%3$s). Changes here apply to this date only.', 'ar-net-logger' ),
							$o->title(),
							arnl_format_date( $o->occurrence_date, 'l, ' . get_option( 'date_format' ) ),
							arnl_describe_schedule( $o->net )
						)
					);
					?>
				</p></div>
			<?php elseif ( ! $o ) : ?>
				<p><?php esc_html_e( 'Add a session outside a net\'s regular schedule, e.g. a special event net, a drill or a make-up session. To change a regular session, use Edit on the schedule instead.', 'ar-net-logger' ); ?></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="arnl_save_event">
				<input type="hidden" name="event" value="<?php echo esc_attr( $key ); ?>">
				<?php wp_nonce_field( 'arnl_save_event_' . $key ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="arnl-event-net"><?php esc_html_e( 'Net', 'ar-net-logger' ); ?></label></th>
						<td>
							<?php if ( $o ) : ?>
								<strong><?php echo esc_html( $o->title() ); ?></strong>
							<?php else : ?>
								<select name="net_id" id="arnl-event-net">
									<?php foreach ( $nets as $n ) : ?>
										<option value="<?php echo (int) $n['id']; ?>" <?php selected( (int) $values['net_id'], $n['id'] ); ?>><?php echo esc_html( $n['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-event-date"><?php esc_html_e( 'Date', 'ar-net-logger' ); ?></label></th>
						<td><input type="date" id="arnl-event-date" name="event_date" value="<?php echo esc_attr( $values['event_date'] ); ?>" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-event-start"><?php esc_html_e( 'Time', 'ar-net-logger' ); ?></label></th>
						<td>
							<input type="time" id="arnl-event-start" name="start_time" value="<?php echo esc_attr( substr( $values['start_time'], 0, 5 ) ); ?>">
							<?php esc_html_e( 'to', 'ar-net-logger' ); ?>
							<label class="screen-reader-text" for="arnl-event-end"><?php esc_html_e( 'End time', 'ar-net-logger' ); ?></label>
							<input type="time" id="arnl-event-end" name="end_time" value="<?php echo esc_attr( substr( $values['end_time'], 0, 5 ) ); ?>">
							<p class="description"><?php esc_html_e( 'Leave blank to use the net\'s regular time.', 'ar-net-logger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-event-topic"><?php esc_html_e( 'Topic', 'ar-net-logger' ); ?></label></th>
						<td><input type="text" id="arnl-event-topic" name="topic" class="large-text" value="<?php echo esc_attr( $values['topic'] ); ?>" maxlength="255" placeholder="<?php esc_attr_e( 'e.g. Winter antenna maintenance, SET drill, Swap & shop', 'ar-net-logger' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-event-ncs"><?php esc_html_e( 'Net Control Station', 'ar-net-logger' ); ?></label></th>
						<td>
							<input type="text" id="arnl-event-ncs" name="ncs_callsign" class="arnl-callsign-input" value="<?php echo esc_attr( $values['ncs_callsign'] ); ?>" maxlength="20" placeholder="<?php echo esc_attr( '' !== $net['ncs_callsign'] ? $net['ncs_callsign'] : __( 'Callsign', 'ar-net-logger' ) ); ?>">
							<label class="screen-reader-text" for="arnl-event-ncs-name"><?php esc_html_e( 'NCS name', 'ar-net-logger' ); ?></label>
							<input type="text" id="arnl-event-ncs-name" name="ncs_name" value="<?php echo esc_attr( $values['ncs_name'] ); ?>" maxlength="100" placeholder="<?php echo esc_attr( '' !== $net['ncs_name'] ? $net['ncs_name'] : __( 'Name', 'ar-net-logger' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Leave blank to use the net\'s default Net Control Station.', 'ar-net-logger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-event-frequency"><?php esc_html_e( 'Frequency', 'ar-net-logger' ); ?></label></th>
						<td>
							<input type="text" id="arnl-event-frequency" name="frequency" class="regular-text" value="<?php echo esc_attr( $values['frequency'] ); ?>" maxlength="100" placeholder="<?php echo esc_attr( $net['frequency'] ); ?>">
							<p class="description"><?php esc_html_e( 'Only if this session meets on a different frequency (e.g. back-up repeater).', 'ar-net-logger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-event-notes"><?php esc_html_e( 'Public notes', 'ar-net-logger' ); ?></label></th>
						<td><textarea id="arnl-event-notes" name="notes" class="large-text" rows="4"><?php echo esc_textarea( $values['notes'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-event-status"><?php esc_html_e( 'Status', 'ar-net-logger' ); ?></label></th>
						<td><?php ARNL_Admin::select( 'status', arnl_event_statuses(), $values['status'], array( 'id' => 'arnl-event-status' ) ); ?></td>
					</tr>
				</table>
				<?php submit_button( $o ? __( 'Save Session', 'ar-net-logger' ) : __( 'Add Session', 'ar-net-logger' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Save a session.
	 */
	public static function handle_save() {
		if ( ! current_user_can( ARNL_Install::CAP_MANAGE_NETS ) ) {
			wp_die( esc_html__( 'You are not allowed to edit the schedule.', 'ar-net-logger' ), 403 );
		}
		$key = isset( $_POST['event'] ) ? sanitize_text_field( wp_unslash( $_POST['event'] ) ) : '';
		check_admin_referer( 'arnl_save_event_' . $key );

		$data = array();
		foreach ( array( 'event_date', 'start_time', 'end_time', 'topic', 'ncs_callsign', 'ncs_name', 'frequency', 'status' ) as $field ) {
			$data[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		$data['notes'] = isset( $_POST['notes'] ) ? wp_kses_post( wp_unslash( $_POST['notes'] ) ) : '';

		if ( '' !== $key ) {
			$o = ARNL_Schedule::find( $key );
			if ( ! $o ) {
				wp_die( esc_html__( 'Session not found.', 'ar-net-logger' ) );
			}
			$form = ARNL_Admin::url(
				'arnl-schedule',
				array(
					'action' => 'edit',
					'event'  => $key,
				)
			);
			$o    = ARNL_Schedule::materialize( $o );
			if ( is_wp_error( $o ) ) {
				ARNL_Admin::fail( $form, $o, $data );
			}
			$result = ARNL_Events::update( $o->event_id, self::with_status_times( $data, $o->event ) );
			$notice = 'event_saved';
			$net_id = $o->net_id;
		} else {
			$net_id = isset( $_POST['net_id'] ) ? absint( $_POST['net_id'] ) : 0;
			$form   = ARNL_Admin::url(
				'arnl-schedule',
				array(
					'action' => 'new',
					'net'    => $net_id,
				)
			);
			$result = ARNL_Events::insert( array_merge( self::with_status_times( $data, null ), array( 'net_id' => $net_id ) ) );
			$notice = 'event_created';
		}

		if ( is_wp_error( $result ) ) {
			ARNL_Admin::fail( $form, $result, $data );
		}

		$date = arnl_sanitize_date( $data['event_date'] );
		ARNL_Admin::redirect(
			ARNL_Admin::url(
				'arnl-schedule',
				array_filter(
					array(
						'net'  => $net_id,
						'from' => $date && $date < arnl_today() ? $date : '',
					)
				)
			),
			$notice
		);
	}

	/**
	 * Stamp opened / closed times when the status changes.
	 *
	 * @param array      $data    New values (with status).
	 * @param array|null $current Current row.
	 * @return array
	 */
	protected static function with_status_times( array $data, $current ) {
		$now = current_time( 'mysql' );
		if ( 'active' === $data['status'] && ( ! $current || '' === $current['opened_at'] ) ) {
			$data['opened_at'] = $now;
		}
		if ( 'completed' === $data['status'] && ( ! $current || '' === $current['closed_at'] ) ) {
			$data['closed_at'] = $now;
			if ( ! $current || '' === $current['opened_at'] ) {
				$data['opened_at'] = $now;
			}
		}
		return $data;
	}

	/**
	 * Change a session's status (open / close / cancel / restore).
	 */
	public static function handle_status() {
		$key = isset( $_GET['event'] ) ? sanitize_text_field( wp_unslash( $_GET['event'] ) ) : '';
		check_admin_referer( 'arnl_event_status_' . $key );
		if ( ! current_user_can( ARNL_Install::CAP_LOG ) ) {
			wp_die( esc_html__( 'You are not allowed to run nets.', 'ar-net-logger' ), 403 );
		}
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		if ( ! array_key_exists( $status, arnl_event_statuses() ) ) {
			wp_die( esc_html__( 'Unknown status.', 'ar-net-logger' ) );
		}
		$o = ARNL_Schedule::find( $key );
		$o = $o ? ARNL_Schedule::materialize( $o ) : null;
		if ( ! $o || is_wp_error( $o ) ) {
			wp_die( esc_html__( 'Session not found.', 'ar-net-logger' ) );
		}

		$data = self::with_status_times( array( 'status' => $status ), $o->event );
		if ( 'active' === $status && 'completed' === $o->status ) {
			$data['closed_at'] = ''; // Re-opened.
		}
		ARNL_Events::update( $o->event_id, $data );

		$back = wp_get_referer();
		$back = $back ? remove_query_arg( array( 'arnl_notice', 'arnl_error', 'event' ), $back ) : ARNL_Admin::url( 'arnl-schedule' );
		// Status buttons on the Net Log keep you on the (now stored) session.
		if ( false !== strpos( $back, 'action=log' ) ) {
			$back = self::log_url( ARNL_Schedule::find( 'e' . $o->event_id ) );
		}
		ARNL_Admin::redirect( $back, 'event_' . $status );
	}

	/**
	 * Delete a stored session (and its check-ins).
	 */
	public static function handle_delete() {
		$id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0;
		check_admin_referer( 'arnl_delete_event_' . $id );
		if ( ! current_user_can( ARNL_Install::CAP_MANAGE_NETS ) ) {
			wp_die( esc_html__( 'You are not allowed to edit the schedule.', 'ar-net-logger' ), 403 );
		}
		$event = ARNL_Events::get( $id );
		ARNL_Events::delete( $id );
		ARNL_Admin::redirect( ARNL_Admin::url( 'arnl-schedule', $event ? array( 'net' => $event['net_id'] ) : array() ), 'event_deleted' );
	}
}
