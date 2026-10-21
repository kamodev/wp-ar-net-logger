<?php
/**
 * Nets list table.
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List of nets with search, status views and bulk actions.
 */
class ARNL_Nets_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'net',
				'plural'   => 'nets',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Current status view.
	 *
	 * @return string
	 */
	protected function status() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'any';
		return in_array( $status, array( 'active', 'inactive' ), true ) ? $status : 'any';
	}

	/**
	 * Load items.
	 */
	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- list filters.
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'name';
		$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'asc';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$per_page = $this->get_items_per_page( 'arnl_nets_per_page', 20 );
		$args     = array(
			'status'  => $this->status(),
			'search'  => $search,
			'orderby' => $orderby,
			'order'   => $order,
		);

		$total       = ARNL_Nets::query( array_merge( $args, array( 'count' => true ) ) );
		$this->items = array_values(
			ARNL_Nets::query(
				array_merge(
					$args,
					array(
						'number' => $per_page,
						'offset' => ( $this->get_pagenum() - 1 ) * $per_page,
					)
				)
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'name' );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'        => '<input type="checkbox">',
			'name'      => __( 'Net', 'ar-net-logger' ),
			'schedule'  => __( 'Schedule', 'ar-net-logger' ),
			'frequency' => __( 'Frequency / Mode', 'ar-net-logger' ),
			'net_type'  => __( 'Type', 'ar-net-logger' ),
			'ncs'       => __( 'Default NCS', 'ar-net-logger' ),
			'status'    => __( 'Status', 'ar-net-logger' ),
			'shortcode' => __( 'Shortcode', 'ar-net-logger' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'name'      => array( 'name', false ),
			'schedule'  => array( 'start_time', false ),
			'frequency' => array( 'frequency', false ),
			'net_type'  => array( 'net_type', false ),
			'status'    => array( 'status', false ),
		);
	}

	/**
	 * Status views.
	 *
	 * @return array
	 */
	protected function get_views() {
		$current = $this->status();
		$views   = array();
		$labels  = array(
			'any'      => __( 'All', 'ar-net-logger' ),
			'active'   => __( 'Active', 'ar-net-logger' ),
			'inactive' => __( 'Inactive', 'ar-net-logger' ),
		);
		foreach ( $labels as $status => $label ) {
			$count            = ARNL_Nets::query(
				array(
					'status' => $status,
					'count'  => true,
				)
			);
			$url              = ARNL_Admin::url( 'arnl-nets', 'any' === $status ? array() : array( 'status' => $status ) );
			$views[ $status ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( $url ),
				$current === $status ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				(int) $count
			);
		}
		return $views;
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'activate'   => __( 'Activate', 'ar-net-logger' ),
			'deactivate' => __( 'Deactivate', 'ar-net-logger' ),
			'delete'     => __( 'Delete', 'ar-net-logger' ),
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param array $item Net.
	 * @return string
	 */
	protected function column_cb( $item ) {
		/* translators: %s: net name. */
		return sprintf( '<label class="screen-reader-text" for="net_%1$d">%2$s</label><input type="checkbox" id="net_%1$d" name="net_ids[]" value="%1$d">', $item['id'], esc_html( sprintf( __( 'Select %s', 'ar-net-logger' ), $item['name'] ) ) );
	}

	/**
	 * Name column with row actions.
	 *
	 * @param array $item Net.
	 * @return string
	 */
	protected function column_name( $item ) {
		$edit    = ARNL_Admin::url(
			'arnl-nets',
			array(
				'action' => 'edit',
				'id'     => $item['id'],
			)
		);
		$actions = array(
			'edit'     => '<a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'ar-net-logger' ) . '</a>',
			'schedule' => '<a href="' . esc_url( ARNL_Admin::url( 'arnl-schedule', array( 'net' => $item['id'] ) ) ) . '">' . esc_html__( 'Sessions', 'ar-net-logger' ) . '</a>',
			'view'     => 'active' === $item['status'] && '' !== arnl_net_url( $item ) ? '<a href="' . esc_url( arnl_net_url( $item ) ) . '">' . esc_html__( 'View', 'ar-net-logger' ) . '</a>' : '',
			'delete'   => '<a class="arnl-confirm" data-arnl-confirm="' . esc_attr__( 'Delete this net together with all of its sessions and check-in logs?', 'ar-net-logger' ) . '" href="' . esc_url( ARNL_Admin::action_url( 'arnl_delete_net', array( 'id' => $item['id'] ), 'arnl_delete_net_' . $item['id'] ) ) . '">' . esc_html__( 'Delete', 'ar-net-logger' ) . '</a>',
		);
		return sprintf(
			'<span class="arnl-swatch" style="--arnl-color: %1$s" aria-hidden="true"></span> <strong><a class="row-title" href="%2$s">%3$s</a></strong>%4$s',
			esc_attr( $item['color'] ),
			esc_url( $edit ),
			esc_html( $item['name'] ),
			$this->row_actions( array_filter( $actions ) )
		);
	}

	/**
	 * Default column output.
	 *
	 * @param array  $item        Net.
	 * @param string $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'schedule':
				return esc_html( arnl_describe_schedule( $item ) );
			case 'frequency':
				return esc_html( arnl_describe_frequency( $item ) );
			case 'net_type':
				return esc_html( arnl_label( arnl_net_types(), $item['net_type'] ) );
			case 'ncs':
				return esc_html( trim( $item['ncs_callsign'] . ( '' !== $item['ncs_name'] ? ' – ' . $item['ncs_name'] : '' ) ) );
			case 'status':
				return 'active' === $item['status']
					? '<span class="arnl-badge arnl-badge--scheduled">' . esc_html__( 'Active', 'ar-net-logger' ) . '</span>'
					: '<span class="arnl-badge arnl-badge--cancelled">' . esc_html__( 'Inactive', 'ar-net-logger' ) . '</span>';
			case 'shortcode':
				return '<code class="arnl-copy">[arnl_calendar net="' . esc_html( $item['slug'] ) . '"]</code>';
		}
		return '';
	}

	/**
	 * Empty table message.
	 */
	public function no_items() {
		esc_html_e( 'No nets yet. Add your first net to start building the schedule.', 'ar-net-logger' );
	}
}
