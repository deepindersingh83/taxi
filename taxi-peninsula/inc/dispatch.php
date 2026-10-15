<?php
/**
 * Operator tools: the Dispatch board (one column per driver, quick assign),
 * clash warnings, and the Change requests inbox (approve / decline).
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'tp_dispatch_menu', 12 );
function tp_dispatch_menu() {
	$parent   = 'edit.php?post_type=tp_booking';
	$requests = tp_open_request_count();
	$bubble   = $requests ? sprintf( ' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>', $requests ) : '';
	add_submenu_page( $parent, __( 'Dispatch board', 'taxi-peninsula' ), __( 'Dispatch board', 'taxi-peninsula' ), 'edit_tp_bookings', 'tp-dispatch', 'tp_render_dispatch_page', 1 );
	add_submenu_page( $parent, __( 'Change requests', 'taxi-peninsula' ), __( 'Change requests', 'taxi-peninsula' ) . $bubble, 'edit_tp_bookings', 'tp-requests', 'tp_render_requests_page', 2 );
}

/* -------------------------------------------------------------------------
 * Clash and vehicle warnings.
 * ---------------------------------------------------------------------- */

/**
 * Pick-up time as a timestamp, and when the driver is free again.
 *
 * @return int[] [start, end]
 */
function tp_trip_window( array $b ) {
	$start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $b['date'] . ' ' . ( $b['time'] ?: '00:00' ), wp_timezone() );
	$start = $start ? $start->getTimestamp() : 0;
	// Trip time (or 45 min if unknown) plus 15 min to load and unload.
	$minutes = max( 30, (int) $b['duration'] ?: 45 ) + 15;
	return array( $start, $start + $minutes * MINUTE_IN_SECONDS );
}

/**
 * Why a driver/vehicle may not suit a trip.
 *
 * @param array   $b         Booking.
 * @param int     $driver_id Driver to check.
 * @param int     $fleet_id  Vehicle to check.
 * @param array[] $others    The driver's other bookings that day (optional; looked up if null).
 * @return string[]
 */
function tp_trip_warnings( array $b, $driver_id, $fleet_id, $others = null ) {
	$warnings = array();

	if ( $driver_id ) {
		if ( null === $others ) {
			$others = tp_driver_trips( $driver_id, $b['date'], $b['date'] );
		}
		list( $start, $end ) = tp_trip_window( $b );
		foreach ( $others as $o ) {
			if ( (int) $o['id'] === (int) $b['id'] || ! in_array( $o['status'], tp_active_statuses(), true ) ) {
				continue;
			}
			list( $o_start, $o_end ) = tp_trip_window( $o );
			if ( $start < $o_end && $o_start < $end ) {
				/* translators: 1: reference, 2: time */
				$warnings[] = sprintf( __( 'Clashes with %1$s at %2$s', 'taxi-peninsula' ), $o['reference'], tp_format_pickup( $o, 'g:i a' ) );
			}
		}
	}

	if ( $fleet_id && 'tp_fleet' === get_post_type( $fleet_id ) ) {
		$chairs = get_post_meta( $fleet_id, '_tp_wheelchairs', true );
		$seats  = get_post_meta( $fleet_id, '_tp_seats', true );
		$access = get_post_meta( $fleet_id, '_tp_access', true );
		if ( $b['wheelchairs'] > 0 && 'none' === $access ) {
			$warnings[] = __( 'This vehicle has no wheelchair access', 'taxi-peninsula' );
		} elseif ( '' !== $chairs && (int) $chairs < (int) $b['wheelchairs'] ) {
			/* translators: 1: wheelchair positions, 2: wheelchairs needed */
			$warnings[] = sprintf( __( 'Vehicle takes %1$d wheelchair(s); trip needs %2$d', 'taxi-peninsula' ), (int) $chairs, (int) $b['wheelchairs'] );
		} elseif ( 'maxi_wat' === $b['vehicle'] && '' !== $chairs && (int) $chairs < 2 ) {
			$warnings[] = __( 'Booked as a Maxi, but this vehicle takes one wheelchair', 'taxi-peninsula' );
		}
		if ( '' !== $seats && (int) $seats > 0 && (int) $seats < (int) $b['passengers'] ) {
			/* translators: 1: seats, 2: passengers */
			$warnings[] = sprintf( __( 'Vehicle seats %1$d; trip has %2$d passengers', 'taxi-peninsula' ), (int) $seats, (int) $b['passengers'] );
		}
	}
	return $warnings;
}

/* -------------------------------------------------------------------------
 * Dispatch board.
 * ---------------------------------------------------------------------- */

function tp_render_dispatch_page() {
	if ( ! current_user_can( 'edit_tp_bookings' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view.
	$day = isset( $_GET['day'] ) ? sanitize_text_field( wp_unslash( $_GET['day'] ) ) : wp_date( 'Y-m-d' );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
		$day = wp_date( 'Y-m-d' );
	}
	$dt   = DateTimeImmutable::createFromFormat( '!Y-m-d', $day, wp_timezone() );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view.
	$view = isset( $_GET['view'] ) && 'timeline' === $_GET['view'] ? 'timeline' : 'columns';
	if ( isset( $_GET['view'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		update_user_meta( get_current_user_id(), 'tp_dispatch_view', $view );
	} elseif ( 'timeline' === get_user_meta( get_current_user_id(), 'tp_dispatch_view', true ) ) {
		$view = 'timeline';
	}
	$base = admin_url( 'edit.php?post_type=tp_booking&page=tp-dispatch' );

	$ids = get_posts(
		array(
			'post_type'   => 'tp_booking',
			'numberposts' => 300,
			'fields'      => 'ids',
			'meta_key'    => '_tp_pickup_at',
			'orderby'     => 'meta_value',
			'order'       => 'ASC',
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array( 'key' => '_tp_date', 'value' => $day ),
				array( 'key' => '_tp_status', 'value' => 'cancelled', 'compare' => '!=' ),
			),
		)
	);
	$trips      = array_map( 'tp_get_booking', $ids );
	$drivers    = tp_drivers();
	$by_driver  = array();
	$unassigned = array();
	foreach ( $trips as $b ) {
		if ( $b['driver_id'] ) {
			$by_driver[ $b['driver_id'] ][] = $b;
		} elseif ( in_array( $b['status'], tp_active_statuses(), true ) ) {
			$unassigned[] = $b;
		}
	}
	$warn_count = 0;
	foreach ( $by_driver as $driver_id => $list ) {
		foreach ( $list as $b ) {
			if ( in_array( $b['status'], tp_active_statuses(), true ) && tp_trip_warnings( $b, $driver_id, $b['fleet_id'], $list ) ) {
				$warn_count++;
			}
		}
	}
	$notice = get_transient( 'tp_dispatch_notice_' . get_current_user_id() );
	delete_transient( 'tp_dispatch_notice_' . get_current_user_id() );
	?>
	<div class="wrap tp-dispatch">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Dispatch board', 'taxi-peninsula' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=tp_booking' ) ); ?>"><?php esc_html_e( 'Add phone booking', 'taxi-peninsula' ); ?></a>
		<hr class="wp-header-end">

		<?php if ( is_array( $notice ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice['warnings'] ? 'warning' : 'success' ); ?> is-dismissible">
				<p><strong><?php echo esc_html( $notice['message'] ); ?></strong></p>
				<?php if ( $notice['warnings'] ) : ?>
					<ul class="ul-disc"><?php foreach ( $notice['warnings'] as $w ) : ?><li><?php echo esc_html( $w ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<form class="tp-dispatch__nav" method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
			<input type="hidden" name="post_type" value="tp_booking">
			<input type="hidden" name="page" value="tp-dispatch">
			<a class="button" href="<?php echo esc_url( add_query_arg( 'day', $dt->modify( '-1 day' )->format( 'Y-m-d' ), $base ) ); ?>" aria-label="<?php esc_attr_e( 'Previous day', 'taxi-peninsula' ); ?>">‹</a>
			<label class="screen-reader-text" for="tp-dispatch-day"><?php esc_html_e( 'Day', 'taxi-peninsula' ); ?></label>
			<input type="date" id="tp-dispatch-day" name="day" value="<?php echo esc_attr( $day ); ?>" onchange="this.form.submit()">
			<a class="button" href="<?php echo esc_url( add_query_arg( 'day', $dt->modify( '+1 day' )->format( 'Y-m-d' ), $base ) ); ?>" aria-label="<?php esc_attr_e( 'Next day', 'taxi-peninsula' ); ?>">›</a>
			<a class="button" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Today', 'taxi-peninsula' ); ?></a>
			<strong class="tp-dispatch__date"><?php echo esc_html( wp_date( 'l j F Y', $dt->getTimestamp() ) ); ?></strong>
			<span class="tp-dispatch__views" role="group" aria-label="<?php esc_attr_e( 'View', 'taxi-peninsula' ); ?>">
				<a class="button<?php echo 'columns' === $view ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'day' => $day, 'view' => 'columns' ), $base ) ); ?>"<?php echo 'columns' === $view ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Columns', 'taxi-peninsula' ); ?></a>
				<a class="button<?php echo 'timeline' === $view ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'day' => $day, 'view' => 'timeline' ), $base ) ); ?>"<?php echo 'timeline' === $view ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Timeline', 'taxi-peninsula' ); ?></a>
			</span>
			<noscript><button class="button" type="submit"><?php esc_html_e( 'Go', 'taxi-peninsula' ); ?></button></noscript>
		</form>

		<ul class="tp-tiles tp-dispatch__tiles">
			<li><span class="tp-tiles__n"><?php echo esc_html( count( $trips ) ); ?></span><?php esc_html_e( 'Trips', 'taxi-peninsula' ); ?></li>
			<li class="<?php echo $unassigned ? 'is-alert' : ''; ?>"><span class="tp-tiles__n"><?php echo esc_html( count( $unassigned ) ); ?></span><?php esc_html_e( 'Need a driver', 'taxi-peninsula' ); ?></li>
			<li class="<?php echo $warn_count ? 'is-alert' : ''; ?>"><span class="tp-tiles__n"><?php echo esc_html( $warn_count ); ?></span><?php esc_html_e( 'Warnings', 'taxi-peninsula' ); ?></li>
			<li><span class="tp-tiles__n"><?php echo esc_html( tp_open_request_count() ); ?></span><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=tp_booking&page=tp-requests' ) ); ?>"><?php esc_html_e( 'Change requests', 'taxi-peninsula' ); ?></a></li>
		</ul>

		<?php if ( 'timeline' === $view ) : ?>
			<?php tp_dispatch_timeline( $day, $drivers, $by_driver, $unassigned ); ?>
		<?php endif; ?>

		<section class="tp-dispatch__unassigned" aria-labelledby="tp-unassigned">
			<h2 id="tp-unassigned"><?php esc_html_e( 'Needs a driver', 'taxi-peninsula' ); ?> <span class="count">(<?php echo esc_html( count( $unassigned ) ); ?>)</span></h2>
			<?php if ( ! $unassigned ) : ?>
				<div class="tp-admin-empty"><?php echo tp_illustration( 'success' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><p><?php esc_html_e( 'Every trip for this day has a driver.', 'taxi-peninsula' ); ?></p></div>
			<?php else : ?>
				<div class="tp-dispatch__cards">
					<?php foreach ( $unassigned as $b ) : ?>
						<?php tp_dispatch_card( $b, $drivers, array(), $day ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>

		<?php if ( 'columns' === $view ) : ?>
		<h2><?php esc_html_e( 'Drivers', 'taxi-peninsula' ); ?></h2>
		<?php endif; ?>
		<?php if ( 'timeline' === $view ) : ?>
			<?php // Timeline already shows the drivers. ?>
		<?php elseif ( ! $drivers ) : ?>
			<p><?php esc_html_e( 'No drivers yet.', 'taxi-peninsula' ); ?> <a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>"><?php esc_html_e( 'Add a user with the Driver role.', 'taxi-peninsula' ); ?></a></p>
		<?php else : ?>
			<div class="tp-dispatch__columns" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Trips by driver', 'taxi-peninsula' ); ?>">
				<?php foreach ( $drivers as $d ) : ?>
					<?php
					$list   = $by_driver[ $d->ID ] ?? array();
					$mobile = get_user_meta( $d->ID, 'tp_mobile', true );
					$fleet  = (int) get_user_meta( $d->ID, 'tp_default_fleet', true );
					list( $avg, $n ) = tp_driver_rating( $d->ID );
					?>
					<section class="tp-dispatch__col" aria-labelledby="tp-col-<?php echo esc_attr( $d->ID ); ?>">
						<header class="tp-dispatch__colhead">
							<h3 id="tp-col-<?php echo esc_attr( $d->ID ); ?>"><?php echo esc_html( $d->display_name ); ?> <span class="count">(<?php echo esc_html( count( $list ) ); ?>)</span></h3>
							<p>
								<?php if ( $mobile ) : ?>
									<a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $mobile ) ); ?>"><?php echo esc_html( $mobile ); ?></a>
								<?php endif; ?>
								<?php echo $fleet ? '<br>' . esc_html( tp_fleet_label( $fleet ) ) : ''; ?>
								<?php if ( $n ) : ?>
									<br>
									<?php
									/* translators: 1: average stars, 2: number of ratings */
									echo esc_html( sprintf( __( '%1$s★ from %2$d ratings', 'taxi-peninsula' ), number_format_i18n( $avg, 1 ), $n ) );
									?>
								<?php endif; ?>
							</p>
						</header>
						<?php if ( ! $list ) : ?>
							<p class="description"><?php esc_html_e( 'No trips.', 'taxi-peninsula' ); ?></p>
						<?php endif; ?>
						<?php foreach ( $list as $b ) : ?>
							<?php tp_dispatch_card( $b, $drivers, $list, $day ); ?>
						<?php endforeach; ?>
					</section>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Changing the driver saves straight away, texts the new driver and lets the customer know. Warnings never block an assignment — they are there to help you double-check.', 'taxi-peninsula' ); ?></p>
	</div>
	<?php
}

/**
 * Timeline: drivers as rows, the day's hours across the top, trips as blocks.
 * Overlapping trips stack in lanes and are outlined in red.
 */
function tp_dispatch_timeline( $day, array $drivers, array $by_driver, array $unassigned ) {
	$day_start = DateTimeImmutable::createFromFormat( '!Y-m-d', $day, wp_timezone() )->getTimestamp();
	$first     = 6;
	$last      = 20;
	$all       = array_merge( $unassigned, ...array_values( $by_driver ) );
	foreach ( $all as $b ) {
		list( $s, $e ) = tp_trip_window( $b );
		$first = min( $first, (int) floor( ( $s - $day_start ) / HOUR_IN_SECONDS ) );
		$last  = max( $last, (int) ceil( ( $e - $day_start ) / HOUR_IN_SECONDS ) );
	}
	$first = max( 0, $first );
	$last  = min( 24, max( $last, $first + 4 ) );
	$span  = ( $last - $first ) * HOUR_IN_SECONDS;
	$from  = $day_start + $first * HOUR_IN_SECONDS;

	$rows = array( array( __( 'Needs a driver', 'taxi-peninsula' ), $unassigned, true ) );
	foreach ( $drivers as $d ) {
		$rows[] = array( $d->display_name, $by_driver[ $d->ID ] ?? array(), false );
	}
	$now = time();
	?>
	<section class="tp-timeline" aria-labelledby="tp-timeline-title" style="--hours: <?php echo esc_attr( $last - $first ); ?>">
		<h2 id="tp-timeline-title" class="screen-reader-text"><?php esc_html_e( 'Timeline', 'taxi-peninsula' ); ?></h2>
		<div class="tp-timeline__scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Trips by driver and time', 'taxi-peninsula' ); ?>">
			<div class="tp-timeline__grid">
				<div class="tp-timeline__row tp-timeline__row--head" aria-hidden="true">
					<div class="tp-timeline__label"></div>
					<div class="tp-timeline__track">
						<?php for ( $h = $first; $h < $last; $h++ ) : ?>
							<span class="tp-timeline__hour" style="left: <?php echo esc_attr( ( $h - $first ) / ( $last - $first ) * 100 ); ?>%"><?php echo esc_html( wp_date( 'ga', $day_start + $h * HOUR_IN_SECONDS ) ); ?></span>
						<?php endfor; ?>
					</div>
				</div>
				<?php foreach ( $rows as $row ) : ?>
					<?php
					list( $label, $trips, $is_unassigned ) = $row;
					// Lane packing so overlapping trips don't hide each other.
					$lanes  = array();
					$placed = array();
					foreach ( $trips as $b ) {
						list( $s, $e ) = tp_trip_window( $b );
						$lane = 0;
						while ( isset( $lanes[ $lane ] ) && $lanes[ $lane ] > $s ) {
							$lane++;
						}
						$lanes[ $lane ] = $e;
						$clash          = ! $is_unassigned && in_array( $b['status'], tp_active_statuses(), true ) && (bool) array_filter(
							$trips,
							static function ( $o ) use ( $b, $s, $e ) {
								if ( $o['id'] === $b['id'] || ! in_array( $o['status'], tp_active_statuses(), true ) ) {
									return false;
								}
								list( $os, $oe ) = tp_trip_window( $o );
								return $s < $oe && $os < $e;
							}
						);
						$placed[] = array( $b, $s, $e, $lane, $clash );
					}
					$height = max( 1, count( $lanes ) );
					?>
					<div class="tp-timeline__row<?php echo $is_unassigned ? ' tp-timeline__row--unassigned' : ''; ?>">
						<div class="tp-timeline__label"><strong><?php echo esc_html( $label ); ?></strong> <span class="count">(<?php echo esc_html( count( $trips ) ); ?>)</span></div>
						<ul class="tp-timeline__track" style="--lanes: <?php echo esc_attr( $height ); ?>">
							<?php if ( $now > $from && $now < $from + $span ) : ?>
								<li class="tp-timeline__now" style="left: <?php echo esc_attr( ( $now - $from ) / $span * 100 ); ?>%" aria-hidden="true"></li>
							<?php endif; ?>
							<?php foreach ( $placed as $item ) : ?>
								<?php
								list( $b, $s, $e, $lane, $clash ) = $item;
								$left  = max( 0, ( $s - $from ) / $span * 100 );
								$width = max( 2.5, ( $e - $s ) / $span * 100 );
								?>
								<li class="tp-tblock tp-tblock--<?php echo esc_attr( $b['status'] ); ?><?php echo $clash ? ' is-clash' : ''; ?>" style="left: <?php echo esc_attr( round( $left, 3 ) ); ?>%; width: <?php echo esc_attr( round( $width, 3 ) ); ?>%; --lane: <?php echo esc_attr( $lane ); ?>">
									<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=tp_booking&page=tp-dispatch&view=columns&day=' . rawurlencode( $day ) . '#tp-card-' . $b['id'] ) ); ?>" title="<?php echo esc_attr( tp_format_pickup( $b, 'g:i a' ) . ' · ' . $b['name'] . ' · ' . $b['pickup'] . ' → ' . $b['dropoff'] . ' · ' . ( tp_statuses()[ $b['status'] ] ?? '' ) ); ?>">
										<strong><?php echo esc_html( tp_format_pickup( $b, 'g:i' ) ); ?></strong>
										<span><?php echo esc_html( $b['name'] ); ?> · <?php echo esc_html( tp_short_place( $b['pickup'] ) . '→' . tp_short_place( $b['dropoff'] ) ); ?></span>
										<?php if ( $clash ) : ?>
											<span class="screen-reader-text"><?php esc_html_e( '(clashes with another trip)', 'taxi-peninsula' ); ?></span>
										<?php endif; ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Each block is the pick-up time plus the trip and 15 minutes to load. Red outline = overlapping trips for the same driver. Click a block to assign or move it in the column view.', 'taxi-peninsula' ); ?></p>
	</section>
	<?php
}

/**
 * One trip on the board, with quick assign controls.
 */
function tp_dispatch_card( array $b, array $drivers, array $driver_trips, $day ) {
	$active   = in_array( $b['status'], tp_active_statuses(), true );
	$warnings = $active && $b['driver_id'] ? tp_trip_warnings( $b, $b['driver_id'], $b['fleet_id'], $driver_trips ) : ( $active ? tp_trip_warnings( $b, 0, $b['fleet_id'] ) : array() );
	$declines = array_filter( (array) get_post_meta( $b['id'], '_tp_declines', true ) );
	$last     = $declines ? end( $declines ) : null;
	$vehicles = tp_vehicle_types();
	$id       = 'tp-card-' . $b['id'];
	$locked   = in_array( $b['status'], array( 'on_way', 'arrived', 'onboard' ), true );
	?>
	<article class="tp-dcard tp-dcard--<?php echo esc_attr( $b['status'] ); ?><?php echo $warnings ? ' has-warning' : ''; ?>" id="<?php echo esc_attr( $id ); ?>">
		<header class="tp-dcard__head">
			<strong class="tp-dcard__time"><?php echo esc_html( tp_format_pickup( $b, 'g:i a' ) ); ?></strong>
			<?php echo tp_status_badge( $b['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</header>
		<p class="tp-dcard__who"><a href="<?php echo esc_url( get_edit_post_link( $b['id'] ) ); ?>"><?php echo esc_html( $b['reference'] ); ?></a> · <?php echo esc_html( $b['name'] ); ?></p>
		<p class="tp-dcard__route"><?php echo esc_html( tp_short_place( $b['pickup'] ) ?: $b['pickup'] ); ?> → <?php echo esc_html( tp_short_place( $b['dropoff'] ) ?: $b['dropoff'] ); ?></p>
		<p class="tp-dcard__needs">
			<?php echo esc_html( $vehicles[ $b['vehicle'] ] ?? $b['vehicle'] ); ?>
			<?php
			/* translators: 1: passengers, 2: wheelchairs */
			echo ' · ' . esc_html( sprintf( __( '%1$d pax, %2$d wheelchair(s)', 'taxi-peninsula' ), $b['passengers'], $b['wheelchairs'] ) );
			?>
		</p>
		<?php echo tp_flight_badge( $b ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		<?php if ( $last && ! $b['driver_id'] ) : ?>
			<?php $who = get_userdata( $last['driver'] ); ?>
			<p class="tp-dcard__flag tp-dcard__flag--declined">
				<?php
				/* translators: 1: driver, 2: reason */
				echo esc_html( sprintf( __( 'Declined by %1$s: %2$s', 'taxi-peninsula' ), $who ? $who->display_name : '?', $last['reason'] ) );
				?>
			</p>
		<?php endif; ?>
		<?php if ( ! empty( $b['request'] ) ) : ?>
			<p class="tp-dcard__flag"><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=tp_booking&page=tp-requests#req-' . $b['id'] ) ); ?>"><?php esc_html_e( 'Customer has a change request', 'taxi-peninsula' ); ?></a></p>
		<?php endif; ?>
		<?php foreach ( $warnings as $w ) : ?>
			<p class="tp-dcard__flag tp-dcard__flag--warn"><span class="dashicons dashicons-warning" aria-hidden="true"></span> <?php echo esc_html( $w ); ?></p>
		<?php endforeach; ?>

		<?php if ( $active && ! $locked ) : ?>
			<form class="tp-dcard__assign" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tp_dispatch_assign">
				<input type="hidden" name="booking" value="<?php echo esc_attr( $b['id'] ); ?>">
				<input type="hidden" name="day" value="<?php echo esc_attr( $day ); ?>">
				<?php wp_nonce_field( 'tp_dispatch_' . $b['id'] ); ?>
				<label for="<?php echo esc_attr( $id ); ?>-driver"><?php esc_html_e( 'Driver', 'taxi-peninsula' ); ?></label>
				<select id="<?php echo esc_attr( $id ); ?>-driver" name="driver" data-autosubmit>
					<option value="0"><?php esc_html_e( '— Unassigned —', 'taxi-peninsula' ); ?></option>
					<?php foreach ( $drivers as $d ) : ?>
						<option value="<?php echo esc_attr( $d->ID ); ?>" <?php selected( $b['driver_id'], $d->ID ); ?>><?php echo esc_html( $d->display_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="<?php echo esc_attr( $id ); ?>-fleet"><?php esc_html_e( 'Vehicle', 'taxi-peninsula' ); ?></label>
				<select id="<?php echo esc_attr( $id ); ?>-fleet" name="fleet" data-autosubmit>
					<option value="0"><?php esc_html_e( 'Driver’s usual vehicle', 'taxi-peninsula' ); ?></option>
					<?php foreach ( tp_fleet_vehicles() as $v ) : ?>
						<option value="<?php echo esc_attr( $v->ID ); ?>" <?php selected( $b['fleet_id'], $v->ID ); ?>><?php echo esc_html( $v->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-small"><?php esc_html_e( 'Save', 'taxi-peninsula' ); ?></button>
			</form>
		<?php elseif ( $locked ) : ?>
			<p class="description"><?php esc_html_e( 'Trip in progress.', 'taxi-peninsula' ); ?></p>
		<?php endif; ?>
	</article>
	<?php
}

add_action( 'admin_footer', static function () {
	$screen = get_current_screen();
	if ( ! $screen || false === strpos( $screen->id, 'tp-dispatch' ) ) {
		return;
	}
	?>
	<script>
	document.querySelectorAll('.tp-dcard__assign [data-autosubmit]').forEach(function (s) {
		s.addEventListener('change', function () { s.form.submit(); });
	});
	</script>
	<?php
} );

add_action( 'admin_post_tp_dispatch_assign', 'tp_handle_dispatch_assign' );
function tp_handle_dispatch_assign() {
	$post_id = isset( $_POST['booking'] ) ? absint( $_POST['booking'] ) : 0;
	check_admin_referer( 'tp_dispatch_' . $post_id );
	if ( 'tp_booking' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'You cannot change this booking.', 'taxi-peninsula' ), 403 );
	}
	$driver_id = isset( $_POST['driver'] ) ? absint( $_POST['driver'] ) : 0;
	$fleet_id  = isset( $_POST['fleet'] ) ? absint( $_POST['fleet'] ) : 0;
	$day       = isset( $_POST['day'] ) ? sanitize_text_field( wp_unslash( $_POST['day'] ) ) : '';
	if ( $driver_id && ! in_array( 'tp_driver', (array) ( get_userdata( $driver_id )->roles ?? array() ), true ) ) {
		$driver_id = 0;
	}
	if ( ! $fleet_id && $driver_id ) {
		$fleet_id = (int) get_user_meta( $driver_id, 'tp_default_fleet', true );
	}
	if ( $fleet_id && 'tp_fleet' !== get_post_type( $fleet_id ) ) {
		$fleet_id = 0;
	}

	$b      = tp_get_booking( $post_id );
	$notify = (bool) tp_opt( 'customer_emails' ) || tp_sms_enabled();
	tp_assign( $post_id, $driver_id, $fleet_id, $notify );

	$driver = $driver_id ? get_userdata( $driver_id ) : null;
	set_transient(
		'tp_dispatch_notice_' . get_current_user_id(),
		array(
			'message'  => $driver
				/* translators: 1: reference, 2: driver */
				? sprintf( __( '%1$s assigned to %2$s.', 'taxi-peninsula' ), $b['reference'], $driver->display_name )
				/* translators: %s: reference */
				: sprintf( __( '%s is now unassigned.', 'taxi-peninsula' ), $b['reference'] ),
			'warnings' => tp_trip_warnings( tp_get_booking( $post_id ), $driver_id, $fleet_id ),
		),
		MINUTE_IN_SECONDS
	);
	wp_safe_redirect( admin_url( 'edit.php?post_type=tp_booking&page=tp-dispatch&day=' . rawurlencode( $day ) . '#tp-card-' . $post_id ) );
	exit;
}

/* -------------------------------------------------------------------------
 * Change requests inbox.
 * ---------------------------------------------------------------------- */

function tp_open_request_ids() {
	return get_posts(
		array(
			'post_type'   => 'tp_booking',
			'numberposts' => 200,
			'fields'      => 'ids',
			'meta_key'    => '_tp_pickup_at',
			'orderby'     => 'meta_value',
			'order'       => 'ASC',
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array( 'key' => '_tp_request', 'compare' => 'EXISTS' ),
			),
		)
	);
}

function tp_open_request_count() {
	static $n = null;
	if ( null === $n ) {
		$n = count( tp_open_request_ids() );
	}
	return $n;
}

function tp_render_requests_page() {
	if ( ! current_user_can( 'edit_tp_bookings' ) ) {
		return;
	}
	$types = tp_request_types();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$done = isset( $_GET['decided'] ) ? sanitize_key( wp_unslash( $_GET['decided'] ) ) : '';
	?>
	<div class="wrap tp-requests">
		<h1><?php esc_html_e( 'Change requests', 'taxi-peninsula' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Changes and cancellations customers asked for on their booking page. Approving or declining lets the customer know by email and SMS, including your reply.', 'taxi-peninsula' ); ?></p>
		<?php if ( 'approved' === $done || 'declined' === $done ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( 'approved' === $done ? __( 'Request approved and the customer has been told.', 'taxi-peninsula' ) : __( 'Request declined and the customer has been told.', 'taxi-peninsula' ) ); ?></p></div>
		<?php endif; ?>

		<?php $ids = tp_open_request_ids(); ?>
		<?php if ( ! $ids ) : ?>
			<div class="tp-admin-empty tp-empty"><?php echo tp_illustration( 'message' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><p><?php esc_html_e( 'No open requests. Nice work!', 'taxi-peninsula' ); ?></p></div>
		<?php endif; ?>

		<?php foreach ( $ids as $id ) : ?>
			<?php
			$b   = tp_get_booking( $id );
			$req = is_array( $b['request'] ) ? $b['request'] : array( 'type' => 'change', 'message' => '', 'time' => '' );
			$pre = 'req-' . $id;
			?>
			<article class="tp-req card" id="<?php echo esc_attr( $pre ); ?>">
				<header class="tp-req__head">
					<h2><?php echo esc_html( $types[ $req['type'] ] ?? __( 'Request', 'taxi-peninsula' ) ); ?> — <a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>"><?php echo esc_html( $b['reference'] ); ?></a></h2>
					<?php echo tp_status_badge( $b['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</header>
				<p class="tp-req__meta">
					<?php
					/* translators: %s: date/time */
					echo esc_html( sprintf( __( 'Asked %s', 'taxi-peninsula' ), $req['time'] ? mysql2date( 'j M Y g:i a', $req['time'] ) : '' ) );
					?>
					· <?php echo esc_html( $b['name'] ); ?> · <a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $b['phone'] ) ); ?>"><?php echo esc_html( $b['phone'] ); ?></a>
				</p>
				<?php if ( $req['message'] ) : ?>
					<blockquote class="tp-req__msg"><?php echo esc_html( $req['message'] ); ?></blockquote>
				<?php endif; ?>
				<p><strong><?php esc_html_e( 'Current trip:', 'taxi-peninsula' ); ?></strong> <?php echo esc_html( tp_format_pickup( $b ) ); ?> — <?php echo esc_html( $b['pickup'] ); ?> → <?php echo esc_html( $b['dropoff'] ); ?></p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tp-req__form">
					<input type="hidden" name="action" value="tp_request_decide">
					<input type="hidden" name="booking" value="<?php echo esc_attr( $id ); ?>">
					<?php wp_nonce_field( 'tp_decide_' . $id ); ?>
					<?php if ( 'change' === $req['type'] ) : ?>
						<fieldset class="tp-req__change">
							<legend><?php esc_html_e( 'If approving, update the trip (leave as is to keep):', 'taxi-peninsula' ); ?></legend>
							<label><?php esc_html_e( 'Date', 'taxi-peninsula' ); ?> <input type="date" name="new_date" value="<?php echo esc_attr( $b['date'] ); ?>"></label>
							<label><?php esc_html_e( 'Pick-up time', 'taxi-peninsula' ); ?> <input type="time" name="new_time" value="<?php echo esc_attr( $b['time'] ); ?>"></label>
							<label class="tp-req__wide"><?php esc_html_e( 'Pick-up address', 'taxi-peninsula' ); ?> <input type="text" name="new_pickup" value="<?php echo esc_attr( $b['pickup'] ); ?>"></label>
							<label class="tp-req__wide"><?php esc_html_e( 'Drop-off address', 'taxi-peninsula' ); ?> <input type="text" name="new_dropoff" value="<?php echo esc_attr( $b['dropoff'] ); ?>"></label>
						</fieldset>
					<?php endif; ?>
					<p>
						<label for="<?php echo esc_attr( $pre ); ?>-reply"><strong><?php esc_html_e( 'Message to the customer (optional)', 'taxi-peninsula' ); ?></strong></label><br>
						<textarea id="<?php echo esc_attr( $pre ); ?>-reply" name="reply" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'e.g. All done — see you at 10:15am.', 'taxi-peninsula' ); ?>"></textarea>
					</p>
					<p class="tp-req__buttons">
						<button type="submit" name="decision" value="approve" class="button button-primary">
							<?php echo esc_html( 'change' === $req['type'] ? __( 'Approve change', 'taxi-peninsula' ) : __( 'Approve cancellation', 'taxi-peninsula' ) ); ?>
						</button>
						<button type="submit" name="decision" value="decline" class="button"><?php esc_html_e( 'Decline', 'taxi-peninsula' ); ?></button>
					</p>
				</form>
			</article>
		<?php endforeach; ?>
	</div>
	<?php
}

add_action( 'admin_post_tp_request_decide', 'tp_handle_request_decide' );
function tp_handle_request_decide() {
	$post_id = isset( $_POST['booking'] ) ? absint( $_POST['booking'] ) : 0;
	check_admin_referer( 'tp_decide_' . $post_id );
	if ( 'tp_booking' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'You cannot change this booking.', 'taxi-peninsula' ), 403 );
	}
	$b        = tp_get_booking( $post_id );
	$req      = is_array( $b['request'] ) ? $b['request'] : array( 'type' => 'change' );
	$decision = isset( $_POST['decision'] ) && 'approve' === $_POST['decision'] ? 'approve' : 'decline';
	$reply    = isset( $_POST['reply'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reply'] ) ) : '';
	$site     = tp_sms_site_name();

	if ( 'approve' === $decision ) {
		if ( 'change' === $req['type'] ) {
			$changes = array();
			$date    = isset( $_POST['new_date'] ) ? sanitize_text_field( wp_unslash( $_POST['new_date'] ) ) : '';
			$time    = isset( $_POST['new_time'] ) ? sanitize_text_field( wp_unslash( $_POST['new_time'] ) ) : '';
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && $date !== $b['date'] ) {
				$changes['date'] = $date;
			}
			if ( preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) && $time !== $b['time'] ) {
				$changes['time'] = $time;
			}
			foreach ( array( 'pickup', 'dropoff' ) as $key ) {
				$val = isset( $_POST[ 'new_' . $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'new_' . $key ] ) ) : '';
				if ( '' !== $val && $val !== $b[ $key ] ) {
					$changes[ $key ] = $val;
				}
			}
			if ( $changes ) {
				tp_save_booking_meta( $post_id, array_merge( $b, $changes ) );
				$b = tp_get_booking( $post_id );
			}
			/* translators: 1: site, 2: reference, 3: pick-up time */
			$msg = sprintf( __( '%1$s: your change to booking %2$s is approved. Pick-up: %3$s.', 'taxi-peninsula' ), $site, $b['reference'], tp_format_pickup( $b, 'D j M g:ia' ) );
			tp_add_log( $post_id, array( 'note' => __( 'Change request approved', 'taxi-peninsula' ) . ( $changes ? ' (' . implode( ', ', array_keys( $changes ) ) . ')' : '' ) ) );
		} else {
			$cancel = array( $post_id );
			if ( 'cancel_series' === $req['type'] && $b['series'] ) {
				$from = get_post_meta( $post_id, '_tp_pickup_at', true );
				foreach ( tp_series_ids( $b['series'] ) as $sid ) {
					if ( get_post_meta( $sid, '_tp_pickup_at', true ) >= $from ) {
						$cancel[] = (int) $sid;
					}
				}
			}
			foreach ( array_unique( $cancel ) as $cid ) {
				tp_set_status( $cid, 'cancelled', false );
			}
			/* translators: 1: site, 2: reference */
			$msg = sprintf( __( '%1$s: your cancellation of booking %2$s is confirmed.', 'taxi-peninsula' ), $site, $b['reference'] );
			tp_add_log( $post_id, array( 'note' => __( 'Cancellation request approved', 'taxi-peninsula' ) ) );
		}
	} else {
		/* translators: 1: site, 2: reference */
		$msg = sprintf( __( '%1$s: we could not make the change you asked for on booking %2$s.', 'taxi-peninsula' ), $site, $b['reference'] );
		tp_add_log( $post_id, array( 'note' => __( 'Customer request declined', 'taxi-peninsula' ) . ( $reply ? ': ' . $reply : '' ) ) );
	}
	if ( $reply ) {
		$msg .= ' ' . $reply;
	}
	/* translators: %s: phone */
	$msg .= ' ' . sprintf( __( 'Questions? Call %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) );
	delete_post_meta( $post_id, '_tp_request' );
	tp_notify_customer( $post_id, 'approve' === $decision ? __( 'Your request is approved', 'taxi-peninsula' ) : __( 'About your request', 'taxi-peninsula' ), $msg );

	wp_safe_redirect( admin_url( 'edit.php?post_type=tp_booking&page=tp-requests&decided=' . ( 'approve' === $decision ? 'approved' : 'declined' ) ) );
	exit;
}
