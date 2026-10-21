<?php
/**
 * Calendar renderers (month grid and agenda list) shared by shortcodes and admin.
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Calendar HTML.
 */
class ARNL_Calendar {

	/**
	 * Default render options.
	 *
	 * @return array
	 */
	protected static function defaults() {
		return array(
			'link'           => null,  // callable( ARNL_Occurrence ): string URL.
			'show_time'      => true,
			'show_ncs'       => true,
			'show_topic'     => true,
			'show_frequency' => true,
			'checkin_links'  => false, // Show a "Check in" link while online check-in is open.
			'prev_url'       => '',
			'next_url'       => '',
			'title'          => '',
			'header_extra'   => '',
			'footer'         => '',
			'empty_text'     => __( 'No nets scheduled for this period.', 'ar-net-logger' ),
		);
	}

	/**
	 * Month grid.
	 *
	 * @param ARNL_Occurrence[] $occurrences Occurrences within the month.
	 * @param int               $year        Year.
	 * @param int               $month       Month (1-12).
	 * @param array             $opts        Options.
	 * @return string
	 */
	public static function month( array $occurrences, $year, $month, array $opts = array() ) {
		$opts  = wp_parse_args( $opts, self::defaults() );
		$tz    = wp_timezone();
		$first = new DateTimeImmutable( sprintf( '%04d-%02d-01', $year, $month ), $tz );
		$days  = (int) $first->format( 't' );
		$week0 = (int) get_option( 'start_of_week', 0 );
		$names = arnl_weekday_names();
		$short = arnl_weekday_names( true );
		$today = arnl_today();

		$by_day = array();
		foreach ( $occurrences as $o ) {
			$by_day[ $o->date ][] = $o;
		}

		$title = '' !== $opts['title'] ? $opts['title'] : wp_date( 'F Y', $first->modify( '+12 hours' )->getTimestamp(), $tz );

		ob_start();
		?>
		<div class="arnl-calendar arnl-calendar--month">
			<?php self::header( $title, $opts ); ?>
			<table class="arnl-cal-grid">
				<caption class="screen-reader-text"><?php echo esc_html( $title ); ?></caption>
				<thead>
					<tr>
						<?php
						for ( $i = 0; $i < 7; $i++ ) {
							$d = ( $week0 + $i ) % 7;
							printf( '<th scope="col"><abbr title="%1$s">%2$s</abbr></th>', esc_attr( $names[ $d ] ), esc_html( $short[ $d ] ) );
						}
						?>
					</tr>
				</thead>
				<tbody>
					<tr>
					<?php
					$lead = ( (int) $first->format( 'w' ) - $week0 + 7 ) % 7;
					for ( $i = 0; $i < $lead; $i++ ) {
						echo '<td class="arnl-cal-day arnl-cal-pad" aria-hidden="true"></td>';
					}
					$col = $lead;
					for ( $day = 1; $day <= $days; $day++ ) {
						if ( 7 === $col ) {
							echo "</tr>\n<tr>";
							$col = 0;
						}
						$ymd     = sprintf( '%04d-%02d-%02d', $year, $month, $day );
						$list    = isset( $by_day[ $ymd ] ) ? $by_day[ $ymd ] : array();
						$classes = array( 'arnl-cal-day' );
						if ( $ymd === $today ) {
							$classes[] = 'arnl-cal-today';
						}
						if ( $ymd < $today ) {
							$classes[] = 'arnl-cal-past';
						}
						$classes[] = $list ? 'arnl-has-events' : 'arnl-no-events';
						$label     = arnl_format_date( $ymd, 'l, ' . get_option( 'date_format' ) );
						?>
						<td class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
							<div class="arnl-cal-date">
								<span class="arnl-cal-daynum" aria-hidden="true"><?php echo (int) $day; ?></span>
								<span class="arnl-cal-fulldate"><?php echo esc_html( $label ); ?></span>
							</div>
							<?php if ( $list ) : ?>
								<ul class="arnl-cal-events">
									<?php
									foreach ( $list as $o ) {
										self::month_item( $o, $opts );
									}
									?>
								</ul>
							<?php endif; ?>
						</td>
						<?php
						++$col;
					}
					for ( ; $col < 7; $col++ ) {
						echo '<td class="arnl-cal-day arnl-cal-pad" aria-hidden="true"></td>';
					}
					?>
					</tr>
				</tbody>
			</table>
			<?php if ( ! $occurrences ) : ?>
				<p class="arnl-cal-empty"><?php echo esc_html( $opts['empty_text'] ); ?></p>
			<?php endif; ?>
			<?php echo $opts['footer']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by the caller from escaped parts. ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * One entry in a month cell.
	 *
	 * @param ARNL_Occurrence $o    Occurrence.
	 * @param array           $opts Options.
	 */
	protected static function month_item( ARNL_Occurrence $o, array $opts ) {
		$url   = is_callable( $opts['link'] ) ? call_user_func( $opts['link'], $o ) : '';
		$badge = self::badge( $o );
		$tag   = $url ? 'a' : 'span';
		?>
		<li class="<?php echo esc_attr( self::item_classes( $o ) ); ?>" style="--arnl-color: <?php echo esc_attr( $o->net['color'] ); ?>">
			<<?php echo esc_html( $tag ); ?> class="arnl-cal-event-link"<?php echo $url ? ' href="' . esc_url( $url ) . '"' : ''; ?>>
				<?php if ( $opts['show_time'] ) : ?>
					<span class="arnl-cal-time"><?php echo esc_html( arnl_format_time( $o->start_time ) ); ?></span>
				<?php endif; ?>
				<span class="arnl-cal-name"><?php echo esc_html( $o->title() ); ?></span>
				<?php if ( $badge ) : ?>
					<span class="arnl-badge arnl-badge--<?php echo esc_attr( $badge[0] ); ?>"><?php echo esc_html( $badge[1] ); ?></span>
				<?php endif; ?>
				<?php if ( $opts['show_topic'] && '' !== $o->topic ) : ?>
					<span class="arnl-cal-topic"><?php echo esc_html( $o->topic ); ?></span>
				<?php endif; ?>
				<?php if ( $opts['show_ncs'] && '' !== $o->ncs_callsign ) : ?>
					<span class="arnl-cal-ncs">
						<?php
						/* translators: %s: NCS callsign. */
						echo esc_html( sprintf( __( 'NCS: %s', 'ar-net-logger' ), $o->ncs_callsign ) );
						?>
					</span>
				<?php endif; ?>
			</<?php echo esc_html( $tag ); ?>>
		</li>
		<?php
	}

	/**
	 * Agenda list grouped by day.
	 *
	 * @param ARNL_Occurrence[] $occurrences Occurrences.
	 * @param array             $opts        Options.
	 * @return string
	 */
	public static function agenda( array $occurrences, array $opts = array() ) {
		$opts   = wp_parse_args( $opts, self::defaults() );
		$by_day = array();
		foreach ( $occurrences as $o ) {
			$by_day[ $o->date ][] = $o;
		}
		$today = arnl_today();

		ob_start();
		?>
		<div class="arnl-calendar arnl-calendar--list">
			<?php
			if ( '' !== $opts['title'] || $opts['prev_url'] || $opts['next_url'] || '' !== $opts['header_extra'] ) {
				self::header( $opts['title'], $opts );
			}
			?>
			<?php if ( ! $by_day ) : ?>
				<p class="arnl-cal-empty"><?php echo esc_html( $opts['empty_text'] ); ?></p>
			<?php endif; ?>
			<?php foreach ( $by_day as $date => $list ) : ?>
				<section class="arnl-list-day<?php echo $date === $today ? ' arnl-list-today' : ''; ?>">
					<h4 class="arnl-list-date">
						<?php echo esc_html( arnl_format_date( $date, 'l, ' . get_option( 'date_format' ) ) ); ?>
						<?php if ( $date === $today ) : ?>
							<span class="arnl-badge arnl-badge--today"><?php esc_html_e( 'Today', 'ar-net-logger' ); ?></span>
						<?php endif; ?>
					</h4>
					<ul class="arnl-list-events">
						<?php foreach ( $list as $o ) : ?>
							<?php
							$url   = is_callable( $opts['link'] ) ? call_user_func( $opts['link'], $o ) : '';
							$badge = self::badge( $o );
							?>
							<li class="<?php echo esc_attr( self::item_classes( $o ) ); ?>" style="--arnl-color: <?php echo esc_attr( $o->net['color'] ); ?>">
								<?php if ( $opts['show_time'] ) : ?>
									<span class="arnl-list-time"><?php echo esc_html( self::time_range( $o ) ); ?></span>
								<?php endif; ?>
								<span class="arnl-list-body">
									<?php if ( $url ) : ?>
										<a class="arnl-list-name" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $o->title() ); ?></a>
									<?php else : ?>
										<span class="arnl-list-name"><?php echo esc_html( $o->title() ); ?></span>
									<?php endif; ?>
									<?php if ( $badge ) : ?>
										<span class="arnl-badge arnl-badge--<?php echo esc_attr( $badge[0] ); ?>"><?php echo esc_html( $badge[1] ); ?></span>
									<?php endif; ?>
									<?php if ( $opts['show_topic'] && '' !== $o->topic ) : ?>
										<span class="arnl-list-topic"><?php echo esc_html( $o->topic ); ?></span>
									<?php endif; ?>
									<span class="arnl-list-meta">
										<?php
										$meta = array();
										if ( $opts['show_frequency'] ) {
											$freq = arnl_describe_frequency( $o->net, $o->frequency );
											if ( '' !== $freq ) {
												$meta[] = esc_html( $freq );
											}
										}
										if ( $opts['show_ncs'] && '' !== $o->ncs_callsign ) {
											$meta[] = esc_html( self::ncs_label( $o ) );
										}
										echo implode( ' <span class="arnl-sep" aria-hidden="true">|</span> ', $meta ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
										?>
									</span>
									<?php if ( $url && $opts['checkin_links'] && $o->checkins_open() ) : ?>
										<a class="arnl-button arnl-button--small" href="<?php echo esc_url( $url . '#arnl-checkin' ); ?>"><?php esc_html_e( 'Check in', 'ar-net-logger' ); ?></a>
									<?php endif; ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endforeach; ?>
			<?php echo $opts['footer']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by the caller from escaped parts. ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Title bar with navigation.
	 *
	 * @param string $title Title.
	 * @param array  $opts  Options.
	 */
	protected static function header( $title, array $opts ) {
		?>
		<div class="arnl-cal-header">
			<?php if ( $opts['prev_url'] ) : ?>
				<a class="arnl-cal-nav arnl-cal-prev" href="<?php echo esc_url( $opts['prev_url'] ); ?>" rel="nofollow">
					<span aria-hidden="true">&lsaquo;</span><span class="screen-reader-text"><?php esc_html_e( 'Previous', 'ar-net-logger' ); ?></span>
				</a>
			<?php endif; ?>
			<?php if ( '' !== $title ) : ?>
				<h3 class="arnl-cal-title"><?php echo esc_html( $title ); ?></h3>
			<?php endif; ?>
			<?php if ( $opts['next_url'] ) : ?>
				<a class="arnl-cal-nav arnl-cal-next" href="<?php echo esc_url( $opts['next_url'] ); ?>" rel="nofollow">
					<span class="screen-reader-text"><?php esc_html_e( 'Next', 'ar-net-logger' ); ?></span><span aria-hidden="true">&rsaquo;</span>
				</a>
			<?php endif; ?>
		</div>
		<?php
		echo $opts['header_extra']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by the caller from escaped parts.
	}

	/**
	 * CSS classes for an occurrence.
	 *
	 * @param ARNL_Occurrence $o Occurrence.
	 * @return string
	 */
	protected static function item_classes( ARNL_Occurrence $o ) {
		$classes = array( 'arnl-cal-event', 'arnl-status-' . $o->status, 'arnl-net-' . $o->net['slug'], 'arnl-type-' . $o->net['net_type'] );
		if ( $o->is_rescheduled() ) {
			$classes[] = 'arnl-rescheduled';
		}
		return implode( ' ', array_map( 'sanitize_html_class', $classes ) );
	}

	/**
	 * Status badge for an occurrence: array( key, label ) or null.
	 *
	 * @param ARNL_Occurrence $o Occurrence.
	 * @return array|null
	 */
	public static function badge( ARNL_Occurrence $o ) {
		if ( $o->is_cancelled() ) {
			return array( 'cancelled', __( 'Cancelled', 'ar-net-logger' ) );
		}
		if ( 'active' === $o->status ) {
			return array( 'active', __( 'On the air', 'ar-net-logger' ) );
		}
		if ( $o->is_rescheduled() ) {
			return array( 'rescheduled', __( 'Rescheduled', 'ar-net-logger' ) );
		}
		if ( $o->is_one_off() && ARNL_Recurrence::ONCE !== $o->net['recurrence'] ) {
			return array( 'special', __( 'Special session', 'ar-net-logger' ) );
		}
		return null;
	}

	/**
	 * "7:00 PM – 8:00 PM EDT".
	 *
	 * @param ARNL_Occurrence $o Occurrence.
	 * @return string
	 */
	public static function time_range( ARNL_Occurrence $o ) {
		/* translators: 1: start time, 2: end time, 3: timezone abbreviation. */
		return sprintf( __( '%1$s – %2$s %3$s', 'ar-net-logger' ), arnl_format_time( $o->start_time ), arnl_format_time( $o->end_time ), arnl_timezone_label( $o->start()->getTimestamp() ) );
	}

	/**
	 * "NCS: W1AW (Hiram)".
	 *
	 * @param ARNL_Occurrence $o Occurrence.
	 * @return string
	 */
	public static function ncs_label( ARNL_Occurrence $o ) {
		$ncs = '' !== $o->ncs_name ? sprintf( '%s (%s)', $o->ncs_callsign, $o->ncs_name ) : $o->ncs_callsign;
		/* translators: %s: Net Control Station callsign and name. */
		return sprintf( __( 'NCS: %s', 'ar-net-logger' ), $ncs );
	}
}
