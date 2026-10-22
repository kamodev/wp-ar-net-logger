<?php
/**
 * Nets admin screens: list, add / edit form, delete, bulk actions.
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nets CRUD screens.
 */
class ARNL_Admin_Nets {

	/**
	 * List table instance.
	 *
	 * @var ARNL_Nets_List_Table|null
	 */
	protected static $table = null;

	/**
	 * Register handlers.
	 */
	public static function init() {
		add_action( 'admin_post_arnl_save_net', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_arnl_delete_net', array( __CLASS__, 'handle_delete' ) );
		add_filter( 'set_screen_option_arnl_nets_per_page', array( __CLASS__, 'save_per_page' ), 10, 3 );
	}

	/**
	 * Persist the "Number of items per page" screen option.
	 *
	 * @param mixed  $keep   Default.
	 * @param string $option Option name.
	 * @param int    $value  Value.
	 * @return int
	 */
	public static function save_per_page( $keep, $option, $value ) {
		return max( 1, min( 999, (int) $value ) );
	}

	/**
	 * Screen load: process bulk actions and set up the list table.
	 */
	public static function load() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( in_array( $action, array( 'edit', 'new' ), true ) ) {
			// Media library for the net logo.
			add_action( 'admin_enqueue_scripts', 'wp_enqueue_media' );
			return;
		}

		self::$table = new ARNL_Nets_List_Table();
		$bulk        = self::$table->current_action();
		if ( $bulk && in_array( $bulk, array( 'delete', 'activate', 'deactivate' ), true ) ) {
			check_admin_referer( 'bulk-nets' );
			if ( ! current_user_can( ARNL_Install::CAP_MANAGE_NETS ) ) {
				wp_die( esc_html__( 'You are not allowed to manage nets.', 'ar-net-logger' ) );
			}
			$ids = isset( $_REQUEST['net_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['net_ids'] ) ) : array();
			foreach ( $ids as $id ) {
				if ( 'delete' === $bulk ) {
					ARNL_Nets::delete( $id );
				} else {
					ARNL_Nets::update( $id, array( 'status' => 'activate' === $bulk ? 'active' : 'inactive' ) );
				}
			}
			ARNL_Admin::redirect( ARNL_Admin::url( 'arnl-nets' ), 'delete' === $bulk ? 'nets_deleted' : 'nets_updated' );
		}

		add_screen_option(
			'per_page',
			array(
				'default' => 20,
				'option'  => 'arnl_nets_per_page',
			)
		);
	}

	/**
	 * Render the screen.
	 */
	public static function render() {
		if ( ! current_user_can( ARNL_Install::CAP_MANAGE_NETS ) ) {
			wp_die( esc_html__( 'You are not allowed to manage nets.', 'ar-net-logger' ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- routing only.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( 'new' === $action || ( 'edit' === $action && $id ) ) {
			$net = $id ? ARNL_Nets::get( $id ) : ARNL_Nets::defaults();
			if ( ! $net ) {
				wp_die( esc_html__( 'Net not found.', 'ar-net-logger' ) );
			}
			self::render_form( $net );
			return;
		}

		if ( ! self::$table ) {
			self::$table = new ARNL_Nets_List_Table();
		}
		self::$table->prepare_items();
		?>
		<div class="wrap arnl-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Nets', 'ar-net-logger' ); ?></h1>
			<a href="<?php echo esc_url( ARNL_Admin::url( 'arnl-nets', array( 'action' => 'new' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Net', 'ar-net-logger' ); ?></a>
			<hr class="wp-header-end">
			<?php self::$table->views(); ?>
			<form method="get">
				<input type="hidden" name="page" value="arnl-nets">
				<?php
				self::$table->search_box( __( 'Search nets', 'ar-net-logger' ), 'arnl-net' );
				self::$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Add / edit form.
	 *
	 * @param array $net Net (defaults for a new one).
	 */
	protected static function render_form( array $net ) {
		$failed = ARNL_Admin::failed_data();
		if ( $failed ) {
			$net = array_merge( $net, $failed );
		}
		$is_new = empty( $net['id'] );
		?>
		<div class="wrap arnl-wrap">
			<h1><?php echo esc_html( $is_new ? __( 'Add New Net', 'ar-net-logger' ) : __( 'Edit Net', 'ar-net-logger' ) ); ?></h1>
			<?php if ( ! $is_new ) : ?>
				<p class="arnl-subtitle">
					<?php echo esc_html( arnl_describe_schedule( $net ) ); ?>
					&middot; <a href="<?php echo esc_url( ARNL_Admin::url( 'arnl-schedule', array( 'net' => $net['id'] ) ) ); ?>"><?php esc_html_e( 'View sessions', 'ar-net-logger' ); ?></a>
					&middot; <code class="arnl-copy">[arnl_calendar net="<?php echo esc_html( $net['slug'] ); ?>"]</code>
				</p>
				<?php $net_url = arnl_net_url( $net ); ?>
				<?php if ( '' !== $net_url ) : ?>
					<p class="arnl-permalink">
						<strong><?php esc_html_e( 'Permalink:', 'ar-net-logger' ); ?></strong>
						<?php if ( 'active' === $net['status'] ) : ?>
							<a href="<?php echo esc_url( $net_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $net_url ); ?></a>
						<?php else : ?>
							<code><?php echo esc_html( $net_url ); ?></code> <span class="arnl-muted"><?php esc_html_e( '(inactive nets are not shown publicly)', 'ar-net-logger' ); ?></span>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="arnl-net-form">
				<input type="hidden" name="action" value="arnl_save_net">
				<input type="hidden" name="id" value="<?php echo (int) $net['id']; ?>">
				<?php wp_nonce_field( 'arnl_save_net_' . (int) $net['id'] ); ?>

				<h2 class="title"><?php esc_html_e( 'Net details', 'ar-net-logger' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="arnl-name"><?php esc_html_e( 'Net name', 'ar-net-logger' ); ?> <span class="arnl-required">*</span></label></th>
						<td><input type="text" id="arnl-name" name="name" class="regular-text" value="<?php echo esc_attr( $net['name'] ); ?>" required maxlength="190" placeholder="<?php esc_attr_e( 'e.g. Tuesday Night 2m Club Net', 'ar-net-logger' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-slug"><?php esc_html_e( 'Slug', 'ar-net-logger' ); ?></label></th>
						<td>
							<input type="text" id="arnl-slug" name="slug" class="regular-text" value="<?php echo esc_attr( $net['slug'] ); ?>" maxlength="180">
							<p class="description"><?php esc_html_e( 'Used in shortcodes and calendar feed URLs. Leave blank to generate it from the name.', 'ar-net-logger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-type"><?php esc_html_e( 'Net type', 'ar-net-logger' ); ?></label></th>
						<td><?php ARNL_Admin::select( 'net_type', arnl_net_types(), $net['net_type'], array( 'id' => 'arnl-type' ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-frequency"><?php esc_html_e( 'Frequency', 'ar-net-logger' ); ?></label></th>
						<td>
							<input type="text" id="arnl-frequency" name="frequency" class="regular-text" value="<?php echo esc_attr( $net['frequency'] ); ?>" maxlength="100" placeholder="<?php esc_attr_e( 'e.g. 146.940 MHz', 'ar-net-logger' ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-mode"><?php esc_html_e( 'Mode / Band', 'ar-net-logger' ); ?></label></th>
						<td>
							<?php ARNL_Admin::select( 'mode', array( '' => __( '— Mode —', 'ar-net-logger' ) ) + arnl_modes(), $net['mode'], array( 'id' => 'arnl-mode' ) ); ?>
							<label class="screen-reader-text" for="arnl-band"><?php esc_html_e( 'Band', 'ar-net-logger' ); ?></label>
							<?php ARNL_Admin::select( 'band', array( '' => __( '— Band —', 'ar-net-logger' ) ) + arnl_bands(), $net['band'], array( 'id' => 'arnl-band' ) ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-access"><?php esc_html_e( 'Repeater / access info', 'ar-net-logger' ); ?></label></th>
						<td>
							<input type="text" id="arnl-access" name="access_info" class="large-text" value="<?php echo esc_attr( $net['access_info'] ); ?>" maxlength="255" placeholder="<?php esc_attr_e( 'e.g. W1AW repeater, -0.600 offset, PL 100.0 Hz; EchoLink node 123456', 'ar-net-logger' ); ?>">
						</td>
					</tr>
					<tr class="arnl-row-linked" data-arnl-bands="<?php echo esc_attr( implode( ',', arnl_linked_system_bands() ) ); ?>">
						<th scope="row"><?php esc_html_e( 'Linked repeater system', 'ar-net-logger' ); ?></th>
						<td>
							<label><input type="checkbox" name="linked_system" value="1" data-arnl-toggle="arnl-linked-fields" <?php checked( $net['linked_system'] ); ?>> <?php esc_html_e( 'This net runs on a linked repeater system', 'ar-net-logger' ); ?></label>
							<div id="arnl-linked-fields" class="arnl-subfields">
								<p>
									<label for="arnl-system-name"><?php esc_html_e( 'System name', 'ar-net-logger' ); ?></label><br>
									<input type="text" id="arnl-system-name" name="system_name" class="regular-text" value="<?php echo esc_attr( $net['system_name'] ); ?>" maxlength="100" placeholder="<?php esc_attr_e( 'e.g. Colorado Connection', 'ar-net-logger' ); ?>">
								</p>
								<p>
									<label for="arnl-system-url"><?php esc_html_e( 'System website', 'ar-net-logger' ); ?></label><br>
									<input type="url" id="arnl-system-url" name="system_url" class="regular-text" value="<?php echo esc_attr( $net['system_url'] ); ?>" maxlength="255" placeholder="https://">
								</p>
								<p class="description"><?php esc_html_e( 'For 2 m and 70 cm nets carried by a network of linked repeaters. The website (repeater list, coverage map) is linked from the net and session pages.', 'ar-net-logger' ); ?></p>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Digital net', 'ar-net-logger' ); ?></th>
						<td>
							<label><input type="checkbox" id="arnl-is-digital" name="is_digital" value="1" data-arnl-toggle="arnl-digital-fields" <?php checked( $net['is_digital'] ); ?>> <?php esc_html_e( 'This is a digital net (or is also carried on digital modes)', 'ar-net-logger' ); ?></label>
							<div id="arnl-digital-fields" class="arnl-subfields arnl-digital-grid">
								<p>
									<label for="arnl-ysf-room"><?php esc_html_e( 'YSF room', 'ar-net-logger' ); ?></label>
									<input type="text" id="arnl-ysf-room" name="ysf_room" value="<?php echo esc_attr( $net['ysf_room'] ); ?>" maxlength="100" placeholder="<?php esc_attr_e( 'e.g. US-Colorado-Link 12345', 'ar-net-logger' ); ?>">
								</p>
								<p>
									<label for="arnl-wiresx-room"><?php esc_html_e( 'Wires-X room', 'ar-net-logger' ); ?></label>
									<input type="text" id="arnl-wiresx-room" name="wiresx_room" value="<?php echo esc_attr( $net['wiresx_room'] ); ?>" maxlength="100" placeholder="<?php esc_attr_e( 'e.g. COLORADO-LINK 28733', 'ar-net-logger' ); ?>">
								</p>
								<p>
									<label for="arnl-dstar-reflector"><?php esc_html_e( 'D-STAR reflector', 'ar-net-logger' ); ?></label>
									<input type="text" id="arnl-dstar-reflector" name="dstar_reflector" value="<?php echo esc_attr( $net['dstar_reflector'] ); ?>" maxlength="100" placeholder="<?php esc_attr_e( 'e.g. REF030 C', 'ar-net-logger' ); ?>">
								</p>
								<p>
									<label for="arnl-xlx-reflector"><?php esc_html_e( 'XLX reflector', 'ar-net-logger' ); ?></label>
									<input type="text" id="arnl-xlx-reflector" name="xlx_reflector" value="<?php echo esc_attr( $net['xlx_reflector'] ); ?>" maxlength="100" placeholder="<?php esc_attr_e( 'e.g. XLX307 module D', 'ar-net-logger' ); ?>">
								</p>
								<p>
									<label for="arnl-dmr-talkgroup"><?php esc_html_e( 'DMR talkgroup', 'ar-net-logger' ); ?></label>
									<input type="text" id="arnl-dmr-talkgroup" name="dmr_talkgroup" value="<?php echo esc_attr( $net['dmr_talkgroup'] ); ?>" maxlength="100" placeholder="<?php esc_attr_e( 'e.g. BrandMeister TG 31088, TS2', 'ar-net-logger' ); ?>">
								</p>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-description"><?php esc_html_e( 'Description', 'ar-net-logger' ); ?></label></th>
						<td>
							<textarea id="arnl-description" name="description" class="large-text" rows="4"><?php echo esc_textarea( $net['description'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Shown publicly with the net and its sessions. Basic HTML allowed.', 'ar-net-logger' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Schedule', 'ar-net-logger' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="arnl-recurrence"><?php esc_html_e( 'Repeats', 'ar-net-logger' ); ?></label></th>
						<td><?php ARNL_Admin::select( 'recurrence', arnl_recurrence_types(), $net['recurrence'], array( 'id' => 'arnl-recurrence' ) ); ?></td>
					</tr>
					<tr class="arnl-row-days">
						<th scope="row"><?php esc_html_e( 'Day(s) of the week', 'ar-net-logger' ); ?></th>
						<td><?php ARNL_Admin::checkboxes( 'days_of_week', arnl_weekday_names(), $net['days_of_week'] ); ?></td>
					</tr>
					<tr class="arnl-row-weeks">
						<th scope="row"><?php esc_html_e( 'Week(s) of the month', 'ar-net-logger' ); ?></th>
						<td>
							<?php ARNL_Admin::checkboxes( 'weeks_of_month', arnl_week_of_month_options(), $net['weeks_of_month'] ); ?>
							<p class="description"><?php esc_html_e( 'For example 1st and 3rd Tuesday, or Last Saturday of the month.', 'ar-net-logger' ); ?></p>
						</td>
					</tr>
					<tr class="arnl-row-months">
						<th scope="row"><?php esc_html_e( 'Month(s)', 'ar-net-logger' ); ?></th>
						<td>
							<?php ARNL_Admin::checkboxes( 'months', arnl_month_names( true ), $net['months'] ? $net['months'] : range( 1, 12 ) ); ?>
							<p class="description"><?php esc_html_e( 'Untick months when the net does not meet, e.g. a summer-only net or a winter break.', 'ar-net-logger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-start-time"><?php esc_html_e( 'Time', 'ar-net-logger' ); ?></label></th>
						<td>
							<input type="time" id="arnl-start-time" name="start_time" value="<?php echo esc_attr( substr( $net['start_time'], 0, 5 ) ); ?>" required>
							<?php esc_html_e( 'to', 'ar-net-logger' ); ?>
							<label class="screen-reader-text" for="arnl-end-time"><?php esc_html_e( 'End time', 'ar-net-logger' ); ?></label>
							<input type="time" id="arnl-end-time" name="end_time" value="<?php echo esc_attr( substr( $net['end_time'], 0, 5 ) ); ?>">
							<p class="description">
								<?php
								/* translators: %s: timezone name. */
								echo esc_html( sprintf( __( 'Local time (%s), from Settings → General.', 'ar-net-logger' ), wp_timezone_string() ) );
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-start-date"><?php esc_html_e( 'Runs from', 'ar-net-logger' ); ?></label></th>
						<td>
							<input type="date" id="arnl-start-date" name="start_date" value="<?php echo esc_attr( $net['start_date'] ); ?>">
							<?php esc_html_e( 'until', 'ar-net-logger' ); ?>
							<label class="screen-reader-text" for="arnl-end-date"><?php esc_html_e( 'End date', 'ar-net-logger' ); ?></label>
							<input type="date" id="arnl-end-date" name="end_date" value="<?php echo esc_attr( $net['end_date'] ); ?>">
							<p class="description"><?php esc_html_e( 'Both optional. "Every other week" counts from the start date; a one-time net uses the start date.', 'ar-net-logger' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Net Control', 'ar-net-logger' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="arnl-ncs-callsign"><?php esc_html_e( 'Default Net Control Station', 'ar-net-logger' ); ?></label></th>
						<td>
							<input type="text" id="arnl-ncs-callsign" name="ncs_callsign" class="arnl-callsign-input" value="<?php echo esc_attr( $net['ncs_callsign'] ); ?>" maxlength="20" placeholder="<?php esc_attr_e( 'Callsign', 'ar-net-logger' ); ?>" autocomplete="off" data-arnl-lookup="<?php echo esc_attr( wp_json_encode( array( 'name' => '#arnl-ncs-name' ) ) ); ?>">
							<label class="screen-reader-text" for="arnl-ncs-name"><?php esc_html_e( 'NCS name', 'ar-net-logger' ); ?></label>
							<input type="text" id="arnl-ncs-name" name="ncs_name" value="<?php echo esc_attr( $net['ncs_name'] ); ?>" maxlength="100" placeholder="<?php esc_attr_e( 'Name', 'ar-net-logger' ); ?>">
							<p class="description"><?php esc_html_e( 'Can be changed for each session on the schedule.', 'ar-net-logger' ); ?></p>
						</td>
					</tr>
					<tr id="arnl-script-row">
						<th scope="row"><label for="arnl_preamble"><?php esc_html_e( 'Net script', 'ar-net-logger' ); ?></label></th>
						<td>
							<p class="description arnl-script-intro"><?php esc_html_e( 'The preamble and script Net Control reads on the air. It is shown, formatted and read-only, on the Net Log of every session.', 'ar-net-logger' ); ?></p>
							<?php
							wp_editor(
								$net['preamble'],
								'arnl_preamble',
								array(
									'textarea_name' => 'preamble',
									'textarea_rows' => 16,
									'media_buttons' => false,
									'tinymce'       => array(
										'toolbar1'      => 'formatselect,bold,italic,underline,forecolor,backcolor,bullist,numlist,blockquote,hr,alignleft,aligncenter,link,removeformat,undo,redo',
										'toolbar2'      => '',
										'block_formats' => __( 'Paragraph=p;Heading=h3;Subheading=h4;Preformatted=pre', 'ar-net-logger' ),
									),
									'quicktags'     => array( 'buttons' => 'strong,em,ul,ol,li,block,link,close' ),
								)
							);
							?>
							<div class="arnl-script-tools">
								<p class="arnl-placeholders">
									<span class="arnl-placeholders-label"><?php esc_html_e( 'Insert a placeholder, filled in for each session:', 'ar-net-logger' ); ?></span>
									<?php foreach ( arnl_script_placeholders() as $key => $label ) : ?>
										<button type="button" class="button-link arnl-insert-placeholder" data-placeholder="<?php echo esc_attr( '{' . $key . '}' ); ?>" title="<?php echo esc_attr( $label ); ?>"><code><?php echo esc_html( '{' . $key . '}' ); ?></code></button>
									<?php endforeach; ?>
								</p>
								<p>
									<button type="button" class="button" id="arnl-insert-sample" data-arnl-confirm="<?php esc_attr_e( 'Replace the current script with the sample directed-net script?', 'ar-net-logger' ); ?>"><?php esc_html_e( 'Insert sample script', 'ar-net-logger' ); ?></button>
								</p>
								<template id="arnl-sample-script"><?php echo wp_kses_post( arnl_sample_script() ); ?></template>
							</div>
						</td>
					</tr>
				</table>

				<?php
				$discord_sources  = self::sharing_sources( $net, 'discord' );
				$facebook_sources = self::sharing_sources( $net, 'facebook' );
				$discord_error    = $net['id'] ? ARNL_Discord::last_error( $net['id'] ) : null;
				$facebook_error   = $net['id'] ? ARNL_Facebook::last_error( $net['id'] ) : null;
				?>
				<h2 class="title" id="arnl-discord"><?php esc_html_e( 'Discord', 'ar-net-logger' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Post to Discord', 'ar-net-logger' ); ?></th>
						<td>
							<label><input type="checkbox" id="arnl-discord-on" name="discord_mode" value="on" data-arnl-toggle="arnl-discord-fields" <?php checked( 'on', $net['discord_mode'] ); ?>> <?php esc_html_e( 'Post this net to a Discord channel', 'ar-net-logger' ); ?></label>
							<div id="arnl-discord-fields" class="arnl-subfields">
								<p>
									<label for="arnl-discord-webhook"><?php esc_html_e( 'Webhook URL', 'ar-net-logger' ); ?></label><br>
									<input type="password" id="arnl-discord-webhook" name="discord_webhook" class="large-text code" value="" autocomplete="off" placeholder="<?php echo esc_attr( '' !== $net['discord_webhook'] ? __( 'Saved – leave blank to keep it', 'ar-net-logger' ) : 'https://discord.com/api/webhooks/…' ); ?>">
									<?php if ( '' !== $net['discord_webhook'] ) : ?>
										<br><label><input type="checkbox" name="discord_webhook_clear" value="1"> <?php esc_html_e( 'Remove the saved webhook', 'ar-net-logger' ); ?></label>
									<?php endif; ?>
								</p>
								<?php if ( $discord_sources ) : ?>
									<p>
										<label for="arnl-discord-copy"><?php esc_html_e( 'Or post to the same channel as', 'ar-net-logger' ); ?></label>
										<?php ARNL_Admin::select( 'discord_copy_from', array( '' => __( '— another net —', 'ar-net-logger' ) ) + $discord_sources, '', array( 'id' => 'arnl-discord-copy' ) ); ?>
									</p>
								<?php endif; ?>
								<p class="description"><?php esc_html_e( 'In Discord: Server Settings → Integrations → Webhooks → New Webhook, choose the channel, then Copy Webhook URL. The URL is a password: it is not shown again after saving.', 'ar-net-logger' ); ?></p>
								<fieldset>
									<legend><?php esc_html_e( 'Post', 'ar-net-logger' ); ?></legend>
									<?php foreach ( ARNL_Discord::post_options() as $key => $label ) : ?>
										<label><input type="checkbox" name="discord_post[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $net['discord_post'], true ) ); ?>> <?php echo esc_html( $label ); ?></label><br>
									<?php endforeach; ?>
								</fieldset>
								<fieldset>
									<legend><?php esc_html_e( 'Include in check-in posts', 'ar-net-logger' ); ?></legend>
									<?php foreach ( ARNL_Discord::include_options() as $key => $label ) : ?>
										<label><input type="checkbox" name="discord_include[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $net['discord_include'], true ) ); ?>> <?php echo esc_html( $label ); ?></label> &nbsp;
									<?php endforeach; ?>
									<p class="description"><?php esc_html_e( 'Callsign, station type, traffic and relays are always included. Mentions such as @everyone are never triggered.', 'ar-net-logger' ); ?></p>
								</fieldset>
								<p>
									<label for="arnl-discord-username"><?php esc_html_e( 'Post as', 'ar-net-logger' ); ?></label><br>
									<input type="text" id="arnl-discord-username" name="discord_username" class="regular-text" value="<?php echo esc_attr( $net['discord_username'] ); ?>" maxlength="80" placeholder="<?php esc_attr_e( 'The webhook\'s own name', 'ar-net-logger' ); ?>">
								</p>
								<?php if ( $discord_error ) : ?>
									<p class="arnl-error-text">
										<?php
										/* translators: 1: date and time, 2: error message. */
										echo esc_html( sprintf( __( 'Last error (%1$s): %2$s', 'ar-net-logger' ), $discord_error['time'], $discord_error['message'] ) );
										?>
									</p>
								<?php endif; ?>
								<p><button type="submit" class="button" name="arnl_after_save" value="discord_test"><?php esc_html_e( 'Save and send a test message', 'ar-net-logger' ); ?></button></p>
							</div>
						</td>
					</tr>
				</table>

				<h2 class="title" id="arnl-facebook"><?php esc_html_e( 'Facebook Page', 'ar-net-logger' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Post to Facebook', 'ar-net-logger' ); ?></th>
						<td>
							<label><input type="checkbox" id="arnl-facebook-on" name="facebook_mode" value="on" data-arnl-toggle="arnl-facebook-fields" <?php checked( 'on', $net['facebook_mode'] ); ?>> <?php esc_html_e( 'Announce this net\'s sessions on a Facebook Page', 'ar-net-logger' ); ?></label>
							<div id="arnl-facebook-fields" class="arnl-subfields">
								<p class="description"><?php esc_html_e( 'Sessions are posted on the Page. Facebook does not allow apps to create Page or Group events, or to post in Groups, so the Net Log of each session shows ready-to-paste event details for creating those by hand.', 'ar-net-logger' ); ?></p>
								<p>
									<label for="arnl-facebook-page"><?php esc_html_e( 'Page ID', 'ar-net-logger' ); ?></label><br>
									<input type="text" id="arnl-facebook-page" name="facebook_page_id" class="regular-text code" value="<?php echo esc_attr( $net['facebook_page_id'] ); ?>" inputmode="numeric" maxlength="64">
								</p>
								<p>
									<label for="arnl-facebook-token"><?php esc_html_e( 'Page access token', 'ar-net-logger' ); ?></label><br>
									<input type="password" id="arnl-facebook-token" name="facebook_token" class="large-text code" value="" autocomplete="off" placeholder="<?php echo esc_attr( '' !== $net['facebook_token'] ? __( 'Saved – leave blank to keep it', 'ar-net-logger' ) : '' ); ?>">
									<?php if ( '' !== $net['facebook_token'] ) : ?>
										<br><label><input type="checkbox" name="facebook_token_clear" value="1"> <?php esc_html_e( 'Remove the saved token', 'ar-net-logger' ); ?></label>
									<?php endif; ?>
								</p>
								<?php if ( $facebook_sources ) : ?>
									<p>
										<label for="arnl-facebook-copy"><?php esc_html_e( 'Or post to the same Page as', 'ar-net-logger' ); ?></label>
										<?php ARNL_Admin::select( 'facebook_copy_from', array( '' => __( '— another net —', 'ar-net-logger' ) ) + $facebook_sources, '', array( 'id' => 'arnl-facebook-copy' ) ); ?>
									</p>
								<?php endif; ?>
								<p class="description"><?php esc_html_e( 'A Page access token with the pages_manage_posts permission (see the plugin README for the steps). It is not shown again after saving.', 'ar-net-logger' ); ?></p>
								<p>
									<label for="arnl-facebook-lead"><?php esc_html_e( 'Announce', 'ar-net-logger' ); ?></label>
									<input type="number" id="arnl-facebook-lead" name="facebook_lead_hours" min="0" max="336" class="small-text" value="<?php echo (int) $net['facebook_lead_hours']; ?>">
									<?php esc_html_e( 'hours before each session', 'ar-net-logger' ); ?>
									<span class="description"><?php esc_html_e( '(0 = only when you click "Post to Facebook" on the schedule; checked hourly)', 'ar-net-logger' ); ?></span>
								</p>
								<p>
									<label for="arnl-facebook-template"><?php esc_html_e( 'Post text', 'ar-net-logger' ); ?></label><br>
									<textarea id="arnl-facebook-template" name="facebook_template" rows="8" class="large-text"><?php echo esc_textarea( '' !== $net['facebook_template'] ? $net['facebook_template'] : ARNL_Facebook::default_template() ); ?></textarea>
									<span class="description"><?php esc_html_e( 'Placeholders: {net} {date} {time} {end_time} {timezone} {frequency} {digital} {system} {system_url} {ncs} {topic} {type} {description} {url}. Lines whose placeholders are all empty are left out. A cancelled session\'s post is marked CANCELLED.', 'ar-net-logger' ); ?></span>
								</p>
								<?php if ( $facebook_error ) : ?>
									<p class="arnl-error-text">
										<?php
										/* translators: 1: date and time, 2: error message. */
										echo esc_html( sprintf( __( 'Last error (%1$s): %2$s', 'ar-net-logger' ), $facebook_error['time'], $facebook_error['message'] ) );
										?>
									</p>
								<?php endif; ?>
								<p><button type="submit" class="button" name="arnl_after_save" value="facebook_test"><?php esc_html_e( 'Save and test the connection', 'ar-net-logger' ); ?></button></p>
							</div>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Display & check-ins', 'ar-net-logger' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Logo', 'ar-net-logger' ); ?></th>
						<td>
							<?php $logo = arnl_net_logo( $net, 'thumbnail', 'arnl-logo-preview-img' ); ?>
							<div class="arnl-logo-field<?php echo '' !== $logo ? ' has-logo' : ''; ?>">
								<span class="arnl-logo-preview"><?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(). ?></span>
								<input type="hidden" id="arnl-logo-id" name="logo_id" value="<?php echo (int) $net['logo_id']; ?>">
								<button type="button" class="button arnl-logo-choose" data-title="<?php esc_attr_e( 'Net logo', 'ar-net-logger' ); ?>" data-button="<?php esc_attr_e( 'Use this logo', 'ar-net-logger' ); ?>">
									<span class="arnl-logo-add"><?php esc_html_e( 'Choose logo', 'ar-net-logger' ); ?></span>
									<span class="arnl-logo-change"><?php esc_html_e( 'Change logo', 'ar-net-logger' ); ?></span>
								</button>
								<button type="button" class="button-link arnl-logo-remove"><?php esc_html_e( 'Remove logo', 'ar-net-logger' ); ?></button>
							</div>
							<p class="description"><?php esc_html_e( 'From the Media Library. Shown on the net and session pages, the nets directory, the Net Log and in Discord posts. A square image of at least 300 × 300 pixels works best.', 'ar-net-logger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-color"><?php esc_html_e( 'Calendar color', 'ar-net-logger' ); ?></label></th>
						<td><input type="text" id="arnl-color" name="color" class="arnl-color-picker" value="<?php echo esc_attr( $net['color'] ); ?>" data-default-color="#2271b1"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Online check-ins', 'ar-net-logger' ); ?></th>
						<td>
							<label><input type="checkbox" name="allow_checkins" value="1" data-arnl-toggle="arnl-window-fields" <?php checked( $net['allow_checkins'] ); ?>> <?php esc_html_e( 'Allow stations to check in from the website', 'ar-net-logger' ); ?></label>
							<div id="arnl-window-fields" class="arnl-subfields">
								<p>
									<label for="arnl-checkin-before"><?php esc_html_e( 'Check-in opens', 'ar-net-logger' ); ?></label>
									<?php ARNL_Admin::select( 'checkin_before', arnl_checkin_window_options( 'before' ), (string) $net['checkin_before'], array( 'id' => 'arnl-checkin-before' ) ); ?>
									<label for="arnl-checkin-after"><?php esc_html_e( 'and closes', 'ar-net-logger' ); ?></label>
									<?php ARNL_Admin::select( 'checkin_after', arnl_checkin_window_options( 'after' ), (string) $net['checkin_after'], array( 'id' => 'arnl-checkin-after' ) ); ?>
								</p>
								<p class="description"><?php esc_html_e( 'Up to an hour before the start and after the end. While Net Control has the net open, check-ins stay open regardless.', 'ar-net-logger' ); ?></p>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-status"><?php esc_html_e( 'Status', 'ar-net-logger' ); ?></label></th>
						<td>
							<?php
							ARNL_Admin::select(
								'status',
								array(
									'active'   => __( 'Active (shown on the calendar)', 'ar-net-logger' ),
									'inactive' => __( 'Inactive (hidden, schedule paused)', 'ar-net-logger' ),
								),
								$net['status'],
								array( 'id' => 'arnl-status' )
							);
							?>
						</td>
					</tr>
				</table>

				<?php submit_button( $is_new ? __( 'Create Net', 'ar-net-logger' ) : __( 'Save Net', 'ar-net-logger' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Save handler (create / update).
	 */
	public static function handle_save() {
		if ( ! current_user_can( ARNL_Install::CAP_MANAGE_NETS ) ) {
			wp_die( esc_html__( 'You are not allowed to manage nets.', 'ar-net-logger' ), 403 );
		}
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		check_admin_referer( 'arnl_save_net_' . $id );

		$text = array( 'name', 'slug', 'net_type', 'mode', 'band', 'frequency', 'access_info', 'recurrence', 'start_time', 'end_time', 'start_date', 'end_date', 'ncs_callsign', 'ncs_name', 'color', 'status', 'system_name', 'system_url', 'ysf_room', 'xlx_reflector', 'dstar_reflector', 'wiresx_room', 'dmr_talkgroup', 'checkin_before', 'checkin_after' );
		$data = array();
		foreach ( $text as $field ) {
			$data[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		foreach ( array( 'description', 'preamble' ) as $field ) {
			$data[ $field ] = isset( $_POST[ $field ] ) ? wp_kses_post( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		foreach ( array( 'days_of_week', 'weeks_of_month', 'months' ) as $field ) {
			$data[ $field ] = isset( $_POST[ $field ] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST[ $field ] ) ) : array();
		}
		foreach ( array( 'allow_checkins', 'linked_system', 'is_digital' ) as $field ) {
			$data[ $field ] = empty( $_POST[ $field ] ) ? 0 : 1;
		}
		$data['logo_id'] = isset( $_POST['logo_id'] ) ? absint( $_POST['logo_id'] ) : 0;
		// Discord and Facebook for this net.
		foreach ( array( 'discord_mode', 'facebook_mode' ) as $field ) {
			$data[ $field ] = empty( $_POST[ $field ] ) ? 'off' : 'on';
		}
		foreach ( array( 'discord_post', 'discord_include' ) as $field ) {
			$data[ $field ] = isset( $_POST[ $field ] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST[ $field ] ) ) : array();
		}
		foreach ( array( 'discord_username', 'facebook_page_id', 'facebook_lead_hours' ) as $field ) {
			$data[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		$data['facebook_template'] = isset( $_POST['facebook_template'] ) ? sanitize_textarea_field( wp_unslash( $_POST['facebook_template'] ) ) : '';

		// The webhook and token are secrets and are not shown again: a blank field
		// keeps the saved one, and another net's can be reused without showing it.
		$saved   = $id ? ARNL_Nets::get( $id ) : null;
		$sources = array();
		foreach ( array( 'discord', 'facebook' ) as $service ) {
			$from                = isset( $_POST[ $service . '_copy_from' ] ) ? absint( $_POST[ $service . '_copy_from' ] ) : 0;
			$sources[ $service ] = $from && $from !== $id && isset( self::sharing_sources( $saved ? $saved : array( 'id' => 0 ), $service )[ $from ] ) ? ARNL_Nets::get( $from ) : null;
		}
		$webhook = isset( $_POST['discord_webhook'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['discord_webhook'] ) ) ) : '';
		if ( '' === $webhook && $sources['discord'] ) {
			$webhook = $sources['discord']['discord_webhook'];
		} elseif ( '' === $webhook && empty( $_POST['discord_webhook_clear'] ) && $saved ) {
			$webhook = $saved['discord_webhook'];
		}
		$data['discord_webhook'] = $webhook;
		$token                   = isset( $_POST['facebook_token'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['facebook_token'] ) ) ) : '';
		if ( '' === $token && $sources['facebook'] ) {
			$token                    = $sources['facebook']['facebook_token'];
			$data['facebook_page_id'] = $sources['facebook']['facebook_page_id'];
		} elseif ( '' === $token && empty( $_POST['facebook_token_clear'] ) && $saved ) {
			$token = $saved['facebook_token'];
		}
		$data['facebook_token'] = $token;

		$result = $id ? ARNL_Nets::update( $id, $data ) : ARNL_Nets::insert( $data );
		$form   = ARNL_Admin::url(
			'arnl-nets',
			$id ? array(
				'action' => 'edit',
				'id'     => $id,
			) : array( 'action' => 'new' )
		);
		if ( is_wp_error( $result ) ) {
			// Never keep the secrets in the form state.
			unset( $data['discord_webhook'], $data['facebook_token'] );
			ARNL_Admin::fail( $form, $result, $data );
		}

		$id   = $id ? $id : (int) $result;
		$edit = ARNL_Admin::url(
			'arnl-nets',
			array(
				'action' => 'edit',
				'id'     => $id,
			)
		);
		$test = isset( $_POST['arnl_after_save'] ) ? sanitize_key( wp_unslash( $_POST['arnl_after_save'] ) ) : '';
		if ( 'discord_test' === $test ) {
			ARNL_Nets::flush_cache();
			$sent = ARNL_Discord::test( ARNL_Nets::get( $id ) );
			if ( is_wp_error( $sent ) ) {
				/* translators: %s: error message. */
				ARNL_Admin::fail( $edit . '#arnl-discord', sprintf( __( 'The net was saved, but the Discord test failed: %s', 'ar-net-logger' ), $sent->get_error_message() ) );
			}
			ARNL_Admin::redirect( $edit . '#arnl-discord', 'discord_test_sent' );
		}
		if ( 'facebook_test' === $test ) {
			ARNL_Nets::flush_cache();
			$page = ARNL_Facebook::test( ARNL_Nets::get( $id ) );
			if ( is_wp_error( $page ) ) {
				/* translators: %s: error message. */
				ARNL_Admin::fail( $edit . '#arnl-facebook', sprintf( __( 'The net was saved, but the Facebook test failed: %s', 'ar-net-logger' ), $page->get_error_message() ) );
			}
			ARNL_Admin::redirect( add_query_arg( 'arnl_page', rawurlencode( $page ), $edit ) . '#arnl-facebook', 'facebook_connected' );
		}
		ARNL_Admin::redirect( $edit, true === $result ? 'net_saved' : 'net_created' );
	}

	/**
	 * Other nets whose Discord channel / Facebook Page can be reused: ID => name.
	 *
	 * @param array  $net     Net being edited.
	 * @param string $service "discord" or "facebook".
	 * @return array<int,string>
	 */
	protected static function sharing_sources( array $net, $service ) {
		$sources = array();
		foreach ( ARNL_Nets::query( array( 'status' => 'any' ) ) as $other ) {
			if ( (int) $other['id'] === (int) $net['id'] ) {
				continue;
			}
			$ready = 'discord' === $service ? ARNL_Discord::valid_webhook( $other['discord_webhook'] ) : ARNL_Facebook::configured( $other );
			if ( $ready ) {
				$sources[ (int) $other['id'] ] = $other['name'];
			}
		}
		return $sources;
	}

	/**
	 * Delete handler.
	 */
	public static function handle_delete() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'arnl_delete_net_' . $id );
		if ( ! current_user_can( ARNL_Install::CAP_MANAGE_NETS ) ) {
			wp_die( esc_html__( 'You are not allowed to manage nets.', 'ar-net-logger' ), 403 );
		}
		ARNL_Nets::delete( $id );
		ARNL_Admin::redirect( ARNL_Admin::url( 'arnl-nets' ), 'net_deleted' );
	}
}
