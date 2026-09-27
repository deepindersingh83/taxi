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
	$day = isset( $_GET['jobs_day'] ) ? sanitize_text_field( wp_unslash( $_GET['jobs_day'] ) ) : wp_date( 'Y-m-d' );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
		$day = wp_date( 'Y-m-d' );
	}
	$driver_id = get_current_user_id();
	if ( $is_manager ) {
		$driver_id = isset( $_GET['driver'] ) ? absint( $_GET['driver'] ) : 0;
	}
	$done = ! empty( $_GET['done'] );
	// phpcs:enable

	$base    = get_permalink();
	$dt      = DateTimeImmutable::createFromFormat( '!Y-m-d', $day, wp_timezone() );
	$prev    = $dt->modify( '-1 day' )->format( 'Y-m-d' );
	$next    = $dt->modify( '+1 day' )->format( 'Y-m-d' );
	$nav_arg = $is_manager && $driver_id ? array( 'driver' => $driver_id ) : array();
	?>
	<div class="driver-jobs__bar">
		<a class="btn btn--ghost" href="<?php echo esc_url( add_query_arg( array_merge( $nav_arg, array( 'jobs_day' => $prev ) ), $base ) ); ?>" aria-label="<?php esc_attr_e( 'Previous day', 'taxi-peninsula' ); ?>">‹</a>
		<h2 class="driver-jobs__day"><?php echo esc_html( wp_date( 'l j F', $dt->getTimestamp() ) ); ?></h2>
		<a class="btn btn--ghost" href="<?php echo esc_url( add_query_arg( array_merge( $nav_arg, array( 'jobs_day' => $next ) ), $base ) ); ?>" aria-label="<?php esc_attr_e( 'Next day', 'taxi-peninsula' ); ?>">›</a>
	</div>

	<?php if ( $is_manager ) : ?>
		<form class="driver-jobs__filter" method="get" action="<?php echo esc_url( $base ); ?>">
			<input type="hidden" name="jobs_day" value="<?php echo esc_attr( $day ); ?>">
			<label for="dj-driver"><?php esc_html_e( 'Driver', 'taxi-peninsula' ); ?></label>
			<select id="dj-driver" name="driver" onchange="this.form.submit()">
				<option value="0"><?php esc_html_e( 'All drivers', 'taxi-peninsula' ); ?></option>
				<?php foreach ( tp_drivers() as $d ) : ?>
					<option value="<?php echo esc_attr( $d->ID ); ?>" <?php selected( $driver_id, $d->ID ); ?>><?php echo esc_html( $d->display_name ); ?></option>
				<?php endforeach; ?>
			</select>
			<noscript><button class="btn btn--primary" type="submit"><?php esc_html_e( 'Show', 'taxi-peninsula' ); ?></button></noscript>
		</form>
	<?php endif; ?>

	<?php if ( $done ) : ?>
		<p class="notice notice--success" role="status"><?php esc_html_e( 'Job marked as completed.', 'taxi-peninsula' ); ?></p>
	<?php endif; ?>

	<?php
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

	$vehicles = tp_vehicle_types();
	$aids     = tp_mobility_aids();
	foreach ( $jobs as $job ) {
		$b    = tp_get_booking( $job->ID );
		$maps = static function ( $addr ) {
			return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $addr );
		};
		?>
		<article class="job card job--<?php echo esc_attr( $b['status'] ); ?>">
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
				<?php if ( in_array( $b['status'], array( 'confirmed', 'assigned', 'pending' ), true ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="tp_driver_complete">
						<input type="hidden" name="booking" value="<?php echo esc_attr( $b['id'] ); ?>">
						<input type="hidden" name="day" value="<?php echo esc_attr( $day ); ?>">
						<?php wp_nonce_field( 'tp_complete_' . $b['id'] ); ?>
						<button type="submit" class="btn btn--accent"><?php tp_the_icon( 'check' ); ?> <?php esc_html_e( 'Completed', 'taxi-peninsula' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}

	printf( '<p class="driver-jobs__logout"><a href="%s">%s</a></p>', esc_url( wp_logout_url( $base ) ), esc_html__( 'Sign out', 'taxi-peninsula' ) );
	echo '</div>';
	return ob_get_clean();
}

add_action( 'admin_post_tp_driver_complete', 'tp_handle_driver_complete' );
function tp_handle_driver_complete() {
	$post_id = isset( $_POST['booking'] ) ? absint( $_POST['booking'] ) : 0;
	check_admin_referer( 'tp_complete_' . $post_id );

	$assigned = (int) get_post_meta( $post_id, '_tp_driver_id', true );
	$allowed  = current_user_can( 'edit_post', $post_id ) || ( current_user_can( 'tp_driver_jobs' ) && get_current_user_id() === $assigned );
	if ( ! $allowed || 'tp_booking' !== get_post_type( $post_id ) ) {
		wp_die( esc_html__( 'You cannot update this job.', 'taxi-peninsula' ), 403 );
	}
	tp_set_status( $post_id, 'completed', false );

	$day = isset( $_POST['day'] ) ? sanitize_text_field( wp_unslash( $_POST['day'] ) ) : '';
	wp_safe_redirect(
		add_query_arg(
			array(
				'jobs_day' => $day,
				'done'     => 1,
			),
			tp_page_url_by_template( 'driver', home_url( '/' ) )
		)
	);
	exit;
}
