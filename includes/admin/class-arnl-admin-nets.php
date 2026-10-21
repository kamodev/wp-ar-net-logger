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
							<input type="text" id="arnl-ncs-callsign" name="ncs_callsign" class="arnl-callsign-input" value="<?php echo esc_attr( $net['ncs_callsign'] ); ?>" maxlength="20" placeholder="<?php esc_attr_e( 'Callsign', 'ar-net-logger' ); ?>">
							<label class="screen-reader-text" for="arnl-ncs-name"><?php esc_html_e( 'NCS name', 'ar-net-logger' ); ?></label>
							<input type="text" id="arnl-ncs-name" name="ncs_name" value="<?php echo esc_attr( $net['ncs_name'] ); ?>" maxlength="100" placeholder="<?php esc_attr_e( 'Name', 'ar-net-logger' ); ?>">
							<p class="description"><?php esc_html_e( 'Can be changed for each session on the schedule.', 'ar-net-logger' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="arnl-preamble"><?php esc_html_e( 'Net preamble / script', 'ar-net-logger' ); ?></label></th>
						<td>
							<textarea id="arnl-preamble" name="preamble" class="large-text" rows="8" placeholder="<?php esc_attr_e( 'This is [callsign], net control for the ... net. This is a directed net. Is there any emergency or priority traffic? ...', 'ar-net-logger' ); ?>"><?php echo esc_textarea( $net['preamble'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Shown to the NCS on the Net Log screen while running the net.', 'ar-net-logger' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Display & check-ins', 'ar-net-logger' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="arnl-color"><?php esc_html_e( 'Calendar color', 'ar-net-logger' ); ?></label></th>
						<td><input type="text" id="arnl-color" name="color" class="arnl-color-picker" value="<?php echo esc_attr( $net['color'] ); ?>" data-default-color="#2271b1"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Online check-ins', 'ar-net-logger' ); ?></th>
						<td>
							<label><input type="checkbox" name="allow_checkins" value="1" <?php checked( $net['allow_checkins'] ); ?>> <?php esc_html_e( 'Allow stations to check in from the website', 'ar-net-logger' ); ?></label>
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

		$text = array( 'name', 'slug', 'net_type', 'mode', 'band', 'frequency', 'access_info', 'recurrence', 'start_time', 'end_time', 'start_date', 'end_date', 'ncs_callsign', 'ncs_name', 'color', 'status' );
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
		$data['allow_checkins'] = empty( $_POST['allow_checkins'] ) ? 0 : 1;

		$result = $id ? ARNL_Nets::update( $id, $data ) : ARNL_Nets::insert( $data );
		$form   = ARNL_Admin::url(
			'arnl-nets',
			$id ? array(
				'action' => 'edit',
				'id'     => $id,
			) : array( 'action' => 'new' )
		);
		if ( is_wp_error( $result ) ) {
			ARNL_Admin::fail( $form, $result, $data );
		}

		$id = $id ? $id : (int) $result;
		ARNL_Admin::redirect(
			ARNL_Admin::url(
				'arnl-nets',
				array(
					'action' => 'edit',
					'id'     => $id,
				)
			),
			true === $result ? 'net_saved' : 'net_created'
		);
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
