<?php
/**
 * Drivers: the Driver role, mobile number on user profiles, and the
 * mobile-friendly [tp_driver_jobs] page listing each driver's jobs for a day.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * Users who can be assigned to bookings.
 *
 * @return WP_User[]
 */
function tp_drivers() {
	return get_users(
		array(
			'role__in' => array( 'tp_driver' ),
			'orderby'  => 'display_name',
		)
	);
}

function tp_is_driver_only( $user = null ) {
	$user = $user ?: wp_get_current_user();
	return $user && $user->exists() && user_can( $user, 'tp_driver_jobs' ) && ! user_can( $user, 'edit_tp_bookings' );
}

/* ---- Profile: mobile number used for job SMS ---- */

add_action( 'show_user_profile', 'tp_user_mobile_field' );
add_action( 'edit_user_profile', 'tp_user_mobile_field' );
function tp_user_mobile_field( WP_User $user ) {
	if ( ! user_can( $user, 'tp_driver_jobs' ) && ! current_user_can( 'edit_tp_bookings' ) ) {
		return;
	}
	?>
	<h2><?php esc_html_e( 'Driver details', 'taxi-peninsula' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="tp_mobile"><?php esc_html_e( 'Mobile (for job SMS)', 'taxi-peninsula' ); ?></label></th>
			<td><input type="tel" name="tp_mobile" id="tp_mobile" class="regular-text" value="<?php echo esc_attr( get_user_meta( $user->ID, 'tp_mobile', true ) ); ?>"></td>
		</tr>
		<?php if ( user_can( $user, 'tp_driver_jobs' ) && current_user_can( 'edit_tp_bookings' ) ) : ?>
			<tr>
				<th><label for="tp_default_fleet"><?php esc_html_e( 'Usual vehicle', 'taxi-peninsula' ); ?></label></th>
				<td>
					<select name="tp_default_fleet" id="tp_default_fleet">
						<option value="0"><?php esc_html_e( '— None —', 'taxi-peninsula' ); ?></option>
						<?php foreach ( tp_fleet_vehicles() as $v ) : ?>
							<option value="<?php echo esc_attr( $v->ID ); ?>" <?php selected( (int) get_user_meta( $user->ID, 'tp_default_fleet', true ), $v->ID ); ?>><?php echo esc_html( $v->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Used on the dispatch board when you assign this driver without choosing a vehicle.', 'taxi-peninsula' ); ?></p>
				</td>
			</tr>
		<?php endif; ?>
	</table>
	<?php
}

add_action( 'personal_options_update', 'tp_save_user_mobile' );
add_action( 'edit_user_profile_update', 'tp_save_user_mobile' );
function tp_save_user_mobile( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) || ! isset( $_POST['tp_mobile'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- core verifies the profile nonce.
		return;
	}
	update_user_meta( $user_id, 'tp_mobile', sanitize_text_field( wp_unslash( $_POST['tp_mobile'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( isset( $_POST['tp_default_fleet'] ) && current_user_can( 'edit_tp_bookings' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$fleet = absint( $_POST['tp_default_fleet'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		update_user_meta( $user_id, 'tp_default_fleet', 'tp_fleet' === get_post_type( $fleet ) ? $fleet : 0 );
	}
}

/* ---- Keep drivers on the front end ---- */

add_filter(
	'login_redirect',
	static function ( $redirect, $requested, $user ) {
		if ( $user instanceof WP_User && tp_is_driver_only( $user ) ) {
			return tp_page_url_by_template( 'driver', home_url( '/' ) );
		}
		return $redirect;
	},
	10,
	3
);

add_action(
	'admin_init',
	static function () {
		if ( wp_doing_ajax() || ! tp_is_driver_only() ) {
			return;
		}
		global $pagenow;
		if ( 'profile.php' === $pagenow || 'admin-post.php' === $pagenow ) {
			return;
		}
		wp_safe_redirect( tp_page_url_by_template( 'driver', home_url( '/' ) ) );
		exit;
	}
);

add_filter(
	'show_admin_bar',
	static function ( $show ) {
		return tp_is_driver_only() ? false : $show;
	}
);

/* ---- Jobs page ---- */

add_shortcode( 'tp_driver_jobs', 'tp_driver_jobs_shortcode' );
function tp_driver_jobs_shortcode() {
	ob_start();
	echo '<div class="tp-component driver-jobs">';

	if ( ! is_user_logged_in() ) {
		echo '<div class="card"><h2>' . esc_html__( 'Driver sign in', 'taxi-peninsula' ) . '</h2>';
		wp_login_form( array( 'redirect' => get_permalink() ) );
		echo '</div></div>';
		return ob_get_clean();
	}

	$is_manager = current_user_can( 'edit_tp_bookings' );
	if ( ! $is_manager && ! current_user_can( 'tp_driver_jobs' ) ) {
		echo '<p class="notice notice--error">' . esc_html__( 'This page is for drivers only.', 'taxi-peninsula' ) . '</p></div>';
		return ob_get_clean();
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
	$view      = isset( $_GET['view'] ) && 'week' === $_GET['view'] ? 'week' : 'day';
	$driver_id = $is_manager ? ( isset( $_GET['driver'] ) ? absint( $_GET['driver'] ) : 0 ) : get_current_user_id();
	// phpcs:enable
	$base = get_permalink();

	$tabs = array(
		'day'  => __( 'Jobs by day', 'taxi-peninsula' ),
		'week' => __( 'My week', 'taxi-peninsula' ),
	);
	echo '<nav class="driver-jobs__tabs" aria-label="' . esc_attr__( 'Driver views', 'taxi-peninsula' ) . '">';
	foreach ( $tabs as $key => $label ) {
		$args = array( 'view' => 'week' === $key ? 'week' : false );
		if ( $is_manager && $driver_id ) {
			$args['driver'] = $driver_id;
		}
		printf(
			'<a class="btn %1$s" href="%2$s"%3$s>%4$s</a>',
			$view === $key ? 'btn--primary' : 'btn--ghost',
			esc_url( add_query_arg( array_filter( $args ), $base ) ),
			$view === $key ? ' aria-current="page"' : '',
			esc_html( $label )
		);
	}
	echo '</nav>';

	if ( $is_manager ) {
		tp_driver_jobs_filter( $base, $driver_id, $view );
	}

	if ( 'week' === $view ) {
		tp_driver_week_view( $driver_id, $base, $is_manager );
	} else {
		tp_driver_day_view( $driver_id, $base, $is_manager );
	}

	printf( '<p class="driver-jobs__logout"><a href="%s">%s</a></p>', esc_url( wp_logout_url( $base ) ), esc_html__( 'Sign out', 'taxi-peninsula' ) );
	echo '</div>';
	return ob_get_clean();
}

/**
 * Office staff can look at any driver's jobs.
 */
function tp_driver_jobs_filter( $base, $driver_id, $view ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$day = isset( $_GET['jobs_day'] ) ? sanitize_text_field( wp_unslash( $_GET['jobs_day'] ) ) : '';
	?>
	<form class="driver-jobs__filter" method="get" action="<?php echo esc_url( $base ); ?>">
		<?php if ( $day ) : ?>
			<input type="hidden" name="jobs_day" value="<?php echo esc_attr( $day ); ?>">
		<?php endif; ?>
		<?php if ( 'week' === $view ) : ?>
			<input type="hidden" name="view" value="week">
		<?php endif; ?>
		<label for="dj-driver"><?php esc_html_e( 'Driver', 'taxi-peninsula' ); ?></label>
		<select id="dj-driver" name="driver" onchange="this.form.submit()">
			<option value="0"><?php esc_html_e( 'All drivers', 'taxi-peninsula' ); ?></option>
			<?php foreach ( tp_drivers() as $d ) : ?>
				<option value="<?php echo esc_attr( $d->ID ); ?>" <?php selected( $driver_id, $d->ID ); ?>><?php echo esc_html( $d->display_name ); ?></option>
			<?php endforeach; ?>
		</select>
		<noscript><button class="btn btn--primary" type="submit"><?php esc_html_e( 'Show', 'taxi-peninsula' ); ?></button></noscript>
	</form>
	<?php
}

function tp_driver_day_view( $driver_id, $base, $is_manager ) {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display state.
	$day = isset( $_GET['jobs_day'] ) ? sanitize_text_field( wp_unslash( $_GET['jobs_day'] ) ) : wp_date( 'Y-m-d' );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
		$day = wp_date( 'Y-m-d' );
	}
	$flash = array(
		'updated'  => isset( $_GET['updated'] ) ? sanitize_key( wp_unslash( $_GET['updated'] ) ) : '',
		'declined' => ! empty( $_GET['declined'] ),
		'stale'    => ! empty( $_GET['stale'] ),
	);
	// phpcs:enable

	$dt      = DateTimeImmutable::createFromFormat( '!Y-m-d', $day, wp_timezone() );
	$nav_arg = $is_manager && $driver_id ? array( 'driver' => $driver_id ) : array();
	?>
	<div class="driver-jobs__bar">
		<a class="btn btn--ghost" href="<?php echo esc_url( add_query_arg( array_merge( $nav_arg, array( 'jobs_day' => $dt->modify( '-1 day' )->format( 'Y-m-d' ) ) ), $base ) ); ?>" aria-label="<?php esc_attr_e( 'Previous day', 'taxi-peninsula' ); ?>">‹</a>
		<h2 class="driver-jobs__day"><?php echo esc_html( wp_date( 'l j F', $dt->getTimestamp() ) ); ?></h2>
		<a class="btn btn--ghost" href="<?php echo esc_url( add_query_arg( array_merge( $nav_arg, array( 'jobs_day' => $dt->modify( '+1 day' )->format( 'Y-m-d' ) ) ), $base ) ); ?>" aria-label="<?php esc_attr_e( 'Next day', 'taxi-peninsula' ); ?>">›</a>
	</div>

	<?php
	$steps = tp_trip_steps();
	if ( $flash['updated'] && isset( $steps[ $flash['updated'] ] ) ) {
		$status_label = tp_statuses()[ $steps[ $flash['updated'] ][0] ];
		/* translators: %s: new status */
		echo '<p class="notice notice--success" role="status">' . esc_html( sprintf( __( 'Trip updated: %s.', 'taxi-peninsula' ), $status_label ) ) . '</p>';
	} elseif ( $flash['declined'] ) {
		echo '<p class="notice notice--success" role="status">' . esc_html__( 'Trip declined. The office has been told so they can reassign it.', 'taxi-peninsula' ) . '</p>';
	} elseif ( $flash['stale'] ) {
		echo '<p class="notice notice--info" role="status">' . esc_html__( 'That trip had already changed, so nothing was updated. Here is its latest status.', 'taxi-peninsula' ) . '</p>';
	}

	$meta = array(
		'relation' => 'AND',
		array( 'key' => '_tp_date', 'value' => $day ),
		array( 'key' => '_tp_status', 'value' => 'cancelled', 'compare' => '!=' ),
	);
	if ( $driver_id ) {
		$meta[] = array( 'key' => '_tp_driver_id', 'value' => $driver_id );
	}
	$jobs = get_posts(
		array(
			'post_type'   => 'tp_booking',
			'numberposts' => 100,
			'meta_key'    => '_tp_pickup_at',
			'orderby'     => 'meta_value',
			'order'       => 'ASC',
			'meta_query'  => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		)
	);

	if ( ! $jobs ) {
		echo '<p class="empty-state">' . esc_html__( 'No jobs for this day.', 'taxi-peninsula' ) . '</p>';
	}
	foreach ( $jobs as $job ) {
		tp_driver_job_card( tp_get_booking( $job->ID ), $day, $is_manager );
	}
}

/**
 * One job card with the driver's next actions.
 */
function tp_driver_job_card( array $b, $day, $is_manager ) {
	$vehicles = tp_vehicle_types();
	$aids     = tp_mobility_aids();
	$maps     = static function ( $addr ) {
		return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $addr );
	};
	$next     = $b['driver_id'] ? tp_trip_next_steps( $b['status'] ) : array();
	$steps    = tp_trip_steps();
	$action   = admin_url( 'admin-post.php' );
	$hidden   = static function ( $step ) use ( $b, $day ) {
		printf( '<input type="hidden" name="action" value="tp_driver_step"><input type="hidden" name="booking" value="%1$d"><input type="hidden" name="day" value="%2$s"><input type="hidden" name="step" value="%3$s">', (int) $b['id'], esc_attr( $day ), esc_attr( $step ) );
		wp_nonce_field( 'tp_step_' . $b['id'] );
	};
	?>
	<article class="job card job--<?php echo esc_attr( $b['status'] ); ?>" id="job-<?php echo esc_attr( $b['id'] ); ?>" tabindex="-1">
		<header class="job__head">
			<p class="job__time"><?php echo esc_html( tp_format_pickup( $b, 'g:i a' ) ); ?></p>
			<?php echo tp_status_badge( $b['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</header>
		<p class="job__name"><strong><?php echo esc_html( $b['name'] ); ?></strong> · <?php echo esc_html( $b['reference'] ); ?>
			<?php if ( $is_manager && $b['driver_id'] ) : ?>
				<?php $d = get_userdata( $b['driver_id'] ); ?>
				· <?php echo esc_html( $d ? $d->display_name : '' ); ?>
			<?php endif; ?>
		</p>
		<ol class="job__route">
			<li><span class="job__label"><?php esc_html_e( 'Pick up', 'taxi-peninsula' ); ?></span> <a href="<?php echo esc_url( $maps( $b['pickup'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $b['pickup'] ); ?></a></li>
			<li><span class="job__label"><?php esc_html_e( 'Drop off', 'taxi-peninsula' ); ?></span> <a href="<?php echo esc_url( $maps( $b['dropoff'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $b['dropoff'] ); ?></a></li>
		</ol>
		<ul class="job__facts">
			<li><?php echo esc_html( $vehicles[ $b['vehicle'] ] ?? $b['vehicle'] ); ?><?php echo $b['fleet_id'] ? ' — ' . esc_html( tp_fleet_label( $b['fleet_id'] ) ) : ''; ?></li>
			<li>
				<?php
				/* translators: 1: passengers, 2: wheelchairs */
				echo esc_html( sprintf( __( '%1$d pax · %2$d wheelchair(s)', 'taxi-peninsula' ), $b['passengers'], $b['wheelchairs'] ) );
				?>
				· <?php echo esc_html( $aids[ $b['mobility_aid'] ] ?? '' ); ?>
			</li>
			<?php if ( $b['return_trip'] ) : ?>
				<li><?php esc_html_e( 'Return trip:', 'taxi-peninsula' ); ?> <?php echo esc_html( $b['return_time'] ?: __( 'TBA', 'taxi-peninsula' ) ); ?></li>
			<?php endif; ?>
			<?php if ( $b['mptp'] ) : ?>
				<li><strong>MPTP</strong></li>
			<?php endif; ?>
			<?php if ( 'account' === $b['payment'] ) : ?>
				<li><?php esc_html_e( 'Account — do not charge passenger', 'taxi-peninsula' ); ?></li>
			<?php elseif ( $b['paid'] > 0 ) : ?>
				<li>
					<?php
					/* translators: %s: amount */
					echo esc_html( sprintf( __( 'Deposit paid: %s', 'taxi-peninsula' ), tp_money( $b['paid'] ) ) );
					?>
				</li>
			<?php endif; ?>
		</ul>
		<?php if ( $b['notes'] ) : ?>
			<p class="job__notes"><?php echo esc_html( $b['notes'] ); ?></p>
		<?php endif; ?>
		<div class="job__actions">
			<a class="btn btn--primary" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $b['phone'] ) ); ?>"><?php tp_the_icon( 'phone' ); ?> <?php esc_html_e( 'Call passenger', 'taxi-peninsula' ); ?></a>
			<?php foreach ( $next as $step ) : ?>
				<?php
				if ( 'decline' === $step ) {
					continue;
				}
				?>
				<form method="post" action="<?php echo esc_url( $action ); ?>">
					<?php $hidden( $step ); ?>
					<button type="submit" class="btn btn--<?php echo esc_attr( $steps[ $step ][2] ); ?>"><?php echo esc_html( $steps[ $step ][1] ); ?></button>
				</form>
			<?php endforeach; ?>
		</div>
		<?php if ( in_array( 'decline', $next, true ) ) : ?>
			<details class="job__decline">
				<summary><?php esc_html_e( 'Can’t do this trip?', 'taxi-peninsula' ); ?></summary>
				<form method="post" action="<?php echo esc_url( $action ); ?>" class="job__decline-form">
					<?php $hidden( 'decline' ); ?>
					<fieldset class="radio-list">
						<legend class="field__label"><?php esc_html_e( 'Reason', 'taxi-peninsula' ); ?></legend>
						<?php foreach ( tp_decline_reasons() as $key => $label ) : ?>
							<label class="radio-row"><input type="radio" name="reason" value="<?php echo esc_attr( $key ); ?>" <?php checked( 'unavailable', $key ); ?>> <span><?php echo esc_html( $label ); ?></span></label>
						<?php endforeach; ?>
					</fieldset>
					<div class="field">
						<label for="decline-note-<?php echo esc_attr( $b['id'] ); ?>"><?php esc_html_e( 'Note for the office (optional)', 'taxi-peninsula' ); ?></label>
						<input type="text" id="decline-note-<?php echo esc_attr( $b['id'] ); ?>" name="reason_note" maxlength="200">
					</div>
					<button type="submit" class="btn btn--ghost"><?php esc_html_e( 'Decline trip', 'taxi-peninsula' ); ?></button>
				</form>
			</details>
		<?php endif; ?>
	</article>
	<?php
}

/**
 * Upcoming trips for the next 7 days and a timesheet of the selected week.
 */
function tp_driver_week_view( $driver_id, $base, $is_manager ) {
	if ( ! $driver_id ) {
		echo '<p class="empty-state">' . esc_html__( 'Choose a driver to see their week.', 'taxi-peninsula' ) . '</p>';
		return;
	}
	$tz = wp_timezone();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view.
	$week  = isset( $_GET['week'] ) ? sanitize_text_field( wp_unslash( $_GET['week'] ) ) : '';
	$today = new DateTimeImmutable( 'today', $tz );
	$start = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $week ) ? DateTimeImmutable::createFromFormat( '!Y-m-d', $week, $tz ) : $today;
	$start = $start->modify( '-' . ( (int) $start->format( 'N' ) - 1 ) . ' days' ); // Monday.
	$end   = $start->modify( '+6 days' );
	$args  = array( 'view' => 'week' );
	if ( $is_manager ) {
		$args['driver'] = $driver_id;
	}

	list( $avg, $count ) = tp_driver_rating( $driver_id );

	// Upcoming: today + 6 days, still to do.
	$upcoming = array_filter(
		tp_driver_trips( $driver_id, $today->format( 'Y-m-d' ), $today->modify( '+6 days' )->format( 'Y-m-d' ) ),
		static function ( $b ) {
			return in_array( $b['status'], tp_active_statuses(), true );
		}
	);
	?>
	<section class="card driver-week" aria-labelledby="dw-up">
		<h2 id="dw-up" class="driver-week__title"><?php esc_html_e( 'Coming up (next 7 days)', 'taxi-peninsula' ); ?></h2>
		<?php if ( ! $upcoming ) : ?>
			<p><?php esc_html_e( 'No upcoming trips.', 'taxi-peninsula' ); ?></p>
		<?php else : ?>
			<ul class="driver-week__list">
				<?php foreach ( $upcoming as $b ) : ?>
					<li>
						<a href="<?php echo esc_url( add_query_arg( array_filter( array( 'jobs_day' => $b['date'], 'driver' => $is_manager ? $driver_id : 0 ) ), $base ) . '#job-' . $b['id'] ); ?>">
							<strong><?php echo esc_html( tp_format_pickup( $b, 'D j M, g:i a' ) ); ?></strong>
						</a>
						<span><?php echo esc_html( $b['name'] ); ?> · <?php echo esc_html( tp_short_place( $b['pickup'] ) ); ?> → <?php echo esc_html( tp_short_place( $b['dropoff'] ) ); ?></span>
						<?php echo tp_status_badge( $b['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>

	<?php
	$trips  = tp_driver_trips( $driver_id, $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ) );
	$done   = array_filter( $trips, static fn( $b ) => in_array( $b['status'], tp_finished_statuses(), true ) );
	$mins   = 0;
	$km     = 0;
	$noshow = 0;
	foreach ( $done as $b ) {
		$mins += tp_trip_minutes( $b['id'] );
		$km   += $b['distance_km'];
		$noshow += 'no_show' === $b['status'] ? 1 : 0;
	}
	$time = static function ( $post_id, $status ) {
		$ts = tp_trip_time( $post_id, $status );
		return $ts ? wp_date( 'g:i a', $ts ) : '—';
	};
	?>
	<section class="card driver-week" aria-labelledby="dw-sheet">
		<div class="driver-jobs__bar">
			<a class="btn btn--ghost" href="<?php echo esc_url( add_query_arg( array_merge( $args, array( 'week' => $start->modify( '-7 days' )->format( 'Y-m-d' ) ) ), $base ) ); ?>" aria-label="<?php esc_attr_e( 'Previous week', 'taxi-peninsula' ); ?>">‹</a>
			<h2 id="dw-sheet" class="driver-jobs__day">
				<?php
				/* translators: 1: start date, 2: end date */
				echo esc_html( sprintf( __( 'Timesheet %1$s – %2$s', 'taxi-peninsula' ), wp_date( 'j M', $start->getTimestamp() ), wp_date( 'j M', $end->getTimestamp() ) ) );
				?>
			</h2>
			<a class="btn btn--ghost" href="<?php echo esc_url( add_query_arg( array_merge( $args, array( 'week' => $start->modify( '+7 days' )->format( 'Y-m-d' ) ) ), $base ) ); ?>" aria-label="<?php esc_attr_e( 'Next week', 'taxi-peninsula' ); ?>">›</a>
		</div>
		<ul class="tp-tiles driver-week__tiles">
			<li><span class="tp-tiles__n"><?php echo esc_html( number_format_i18n( count( $done ) - $noshow ) ); ?></span> <?php esc_html_e( 'Trips completed', 'taxi-peninsula' ); ?></li>
			<li><span class="tp-tiles__n"><?php echo esc_html( number_format_i18n( $mins / 60, 1 ) ); ?></span> <?php esc_html_e( 'Hours with passengers', 'taxi-peninsula' ); ?></li>
			<li><span class="tp-tiles__n"><?php echo esc_html( number_format_i18n( $km, 0 ) ); ?></span> <?php esc_html_e( 'Trip km (estimated)', 'taxi-peninsula' ); ?></li>
			<li><span class="tp-tiles__n"><?php echo esc_html( number_format_i18n( $noshow ) ); ?></span> <?php esc_html_e( 'No-shows', 'taxi-peninsula' ); ?></li>
			<?php if ( $count ) : ?>
				<li><span class="tp-tiles__n"><?php echo esc_html( number_format_i18n( $avg, 1 ) ); ?>★</span> <?php /* translators: %d: number of ratings */ echo esc_html( sprintf( _n( 'Rating (%d review)', 'Rating (%d reviews)', $count, 'taxi-peninsula' ), $count ) ); ?></li>
			<?php endif; ?>
		</ul>
		<?php if ( ! $done ) : ?>
			<p><?php esc_html_e( 'No finished trips this week yet.', 'taxi-peninsula' ); ?></p>
		<?php else : ?>
			<div class="table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Timesheet', 'taxi-peninsula' ); ?>">
				<table class="timesheet">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Date', 'taxi-peninsula' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Booking', 'taxi-peninsula' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Route', 'taxi-peninsula' ); ?></th>
							<th scope="col"><?php esc_html_e( 'On my way', 'taxi-peninsula' ); ?></th>
							<th scope="col"><?php esc_html_e( 'On board', 'taxi-peninsula' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Finished', 'taxi-peninsula' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Minutes', 'taxi-peninsula' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $done as $b ) : ?>
							<tr>
								<td><?php echo esc_html( tp_format_pickup( $b, 'D j M' ) ); ?></td>
								<td><?php echo esc_html( $b['reference'] ); ?><?php echo 'no_show' === $b['status'] ? ' <small>(' . esc_html__( 'no-show', 'taxi-peninsula' ) . ')</small>' : ''; ?></td>
								<td><?php echo esc_html( tp_short_place( $b['pickup'] ) . ' → ' . tp_short_place( $b['dropoff'] ) ); ?></td>
								<td><?php echo esc_html( $time( $b['id'], 'on_way' ) ); ?></td>
								<td><?php echo esc_html( $time( $b['id'], 'onboard' ) ); ?></td>
								<td><?php echo esc_html( $time( $b['id'], 'completed' === $b['status'] ? 'completed' : 'no_show' ) ); ?></td>
								<td><?php echo esc_html( tp_trip_minutes( $b['id'] ) ?: '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p><button type="button" class="btn btn--ghost" onclick="window.print()"><?php esc_html_e( 'Print timesheet', 'taxi-peninsula' ); ?></button></p>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * "12 Smith St, Frankston VIC 3199, Australia" → "Frankston".
 */
function tp_short_place( $address ) {
	$parts = array_map( 'trim', explode( ',', (string) $address ) );
	$parts = array_values( array_filter( $parts, static fn( $p ) => '' !== $p && ! preg_match( '/^(australia|vic|victoria)$/i', $p ) ) );
	if ( count( $parts ) >= 2 ) {
		return trim( preg_replace( '/\s+(VIC|NSW|QLD|SA|WA|TAS|NT|ACT)\b.*$/i', '', $parts[1] ) );
	}
	return $parts ? $parts[0] : '';
}
