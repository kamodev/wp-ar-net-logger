<?php
/**
 * Nets repository (CRUD). A net is a named, usually recurring, on-air meeting.
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nets data access.
 */
class ARNL_Nets {

	/**
	 * Request cache of hydrated nets keyed by ID.
	 *
	 * @var array<int,array>
	 */
	protected static $cache = array();

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		$tables = ARNL_Install::tables();
		return $tables['nets'];
	}

	/**
	 * Default values for a new net.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'id'                  => 0,
			'name'                => '',
			'slug'                => '',
			'description'         => '',
			'net_type'            => 'directed',
			'mode'                => 'fm',
			'band'                => '2m',
			'frequency'           => '',
			'access_info'         => '',
			'start_time'          => '19:00:00',
			'end_time'            => '20:00:00',
			'recurrence'          => ARNL_Recurrence::WEEKLY,
			'days_of_week'        => array(),
			'weeks_of_month'      => array(),
			'months'              => array(),
			'start_date'          => '',
			'end_date'            => '',
			'ncs_callsign'        => '',
			'ncs_name'            => '',
			'preamble'            => '',
			'color'               => '#2271b1',
			'allow_checkins'      => 1,
			'checkin_before'      => -1,
			'checkin_after'       => -1,
			'logo_id'             => 0,
			'is_digital'          => 0,
			'ysf_room'            => '',
			'xlx_reflector'       => '',
			'dstar_reflector'     => '',
			'wiresx_room'         => '',
			'dmr_talkgroup'       => '',
			'linked_system'       => 0,
			'system_name'         => '',
			'system_url'          => '',
			'discord_mode'        => 'off',
			'discord_webhook'     => '',
			'discord_username'    => '',
			'discord_post'        => array( 'checkins', 'open_close' ),
			'discord_include'     => array( 'name', 'location', 'announcements' ),
			'facebook_mode'       => 'off',
			'facebook_page_id'    => '',
			'facebook_token'      => '',
			'facebook_lead_hours' => 24,
			'facebook_template'   => '',
			'status'              => 'active',
			'created_at'          => '',
			'updated_at'          => '',
		);
	}

	/**
	 * Get a net by ID.
	 *
	 * @param int $id Net ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}
		if ( ! isset( self::$cache[ $id ] ) ) {
			$table = self::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
			if ( ! $row ) {
				return null;
			}
			self::$cache[ $id ] = self::hydrate( $row );
		}
		return self::$cache[ $id ];
	}

	/**
	 * Get a net by slug.
	 *
	 * @param string $slug Slug.
	 * @return array|null
	 */
	public static function get_by_slug( $slug ) {
		$nets = self::query(
			array(
				'status' => 'any',
				'slug'   => array( $slug ),
				'number' => 1,
			)
		);
		return $nets ? reset( $nets ) : null;
	}

	/**
	 * Get a net by ID or slug.
	 *
	 * @param int|string $ref ID or slug.
	 * @return array|null
	 */
	public static function find( $ref ) {
		return is_numeric( $ref ) ? self::get( (int) $ref ) : self::get_by_slug( sanitize_title( $ref ) );
	}

	/**
	 * Query nets.
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type string       $status   active | inactive | any.
	 *     @type int[]|string $include  IDs (or IDs and slugs mixed; slugs are matched against slug).
	 *     @type string[]     $slug     Slugs.
	 *     @type string[]     $net_type Net type keys.
	 *     @type string[]     $mode     Mode keys.
	 *     @type string[]     $band     Band keys.
	 *     @type string       $search   Search in name / frequency / NCS.
	 *     @type string       $orderby  name | start_time | net_type | status | created_at.
	 *     @type string       $order    ASC | DESC.
	 *     @type int          $number   Limit (0 = no limit).
	 *     @type int          $offset   Offset.
	 *     @type bool         $count    Return a count instead of rows.
	 * }
	 * @return array<int,array>|int Nets keyed by ID, or a count.
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'status'   => 'active',
				'include'  => array(),
				'slug'     => array(),
				'net_type' => array(),
				'mode'     => array(),
				'band'     => array(),
				'search'   => '',
				'orderby'  => 'name',
				'order'    => 'ASC',
				'number'   => 0,
				'offset'   => 0,
				'count'    => false,
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( in_array( $args['status'], array( 'active', 'inactive' ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		// "include" may mix IDs and slugs (shortcode net="12,club-net").
		$ids   = array();
		$slugs = array_map( 'sanitize_title', arnl_parse_list( $args['slug'] ) );
		foreach ( arnl_parse_list( $args['include'] ) as $ref ) {
			if ( is_numeric( $ref ) ) {
				$ids[] = (int) $ref;
			} else {
				$slugs[] = sanitize_title( $ref );
			}
		}
		if ( $ids || $slugs ) {
			$or = array();
			if ( $ids ) {
				$or[]   = 'id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
				$params = array_merge( $params, $ids );
			}
			if ( $slugs ) {
				$or[]   = 'slug IN (' . implode( ',', array_fill( 0, count( $slugs ), '%s' ) ) . ')';
				$params = array_merge( $params, $slugs );
			}
			$where[] = '(' . implode( ' OR ', $or ) . ')';
		}

		foreach ( array( 'net_type', 'mode', 'band' ) as $column ) {
			$values = arnl_parse_list( $args[ $column ] );
			if ( $values ) {
				$where[] = "{$column} IN (" . implode( ',', array_fill( 0, count( $values ), '%s' ) ) . ')';
				$params  = array_merge( $params, $values );
			}
		}

		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(name LIKE %s OR frequency LIKE %s OR ncs_callsign LIKE %s OR description LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$table     = self::table();
		$where_sql = implode( ' AND ', $where );

		if ( $args['count'] ) {
			$sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
			return (int) $wpdb->get_var( $params ? $wpdb->prepare( $sql, $params ) : $sql );
		}

		$orderby = in_array( $args['orderby'], array( 'name', 'start_time', 'net_type', 'status', 'created_at', 'frequency' ), true ) ? $args['orderby'] : 'name';
		$order   = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
		$sql     = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order}, id ASC";
		if ( $args['number'] > 0 ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$params[] = (int) $args['number'];
			$params[] = (int) $args['offset'];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $params ? $wpdb->prepare( $sql, $params ) : $sql, ARRAY_A );
		$nets = array();
		foreach ( (array) $rows as $row ) {
			$net                       = self::hydrate( $row );
			$nets[ $net['id'] ]        = $net;
			self::$cache[ $net['id'] ] = $net;
		}
		return $nets;
	}

	/**
	 * Create a net.
	 *
	 * @param array $data Field values.
	 * @return int|WP_Error New ID.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$row = self::prepare( array_merge( self::defaults(), $data ) );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$now               = current_time( 'mysql' );
		$row['created_at'] = $now;
		$row['updated_at'] = $now;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $wpdb->insert( self::table(), $row ) ) {
			return new WP_Error( 'arnl_db_error', __( 'The net could not be saved.', 'ar-net-logger' ) );
		}
		$id = (int) $wpdb->insert_id;
		do_action( 'arnl_net_saved', $id, true );
		return $id;
	}

	/**
	 * Update a net. Missing fields keep their current value.
	 *
	 * @param int   $id   Net ID.
	 * @param array $data Field values.
	 * @return true|WP_Error
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$current = self::get( $id );
		if ( ! $current ) {
			return new WP_Error( 'arnl_not_found', __( 'Net not found.', 'ar-net-logger' ) );
		}
		$row = self::prepare( array_merge( $current, $data ), (int) $id );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$row['updated_at'] = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( false === $wpdb->update( self::table(), $row, array( 'id' => (int) $id ) ) ) {
			return new WP_Error( 'arnl_db_error', __( 'The net could not be saved.', 'ar-net-logger' ) );
		}
		unset( self::$cache[ (int) $id ] );
		do_action( 'arnl_net_saved', (int) $id, false );
		return true;
	}

	/**
	 * Delete a net together with its sessions and check-ins.
	 *
	 * @param int $id Net ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( ! self::get( $id ) ) {
			return false;
		}
		do_action( 'arnl_before_delete_net', $id );
		foreach ( ARNL_Events::query( array( 'net_id' => $id ) ) as $event ) {
			ARNL_Events::delete( $event['id'] );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = (bool) $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
		unset( self::$cache[ $id ] );
		return $deleted;
	}

	/**
	 * Sanitize and validate a full set of fields into a database row.
	 *
	 * @param array $data Field values (merged with defaults or the current record).
	 * @param int   $id   ID being updated (for slug uniqueness).
	 * @return array|WP_Error
	 */
	public static function prepare( array $data, $id = 0 ) {
		$errors = new WP_Error();

		$name = substr( sanitize_text_field( (string) $data['name'] ), 0, 190 );
		if ( '' === $name ) {
			$errors->add( 'arnl_name', __( 'Please enter a net name.', 'ar-net-logger' ) );
		}

		$recurrence = array_key_exists( (string) $data['recurrence'], arnl_recurrence_types() ) ? (string) $data['recurrence'] : ARNL_Recurrence::WEEKLY;
		$rule       = ARNL_Recurrence::normalize(
			array(
				'recurrence'     => $recurrence,
				'days_of_week'   => is_array( $data['days_of_week'] ) ? $data['days_of_week'] : arnl_parse_list( $data['days_of_week'] ),
				'weeks_of_month' => is_array( $data['weeks_of_month'] ) ? $data['weeks_of_month'] : arnl_parse_list( $data['weeks_of_month'] ),
				'months'         => is_array( $data['months'] ) ? $data['months'] : arnl_parse_list( $data['months'] ),
			)
		);
		$days       = array_unique( $rule['days_of_week'] );
		$weeks      = array_unique( $rule['weeks_of_month'] );
		$months     = array_unique( $rule['months'] );
		sort( $days );
		sort( $months );
		usort(
			$weeks,
			static function ( $a, $b ) {
				return ( 'last' === $a ? 6 : $a ) <=> ( 'last' === $b ? 6 : $b );
			}
		);
		if ( 12 === count( $months ) ) {
			$months = array();
		}

		$start_date = arnl_sanitize_date( $data['start_date'] );
		$end_date   = arnl_sanitize_date( $data['end_date'] );

		if ( in_array( $recurrence, array( ARNL_Recurrence::WEEKLY, ARNL_Recurrence::BIWEEKLY, ARNL_Recurrence::MONTHLY ), true ) && ! $days ) {
			$errors->add( 'arnl_days', __( 'Choose at least one day of the week.', 'ar-net-logger' ) );
		}
		if ( ARNL_Recurrence::MONTHLY === $recurrence && ! $weeks ) {
			$errors->add( 'arnl_weeks', __( 'Choose at least one week of the month (1st, 2nd, ..., Last).', 'ar-net-logger' ) );
		}
		if ( ARNL_Recurrence::ONCE === $recurrence && '' === $start_date ) {
			$errors->add( 'arnl_start_date', __( 'A one-time net needs a date. Set the start date.', 'ar-net-logger' ) );
		}
		if ( ARNL_Recurrence::BIWEEKLY === $recurrence && '' === $start_date ) {
			// Every-other-week schedules need an anchor week.
			$start_date = arnl_today();
		}
		if ( '' !== $start_date && '' !== $end_date && $end_date < $start_date ) {
			$errors->add( 'arnl_end_date', __( 'The end date must be on or after the start date.', 'ar-net-logger' ) );
		}

		// Discord and Facebook are set up for each net.
		$discord_mode    = 'off' === (string) $data['discord_mode'] ? 'off' : 'on';
		$discord_webhook = trim( (string) $data['discord_webhook'] );
		if ( '' !== $discord_webhook && ! ARNL_Discord::valid_webhook( $discord_webhook ) ) {
			$errors->add( 'arnl_discord_webhook', __( 'Enter a Discord webhook URL (https://discord.com/api/webhooks/...) or leave it blank.', 'ar-net-logger' ) );
		}
		if ( 'on' === $discord_mode && '' === $discord_webhook ) {
			$errors->add( 'arnl_discord_webhook', __( 'Enter the Discord webhook URL for this net, or turn Discord off for it.', 'ar-net-logger' ) );
		}
		$facebook_mode    = 'off' === (string) $data['facebook_mode'] ? 'off' : 'on';
		$facebook_page_id = substr( preg_replace( '/\D/', '', (string) $data['facebook_page_id'] ), 0, 64 );
		$facebook_token   = preg_replace( '/[^A-Za-z0-9_|.\-]/', '', (string) $data['facebook_token'] );
		if ( 'on' === $facebook_mode && ( '' === $facebook_page_id || '' === $facebook_token ) ) {
			$errors->add( 'arnl_facebook', __( 'Enter the Facebook Page ID and Page access token for this net, or turn Facebook off for it.', 'ar-net-logger' ) );
		}
		$facebook_template = trim( substr( sanitize_textarea_field( (string) $data['facebook_template'] ), 0, 2000 ) );
		if ( ARNL_Facebook::default_template() === $facebook_template ) {
			$facebook_template = '';
		}

		$band          = array_key_exists( (string) $data['band'], arnl_bands() ) ? (string) $data['band'] : '';
		$linked_system = ! empty( $data['linked_system'] ) && in_array( $band, arnl_linked_system_bands(), true ) ? 1 : 0;
		$system_url    = trim( (string) $data['system_url'] );
		if ( '' !== $system_url ) {
			$system_url = esc_url_raw( false === strpos( $system_url, '://' ) ? 'https://' . $system_url : $system_url, array( 'http', 'https' ) );
			$host       = '' !== $system_url ? (string) wp_parse_url( $system_url, PHP_URL_HOST ) : '';
			if ( '' === $host || false === strpos( $host, '.' ) ) {
				$errors->add( 'arnl_system_url', __( 'Enter the repeater system website as a web address, for example https://example.org/', 'ar-net-logger' ) );
			}
		}

		$logo_id = absint( $data['logo_id'] );
		if ( $logo_id && ! wp_attachment_is_image( $logo_id ) ) {
			$logo_id = 0;
		}

		$start_time = arnl_sanitize_time( $data['start_time'], '' );
		if ( '' === $start_time ) {
			$errors->add( 'arnl_start_time', __( 'Please enter a valid start time.', 'ar-net-logger' ) );
			$start_time = '19:00:00';
		}
		$end_time = arnl_sanitize_time( $data['end_time'], '' );
		if ( '' === $end_time ) {
			$end_time = gmdate( 'H:i:s', strtotime( '1970-01-01 ' . $start_time . ' UTC' ) + HOUR_IN_SECONDS );
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		$slug = sanitize_title( '' !== (string) $data['slug'] ? $data['slug'] : $name );
		if ( '' === $slug || is_numeric( $slug ) ) {
			$slug = 'net-' . $slug;
		}

		$color = sanitize_hex_color( (string) $data['color'] );

		return array(
			'name'                => $name,
			'slug'                => self::unique_slug( substr( $slug, 0, 180 ), (int) $id ),
			'description'         => wp_kses_post( (string) $data['description'] ),
			'net_type'            => array_key_exists( (string) $data['net_type'], arnl_net_types() ) ? (string) $data['net_type'] : 'other',
			'mode'                => array_key_exists( (string) $data['mode'], arnl_modes() ) ? (string) $data['mode'] : '',
			'band'                => $band,
			'frequency'           => substr( sanitize_text_field( (string) $data['frequency'] ), 0, 100 ),
			'access_info'         => substr( sanitize_text_field( (string) $data['access_info'] ), 0, 255 ),
			'start_time'          => $start_time,
			'end_time'            => $end_time,
			'recurrence'          => $recurrence,
			'days_of_week'        => implode( ',', $days ),
			'weeks_of_month'      => ARNL_Recurrence::MONTHLY === $recurrence ? implode( ',', $weeks ) : '',
			'months'              => implode( ',', $months ),
			'start_date'          => '' !== $start_date ? $start_date : null,
			'end_date'            => '' !== $end_date ? $end_date : null,
			'ncs_callsign'        => arnl_sanitize_callsign( $data['ncs_callsign'] ),
			'ncs_name'            => substr( sanitize_text_field( (string) $data['ncs_name'] ), 0, 100 ),
			'preamble'            => wp_kses_post( (string) $data['preamble'] ),
			'color'               => $color ? $color : '#2271b1',
			'allow_checkins'      => empty( $data['allow_checkins'] ) ? 0 : 1,
			'checkin_before'      => self::window_minutes( $data['checkin_before'] ),
			'checkin_after'       => self::window_minutes( $data['checkin_after'] ),
			'logo_id'             => $logo_id,
			'is_digital'          => empty( $data['is_digital'] ) ? 0 : 1,
			'ysf_room'            => substr( sanitize_text_field( (string) $data['ysf_room'] ), 0, 100 ),
			'xlx_reflector'       => substr( sanitize_text_field( (string) $data['xlx_reflector'] ), 0, 100 ),
			'dstar_reflector'     => substr( sanitize_text_field( (string) $data['dstar_reflector'] ), 0, 100 ),
			'wiresx_room'         => substr( sanitize_text_field( (string) $data['wiresx_room'] ), 0, 100 ),
			'dmr_talkgroup'       => substr( sanitize_text_field( (string) $data['dmr_talkgroup'] ), 0, 100 ),
			'linked_system'       => $linked_system,
			'system_name'         => substr( sanitize_text_field( (string) $data['system_name'] ), 0, 100 ),
			'system_url'          => substr( $system_url, 0, 255 ),
			'discord_mode'        => $discord_mode,
			'discord_webhook'     => $discord_webhook,
			'discord_username'    => substr( sanitize_text_field( (string) $data['discord_username'] ), 0, 80 ),
			'discord_post'        => self::option_list( $data['discord_post'], array_keys( ARNL_Discord::post_options() ) ),
			'discord_include'     => self::option_list( $data['discord_include'], array_keys( ARNL_Discord::include_options() ) ),
			'facebook_mode'       => $facebook_mode,
			'facebook_page_id'    => $facebook_page_id,
			'facebook_token'      => $facebook_token,
			'facebook_lead_hours' => max( 0, min( 336, (int) $data['facebook_lead_hours'] ) ),
			'facebook_template'   => $facebook_template,
			'status'              => 'inactive' === $data['status'] ? 'inactive' : 'active',
		);
	}

	/**
	 * Keep the allowed keys of a checkbox list, in their canonical order, as a comma list.
	 *
	 * @param array|string $value   Submitted keys (array or comma list).
	 * @param string[]     $allowed Allowed keys.
	 * @return string
	 */
	public static function option_list( $value, array $allowed ) {
		$chosen = is_array( $value ) ? array_map( 'strval', $value ) : arnl_parse_list( (string) $value );
		return implode( ',', array_values( array_intersect( $allowed, $chosen ) ) );
	}

	/**
	 * Minutes of a net's own online check-in window: -1 for the site default, otherwise 0-60.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */
	public static function window_minutes( $value ) {
		if ( '' === $value || null === $value || ! is_numeric( $value ) || (int) $value < 0 ) {
			return -1;
		}
		return min( ARNL_Settings::MAX_CHECKIN_WINDOW, (int) $value );
	}

	/**
	 * Make a slug unique among nets.
	 *
	 * @param string $slug Desired slug.
	 * @param int    $id   ID of the net being updated.
	 * @return string
	 */
	public static function unique_slug( $slug, $id = 0 ) {
		global $wpdb;
		$table     = self::table();
		$candidate = $slug;
		$suffix    = 2;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s AND id <> %d", $candidate, (int) $id ) ) ) {
			$candidate = $slug . '-' . ( $suffix++ );
		}
		return $candidate;
	}

	/**
	 * Convert a database row into a typed record.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function hydrate( array $row ) {
		$net = array_merge( self::defaults(), $row );

		$net['id']             = (int) $net['id'];
		$net['allow_checkins'] = (int) $net['allow_checkins'];
		foreach ( array( 'checkin_before', 'checkin_after', 'logo_id', 'is_digital', 'linked_system', 'facebook_lead_hours' ) as $key ) {
			$net[ $key ] = (int) $net[ $key ];
		}
		foreach ( array( 'discord_post', 'discord_include' ) as $key ) {
			$net[ $key ] = is_array( $net[ $key ] ) ? $net[ $key ] : arnl_parse_list( (string) $net[ $key ] );
		}
		$net['facebook_token']    = (string) $net['facebook_token'];
		$net['facebook_template'] = (string) $net['facebook_template'];
		$net['description']       = (string) $net['description'];
		$net['preamble']          = (string) $net['preamble'];
		$net['start_date']        = (string) $net['start_date'];
		$net['end_date']          = (string) $net['end_date'];
		$net['days_of_week']      = array_map( 'intval', arnl_parse_list( $net['days_of_week'] ) );
		$net['months']            = array_map( 'intval', arnl_parse_list( $net['months'] ) );
		$weeks                    = array();
		foreach ( arnl_parse_list( $net['weeks_of_month'] ) as $w ) {
			$weeks[] = 'last' === $w ? 'last' : (int) $w;
		}
		$net['weeks_of_month'] = $weeks;
		return $net;
	}

	/**
	 * The recurrence rule of a net.
	 *
	 * @param array $net Net.
	 * @return array
	 */
	public static function rule( array $net ) {
		return array(
			'recurrence'     => $net['recurrence'],
			'days_of_week'   => $net['days_of_week'],
			'weeks_of_month' => $net['weeks_of_month'],
			'months'         => $net['months'],
			'start_date'     => $net['start_date'],
			'end_date'       => $net['end_date'],
		);
	}

	/**
	 * Clear the request cache.
	 */
	public static function flush_cache() {
		self::$cache = array();
	}
}
