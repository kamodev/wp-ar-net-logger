<?php
/**
 * Front-end shortcodes.
 *
 * [arnl_calendar]  Month grid or list calendar with filters, navigation and subscribe links.
 * [arnl_upcoming]  Compact list of the next sessions.
 * [arnl_nets]      Directory of nets with schedule, frequency and next session.
 * [arnl_event]     Details of one session, with online check-in and the check-in list.
 * [arnl_checkin]   Check-in form for a net's current / next session.
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcodes.
 */
class ARNL_Shortcodes {

	/**
	 * Whether a session detail view was already rendered on this request.
	 *
	 * @var bool
	 */
	protected static $event_rendered = false;

	/**
	 * Register shortcodes and assets.
	 */
	public static function init() {
		add_shortcode( 'arnl_calendar', array( __CLASS__, 'calendar' ) );
		add_shortcode( 'arnl_upcoming', array( __CLASS__, 'upcoming' ) );
		add_shortcode( 'arnl_nets', array( __CLASS__, 'nets' ) );
		add_shortcode( 'arnl_event', array( __CLASS__, 'event' ) );
		add_shortcode( 'arnl_checkin', array( __CLASS__, 'checkin' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register (not enqueue) front-end assets.
	 */
	public static function register_assets() {
		wp_register_style( 'arnl-public', ARNL_URL . 'assets/css/arnl-public.css', array(), ARNL_VERSION );
		wp_register_script( 'arnl-public', ARNL_URL . 'assets/js/arnl-public.js', array(), ARNL_VERSION, true );
	}

	/**
	 * Enqueue assets when a shortcode renders.
	 */
	protected static function enqueue() {
		if ( ! wp_style_is( 'arnl-public', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'arnl-public' );
		wp_enqueue_script( 'arnl-public' );
	}

	/**
	 * Interpret yes/no shortcode attributes.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	protected static function yes( $value ) {
		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'yes', 'true', 'on' ), true );
	}

	/**
	 * Schedule filters from shortcode attributes, narrowed by the visitor's net filter.
	 *
	 * @param array $atts Attributes.
	 * @return array
	 */
	protected static function filters( array $atts ) {
		$filters = array(
			'net'      => arnl_parse_list( $atts['net'] ),
			'net_type' => arnl_parse_list( $atts['type'] ),
			'mode'     => arnl_parse_list( $atts['mode'] ),
			'band'     => arnl_parse_list( $atts['band'] ),
		);
		$chosen  = self::visitor_net( $atts );
		if ( $chosen ) {
			$filters['net'] = array( (string) $chosen['id'] );
		}
		return $filters;
	}

	/**
	 * Net chosen in the visitor filter, if allowed by the shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return array|null
	 */
	protected static function visitor_net( array $atts ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$ref = isset( $_GET['arnl_net'] ) ? sanitize_title( wp_unslash( $_GET['arnl_net'] ) ) : '';
		if ( '' === $ref || empty( $atts['show_filter'] ) || ! self::yes( $atts['show_filter'] ) ) {
			return null;
		}
		$allowed = self::allowed_nets( $atts );
		foreach ( $allowed as $net ) {
			if ( $net['slug'] === $ref ) {
				return $net;
			}
		}
		return null;
	}

	/**
	 * Nets selected by the shortcode attributes.
	 *
	 * @param array $atts Attributes.
	 * @return array<int,array>
	 */
	protected static function allowed_nets( array $atts ) {
		return ARNL_Nets::query(
			array(
				'include'  => arnl_parse_list( $atts['net'] ),
				'net_type' => arnl_parse_list( $atts['type'] ),
				'mode'     => arnl_parse_list( $atts['mode'] ),
				'band'     => arnl_parse_list( $atts['band'] ),
			)
		);
	}

	/**
	 * When a session is selected (?arnl_event=...), render it instead of the listing.
	 *
	 * @return string|null
	 */
	protected static function maybe_event_view() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view.
		$key = isset( $_GET['arnl_event'] ) ? sanitize_text_field( wp_unslash( $_GET['arnl_event'] ) ) : '';
		if ( '' === $key || self::$event_rendered ) {
			return null;
		}
		self::$event_rendered = true;
		$occurrence           = ARNL_Schedule::find( $key );
		$back                 = remove_query_arg( array( 'arnl_event', 'arnl_msg', 'arnl_cs' ), arnl_current_url() );
		if ( ! $occurrence || 'active' !== $occurrence->net['status'] ) {
			return '<div class="arnl-event arnl-event--missing"><p>' . esc_html__( 'That net session could not be found.', 'ar-net-logger' ) . '</p><p><a href="' . esc_url( $back ) . '">' . esc_html__( '&larr; Back', 'ar-net-logger' ) . '</a></p></div>';
		}
		return self::render_event( $occurrence, $back );
	}

	/**
	 * [arnl_calendar]
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function calendar( $atts ) {
		$atts = shortcode_atts(
			array(
				'view'           => 'month',
				'net'            => '',
				'type'           => '',
				'mode'           => '',
				'band'           => '',
				'month'          => '',
				'start'          => '',
				'days'           => 30,
				'show_cancelled' => 'yes',
				'show_ncs'       => 'yes',
				'show_topic'     => 'yes',
				'show_time'      => 'yes',
				'show_frequency' => 'yes',
				'show_legend'    => 'yes',
				'show_subscribe' => 'yes',
				'show_filter'    => 'no',
				'nav'            => 'yes',
				'link'           => 'yes',
				'title'          => '',
			),
			$atts,
			'arnl_calendar'
		);
		self::enqueue();

		if ( self::yes( $atts['link'] ) ) {
			$event_view = self::maybe_event_view();
			if ( null !== $event_view ) {
				return $event_view;
			}
		}

		$filters = self::filters( $atts );
		$current = remove_query_arg( array( 'arnl_event', 'arnl_msg', 'arnl_cs' ), arnl_current_url() );
		$opts    = array(
			'link'           => self::yes( $atts['link'] ) ? 'arnl_event_url' : null,
			'show_time'      => self::yes( $atts['show_time'] ),
			'show_ncs'       => self::yes( $atts['show_ncs'] ),
			'show_topic'     => self::yes( $atts['show_topic'] ),
			'show_frequency' => self::yes( $atts['show_frequency'] ),
			'title'          => sanitize_text_field( $atts['title'] ),
			'header_extra'   => self::yes( $atts['show_filter'] ) ? self::filter_form( $atts ) : '',
		);
		$base    = array_merge(
			$filters,
			array( 'include_cancelled' => self::yes( $atts['show_cancelled'] ) )
		);

		if ( 'list' === $atts['view'] ) {
			$days = max( 1, min( 366, absint( $atts['days'] ) ) );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation.
			$from = isset( $_GET['arnl_from'] ) && self::yes( $atts['nav'] ) ? arnl_sanitize_date( sanitize_text_field( wp_unslash( $_GET['arnl_from'] ) ) ) : '';
			if ( '' === $from ) {
				$from = arnl_sanitize_date( $atts['start'] );
			}
			$from        = '' !== $from ? $from : arnl_today();
			$start       = new DateTimeImmutable( $from, wp_timezone() );
			$to          = $start->modify( '+' . ( $days - 1 ) . ' days' )->format( 'Y-m-d' );
			$occurrences = ARNL_Schedule::occurrences( array_merge( $base, compact( 'from', 'to' ) ) );
			if ( self::yes( $atts['nav'] ) ) {
				$opts['prev_url'] = add_query_arg( 'arnl_from', $start->modify( '-' . $days . ' days' )->format( 'Y-m-d' ), $current );
				$opts['next_url'] = add_query_arg( 'arnl_from', $start->modify( '+' . $days . ' days' )->format( 'Y-m-d' ), $current );
				if ( '' === $opts['title'] ) {
					/* translators: 1: first date, 2: last date. */
					$opts['title'] = sprintf( __( '%1$s – %2$s', 'ar-net-logger' ), arnl_format_date( $from ), arnl_format_date( $to ) );
				}
			}
			$opts['footer']        = self::footer( $occurrences, $atts, $filters );
			$opts['checkin_links'] = true;
			return ARNL_Calendar::agenda( $occurrences, $opts );
		}

		// Month view.
		$month = '';
		if ( self::yes( $atts['nav'] ) && isset( $_GET['arnl_month'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation.
			$month = sanitize_text_field( wp_unslash( $_GET['arnl_month'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
			$month = preg_match( '/^\d{4}-\d{2}$/', $atts['month'] ) ? $atts['month'] : arnl_now()->format( 'Y-m' );
		}
		list( $year, $mon ) = array_map( 'intval', explode( '-', $month ) );
		if ( $mon < 1 || $mon > 12 || $year < 1970 || $year > 2200 ) {
			list( $year, $mon ) = array_map( 'intval', explode( '-', arnl_now()->format( 'Y-m' ) ) );
		}
		$first       = new DateTimeImmutable( sprintf( '%04d-%02d-01', $year, $mon ), wp_timezone() );
		$occurrences = ARNL_Schedule::occurrences(
			array_merge(
				$base,
				array(
					'from' => $first->format( 'Y-m-d' ),
					'to'   => $first->format( 'Y-m-t' ),
				)
			)
		);
		if ( self::yes( $atts['nav'] ) ) {
			$opts['prev_url'] = add_query_arg( 'arnl_month', $first->modify( '-1 month' )->format( 'Y-m' ), $current );
			$opts['next_url'] = add_query_arg( 'arnl_month', $first->modify( '+1 month' )->format( 'Y-m' ), $current );
		}
		$opts['footer'] = self::footer( $occurrences, $atts, $filters );
		return ARNL_Calendar::month( $occurrences, $year, $mon, $opts );
	}

	/**
	 * Visitor net filter (GET form).
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	protected static function filter_form( array $atts ) {
		$nets = self::allowed_nets( $atts );
		if ( count( $nets ) < 2 ) {
			return '';
		}
		$chosen = self::visitor_net( $atts );
		$keep   = array();
		// Preserve other query args (e.g. ?page_id=5 on sites without pretty permalinks).
		foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( is_string( $value ) && ! in_array( $key, array( 'arnl_net', 'arnl_event', 'arnl_msg', 'arnl_cs' ), true ) ) {
				$keep[ sanitize_key( $key ) ] = sanitize_text_field( wp_unslash( $value ) );
			}
		}
		$id = 'arnl-filter-' . wp_unique_id();
		ob_start();
		?>
		<form class="arnl-filter" method="get" action="<?php echo esc_url( strtok( arnl_current_url(), '?' ) ); ?>">
			<?php foreach ( $keep as $key => $value ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
			<?php endforeach; ?>
			<label for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Show', 'ar-net-logger' ); ?></label>
			<select id="<?php echo esc_attr( $id ); ?>" name="arnl_net" onchange="this.form.submit()">
				<option value=""><?php esc_html_e( 'All nets', 'ar-net-logger' ); ?></option>
				<?php foreach ( $nets as $net ) : ?>
					<option value="<?php echo esc_attr( $net['slug'] ); ?>" <?php selected( $chosen && $chosen['id'] === $net['id'] ); ?>><?php echo esc_html( $net['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<noscript><button type="submit"><?php esc_html_e( 'Filter', 'ar-net-logger' ); ?></button></noscript>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Legend, timezone note and subscribe links under a calendar.
	 *
	 * @param ARNL_Occurrence[] $occurrences Sessions shown.
	 * @param array             $atts        Attributes.
	 * @param array             $filters     Active filters.
	 * @return string
	 */
	protected static function footer( array $occurrences, array $atts, array $filters ) {
		$html = '<div class="arnl-cal-footer">';

		if ( self::yes( $atts['show_legend'] ) ) {
			$nets = array();
			foreach ( $occurrences as $o ) {
				$nets[ $o->net_id ] = $o->net;
			}
			if ( count( $nets ) > 1 ) {
				$html .= '<ul class="arnl-legend">';
				foreach ( $nets as $net ) {
					$html .= '<li style="--arnl-color: ' . esc_attr( $net['color'] ) . '"><span class="arnl-swatch" aria-hidden="true"></span>' . esc_html( $net['name'] ) . '</li>';
				}
				$html .= '</ul>';
			}
		}

		$tz    = wp_timezone_string();
		$html .= '<p class="arnl-tz-note">' . esc_html(
			sprintf(
				/* translators: %s: timezone name. */
				__( 'Times are shown in %s.', 'ar-net-logger' ),
				false !== strpos( $tz, '/' ) ? str_replace( '_', ' ', $tz ) : 'UTC' . ( 'UTC' === $tz ? '' : $tz )
			)
		) . '</p>';

		if ( self::yes( $atts['show_subscribe'] ) && ARNL_Settings::get( 'ics_enabled' ) ) {
			$feed  = arnl_ics_url(
				array(
					'net'  => $filters['net'],
					'type' => $filters['net_type'],
					'mode' => $filters['mode'],
					'band' => $filters['band'],
				)
			);
			$html .= '<p class="arnl-subscribe"><span class="arnl-subscribe-label">' . esc_html__( 'Add these nets to your calendar:', 'ar-net-logger' ) . '</span> '
				. '<a href="' . esc_url( arnl_webcal_url( $feed ), array( 'webcal', 'http', 'https' ) ) . '">' . esc_html__( 'Subscribe (Apple / Outlook)', 'ar-net-logger' ) . '</a>'
				. ' <span class="arnl-sep" aria-hidden="true">|</span> <a href="' . esc_url( add_query_arg( 'cid', rawurlencode( arnl_webcal_url( $feed ) ), 'https://calendar.google.com/calendar/r' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Google Calendar', 'ar-net-logger' ) . '</a>'
				. ' <span class="arnl-sep" aria-hidden="true">|</span> <a href="' . esc_url( add_query_arg( 'download', 1, $feed ) ) . '">' . esc_html__( 'Download .ics', 'ar-net-logger' ) . '</a></p>';
		}

		return $html . '</div>';
	}

	/**
	 * [arnl_upcoming]
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function upcoming( $atts ) {
		$atts = shortcode_atts(
			array(
				'net'            => '',
				'type'           => '',
				'mode'           => '',
				'band'           => '',
				'limit'          => 5,
				'days'           => 60,
				'show_cancelled' => 'no',
				'show_ncs'       => 'yes',
				'show_topic'     => 'yes',
				'show_time'      => 'yes',
				'show_frequency' => 'yes',
				'link'           => 'yes',
				'title'          => '',
				'show_filter'    => 'no',
			),
			$atts,
			'arnl_upcoming'
		);
		self::enqueue();

		if ( self::yes( $atts['link'] ) ) {
			$event_view = self::maybe_event_view();
			if ( null !== $event_view ) {
				return $event_view;
			}
		}

		$now  = arnl_now();
		$list = ARNL_Schedule::occurrences(
			array_merge(
				self::filters( $atts ),
				array(
					'from'              => $now->modify( '-1 day' )->format( 'Y-m-d' ),
					'to'                => $now->modify( '+' . max( 1, min( 400, absint( $atts['days'] ) ) ) . ' days' )->format( 'Y-m-d' ),
					'include_cancelled' => self::yes( $atts['show_cancelled'] ),
				)
			)
		);
		// Drop sessions that already ended (yesterday's late nets are kept while still running).
		$list = array_values(
			array_filter(
				$list,
				static function ( ARNL_Occurrence $o ) use ( $now ) {
					return 'active' === $o->status || ( $o->end() >= $now && 'completed' !== $o->status );
				}
			)
		);
		$list = array_slice( $list, 0, max( 1, absint( $atts['limit'] ) ) );

		return ARNL_Calendar::agenda(
			$list,
			array(
				'link'           => self::yes( $atts['link'] ) ? 'arnl_event_url' : null,
				'show_time'      => self::yes( $atts['show_time'] ),
				'show_ncs'       => self::yes( $atts['show_ncs'] ),
				'show_topic'     => self::yes( $atts['show_topic'] ),
				'show_frequency' => self::yes( $atts['show_frequency'] ),
				'title'          => sanitize_text_field( $atts['title'] ),
				'checkin_links'  => true,
				'empty_text'     => __( 'No upcoming nets.', 'ar-net-logger' ),
			)
		);
	}

	/**
	 * [arnl_nets]
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function nets( $atts ) {
		$atts = shortcode_atts(
			array(
				'net'              => '',
				'type'             => '',
				'mode'             => '',
				'band'             => '',
				'orderby'          => 'name',
				'show_description' => 'yes',
				'show_next'        => 'yes',
				'show_ncs'         => 'yes',
				'show_subscribe'   => 'yes',
				'link'             => 'yes',
			),
			$atts,
			'arnl_nets'
		);
		self::enqueue();

		if ( self::yes( $atts['link'] ) ) {
			$event_view = self::maybe_event_view();
			if ( null !== $event_view ) {
				return $event_view;
			}
		}

		$nets = ARNL_Nets::query(
			array(
				'include'  => arnl_parse_list( $atts['net'] ),
				'net_type' => arnl_parse_list( $atts['type'] ),
				'mode'     => arnl_parse_list( $atts['mode'] ),
				'band'     => arnl_parse_list( $atts['band'] ),
				'orderby'  => in_array( $atts['orderby'], array( 'name', 'start_time', 'net_type', 'frequency' ), true ) ? $atts['orderby'] : 'name',
			)
		);
		if ( ! $nets ) {
			return '<div class="arnl-nets"><p class="arnl-cal-empty">' . esc_html__( 'No nets found.', 'ar-net-logger' ) . '</p></div>';
		}

		ob_start();
		echo '<div class="arnl-nets">';
		foreach ( $nets as $net ) {
			$next = self::yes( $atts['show_next'] ) ? ARNL_Schedule::current_or_next( $net['id'] ) : null;
			$freq = arnl_describe_frequency( $net );
			?>
			<section class="arnl-net arnl-type-<?php echo esc_attr( $net['net_type'] ); ?>" style="--arnl-color: <?php echo esc_attr( $net['color'] ); ?>">
				<h3 class="arnl-net-name"><?php echo esc_html( $net['name'] ); ?></h3>
				<dl class="arnl-details">
					<dt><?php esc_html_e( 'When', 'ar-net-logger' ); ?></dt>
					<dd><?php echo esc_html( arnl_describe_schedule( $net ) . ' ' . arnl_timezone_label() ); ?></dd>
					<?php if ( '' !== $freq ) : ?>
						<dt><?php esc_html_e( 'Frequency', 'ar-net-logger' ); ?></dt>
						<dd><?php echo esc_html( $freq ); ?></dd>
					<?php endif; ?>
					<dt><?php esc_html_e( 'Type', 'ar-net-logger' ); ?></dt>
					<dd><?php echo esc_html( arnl_label( arnl_net_types(), $net['net_type'] ) ); ?></dd>
					<?php if ( self::yes( $atts['show_ncs'] ) && '' !== $net['ncs_callsign'] ) : ?>
						<dt><?php esc_html_e( 'Net Control', 'ar-net-logger' ); ?></dt>
						<dd><?php echo esc_html( $net['ncs_callsign'] . ( '' !== $net['ncs_name'] ? ' – ' . $net['ncs_name'] : '' ) ); ?></dd>
					<?php endif; ?>
					<?php if ( $next ) : ?>
						<dt><?php esc_html_e( 'Next session', 'ar-net-logger' ); ?></dt>
						<dd>
							<?php
							$label = arnl_format_date( $next->date, 'l, ' . get_option( 'date_format' ) ) . ', ' . ARNL_Calendar::time_range( $next );
							if ( self::yes( $atts['link'] ) ) {
								printf( '<a href="%1$s">%2$s</a>', esc_url( arnl_event_url( $next ) ), esc_html( $label ) );
							} else {
								echo esc_html( $label );
							}
							if ( '' !== $next->topic ) {
								echo ' <span class="arnl-list-topic">' . esc_html( $next->topic ) . '</span>';
							}
							?>
						</dd>
					<?php endif; ?>
				</dl>
				<?php if ( self::yes( $atts['show_description'] ) && '' !== $net['description'] ) : ?>
					<div class="arnl-net-description"><?php echo wp_kses_post( wpautop( $net['description'] ) ); ?></div>
				<?php endif; ?>
				<?php if ( self::yes( $atts['show_subscribe'] ) && ARNL_Settings::get( 'ics_enabled' ) ) : ?>
					<?php $feed = arnl_ics_url( array( 'net' => $net['slug'] ) ); ?>
					<p class="arnl-subscribe">
						<a href="<?php echo esc_url( arnl_webcal_url( $feed ), array( 'webcal', 'http', 'https' ) ); ?>"><?php esc_html_e( 'Subscribe to this net', 'ar-net-logger' ); ?></a>
						<span class="arnl-sep" aria-hidden="true">|</span>
						<a href="<?php echo esc_url( add_query_arg( 'download', 1, $feed ) ); ?>"><?php esc_html_e( 'Download .ics', 'ar-net-logger' ); ?></a>
					</p>
				<?php endif; ?>
			</section>
			<?php
		}
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * [arnl_event]
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function event( $atts ) {
		$atts = shortcode_atts(
			array(
				'event' => '',
				'net'   => '',
			),
			$atts,
			'arnl_event'
		);
		self::enqueue();

		$event_view = self::maybe_event_view();
		if ( null !== $event_view ) {
			return $event_view;
		}

		$occurrence = null;
		if ( '' !== $atts['event'] ) {
			$occurrence = ARNL_Schedule::find( sanitize_text_field( $atts['event'] ) );
		} elseif ( '' !== $atts['net'] ) {
			$net        = ARNL_Nets::find( $atts['net'] );
			$occurrence = $net ? ARNL_Schedule::current_or_next( $net['id'] ) : null;
		}
		if ( ! $occurrence || 'active' !== $occurrence->net['status'] ) {
			return '<div class="arnl-event arnl-event--missing"><p>' . esc_html__( 'Choose a net session from the calendar to see its details.', 'ar-net-logger' ) . '</p></div>';
		}
		return self::render_event( $occurrence );
	}

	/**
	 * [arnl_checkin]
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function checkin( $atts ) {
		$atts = shortcode_atts(
			array(
				'net'      => '',
				'show_log' => 'yes',
			),
			$atts,
			'arnl_checkin'
		);
		self::enqueue();

		$net = '' !== $atts['net'] ? ARNL_Nets::find( $atts['net'] ) : null;
		if ( ! $net || 'active' !== $net['status'] ) {
			return current_user_can( ARNL_Install::CAP_MANAGE_NETS )
				? '<p class="arnl-notice arnl-notice--error">' . esc_html__( '[arnl_checkin] needs a valid net="slug" attribute.', 'ar-net-logger' ) . '</p>'
				: '';
		}
		$o = ARNL_Schedule::current_or_next( $net['id'] );
		if ( ! $o ) {
			return '<div class="arnl-checkin-box"><p>' . esc_html__( 'There is no upcoming session of this net.', 'ar-net-logger' ) . '</p></div>';
		}

		$html  = '<div class="arnl-checkin-box">';
		$html .= '<p class="arnl-checkin-session"><strong>' . esc_html( $o->title() ) . '</strong> – ' . esc_html( arnl_format_date( $o->date, 'l, ' . get_option( 'date_format' ) ) . ', ' . ARNL_Calendar::time_range( $o ) );
		if ( '' !== $o->ncs_callsign ) {
			$html .= ' – ' . esc_html( ARNL_Calendar::ncs_label( $o ) );
		}
		$html .= '</p>';
		$html .= ARNL_Checkin_Handler::form( $o, arnl_current_url() );
		if ( self::yes( $atts['show_log'] ) ) {
			$html .= ARNL_Checkin_Handler::public_log( $o );
		}
		return $html . '</div>';
	}

	/**
	 * Session detail view.
	 *
	 * @param ARNL_Occurrence $o    Session.
	 * @param string          $back "Back" link URL, if any.
	 * @return string
	 */
	public static function render_event( ARNL_Occurrence $o, $back = '' ) {
		$net   = $o->net;
		$badge = ARNL_Calendar::badge( $o );
		$self  = arnl_current_url();
		$freq  = arnl_describe_frequency( $net, $o->frequency );

		ob_start();
		?>
		<article class="arnl-event arnl-status-<?php echo esc_attr( $o->status ); ?>" style="--arnl-color: <?php echo esc_attr( $net['color'] ); ?>">
			<?php if ( '' !== $back ) : ?>
				<p class="arnl-back"><a href="<?php echo esc_url( $back ); ?>"><?php esc_html_e( '&larr; Back to the calendar', 'ar-net-logger' ); ?></a></p>
			<?php endif; ?>
			<header class="arnl-event-header">
				<h2 class="arnl-event-title">
					<?php echo esc_html( $o->title() ); ?>
					<?php if ( $badge ) : ?>
						<span class="arnl-badge arnl-badge--<?php echo esc_attr( $badge[0] ); ?>"><?php echo esc_html( $badge[1] ); ?></span>
					<?php endif; ?>
				</h2>
				<p class="arnl-event-when">
					<time datetime="<?php echo esc_attr( $o->start()->format( DATE_ATOM ) ); ?>">
						<?php echo esc_html( arnl_format_date( $o->date, 'l, ' . get_option( 'date_format' ) ) . ' · ' . ARNL_Calendar::time_range( $o ) ); ?>
					</time>
				</p>
			</header>
			<dl class="arnl-details">
				<?php if ( '' !== $o->topic ) : ?>
					<dt><?php esc_html_e( 'Topic', 'ar-net-logger' ); ?></dt>
					<dd><?php echo esc_html( $o->topic ); ?></dd>
				<?php endif; ?>
				<dt><?php esc_html_e( 'Net Control Station', 'ar-net-logger' ); ?></dt>
				<dd>
					<?php
					if ( '' !== $o->ncs_callsign ) {
						echo '<strong class="arnl-callsign">' . esc_html( $o->ncs_callsign ) . '</strong>' . ( '' !== $o->ncs_name ? ' – ' . esc_html( $o->ncs_name ) : '' );
					} else {
						esc_html_e( 'To be announced', 'ar-net-logger' );
					}
					?>
				</dd>
				<?php if ( '' !== $freq ) : ?>
					<dt><?php esc_html_e( 'Frequency', 'ar-net-logger' ); ?></dt>
					<dd><?php echo esc_html( $freq ); ?></dd>
				<?php endif; ?>
				<dt><?php esc_html_e( 'Net type', 'ar-net-logger' ); ?></dt>
				<dd><?php echo esc_html( arnl_label( arnl_net_types(), $net['net_type'] ) ); ?></dd>
				<?php if ( $o->is_rescheduled() ) : ?>
					<dt><?php esc_html_e( 'Originally scheduled', 'ar-net-logger' ); ?></dt>
					<dd><?php echo esc_html( arnl_format_date( $o->occurrence_date, 'l, ' . get_option( 'date_format' ) ) . ', ' . arnl_format_time( $net['start_time'] ) ); ?></dd>
				<?php endif; ?>
				<?php if ( ARNL_Recurrence::NONE !== $net['recurrence'] && ARNL_Recurrence::ONCE !== $net['recurrence'] ) : ?>
					<dt><?php esc_html_e( 'Regular schedule', 'ar-net-logger' ); ?></dt>
					<dd><?php echo esc_html( arnl_describe_schedule( $net ) ); ?></dd>
				<?php endif; ?>
			</dl>
			<?php if ( '' !== $o->notes ) : ?>
				<div class="arnl-event-notes"><?php echo wp_kses_post( wpautop( $o->notes ) ); ?></div>
			<?php endif; ?>
			<?php if ( '' !== $net['description'] ) : ?>
				<div class="arnl-net-description"><?php echo wp_kses_post( wpautop( $net['description'] ) ); ?></div>
			<?php endif; ?>
			<?php if ( ARNL_Settings::get( 'ics_enabled' ) && ! $o->is_cancelled() ) : ?>
				<?php $link = arnl_event_url( $o, $self ); ?>
				<p class="arnl-add-to-calendar">
					<span class="arnl-subscribe-label"><?php esc_html_e( 'Add to calendar:', 'ar-net-logger' ); ?></span>
					<a href="<?php echo esc_url( arnl_ics_url( array( 'event' => $o->key() ) ) ); ?>"><?php esc_html_e( 'Apple / Outlook (.ics)', 'ar-net-logger' ); ?></a>
					<span class="arnl-sep" aria-hidden="true">|</span>
					<a href="<?php echo esc_url( ARNL_ICal::google_url( $o, $link ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Google', 'ar-net-logger' ); ?></a>
					<span class="arnl-sep" aria-hidden="true">|</span>
					<a href="<?php echo esc_url( ARNL_ICal::outlook_url( $o, $link ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Outlook.com', 'ar-net-logger' ); ?></a>
				</p>
			<?php endif; ?>
			<?php
			echo ARNL_Checkin_Handler::form( $o, $self ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the renderer.
			echo ARNL_Checkin_Handler::public_log( $o ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the renderer.
			?>
		</article>
		<?php
		return (string) ob_get_clean();
	}
}
