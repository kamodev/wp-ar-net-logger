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
		add_action( 'admin_post_arnl_discord_test', array( __CLASS__, 'handle_discord_test' ) );
		add_action( 'admin_post_arnl_facebook_test', array( __CLASS__, 'handle_facebook_test' ) );
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
						<th scope="row"><?php esc_html_e( 'Callsign lookup', 'ar-net-logger' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[online_lookup]" value="1" <?php checked( $s['online_lookup'] ); ?>> <?php esc_html_e( 'On the Net Log, look up stations not logged before in the US license database (callook.info) to fill in name and location', 'ar-net-logger' ); ?></label>
							<p class="description"><?php esc_html_e( 'Stations that checked in before are always suggested from your own logs as you type.', 'ar-net-logger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Check-in window', 'ar-net-logger' ); ?></th>
						<td>
							<?php
							printf(
								/* translators: 1: minutes-before input, 2: minutes-after input. */
								esc_html__( 'Open %1$s minutes before the start until %2$s minutes after the scheduled end.', 'ar-net-logger' ),
								'<input type="number" min="0" max="' . (int) ARNL_Settings::MAX_CHECKIN_WINDOW . '" class="small-text" name="' . esc_attr( $name ) . '[checkin_before]" value="' . (int) $s['checkin_before'] . '" aria-label="' . esc_attr__( 'Minutes before', 'ar-net-logger' ) . '">',
								'<input type="number" min="0" max="' . (int) ARNL_Settings::MAX_CHECKIN_WINDOW . '" class="small-text" name="' . esc_attr( $name ) . '[checkin_after]" value="' . (int) $s['checkin_after'] . '" aria-label="' . esc_attr__( 'Minutes after', 'ar-net-logger' ) . '">'
							);
							?>
							<p class="description"><?php esc_html_e( 'Up to 60 minutes each way. This is the default; each net can set its own window (Nets → Edit → Online check-in). When the NCS clicks "Open Net", check-ins stay open until the net is closed, regardless of the window.', 'ar-net-logger' ); ?></p>
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

				<h2 class="title"><?php esc_html_e( 'Net pages', 'ar-net-logger' ); ?></h2>
				<?php
				$net_base = ARNL_Permalinks::base();
				$net_slug = ARNL_Permalinks::slug();
				$parent   = '' !== $net_slug ? substr( $net_base, 0, -strlen( $net_slug ) ) : '';
				$example  = ARNL_Nets::query( array( 'number' => 1 ) );
				$example  = $example ? reset( $example ) : array( 'slug' => 'colorado-preparedness-net' );
				?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="arnl-net-base"><?php esc_html_e( 'Net permalink base', 'ar-net-logger' ); ?></label></th>
						<td>
							<code><?php echo esc_html( trailingslashit( home_url() ) . $parent ); ?></code><input type="text" id="arnl-net-base" name="<?php echo esc_attr( $name ); ?>[net_base]" value="<?php echo esc_attr( '' !== $net_slug ? $net_slug : 'net' ); ?>" class="regular-text code" style="width: 12em;"><code>/<?php echo esc_html( $example['slug'] ); ?>/</code>
							<?php if ( ARNL_Permalinks::available() ) : ?>
								<p class="description">
									<?php
									printf(
										/* translators: 1: example net URL, 2: example session URL. */
										esc_html__( 'Each net has its own page, e.g. %1$s, and each session of it %2$s (the session number, or its date for regular sessions not yet logged).', 'ar-net-logger' ),
										'<code>' . esc_html( '/' . $net_base . '/' . $example['slug'] . '/' ) . '</code>',
										'<code>' . esc_html( '/' . $net_base . '/' . $example['slug'] . '/?p=134' ) . '</code>'
									);
									?>
									<?php if ( ! ARNL_Permalinks::pretty() ) : ?>
										<br><strong><?php esc_html_e( 'Plain permalinks are active (Settings → Permalinks), so net pages use query-string addresses until pretty permalinks are enabled.', 'ar-net-logger' ); ?></strong>
									<?php endif; ?>
								</p>
								<p class="description">
									<?php
									printf(
										/* translators: %s: link to edit the page. */
										esc_html__( 'The base is the slug of the %s page, which lists all nets. Add an introduction to that page if you like; keep the [arnl_net] shortcode in it.', 'ar-net-logger' ),
										'<a href="' . esc_url( get_edit_post_link( ARNL_Permalinks::page_id() ) ) . '">' . esc_html( get_the_title( ARNL_Permalinks::page_id() ) ) . '</a>'
									);
									?>
								</p>
							<?php else : ?>
								<p class="description"><strong><?php esc_html_e( 'The page for net permalinks is missing or not published. Save the settings to (re)create it.', 'ar-net-logger' ); ?></strong></p>
							<?php endif; ?>
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
									'show_option_none'  => esc_html__( '— Net permalinks (recommended) —', 'ar-net-logger' ),
									'option_none_value' => '0',
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Calendar links open each session (details and check-in form) at its net permalink. Optionally choose a page containing [arnl_event] to show sessions there instead.', 'ar-net-logger' ); ?></p>
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

				<h2 class="title" id="arnl-discord"><?php esc_html_e( 'Discord', 'ar-net-logger' ); ?></h2>
				<?php $discord_error = get_option( ARNL_Discord::ERROR_OPTION ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="arnl-discord-webhook"><?php esc_html_e( 'Default webhook URL', 'ar-net-logger' ); ?></label></th>
						<td>
							<input type="password" id="arnl-discord-webhook" name="<?php echo esc_attr( $name ); ?>[discord_webhook]" value="" class="large-text code" autocomplete="off" placeholder="<?php echo esc_attr( '' !== $s['discord_webhook'] ? __( 'Saved – leave blank to keep it', 'ar-net-logger' ) : 'https://discord.com/api/webhooks/…' ); ?>">
							<?php if ( '' !== $s['discord_webhook'] ) : ?>
								<br><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[discord_webhook_clear]" value="1"> <?php esc_html_e( 'Remove the saved webhook', 'ar-net-logger' ); ?></label>
								&nbsp; <a class="button button-small" href="<?php echo esc_url( ARNL_Admin::action_url( 'arnl_discord_test' ) ); ?>"><?php esc_html_e( 'Send a test message', 'ar-net-logger' ); ?></a>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'In Discord: Server Settings → Integrations → Webhooks → New Webhook, pick the channel, Copy Webhook URL. Nets can use their own channel instead (Nets → Edit → Sharing).', 'ar-net-logger' ); ?></p>
							<?php if ( is_array( $discord_error ) && ! empty( $discord_error['message'] ) ) : ?>
								<p class="arnl-integration-error">
									<?php
									/* translators: 1: date/time, 2: error message. */
									echo esc_html( sprintf( __( 'Last error (%1$s): %2$s', 'ar-net-logger' ), $discord_error['time'], $discord_error['message'] ) );
									?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Post to Discord', 'ar-net-logger' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[discord_checkins]" value="1" <?php checked( $s['discord_checkins'] ); ?>> <?php esc_html_e( 'Each check-in (website check-ins once approved)', 'ar-net-logger' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[discord_open_close]" value="1" <?php checked( $s['discord_open_close'] ); ?>> <?php esc_html_e( 'When the NCS opens the net, and a summary when it closes', 'ar-net-logger' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Include', 'ar-net-logger' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[discord_include_name]" value="1" <?php checked( $s['discord_include_name'] ); ?>> <?php esc_html_e( 'Name', 'ar-net-logger' ); ?></label> &nbsp;
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[discord_include_location]" value="1" <?php checked( $s['discord_include_location'] ); ?>> <?php esc_html_e( 'Location', 'ar-net-logger' ); ?></label> &nbsp;
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[discord_include_announcements]" value="1" <?php checked( $s['discord_include_announcements'] ); ?>> <?php esc_html_e( 'Announcements', 'ar-net-logger' ); ?></label> &nbsp;
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[discord_include_comments]" value="1" <?php checked( $s['discord_include_comments'] ); ?>> <?php esc_html_e( 'Comments', 'ar-net-logger' ); ?></label>
							<p class="description"><?php esc_html_e( 'Callsign, station type, traffic and relays are always included. Mentions such as @everyone are never triggered.', 'ar-net-logger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-discord-username"><?php esc_html_e( 'Post as', 'ar-net-logger' ); ?></label></th>
						<td><input type="text" id="arnl-discord-username" name="<?php echo esc_attr( $name ); ?>[discord_username]" value="<?php echo esc_attr( $s['discord_username'] ); ?>" class="regular-text" maxlength="80" placeholder="<?php esc_attr_e( 'The webhook\'s own name', 'ar-net-logger' ); ?>"></td>
					</tr>
				</table>

				<h2 class="title" id="arnl-facebook"><?php esc_html_e( 'Facebook Page', 'ar-net-logger' ); ?></h2>
				<?php $facebook_error = get_option( ARNL_Facebook::ERROR_OPTION ); ?>
				<p class="description arnl-integration-note">
					<?php esc_html_e( 'Sessions are announced as posts on your Facebook Page. Facebook does not allow apps to create Page or Group events, or to post in Groups, so the Net Log of each session shows ready-to-paste event details for creating those by hand.', 'ar-net-logger' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="arnl-facebook-page"><?php esc_html_e( 'Page ID', 'ar-net-logger' ); ?></label></th>
						<td>
							<input type="text" id="arnl-facebook-page" name="<?php echo esc_attr( $name ); ?>[facebook_page_id]" value="<?php echo esc_attr( $s['facebook_page_id'] ); ?>" class="regular-text code" inputmode="numeric">
							<p class="description"><?php esc_html_e( 'On your Page: About → Page transparency, or Settings → Page ID.', 'ar-net-logger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-facebook-token"><?php esc_html_e( 'Page access token', 'ar-net-logger' ); ?></label></th>
						<td>
							<input type="password" id="arnl-facebook-token" name="<?php echo esc_attr( $name ); ?>[facebook_token]" value="" class="large-text code" autocomplete="off" placeholder="<?php echo esc_attr( '' !== $s['facebook_token'] ? __( 'Saved – leave blank to keep it', 'ar-net-logger' ) : '' ); ?>">
							<?php if ( '' !== $s['facebook_token'] ) : ?>
								<br><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[facebook_token_clear]" value="1"> <?php esc_html_e( 'Remove the saved token', 'ar-net-logger' ); ?></label>
								&nbsp; <a class="button button-small" href="<?php echo esc_url( ARNL_Admin::action_url( 'arnl_facebook_test' ) ); ?>"><?php esc_html_e( 'Test the connection', 'ar-net-logger' ); ?></a>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'A long-lived Page access token with the pages_manage_posts and pages_read_engagement permissions (see the README for the steps).', 'ar-net-logger' ); ?></p>
							<?php if ( is_array( $facebook_error ) && ! empty( $facebook_error['message'] ) ) : ?>
								<p class="arnl-integration-error">
									<?php
									/* translators: 1: date/time, 2: error message. */
									echo esc_html( sprintf( __( 'Last error (%1$s): %2$s', 'ar-net-logger' ), $facebook_error['time'], $facebook_error['message'] ) );
									?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-facebook-lead"><?php esc_html_e( 'Announce sessions', 'ar-net-logger' ); ?></label></th>
						<td>
							<?php
							printf(
								/* translators: %s: hours input. */
								esc_html__( 'Post each session automatically %s hours before it starts.', 'ar-net-logger' ),
								'<input type="number" id="arnl-facebook-lead" min="0" max="336" class="small-text" name="' . esc_attr( $name ) . '[facebook_lead_hours]" value="' . (int) $s['facebook_lead_hours'] . '">'
							);
							?>
							<p class="description"><?php esc_html_e( '0 = only when you click "Post to Facebook" on the schedule. Checked hourly by WP-Cron. A cancelled session\'s post is marked CANCELLED.', 'ar-net-logger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-facebook-template"><?php esc_html_e( 'Post text', 'ar-net-logger' ); ?></label></th>
						<td>
							<textarea id="arnl-facebook-template" name="<?php echo esc_attr( $name ); ?>[facebook_template]" rows="7" class="large-text"><?php echo esc_textarea( '' !== $s['facebook_template'] ? $s['facebook_template'] : ARNL_Facebook::default_template() ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Placeholders: {net} {date} {time} {end_time} {timezone} {frequency} {digital} {system} {system_url} {ncs} {topic} {type} {description} {url}. Lines whose placeholders are all empty are left out.', 'ar-net-logger' ); ?>
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
	 * Send a test message to the default Discord webhook.
	 */
	public static function handle_discord_test() {
		check_admin_referer( 'arnl_discord_test' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'ar-net-logger' ), 403 );
		}
		$back    = ARNL_Admin::url( 'arnl-settings' ) . '#arnl-discord';
		$webhook = (string) ARNL_Settings::get( 'discord_webhook' );
		$result  = '' !== $webhook ? ARNL_Discord::send( $webhook, ARNL_Discord::test_payload() ) : new WP_Error( 'arnl_discord', __( 'Save a Discord webhook URL first.', 'ar-net-logger' ) );
		if ( is_wp_error( $result ) ) {
			/* translators: %s: error message. */
			ARNL_Admin::fail( $back, sprintf( __( 'Discord: %s', 'ar-net-logger' ), $result->get_error_message() ) );
		}
		ARNL_Admin::redirect( $back, 'discord_test_sent' );
	}

	/**
	 * Check the Facebook Page connection.
	 */
	public static function handle_facebook_test() {
		check_admin_referer( 'arnl_facebook_test' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'ar-net-logger' ), 403 );
		}
		$back   = ARNL_Admin::url( 'arnl-settings' ) . '#arnl-facebook';
		$result = ARNL_Facebook::test();
		if ( is_wp_error( $result ) ) {
			ARNL_Admin::fail( $back, $result );
		}
		ARNL_Admin::redirect( add_query_arg( 'arnl_page', rawurlencode( $result ), ARNL_Admin::url( 'arnl-settings' ) ) . '#arnl-facebook', 'facebook_connected' );
	}

	/**
	 * Shortcode and feed reference.
	 */
	protected static function reference() {
		$rows       = array(
			array( '[arnl_calendar]', __( 'Month calendar of all active nets with Net / Type / Mode / Band filters, previous / next navigation, color legend and subscribe links.', 'ar-net-logger' ) ),
			array( '[arnl_calendar view="list" days="60"]', __( 'Agenda list for the next 60 days instead of a month grid.', 'ar-net-logger' ) ),
			array( '[arnl_calendar net="club-net,ares-net" filters="net"]', __( 'Only the listed nets (slugs or IDs), with only the Net drop-down for visitors.', 'ar-net-logger' ) ),
			array( '[arnl_calendar show_filter="no"]', __( 'Calendar without the visitor filter drop-downs.', 'ar-net-logger' ) ),
			array( '[arnl_calendar type="emergency,weather" mode="fm" band="2m,70cm"]', __( 'Filter by net type, mode and band (comma-separated keys).', 'ar-net-logger' ) ),
			array( '[arnl_calendar month="2026-12" nav="no" show_ncs="no" show_topic="no"]', __( 'Fixed month without navigation, hiding NCS and topic.', 'ar-net-logger' ) ),
			array( '[arnl_upcoming limit="5" net="club-net"]', __( 'The next sessions with "Check in" buttons while check-in is open.', 'ar-net-logger' ) ),
			array( '[arnl_nets type="traffic"]', __( 'Directory of nets: schedule, frequency, NCS, next session and per-net subscribe links.', 'ar-net-logger' ) ),
			array( '[arnl_event net="club-net"]', __( 'Details of a net\'s current or next session with the check-in form. Shows the selected session when linked from a calendar.', 'ar-net-logger' ) ),
			array( '[arnl_checkin net="club-net"]', __( 'Just the online check-in form (and check-in list) for a net\'s current / next session.', 'ar-net-logger' ) ),
			array( '[arnl_net]', __( 'On the Nets page: the net or session from the permalink (/net/net-name/?p=134), otherwise the directory of nets. With net="club-net": that net\'s page anywhere.', 'ar-net-logger' ) ),
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
			'show_filter'   => __( 'yes / no: visitor filter drop-downs (on by default in [arnl_calendar], off in [arnl_upcoming]).', 'ar-net-logger' ),
			'filters'       => __( 'Which visitor filters to offer: net, type, mode, band (default all; a drop-down only appears when there are 2+ choices).', 'ar-net-logger' ),
			'show_*'        => __( 'yes / no: show_cancelled, show_ncs, show_topic, show_time, show_frequency, show_legend, show_subscribe, show_description, show_next, show_log.', 'ar-net-logger' ),
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
