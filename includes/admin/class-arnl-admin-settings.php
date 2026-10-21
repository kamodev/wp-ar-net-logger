<?php
/**
 * Settings screen and shortcode / feed reference.
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen.
 */
class ARNL_Admin_Settings {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the option.
	 */
	public static function register() {
		register_setting(
			'arnl_settings',
			ARNL_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'ARNL_Settings', 'sanitize' ),
				'default'           => ARNL_Settings::defaults(),
			)
		);
	}

	/**
	 * Render the screen.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'ar-net-logger' ) );
		}
		$s    = ARNL_Settings::all();
		$name = ARNL_Settings::OPTION;
		?>
		<div class="wrap arnl-wrap">
			<h1><?php esc_html_e( 'Net Logger Settings', 'ar-net-logger' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'arnl_settings' ); ?>

				<h2 class="title"><?php esc_html_e( 'Online check-ins', 'ar-net-logger' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Website check-ins', 'ar-net-logger' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[public_checkins]" value="1" <?php checked( $s['public_checkins'] ); ?>> <?php esc_html_e( 'Let stations check in to a net from the website', 'ar-net-logger' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[require_login]" value="1" <?php checked( $s['require_login'] ); ?>> <?php esc_html_e( 'Only logged-in users can check in', 'ar-net-logger' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[auto_approve]" value="1" <?php checked( $s['auto_approve'] ); ?>> <?php esc_html_e( 'Approve website check-ins automatically (otherwise Net Control approves them on the Net Log)', 'ar-net-logger' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[validate_callsign]" value="1" <?php checked( $s['validate_callsign'] ); ?>> <?php esc_html_e( 'Require a valid amateur radio callsign format (untick to also accept e.g. GMRS or SWL identifiers)', 'ar-net-logger' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Check-in window', 'ar-net-logger' ); ?></th>
						<td>
							<?php
							printf(
								/* translators: 1: minutes-before input, 2: minutes-after input. */
								esc_html__( 'Open %1$s minutes before the start until %2$s minutes after the scheduled end.', 'ar-net-logger' ),
								'<input type="number" min="0" max="1440" class="small-text" name="' . esc_attr( $name ) . '[checkin_before]" value="' . (int) $s['checkin_before'] . '" aria-label="' . esc_attr__( 'Minutes before', 'ar-net-logger' ) . '">',
								'<input type="number" min="0" max="1440" class="small-text" name="' . esc_attr( $name ) . '[checkin_after]" value="' . (int) $s['checkin_after'] . '" aria-label="' . esc_attr__( 'Minutes after', 'ar-net-logger' ) . '">'
							);
							?>
							<p class="description"><?php esc_html_e( 'When the NCS clicks "Open Net", check-ins stay open until the net is closed, regardless of this window.', 'ar-net-logger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-public-log"><?php esc_html_e( 'Public check-in list', 'ar-net-logger' ); ?></label></th>
						<td>
							<?php
							ARNL_Admin::select(
								$name . '[public_log]',
								array(
									'callsign_name' => __( 'Show callsign and name', 'ar-net-logger' ),
									'callsign'      => __( 'Show callsign only', 'ar-net-logger' ),
									'none'          => __( 'Do not show', 'ar-net-logger' ),
								),
								$s['public_log'],
								array( 'id' => 'arnl-public-log' )
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-rate"><?php esc_html_e( 'Flood protection', 'ar-net-logger' ); ?></label></th>
						<td>
							<?php
							printf(
								/* translators: %s: seconds input. */
								esc_html__( 'Allow one website check-in per visitor every %s seconds (0 to disable).', 'ar-net-logger' ),
								'<input type="number" id="arnl-rate" min="0" max="3600" class="small-text" name="' . esc_attr( $name ) . '[rate_limit]" value="' . (int) $s['rate_limit'] . '">'
							);
							?>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Calendar', 'ar-net-logger' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="arnl-event-page"><?php esc_html_e( 'Session details page', 'ar-net-logger' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => esc_attr( $name ) . '[event_page_id]',
									'id'                => 'arnl-event-page',
									'selected'          => (int) $s['event_page_id'],
									'show_option_none'  => esc_html__( '— Same page as the calendar —', 'ar-net-logger' ),
									'option_none_value' => '0',
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Optional page containing [arnl_event]. Calendar links open session details (with the check-in form) there; by default they open on the page that shows the calendar.', 'ar-net-logger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Calendar export (iCal)', 'ar-net-logger' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[ics_enabled]" value="1" <?php checked( $s['ics_enabled'] ); ?>> <?php esc_html_e( 'Enable .ics downloads and subscribable calendar feeds', 'ar-net-logger' ); ?></label>
							<p>
								<?php
								printf(
									/* translators: 1: past days input, 2: future days input. */
									esc_html__( 'Feeds include sessions from %1$s days ago to %2$s days ahead.', 'ar-net-logger' ),
									'<input type="number" min="0" max="3650" class="small-text" name="' . esc_attr( $name ) . '[ics_past_days]" value="' . (int) $s['ics_past_days'] . '" aria-label="' . esc_attr__( 'Days in the past', 'ar-net-logger' ) . '">',
									'<input type="number" min="1" max="730" class="small-text" name="' . esc_attr( $name ) . '[ics_future_days]" value="' . (int) $s['ics_future_days'] . '" aria-label="' . esc_attr__( 'Days ahead', 'ar-net-logger' ) . '">'
								);
								?>
							</p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Uninstall', 'ar-net-logger' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Data', 'ar-net-logger' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[delete_data]" value="1" <?php checked( $s['delete_data'] ); ?>> <?php esc_html_e( 'Delete all nets, sessions, check-ins and settings when the plugin is deleted', 'ar-net-logger' ); ?></label></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr>
			<?php self::reference(); ?>
		</div>
		<?php
	}

	/**
	 * Shortcode and feed reference.
	 */
	protected static function reference() {
		$rows       = array(
			array( '[arnl_calendar]', __( 'Month calendar of all active nets with previous / next navigation, color legend and subscribe links.', 'ar-net-logger' ) ),
			array( '[arnl_calendar view="list" days="60"]', __( 'Agenda list for the next 60 days instead of a month grid.', 'ar-net-logger' ) ),
			array( '[arnl_calendar net="club-net,ares-net" show_filter="yes"]', __( 'Only the listed nets (slugs or IDs), with a drop-down so visitors can narrow it to one net.', 'ar-net-logger' ) ),
			array( '[arnl_calendar type="emergency,weather" mode="fm" band="2m,70cm"]', __( 'Filter by net type, mode and band (comma-separated keys).', 'ar-net-logger' ) ),
			array( '[arnl_calendar month="2026-12" nav="no" show_ncs="no" show_topic="no"]', __( 'Fixed month without navigation, hiding NCS and topic.', 'ar-net-logger' ) ),
			array( '[arnl_upcoming limit="5" net="club-net"]', __( 'The next sessions with "Check in" buttons while check-in is open.', 'ar-net-logger' ) ),
			array( '[arnl_nets type="traffic"]', __( 'Directory of nets: schedule, frequency, NCS, next session and per-net subscribe links.', 'ar-net-logger' ) ),
			array( '[arnl_event net="club-net"]', __( 'Details of a net\'s current or next session with the check-in form. Shows the selected session when linked from a calendar.', 'ar-net-logger' ) ),
			array( '[arnl_checkin net="club-net"]', __( 'Just the online check-in form (and check-in list) for a net\'s current / next session.', 'ar-net-logger' ) ),
		);
		$attributes = array(
			'net'           => __( 'Net slugs or IDs, comma-separated.', 'ar-net-logger' ),
			'type'          => implode( ', ', array_keys( arnl_net_types() ) ),
			'mode'          => implode( ', ', array_keys( arnl_modes() ) ),
			'band'          => implode( ', ', array_keys( arnl_bands() ) ),
			'view'          => __( 'month (default) or list.', 'ar-net-logger' ),
			'month / start' => __( 'First month (YYYY-MM) for the month view / first date (YYYY-MM-DD) for the list view.', 'ar-net-logger' ),
			'days'          => __( 'Number of days in the list view (default 30) or in [arnl_upcoming] (default 60).', 'ar-net-logger' ),
			'limit'         => __( 'Maximum sessions in [arnl_upcoming] (default 5).', 'ar-net-logger' ),
			'show_*'        => __( 'yes / no: show_cancelled, show_ncs, show_topic, show_time, show_frequency, show_legend, show_subscribe, show_filter, show_description, show_next, show_log.', 'ar-net-logger' ),
			'nav'           => __( 'yes / no: previous / next navigation.', 'ar-net-logger' ),
			'link'          => __( 'yes / no: link sessions to their details and check-in form.', 'ar-net-logger' ),
			'title'         => __( 'Heading text.', 'ar-net-logger' ),
		);
		$feed       = arnl_ics_url();
		?>
		<h2><?php esc_html_e( 'Shortcodes', 'ar-net-logger' ); ?></h2>
		<table class="widefat striped arnl-reference">
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr><td><code class="arnl-copy"><?php echo esc_html( $row[0] ); ?></code></td><td><?php echo esc_html( $row[1] ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<h3><?php esc_html_e( 'Filter attributes', 'ar-net-logger' ); ?></h3>
		<table class="widefat striped arnl-reference">
			<tbody>
				<?php foreach ( $attributes as $attr => $text ) : ?>
					<tr><td><code><?php echo esc_html( $attr ); ?></code></td><td><?php echo esc_html( $text ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<h2><?php esc_html_e( 'Calendar feeds', 'ar-net-logger' ); ?></h2>
		<p><?php esc_html_e( 'Subscribe in Google Calendar, Apple Calendar or Outlook. The same filters work as query parameters:', 'ar-net-logger' ); ?></p>
		<ul class="arnl-feed-list">
			<li><code class="arnl-copy"><?php echo esc_html( $feed ); ?></code></li>
			<li><code class="arnl-copy"><?php echo esc_html( add_query_arg( 'net', 'club-net', $feed ) ); ?></code></li>
			<li><code class="arnl-copy">
			<?php
			echo esc_html(
				add_query_arg(
					array(
						'type' => 'emergency',
						'band' => '2m',
					),
					$feed
				)
			);
			?>
										</code></li>
		</ul>
		<?php
	}
}
