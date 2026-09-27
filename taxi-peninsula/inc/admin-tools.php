<?php
/**
 * Bookings → Calendar, Reports and Run sheet screens.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'tp_admin_tools_menu', 15 );
function tp_admin_tools_menu() {
	$parent = 'edit.php?post_type=tp_booking';
	add_submenu_page( $parent, __( 'Booking calendar', 'taxi-peninsula' ), __( 'Calendar', 'taxi-peninsula' ), 'edit_tp_bookings', 'tp-calendar', 'tp_render_calendar_page' );
	add_submenu_page( $parent, __( 'Run sheet', 'taxi-peninsula' ), __( 'Run sheet', 'taxi-peninsula' ), 'edit_tp_bookings', 'tp-runsheet', 'tp_render_runsheet_page' );
	add_submenu_page( $parent, __( 'Booking reports', 'taxi-peninsula' ), __( 'Reports', 'taxi-peninsula' ), 'edit_tp_bookings', 'tp-reports', 'tp_render_reports_page' );
}

/**
 * Lightweight rows for bookings whose pick-up date falls in a range.
 *
 * @param string $from Y-m-d.
 * @param string $to   Y-m-d.
 * @return array[] Each: id, ref, name, date, time, status, wheelchairs, payment, driver, series, paid.
 */
function tp_bookings_between( $from, $to ) {
	global $wpdb;
	$meta = static function ( $alias, $key ) use ( $wpdb ) {
		return $wpdb->prepare( "LEFT JOIN {$wpdb->postmeta} {$alias} ON ({$alias}.post_id = p.ID AND {$alias}.meta_key = %s)", $key ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- alias is a fixed literal.
	};
	$joins = implode(
		' ',
		array(
			$meta( 'r', '_tp_reference' ),
			$meta( 'n', '_tp_name' ),
			$meta( 't', '_tp_time' ),
			$meta( 's', '_tp_status' ),
			$meta( 'w', '_tp_wheelchairs' ),
			$meta( 'pay', '_tp_payment' ),
			$meta( 'dr', '_tp_driver_id' ),
			$meta( 'se', '_tp_series' ),
			$meta( 'pd', '_tp_paid' ),
		)
	);
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- joins are prepared above; reporting query.
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT p.ID AS id, r.meta_value AS ref, n.meta_value AS name, d.meta_value AS date, t.meta_value AS time,
				s.meta_value AS status, w.meta_value AS wheelchairs, pay.meta_value AS payment, dr.meta_value AS driver,
				se.meta_value AS series, pd.meta_value AS paid
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} d ON (d.post_id = p.ID AND d.meta_key = '_tp_date')
			{$joins}
			WHERE p.post_type = 'tp_booking' AND p.post_status = 'publish' AND d.meta_value BETWEEN %s AND %s
			ORDER BY d.meta_value ASC, t.meta_value ASC",
			$from,
			$to
		),
		ARRAY_A
	);
	// phpcs:enable
}

/* -------------------------------------------------------------------------
 * Calendar.
 * ---------------------------------------------------------------------- */

function tp_render_calendar_page() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view.
	$month = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : wp_date( 'Y-m' );
	if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
		$month = wp_date( 'Y-m' );
	}
	$tz    = wp_timezone();
	$first = DateTimeImmutable::createFromFormat( '!Y-m-d', $month . '-01', $tz );
	$last  = $first->modify( 'last day of this month' );
	$start = $first->modify( '-' . ( (int) $first->format( 'N' ) - 1 ) . ' days' );
	$end   = $last->modify( '+' . ( 7 - (int) $last->format( 'N' ) ) . ' days' );
	$today = wp_date( 'Y-m-d' );

	$by_day = array();
	foreach ( tp_bookings_between( $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ) ) as $row ) {
		$by_day[ $row['date'] ][] = $row;
	}

	$base = admin_url( 'edit.php?post_type=tp_booking&page=tp-calendar' );
	$list = admin_url( 'edit.php?post_type=tp_booking&orderby=tp_pickup&order=asc' );
	$run  = admin_url( 'edit.php?post_type=tp_booking&page=tp-runsheet' );
	global $wp_locale;
	?>
	<div class="wrap tp-calendar-page">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Booking calendar', 'taxi-peninsula' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=tp_booking' ) ); ?>"><?php esc_html_e( 'Add booking', 'taxi-peninsula' ); ?></a>
		<hr class="wp-header-end">

		<div class="tp-cal-nav">
			<a class="button" href="<?php echo esc_url( add_query_arg( 'month', $first->modify( '-1 month' )->format( 'Y-m' ), $base ) ); ?>">‹ <?php esc_html_e( 'Previous', 'taxi-peninsula' ); ?></a>
			<h2><?php echo esc_html( wp_date( 'F Y', $first->getTimestamp() ) ); ?></h2>
			<a class="button" href="<?php echo esc_url( add_query_arg( 'month', $first->modify( '+1 month' )->format( 'Y-m' ), $base ) ); ?>"><?php esc_html_e( 'Next', 'taxi-peninsula' ); ?> ›</a>
			<a class="button" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'This month', 'taxi-peninsula' ); ?></a>
		</div>

		<table class="tp-cal">
			<thead>
				<tr>
					<?php for ( $i = 1; $i <= 7; $i++ ) : ?>
						<th scope="col"><?php echo esc_html( $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $i % 7 ) ) ); ?></th>
					<?php endfor; ?>
				</tr>
			</thead>
			<tbody>
				<?php
				for ( $d = $start; $d <= $end; $d = $d->modify( '+1 day' ) ) :
					$key  = $d->format( 'Y-m-d' );
					$rows = $by_day[ $key ] ?? array();
					if ( '1' === $d->format( 'N' ) ) {
						echo '<tr>';
					}
					$class = array( 'tp-cal__day' );
					if ( $d->format( 'm' ) !== $first->format( 'm' ) ) {
						$class[] = 'is-other';
					}
					if ( $key === $today ) {
						$class[] = 'is-today';
					}
					?>
					<td class="<?php echo esc_attr( implode( ' ', $class ) ); ?>">
						<div class="tp-cal__head">
							<span class="tp-cal__num"><?php echo esc_html( $d->format( 'j' ) ); ?></span>
							<?php if ( $rows ) : ?>
								<a class="tp-cal__count" href="<?php echo esc_url( add_query_arg( array( 'tp_from' => $key, 'tp_to' => $key ), $list ) ); ?>">
									<?php
									/* translators: %d: number of bookings */
									echo esc_html( sprintf( _n( '%d trip', '%d trips', count( $rows ), 'taxi-peninsula' ), count( $rows ) ) );
									?>
								</a>
							<?php endif; ?>
						</div>
						<?php if ( $rows ) : ?>
							<ul>
								<?php foreach ( array_slice( $rows, 0, 4 ) as $row ) : ?>
									<li class="tp-cal__item tp-cal__item--<?php echo esc_attr( $row['status'] ); ?>">
										<a href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>">
											<strong><?php echo esc_html( $row['time'] ); ?></strong> <?php echo esc_html( $row['name'] ); ?>
										</a>
										<span class="screen-reader-text"><?php echo esc_html( tp_statuses()[ $row['status'] ] ?? '' ); ?></span>
									</li>
								<?php endforeach; ?>
								<?php if ( count( $rows ) > 4 ) : ?>
									<li><a href="<?php echo esc_url( add_query_arg( 'sheet_day', $key, $run ) ); ?>">
										<?php
										/* translators: %d: number of bookings */
										echo esc_html( sprintf( __( '+%d more', 'taxi-peninsula' ), count( $rows ) - 4 ) );
										?>
									</a></li>
								<?php endif; ?>
							</ul>
							<a class="tp-cal__run" href="<?php echo esc_url( add_query_arg( 'sheet_day', $key, $run ) ); ?>"><?php esc_html_e( 'Run sheet', 'taxi-peninsula' ); ?></a>
						<?php endif; ?>
					</td>
					<?php
					if ( '7' === $d->format( 'N' ) ) {
						echo '</tr>';
					}
				endfor;
				?>
			</tbody>
		</table>

		<ul class="tp-cal-legend">
			<?php foreach ( tp_statuses() as $key => $label ) : ?>
				<li class="tp-cal__item tp-cal__item--<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Run sheet (printable).
 * ---------------------------------------------------------------------- */

function tp_render_runsheet_page() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view.
	$day = isset( $_GET['sheet_day'] ) ? sanitize_text_field( wp_unslash( $_GET['sheet_day'] ) ) : wp_date( 'Y-m-d' );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
		$day = wp_date( 'Y-m-d' );
	}
	$driver_filter = isset( $_GET['driver'] ) ? absint( $_GET['driver'] ) : 0;
	$show_all      = ! empty( $_GET['all'] );
	// phpcs:enable

	$rows = array_filter(
		tp_bookings_between( $day, $day ),
		static function ( $r ) use ( $driver_filter, $show_all ) {
			if ( ! $show_all && 'cancelled' === $r['status'] ) {
				return false;
			}
			return ! $driver_filter || (int) $r['driver'] === $driver_filter;
		}
	);

	$dt       = DateTimeImmutable::createFromFormat( '!Y-m-d', $day, wp_timezone() );
	$vehicles = tp_vehicle_types();
	$aids     = tp_mobility_aids();
	$pays     = tp_all_payment_methods();
	?>
	<div class="wrap tp-runsheet">
		<h1 class="wp-heading-inline">
			<?php
			/* translators: %s: date */
			echo esc_html( sprintf( __( 'Run sheet — %s', 'taxi-peninsula' ), wp_date( 'l j F Y', $dt->getTimestamp() ) ) );
			?>
		</h1>
		<hr class="wp-header-end">

		<form class="tp-runsheet__controls" method="get">
			<input type="hidden" name="post_type" value="tp_booking">
			<input type="hidden" name="page" value="tp-runsheet">
			<label for="rs-day"><?php esc_html_e( 'Date', 'taxi-peninsula' ); ?></label>
			<input type="date" id="rs-day" name="sheet_day" value="<?php echo esc_attr( $day ); ?>">
			<label for="rs-driver"><?php esc_html_e( 'Driver', 'taxi-peninsula' ); ?></label>
			<select id="rs-driver" name="driver">
				<option value="0"><?php esc_html_e( 'All drivers', 'taxi-peninsula' ); ?></option>
				<?php foreach ( tp_drivers() as $d ) : ?>
					<option value="<?php echo esc_attr( $d->ID ); ?>" <?php selected( $driver_filter, $d->ID ); ?>><?php echo esc_html( $d->display_name ); ?></option>
				<?php endforeach; ?>
			</select>
			<label><input type="checkbox" name="all" value="1" <?php checked( $show_all ); ?>> <?php esc_html_e( 'Include cancelled', 'taxi-peninsula' ); ?></label>
			<button class="button" type="submit"><?php esc_html_e( 'Show', 'taxi-peninsula' ); ?></button>
			<button class="button button-primary" type="button" onclick="window.print()"><?php esc_html_e( 'Print', 'taxi-peninsula' ); ?></button>
		</form>

		<p class="tp-runsheet__meta">
			<?php
			/* translators: %d: number of jobs */
			echo esc_html( sprintf( _n( '%d job', '%d jobs', count( $rows ), 'taxi-peninsula' ), count( $rows ) ) );
			?>
			· <?php echo esc_html( get_bloginfo( 'name' ) ); ?> · <?php echo esc_html( tp_opt( 'phone_display' ) ); ?>
		</p>

		<?php if ( ! $rows ) : ?>
			<p><?php esc_html_e( 'No jobs for this day.', 'taxi-peninsula' ); ?></p>
		<?php else : ?>
			<table class="widefat striped tp-runsheet__table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Time', 'taxi-peninsula' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Passenger', 'taxi-peninsula' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Pick up → Drop off', 'taxi-peninsula' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Needs', 'taxi-peninsula' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Driver / vehicle', 'taxi-peninsula' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Payment', 'taxi-peninsula' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Notes', 'taxi-peninsula' ); ?></th>
						<th scope="col" class="tp-runsheet__tick"><?php esc_html_e( 'Done', 'taxi-peninsula' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $rows as $row ) :
						$b      = tp_get_booking( $row['id'] );
						$driver = $b['driver_id'] ? get_userdata( $b['driver_id'] ) : null;
						?>
						<tr class="<?php echo 'cancelled' === $b['status'] ? 'is-cancelled' : ''; ?>">
							<td><strong><?php echo esc_html( tp_format_pickup( $b, 'g:i a' ) ); ?></strong><br><small><?php echo esc_html( $b['reference'] ); ?></small>
								<?php if ( 'cancelled' === $b['status'] ) : ?>
									<br><?php echo tp_status_badge( 'cancelled' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php endif; ?>
							</td>
							<td><a href="<?php echo esc_url( get_edit_post_link( $b['id'] ) ); ?>"><?php echo esc_html( $b['name'] ); ?></a><br><?php echo esc_html( $b['phone'] ); ?></td>
							<td><?php echo esc_html( $b['pickup'] ); ?><br>→ <?php echo esc_html( $b['dropoff'] ); ?>
								<?php if ( $b['return_trip'] ) : ?>
									<br><small><?php esc_html_e( 'Return:', 'taxi-peninsula' ); ?> <?php echo esc_html( $b['return_time'] ?: __( 'TBA', 'taxi-peninsula' ) ); ?></small>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $vehicles[ $b['vehicle'] ] ?? '' ); ?><br>
								<small>
									<?php
									/* translators: 1: passengers, 2: wheelchairs */
									echo esc_html( sprintf( __( '%1$d pax · %2$d wc', 'taxi-peninsula' ), $b['passengers'], $b['wheelchairs'] ) );
									?>
									· <?php echo esc_html( $aids[ $b['mobility_aid'] ] ?? '' ); ?><?php echo $b['mptp'] ? ' · MPTP' : ''; ?>
								</small>
							</td>
							<td><?php echo $driver ? esc_html( $driver->display_name ) : '<em>' . esc_html__( 'Unassigned', 'taxi-peninsula' ) . '</em>'; ?>
								<?php if ( $b['fleet_id'] ) : ?>
									<br><small><?php echo esc_html( tp_fleet_label( $b['fleet_id'] ) ); ?></small>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $pays[ $b['payment'] ] ?? '' ); ?>
								<?php if ( $b['paid'] > 0 ) : ?>
									<br><small>
										<?php
										/* translators: %s: amount */
										echo esc_html( sprintf( __( '%s paid', 'taxi-peninsula' ), tp_money( $b['paid'] ) ) );
										?>
									</small>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $b['notes'] ); ?><?php echo $b['admin_notes'] ? '<br><em>' . esc_html( $b['admin_notes'] ) . '</em>' : ''; ?></td>
							<td class="tp-runsheet__tick"><span class="tp-box" aria-hidden="true"></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Reports.
 * ---------------------------------------------------------------------- */

/**
 * Accessible single-series SVG bar chart with a hover tooltip per bar and a table view.
 *
 * @param string $title  Chart title (names the single series; no legend needed).
 * @param array  $labels Category labels.
 * @param array  $values Numeric values.
 * @param string $unit   Unit for tooltips, e.g. "trips".
 */
function tp_bar_chart( $title, array $labels, array $values, $unit ) {
	$id     = wp_unique_id( 'tp-chart-' );
	$w      = 720;
	$h      = 220;
	$pad_l  = 36;
	$pad_b  = 28;
	$pad_t  = 16;
	$plot_w = $w - $pad_l - 8;
	$plot_h = $h - $pad_b - $pad_t;
	$max    = max( 1, max( $values ?: array( 0 ) ) );
	// "Nice" axis maximum from a 1–10 step ladder × 10^n.
	$mag  = pow( 10, floor( log10( $max ) ) );
	$nice = $mag;
	foreach ( array( 1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10 ) as $m ) {
		if ( $m * $mag >= $max ) {
			$nice = $m * $mag;
			break;
		}
	}
	$n     = count( $values );
	$slot  = $n ? $plot_w / $n : $plot_w;
	$bar_w = max( 2, $slot - 2 ); // 2px surface gap between adjacent bars.
	$step  = max( 1, (int) ceil( $n / 12 ) );
	$peak  = array_search( max( $values ?: array( 0 ) ), $values, true );
	?>
	<figure class="tp-chart" aria-labelledby="<?php echo esc_attr( $id ); ?>-t">
		<figcaption id="<?php echo esc_attr( $id ); ?>-t" class="tp-chart__title"><?php echo esc_html( $title ); ?></figcaption>
		<svg viewBox="0 0 <?php echo (int) $w; ?> <?php echo (int) $h; ?>" role="img" aria-label="<?php echo esc_attr( $title ); ?>" preserveAspectRatio="none">
			<?php
			foreach ( array( 0, 0.5, 1 ) as $f ) :
				$y = $pad_t + $plot_h - $f * $plot_h;
				?>
				<line class="tp-chart__grid" x1="<?php echo (int) $pad_l; ?>" x2="<?php echo (int) ( $w - 8 ); ?>" y1="<?php echo esc_attr( $y ); ?>" y2="<?php echo esc_attr( $y ); ?>"></line>
				<text class="tp-chart__axis" x="<?php echo (int) ( $pad_l - 6 ); ?>" y="<?php echo esc_attr( $y + 4 ); ?>" text-anchor="end"><?php echo esc_html( number_format_i18n( $f * $nice ) ); ?></text>
			<?php endforeach; ?>
			<?php
			foreach ( $values as $i => $v ) :
				$bh = $v > 0 ? max( 2, $v / $nice * $plot_h ) : 0;
				$x  = $pad_l + $i * $slot + 1;
				$y  = $pad_t + $plot_h - $bh;
				$r  = min( 4, $bar_w / 2, $bh );
				?>
				<g class="tp-chart__bar" tabindex="0">
					<title><?php echo esc_html( $labels[ $i ] . ': ' . number_format_i18n( $v ) . ' ' . $unit ); ?></title>
					<rect class="tp-chart__hit" x="<?php echo esc_attr( $pad_l + $i * $slot ); ?>" y="<?php echo (int) $pad_t; ?>" width="<?php echo esc_attr( $slot ); ?>" height="<?php echo (int) $plot_h; ?>"></rect>
					<?php if ( $bh > 0 ) : ?>
						<path class="tp-chart__mark" d="<?php echo esc_attr( sprintf( 'M%1$.1f,%2$.1f V%3$.1f Q%1$.1f,%4$.1f %5$.1f,%4$.1f H%6$.1f Q%7$.1f,%4$.1f %7$.1f,%3$.1f V%2$.1f Z', $x, $y + $bh, $y + $r, $y, $x + $r, $x + $bar_w - $r, $x + $bar_w ) ); ?>"></path>
					<?php endif; ?>
					<?php if ( $i === $peak && $v > 0 ) : ?>
						<text class="tp-chart__value" x="<?php echo esc_attr( $x + $bar_w / 2 ); ?>" y="<?php echo esc_attr( $y - 4 ); ?>" text-anchor="middle"><?php echo esc_html( number_format_i18n( $v ) ); ?></text>
					<?php endif; ?>
				</g>
				<?php if ( 0 === $i % $step ) : ?>
					<text class="tp-chart__axis" x="<?php echo esc_attr( $pad_l + $i * $slot + $slot / 2 ); ?>" y="<?php echo (int) ( $h - 8 ); ?>" text-anchor="middle"><?php echo esc_html( $labels[ $i ] ); ?></text>
				<?php endif; ?>
			<?php endforeach; ?>
			<line class="tp-chart__base" x1="<?php echo (int) $pad_l; ?>" x2="<?php echo (int) ( $w - 8 ); ?>" y1="<?php echo (int) ( $pad_t + $plot_h ); ?>" y2="<?php echo (int) ( $pad_t + $plot_h ); ?>"></line>
		</svg>
		<details class="tp-chart__table">
			<summary><?php esc_html_e( 'Show as table', 'taxi-peninsula' ); ?></summary>
			<table class="widefat striped">
				<tbody>
					<?php foreach ( $values as $i => $v ) : ?>
						<tr><th scope="row"><?php echo esc_html( $labels[ $i ] ); ?></th><td><?php echo esc_html( number_format_i18n( $v ) ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</details>
	</figure>
	<?php
}

function tp_render_reports_page() {
	$ranges = array(
		4  => __( 'Last 4 weeks', 'taxi-peninsula' ),
		12 => __( 'Last 12 weeks', 'taxi-peninsula' ),
		26 => __( 'Last 6 months', 'taxi-peninsula' ),
		52 => __( 'Last 12 months', 'taxi-peninsula' ),
	);
	$weeks = isset( $_GET['weeks'] ) ? absint( $_GET['weeks'] ) : 12; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $ranges[ $weeks ] ) ) {
		$weeks = 12;
	}

	$tz         = wp_timezone();
	$today      = new DateTimeImmutable( 'today', $tz );
	$week_start = $today->modify( '-' . ( (int) $today->format( 'N' ) - 1 ) . ' days' ); // Monday this week.
	$from       = $week_start->modify( '-' . ( $weeks - 1 ) . ' weeks' );
	$rows       = tp_bookings_between( $from->format( 'Y-m-d' ), $today->format( 'Y-m-d' ) );

	$total     = count( $rows );
	$by_status = array_fill_keys( array_keys( tp_statuses() ), 0 );
	$per_week  = array_fill( 0, $weeks, 0 );
	$by_hour   = array_fill( 0, 24, 0 );
	$by_dow    = array_fill( 0, 7, 0 );
	$wc_trips  = 0;
	$repeat    = 0;
	$accounts  = 0;
	$paid      = 0.0;
	$drivers   = array();

	foreach ( $rows as $r ) {
		$status = $r['status'] ?: 'pending';
		$by_status[ $status ] = ( $by_status[ $status ] ?? 0 ) + 1;
		$d = DateTimeImmutable::createFromFormat( '!Y-m-d', $r['date'], $tz );
		if ( ! $d ) {
			continue;
		}
		$wk = (int) floor( ( $d->getTimestamp() - $from->getTimestamp() ) / WEEK_IN_SECONDS );
		if ( 'cancelled' !== $status ) {
			if ( isset( $per_week[ $wk ] ) ) {
				$per_week[ $wk ]++;
			}
			$by_dow[ (int) $d->format( 'N' ) - 1 ]++;
			$hour = (int) substr( (string) $r['time'], 0, 2 );
			$by_hour[ $hour ]++;
			if ( (int) $r['wheelchairs'] > 0 ) {
				$wc_trips++;
			}
			if ( $r['series'] ) {
				$repeat++;
			}
			if ( 'account' === $r['payment'] ) {
				$accounts++;
			}
			if ( $r['driver'] ) {
				$drivers[ (int) $r['driver'] ] = ( $drivers[ (int) $r['driver'] ] ?? 0 ) + 1;
			}
		}
		$paid += (float) $r['paid'];
	}

	$active      = $total - $by_status['cancelled'];
	$cancel_rate = $total ? $by_status['cancelled'] / $total * 100 : 0;
	$week_labels = array();
	for ( $i = 0; $i < $weeks; $i++ ) {
		$week_labels[] = wp_date( 'j M', $from->modify( '+' . $i . ' weeks' )->getTimestamp() );
	}
	$hour_labels = array();
	for ( $h = 0; $h < 24; $h++ ) {
		$hour_labels[] = wp_date( 'ga', gmmktime( $h, 0, 0, 1, 1, 2024 ), new DateTimeZone( 'UTC' ) );
	}
	global $wp_locale;
	$dow_labels = array();
	for ( $i = 1; $i <= 7; $i++ ) {
		$dow_labels[] = $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $i % 7 ) );
	}
	?>
	<div class="wrap tp-reports">
		<h1><?php esc_html_e( 'Booking reports', 'taxi-peninsula' ); ?></h1>

		<form method="get" class="tp-reports__filter">
			<input type="hidden" name="post_type" value="tp_booking">
			<input type="hidden" name="page" value="tp-reports">
			<label for="rp-weeks" class="screen-reader-text"><?php esc_html_e( 'Period', 'taxi-peninsula' ); ?></label>
			<select id="rp-weeks" name="weeks" onchange="this.form.submit()">
				<?php foreach ( $ranges as $k => $label ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $weeks, $k ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="description">
				<?php
				/* translators: 1: start date, 2: end date */
				echo esc_html( sprintf( __( 'Pick-ups from %1$s to %2$s (today). Future bookings are not counted.', 'taxi-peninsula' ), wp_date( 'j M Y', $from->getTimestamp() ), wp_date( 'j M Y', $today->getTimestamp() ) ) );
				?>
			</span>
			<noscript><button class="button"><?php esc_html_e( 'Show', 'taxi-peninsula' ); ?></button></noscript>
		</form>

		<ul class="tp-tiles">
			<li><span class="tp-tiles__n"><?php echo esc_html( number_format_i18n( $active ) ); ?></span><?php esc_html_e( 'Trips (excluding cancelled)', 'taxi-peninsula' ); ?></li>
			<li><span class="tp-tiles__n"><?php echo esc_html( number_format_i18n( $by_status['completed'] ) ); ?></span><?php esc_html_e( 'Completed', 'taxi-peninsula' ); ?></li>
			<li><span class="tp-tiles__n"><?php echo esc_html( number_format_i18n( $cancel_rate, 1 ) ); ?>%</span>
				<?php
				/* translators: %d: cancelled count */
				echo esc_html( sprintf( __( 'Cancelled (%d)', 'taxi-peninsula' ), $by_status['cancelled'] ) );
				?>
			</li>
			<li><span class="tp-tiles__n"><?php echo esc_html( $active ? number_format_i18n( $wc_trips / $active * 100, 0 ) : 0 ); ?>%</span><?php esc_html_e( 'Trips with a wheelchair', 'taxi-peninsula' ); ?></li>
			<li><span class="tp-tiles__n"><?php echo esc_html( number_format_i18n( $repeat ) ); ?></span><?php esc_html_e( 'Repeat-booking trips', 'taxi-peninsula' ); ?></li>
			<li><span class="tp-tiles__n"><?php echo esc_html( number_format_i18n( $accounts ) ); ?></span><?php esc_html_e( 'Account / invoice trips', 'taxi-peninsula' ); ?></li>
			<li><span class="tp-tiles__n"><?php echo esc_html( tp_money( $paid ) ); ?></span><?php esc_html_e( 'Deposits paid online', 'taxi-peninsula' ); ?></li>
		</ul>

		<?php if ( ! $total ) : ?>
			<p><?php esc_html_e( 'No bookings in this period yet.', 'taxi-peninsula' ); ?></p>
		<?php else : ?>
			<div class="tp-charts">
				<?php
				tp_bar_chart( __( 'Trips per week (week starting)', 'taxi-peninsula' ), $week_labels, $per_week, __( 'trips', 'taxi-peninsula' ) );
				tp_bar_chart( __( 'Busiest pick-up times', 'taxi-peninsula' ), $hour_labels, $by_hour, __( 'trips', 'taxi-peninsula' ) );
				tp_bar_chart( __( 'Busiest days', 'taxi-peninsula' ), $dow_labels, $by_dow, __( 'trips', 'taxi-peninsula' ) );
				?>
			</div>

			<?php if ( $drivers ) : ?>
				<h2><?php esc_html_e( 'Trips by driver', 'taxi-peninsula' ); ?></h2>
				<table class="widefat striped tp-reports__drivers">
					<thead><tr><th scope="col"><?php esc_html_e( 'Driver', 'taxi-peninsula' ); ?></th><th scope="col"><?php esc_html_e( 'Trips', 'taxi-peninsula' ); ?></th></tr></thead>
					<tbody>
						<?php
						arsort( $drivers );
						foreach ( $drivers as $uid => $count ) :
							$u = get_userdata( $uid );
							?>
							<tr><td><?php echo esc_html( $u ? $u->display_name : '#' . $uid ); ?></td><td><?php echo esc_html( number_format_i18n( $count ) ); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
