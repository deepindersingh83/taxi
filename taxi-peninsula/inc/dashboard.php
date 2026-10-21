<?php
/**
 * Bookings → Owner dashboard: the business on one page — trips, revenue,
 * cancellations and no-shows (with the change from the previous period),
 * trips and revenue over time, busiest days and hours, top partners and
 * where bookings come from. Charts are drawn by the site (no outside
 * services) and each has a table view.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( tp_admin_parent( 'reports' ), __( 'Owner dashboard', 'taxi-peninsula' ), __( 'Owner dashboard', 'taxi-peninsula' ), 'tp_view_finance', 'tp-owner', 'tp_render_owner_dashboard', 0 );
	},
	12
);

/**
 * Bookings with a pick-up date between two dates (inclusive): rows of
 * { id, date, time, status, fare, venue, source }.
 */
function tp_owner_rows( $from, $to ) {
	/**
	 * Filters the owner dashboard rows (fake bookings are taken out here).
	 *
	 * @param object[] $rows Rows with id, date, time, status, fare, venue, source.
	 */
	return apply_filters( 'tp_owner_rows', tp_owner_rows_raw( $from, $to ) );
}

function tp_owner_rows_raw( $from, $to ) {
	$fast = function_exists( 'tp_trips_owner_rows' ) ? tp_trips_owner_rows( $from, $to ) : null;
	if ( null !== $fast ) {
		return $fast;
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT p.ID id,
				MAX(CASE WHEN m.meta_key = '_tp_date' THEN m.meta_value END) date,
				MAX(CASE WHEN m.meta_key = '_tp_time' THEN m.meta_value END) time,
				MAX(CASE WHEN m.meta_key = '_tp_status' THEN m.meta_value END) status,
				MAX(CASE WHEN m.meta_key = '_tp_fare' THEN m.meta_value END) fare,
				MAX(CASE WHEN m.meta_key = '_tp_venue_id' THEN m.meta_value END) venue,
				MAX(CASE WHEN m.meta_key = '_tp_source' THEN m.meta_value END) source
			FROM {$wpdb->posts} p
			JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key IN ('_tp_date','_tp_time','_tp_status','_tp_fare','_tp_venue_id','_tp_source')
			WHERE p.post_type = 'tp_booking' AND p.post_status = 'publish'
			GROUP BY p.ID
			HAVING date BETWEEN %s AND %s",
			$from,
			$to
		)
	);
	return $rows ?: array();
}

/**
 * Headline numbers for a set of rows.
 */
function tp_owner_kpis( array $rows ) {
	$k = array( 'trips' => 0, 'completed' => 0, 'revenue' => 0.0, 'priced' => 0, 'cancelled' => 0, 'no_show' => 0 );
	foreach ( $rows as $r ) {
		++$k['trips'];
		if ( 'completed' === $r->status ) {
			++$k['completed'];
		} elseif ( 'cancelled' === $r->status ) {
			++$k['cancelled'];
		} elseif ( 'no_show' === $r->status ) {
			++$k['no_show'];
		}
		if ( in_array( $r->status, array( 'completed', 'no_show' ), true ) && '' !== (string) $r->fare && null !== $r->fare ) {
			$k['revenue'] += (float) $r->fare;
			++$k['priced'];
		}
	}
	$k['avg']         = $k['priced'] ? $k['revenue'] / $k['priced'] : 0;
	$k['cancel_rate'] = $k['trips'] ? 100 * $k['cancelled'] / $k['trips'] : 0;
	$k['noshow_rate'] = $k['trips'] ? 100 * $k['no_show'] / $k['trips'] : 0;
	return $k;
}

/**
 * Vertical bar chart (one series) as accessible inline SVG plus a table.
 *
 * @param string   $title  Chart title (also the table caption).
 * @param string[] $labels Bucket labels.
 * @param float[]  $values Values.
 * @param callable $fmt    Value formatter.
 */
function tp_svg_bars( $title, array $labels, array $values, $fmt ) {
	$w     = 720;
	$h     = 220;
	$pad_l = 46;
	$pad_b = 26;
	$pad_t = 10;
	$n     = max( 1, count( $values ) );
	$max   = max( 1, max( $values ?: array( 0 ) ) );
	$slot  = ( $w - $pad_l ) / $n;
	$bar   = max( 2, $slot - 2 );
	$step  = max( 1, (int) ceil( $n / 12 ) );
	$id    = wp_unique_id( 'tpc-' );
	ob_start();
	?>
	<figure class="tp-chart">
		<figcaption class="tp-chart__title" id="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></figcaption>
		<svg viewBox="0 0 <?php echo (int) $w; ?> <?php echo (int) $h; ?>" role="img" aria-labelledby="<?php echo esc_attr( $id ); ?>" class="tp-chart__svg">
			<?php foreach ( array( 0, 0.5, 1 ) as $g ) : ?>
				<?php $y = $pad_t + ( $h - $pad_t - $pad_b ) * ( 1 - $g ); ?>
				<line x1="<?php echo (int) $pad_l; ?>" x2="<?php echo (int) $w; ?>" y1="<?php echo esc_attr( $y ); ?>" y2="<?php echo esc_attr( $y ); ?>" class="tp-chart__grid"/>
				<text x="<?php echo (int) ( $pad_l - 6 ); ?>" y="<?php echo esc_attr( $y + 4 ); ?>" text-anchor="end" class="tp-chart__axis"><?php echo esc_html( $fmt( $max * $g ) ); ?></text>
			<?php endforeach; ?>
			<?php foreach ( $values as $i => $v ) : ?>
				<?php
				$bh = ( $h - $pad_t - $pad_b ) * $v / $max;
				$x  = $pad_l + $i * $slot + 1;
				$y  = $h - $pad_b - $bh;
				$r  = min( 4, $bar / 2, $bh );
				?>
				<g class="tp-chart__bar">
					<rect x="<?php echo esc_attr( round( $pad_l + $i * $slot, 2 ) ); ?>" y="<?php echo (int) $pad_t; ?>" width="<?php echo esc_attr( round( $slot, 2 ) ); ?>" height="<?php echo (int) ( $h - $pad_t - $pad_b ); ?>" class="tp-chart__hit"/>
					<?php if ( $bh > 0 ) : ?>
						<path d="<?php echo esc_attr( sprintf( 'M%1$.2f %2$.2f V%3$.2f Q%1$.2f %4$.2f %5$.2f %4$.2f H%6$.2f Q%7$.2f %4$.2f %7$.2f %3$.2f V%2$.2f Z', $x, $h - $pad_b, $y + $r, $y, $x + $r, $x + $bar - $r, $x + $bar ) ); ?>" class="tp-chart__fill"/>
					<?php endif; ?>
					<title><?php echo esc_html( $labels[ $i ] . ': ' . $fmt( $v ) ); ?></title>
				</g>
				<?php if ( 0 === $i % $step ) : ?>
					<text x="<?php echo esc_attr( round( $x + $bar / 2, 2 ) ); ?>" y="<?php echo (int) ( $h - 8 ); ?>" text-anchor="middle" class="tp-chart__axis"><?php echo esc_html( $labels[ $i ] ); ?></text>
				<?php endif; ?>
			<?php endforeach; ?>
			<line x1="<?php echo (int) $pad_l; ?>" x2="<?php echo (int) $w; ?>" y1="<?php echo (int) ( $h - $pad_b ); ?>" y2="<?php echo (int) ( $h - $pad_b ); ?>" class="tp-chart__base"/>
		</svg>
		<details class="tp-chart__table">
			<summary><?php esc_html_e( 'Show as a table', 'taxi-peninsula' ); ?></summary>
			<table class="widefat striped"><tbody>
				<?php foreach ( $values as $i => $v ) : ?>
					<tr><th scope="row"><?php echo esc_html( $labels[ $i ] ); ?></th><td class="num"><?php echo esc_html( $fmt( $v ) ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>
		</details>
	</figure>
	<?php
	return ob_get_clean();
}

/**
 * Ranked horizontal bars as an HTML list.
 *
 * @param string $title Title.
 * @param array  $items label => count.
 * @param string $empty Message when empty.
 */
function tp_html_hbars( $title, array $items, $empty ) {
	arsort( $items );
	$items = array_slice( $items, 0, 8, true );
	$max   = max( 1, max( $items ?: array( 0 ) ) );
	ob_start();
	?>
	<figure class="tp-chart">
		<figcaption class="tp-chart__title"><?php echo esc_html( $title ); ?></figcaption>
		<?php if ( ! $items ) : ?>
			<p class="description"><?php echo esc_html( $empty ); ?></p>
		<?php else : ?>
			<ol class="tp-hbars">
				<?php foreach ( $items as $label => $n ) : ?>
					<li><span class="tp-hbars__label"><?php echo esc_html( $label ); ?></span><span class="tp-hbars__track"><span class="tp-hbars__fill" style="width:<?php echo esc_attr( round( 100 * $n / $max, 1 ) ); ?>%"></span></span><span class="tp-hbars__n"><?php echo esc_html( number_format_i18n( $n ) ); ?></span></li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</figure>
	<?php
	return ob_get_clean();
}

function tp_render_owner_dashboard() {
	if ( ! current_user_can( 'tp_view_finance' ) ) {
		return;
	}
	$days  = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$days  = in_array( $days, array( 7, 30, 90, 365 ), true ) ? $days : 30;
	$today = wp_date( 'Y-m-d' );
	$from  = wp_date( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );
	$pfrom = wp_date( 'Y-m-d', time() - ( 2 * $days - 1 ) * DAY_IN_SECONDS );
	$pto   = wp_date( 'Y-m-d', time() - $days * DAY_IN_SECONDS );
	$rows  = tp_owner_rows( $from, $today );
	$k     = tp_owner_kpis( $rows );
	$p     = tp_owner_kpis( tp_owner_rows( $pfrom, $pto ) );

	// Buckets: days, weeks or months.
	$unit    = $days <= 31 ? 'day' : ( $days <= 92 ? 'week' : 'month' );
	$buckets = array();
	$cursor  = strtotime( $from . ' 12:00' );
	while ( wp_date( 'Y-m-d', $cursor ) <= $today ) {
		$key = 'day' === $unit ? wp_date( 'Y-m-d', $cursor ) : ( 'week' === $unit ? wp_date( 'o-\WW', $cursor ) : wp_date( 'Y-m', $cursor ) );
		if ( ! isset( $buckets[ $key ] ) ) {
			$buckets[ $key ] = array(
				'label' => 'day' === $unit ? wp_date( 'j M', $cursor ) : ( 'week' === $unit ? wp_date( 'j M', $cursor ) : wp_date( 'M y', $cursor ) ),
				'trips' => 0,
				'rev'   => 0.0,
			);
		}
		$cursor += DAY_IN_SECONDS;
	}
	$heat     = array_fill( 1, 7, array_fill( 5, 19, 0 ) );
	$partners = array();
	$sources  = array();
	$outcomes = array();
	$names    = tp_statuses();
	foreach ( $rows as $r ) {
		$ts  = strtotime( $r->date . ' 12:00' );
		$key = 'day' === $unit ? $r->date : ( 'week' === $unit ? wp_date( 'o-\WW', $ts ) : substr( $r->date, 0, 7 ) );
		$outcome = in_array( $r->status, array( 'completed', 'cancelled', 'no_show' ), true ) ? $names[ $r->status ] : __( 'Booked / in progress', 'taxi-peninsula' );
		$outcomes[ $outcome ] = ( $outcomes[ $outcome ] ?? 0 ) + 1;
		if ( 'cancelled' === $r->status ) {
			continue;
		}
		if ( isset( $buckets[ $key ] ) ) {
			++$buckets[ $key ]['trips'];
			if ( in_array( $r->status, array( 'completed', 'no_show' ), true ) ) {
				$buckets[ $key ]['rev'] += (float) $r->fare;
			}
		}
		$hour = (int) substr( (string) $r->time, 0, 2 );
		$dow  = (int) wp_date( 'N', $ts );
		if ( $r->time && isset( $heat[ $dow ] ) ) {
			$hour = max( 5, min( 23, $hour ) );
			++$heat[ $dow ][ $hour ];
		}
		if ( $r->venue ) {
			$partners[ get_the_title( (int) $r->venue ) ] = ( $partners[ get_the_title( (int) $r->venue ) ] ?? 0 ) + 1;
		}
		$src             = $r->source ? implode( ' / ', array_slice( explode( ' / ', $r->source ), 0, 2 ) ) : __( 'Not recorded (older bookings)', 'taxi-peninsula' );
		$sources[ $src ] = ( $sources[ $src ] ?? 0 ) + 1;
	}
	$heat_max = max( 1, max( array_map( 'max', $heat ) ) );
	$ramp     = array( '#eef3fb', '#c9d8f0', '#93b0de', '#5a83c6', '#2a5aa6', '#0b2a5b' );
	$delta    = static function ( $now, $before, $lower_is_better = false ) {
		if ( ! $before ) {
			return '';
		}
		$pct  = round( 100 * ( $now - $before ) / $before );
		$good = $lower_is_better ? $pct <= 0 : $pct >= 0;
		return sprintf( '<span class="tp-kpi__delta tp-kpi__delta--%1$s">%2$s%3$d%%</span>', $good ? 'good' : 'bad', $pct >= 0 ? '▲ ' : '▼ ', abs( $pct ) );
	};
	$money = static fn( $v ) => '$' . number_format_i18n( $v, 0 );
	$count = static fn( $v ) => number_format_i18n( round( $v ) );
	?>
	<div class="wrap tp-owner">
		<h1><?php esc_html_e( 'Owner dashboard', 'taxi-peninsula' ); ?></h1>
		<p class="tp-leads__range">
			<?php foreach ( array( 7, 30, 90, 365 ) as $d ) : ?>
				<a class="button<?php echo $d === $days ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'days', $d ) ); ?>"<?php echo $d === $days ? ' aria-current="page"' : ''; ?>>
					<?php
					/* translators: %d: days */
					echo esc_html( sprintf( _n( 'Last %d day', 'Last %d days', $d, 'taxi-peninsula' ), $d ) );
					?>
				</a>
			<?php endforeach; ?>
		</p>
		<p class="description">
			<?php
			/* translators: 1: from 2: to 3: days */
			echo esc_html( sprintf( __( 'Trips with a pick-up date from %1$s to %2$s. Arrows compare with the %3$d days before.', 'taxi-peninsula' ), wp_date( 'j M Y', strtotime( $from ) ), wp_date( 'j M Y', strtotime( $today ) ), $days ) );
			?>
		</p>

		<ul class="tp-kpis">
			<li class="tp-kpi"><span class="tp-kpi__label"><?php esc_html_e( 'Trips booked', 'taxi-peninsula' ); ?></span><strong class="tp-kpi__n"><?php echo esc_html( $count( $k['trips'] ) ); ?></strong><?php echo $delta( $k['trips'], $p['trips'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
			<li class="tp-kpi"><span class="tp-kpi__label"><?php esc_html_e( 'Completed', 'taxi-peninsula' ); ?></span><strong class="tp-kpi__n"><?php echo esc_html( $count( $k['completed'] ) ); ?></strong><?php echo $delta( $k['completed'], $p['completed'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
			<li class="tp-kpi"><span class="tp-kpi__label"><?php esc_html_e( 'Revenue (fares entered)', 'taxi-peninsula' ); ?></span><strong class="tp-kpi__n"><?php echo esc_html( $money( $k['revenue'] ) ); ?></strong><?php echo $delta( $k['revenue'], $p['revenue'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
			<li class="tp-kpi"><span class="tp-kpi__label"><?php esc_html_e( 'Average fare', 'taxi-peninsula' ); ?></span><strong class="tp-kpi__n"><?php echo esc_html( tp_money( $k['avg'] ) ); ?></strong><?php echo $delta( $k['avg'], $p['avg'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
			<li class="tp-kpi"><span class="tp-kpi__label"><?php esc_html_e( 'Cancelled', 'taxi-peninsula' ); ?></span><strong class="tp-kpi__n"><?php echo esc_html( number_format_i18n( $k['cancel_rate'], 1 ) ); ?>%</strong><span class="tp-kpi__sub"><?php echo esc_html( sprintf( /* translators: %d: count */ _n( '%d trip', '%d trips', $k['cancelled'], 'taxi-peninsula' ), $k['cancelled'] ) ); ?></span></li>
			<li class="tp-kpi"><span class="tp-kpi__label"><?php esc_html_e( 'No-shows', 'taxi-peninsula' ); ?></span><strong class="tp-kpi__n"><?php echo esc_html( number_format_i18n( $k['noshow_rate'], 1 ) ); ?>%</strong><span class="tp-kpi__sub"><?php echo esc_html( sprintf( /* translators: %d: count */ _n( '%d trip', '%d trips', $k['no_show'], 'taxi-peninsula' ), $k['no_show'] ) ); ?></span></li>
		</ul>
		<?php if ( $k['completed'] > $k['priced'] ) : ?>
			<p class="description">
				<?php
				/* translators: %d: trips */
				echo esc_html( sprintf( _n( '%d finished trip has no fare entered yet, so revenue is lower than it will be.', '%d finished trips have no fare entered yet, so revenue is lower than it will be.', $k['completed'] - $k['priced'], 'taxi-peninsula' ), $k['completed'] - $k['priced'] ) );
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tp-finance' ) ); ?>"><?php esc_html_e( 'Enter fares', 'taxi-peninsula' ); ?></a>
			</p>
		<?php endif; ?>

		<div class="tp-owner__grid">
			<?php
			$unit_label = array( 'day' => __( 'per day', 'taxi-peninsula' ), 'week' => __( 'per week', 'taxi-peninsula' ), 'month' => __( 'per month', 'taxi-peninsula' ) )[ $unit ];
			/* translators: %s: per day/week/month */
			echo tp_svg_bars( sprintf( __( 'Trips %s (not cancelled)', 'taxi-peninsula' ), $unit_label ), array_column( $buckets, 'label' ), array_column( $buckets, 'trips' ), $count ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			/* translators: %s: per day/week/month */
			echo tp_svg_bars( sprintf( __( 'Revenue %s', 'taxi-peninsula' ), $unit_label ), array_column( $buckets, 'label' ), array_column( $buckets, 'rev' ), $money ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>

		<figure class="tp-chart tp-heat">
			<figcaption class="tp-chart__title"><?php esc_html_e( 'Busiest pick-up times', 'taxi-peninsula' ); ?></figcaption>
			<div class="tp-heat__scroll">
				<table class="tp-heat__table">
					<thead><tr><td></td>
						<?php for ( $h = 5; $h <= 23; $h++ ) : ?>
							<th scope="col"><?php echo esc_html( date_i18n( 'ga', gmmktime( $h, 0, 0, 1, 1, 2020 ) ) ); ?></th>
						<?php endfor; ?>
					</tr></thead>
					<tbody>
						<?php foreach ( $heat as $dow => $hours ) : ?>
							<tr><th scope="row"><?php echo esc_html( date_i18n( 'D', gmmktime( 12, 0, 0, 1, $dow, 2024 ) ) ); ?></th>
								<?php foreach ( $hours as $h => $n ) : ?>
									<?php $step = $n ? min( 5, 1 + (int) floor( 5 * ( $n - 1 ) / $heat_max ) ) : 0; ?>
									<td style="background:<?php echo esc_attr( $ramp[ $step ] ); ?>;color:<?php echo $step >= 3 ? '#fff' : '#1d2327'; ?>" title="<?php echo esc_attr( date_i18n( 'l', gmmktime( 12, 0, 0, 1, $dow, 2024 ) ) . ' ' . date_i18n( 'ga', gmmktime( $h, 0, 0, 1, 1, 2020 ) ) . ': ' . $n ); ?>"><?php echo $n ? esc_html( $n ) : ''; ?></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="description"><?php esc_html_e( 'Number of pick-ups by weekday and hour. Darker = busier. Use it to plan driver shifts.', 'taxi-peninsula' ); ?></p>
		</figure>

		<div class="tp-owner__grid tp-owner__grid--3">
			<?php
			echo tp_html_hbars( __( 'Top partners', 'taxi-peninsula' ), $partners, __( 'No bookings from partner pages yet.', 'taxi-peninsula' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo tp_html_hbars( __( 'Where bookings come from', 'taxi-peninsula' ), $sources, __( 'No bookings in this period.', 'taxi-peninsula' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo tp_html_hbars( __( 'Trip outcomes', 'taxi-peninsula' ), $outcomes, __( 'No bookings in this period.', 'taxi-peninsula' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>
		<p>
			<?php if ( current_user_can( 'tp_view_leads' ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tp-leads' ) ); ?>"><?php esc_html_e( 'Leads & calls report', 'taxi-peninsula' ); ?></a> ·
			<?php endif; ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tp-finance' ) ); ?>"><?php esc_html_e( 'Finance', 'taxi-peninsula' ); ?></a>
		</p>
	</div>
	<?php
}
