<?php
/**
 * Trip progress for drivers: Accept → On my way → Arrived → Passenger on board
 * → Completed (or No-show), and Decline with a reason.
 *
 * "On my way" and "Arrived" text the customer. A decline unassigns the trip and
 * alerts the office so it can be reassigned from the dispatch board.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * Driver actions: step key => [new status, button label, button style].
 */
function tp_trip_steps() {
	return array(
		'accept'   => array( 'accepted', __( 'Accept trip', 'taxi-peninsula' ), 'accent' ),
		'on_way'   => array( 'on_way', __( 'On my way', 'taxi-peninsula' ), 'accent' ),
		'arrived'  => array( 'arrived', __( 'Arrived', 'taxi-peninsula' ), 'accent' ),
		'onboard'  => array( 'onboard', __( 'Passenger on board', 'taxi-peninsula' ), 'accent' ),
		'complete' => array( 'completed', __( 'Completed', 'taxi-peninsula' ), 'accent' ),
		'no_show'  => array( 'no_show', __( 'No-show', 'taxi-peninsula' ), 'ghost' ),
	);
}

/**
 * What the driver can do next from a status.
 *
 * @return string[] Step keys (plus "decline" where allowed).
 */
function tp_trip_next_steps( $status ) {
	$map = array(
		'pending'   => array( 'accept', 'decline' ),
		'confirmed' => array( 'accept', 'decline' ),
		'assigned'  => array( 'accept', 'decline' ),
		'accepted'  => array( 'on_way', 'decline' ),
		'on_way'    => array( 'arrived' ),
		'arrived'   => array( 'onboard', 'no_show' ),
		'onboard'   => array( 'complete' ),
	);
	return $map[ $status ] ?? array();
}

function tp_decline_reasons() {
	return array(
		'unavailable' => __( 'Not available at that time', 'taxi-peninsula' ),
		'vehicle'     => __( 'My vehicle is not suitable', 'taxi-peninsula' ),
		'distance'    => __( 'Too far from my area', 'taxi-peninsula' ),
		'other'       => __( 'Other reason', 'taxi-peninsula' ),
	);
}

/**
 * Whether the current user may act on a trip as its driver (or as office staff).
 */
function tp_can_drive_trip( $post_id ) {
	if ( 'tp_booking' !== get_post_type( $post_id ) ) {
		return false;
	}
	if ( current_user_can( 'edit_post', $post_id ) ) {
		return true;
	}
	return current_user_can( 'tp_driver_jobs' ) && get_current_user_id() === (int) get_post_meta( $post_id, '_tp_driver_id', true );
}

/**
 * Time a trip reached a status (stored for timesheets), as a timestamp or 0.
 */
function tp_trip_time( $post_id, $status ) {
	$at = get_post_meta( $post_id, '_tp_at_' . $status, true );
	return $at ? (int) strtotime( get_gmt_from_date( $at ) . ' UTC' ) : 0;
}

add_action( 'tp_booking_status_changed', static function ( $post_id, $old, $status ) {
	if ( in_array( $status, array( 'accepted', 'on_way', 'arrived', 'onboard', 'completed', 'no_show' ), true ) ) {
		update_post_meta( $post_id, '_tp_at_' . $status, current_time( 'mysql' ) );
	}
}, 5, 3 );

/* ---- Driver actions ---- */

add_action( 'admin_post_tp_driver_step', 'tp_handle_driver_step' );
function tp_handle_driver_step() {
	$post_id = isset( $_POST['booking'] ) ? absint( $_POST['booking'] ) : 0;
	check_admin_referer( 'tp_step_' . $post_id );
	if ( ! tp_can_drive_trip( $post_id ) ) {
		wp_die( esc_html__( 'You cannot update this job.', 'taxi-peninsula' ), 403 );
	}

	$step   = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
	$status = get_post_meta( $post_id, '_tp_status', true );
	$back   = array(
		'jobs_day' => isset( $_POST['day'] ) ? sanitize_text_field( wp_unslash( $_POST['day'] ) ) : '',
	);

	if ( ! in_array( $step, tp_trip_next_steps( $status ), true ) ) {
		$back['stale'] = 1;
	} elseif ( 'decline' === $step ) {
		$reason = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : 'other';
		$note   = isset( $_POST['reason_note'] ) ? sanitize_text_field( wp_unslash( $_POST['reason_note'] ) ) : '';
		tp_decline_trip( $post_id, get_current_user_id(), $reason, $note );
		$back['declined'] = 1;
	} else {
		$steps = tp_trip_steps();
		// Customers hear about "on my way", "arrived", completion (thanks + receipt) and no-shows.
		$notify = in_array( $step, array( 'on_way', 'arrived', 'complete', 'no_show' ), true );
		tp_set_status( $post_id, $steps[ $step ][0], $notify );
		$back['updated'] = $step;
	}

	wp_safe_redirect( add_query_arg( array_filter( $back ), tp_page_url_by_template( 'driver', home_url( '/' ) ) ) . '#job-' . $post_id );
	exit;
}

/**
 * Driver declines a trip: unassign it, record why, and alert the office.
 */
function tp_decline_trip( $post_id, $driver_id, $reason, $note = '' ) {
	return tp_in_site_lang( static function () use ( $post_id, $driver_id, $reason, $note ) {
		return tp_decline_trip_run( $post_id, $driver_id, $reason, $note );
	} );
}

function tp_decline_trip_run( $post_id, $driver_id, $reason, $note = '' ) {
	$reasons = tp_decline_reasons();
	$reason  = isset( $reasons[ $reason ] ) ? $reason : 'other';
	$driver  = get_userdata( $driver_id );
	$name    = $driver ? $driver->display_name : '#' . $driver_id;
	$text    = $reasons[ $reason ] . ( $note ? ' — ' . $note : '' );

	$declines   = array_filter( (array) get_post_meta( $post_id, '_tp_declines', true ) );
	$declines[] = array(
		'driver' => (int) $driver_id,
		'reason' => $text,
		'time'   => current_time( 'mysql' ),
	);
	update_post_meta( $post_id, '_tp_declines', $declines );
	update_post_meta( $post_id, '_tp_needs_driver', current_time( 'mysql' ) );

	tp_assign( $post_id, 0, (int) get_post_meta( $post_id, '_tp_fleet_id', true ), false );
	/* translators: 1: driver, 2: reason */
	tp_add_log( $post_id, array( 'note' => sprintf( __( 'Declined by %1$s: %2$s', 'taxi-peninsula' ), $name, $text ) ) );

	$b       = tp_get_booking( $post_id );
	$board   = admin_url( 'admin.php?page=tp-dispatch&day=' . rawurlencode( $b['date'] ) );
	/* translators: 1: driver, 2: reference, 3: pick-up time, 4: reason */
	$message = sprintf( __( '%1$s declined %2$s (%3$s): %4$s. Please reassign.', 'taxi-peninsula' ), $name, $b['reference'], tp_format_pickup( $b, 'D j M g:ia' ), $text );
	wp_mail(
		tp_opt( 'notify_email' ) ?: get_option( 'admin_email' ),
		/* translators: %s: reference */
		sprintf( __( 'Trip declined — %s needs a driver', 'taxi-peninsula' ), $b['reference'] ),
		$message . "\n\n" . tp_booking_summary( $b ) . "\n" . __( 'Dispatch board:', 'taxi-peninsula' ) . ' ' . $board,
		tp_mail_headers()
	);
	if ( tp_setting( 'sms_office_number' ) ) {
		tp_booking_sms( $post_id, tp_setting( 'sms_office_number' ), $message, 'office' );
	}
}

// Assigning a new driver clears the "needs a driver" flag.
add_action( 'tp_driver_assigned', static function ( $post_id ) {
	delete_post_meta( $post_id, '_tp_needs_driver' );
}, 5 );

/* ---- Customer texts for "on my way" and "arrived" ---- */

add_action( 'tp_booking_status_changed', 'tp_sms_trip_progress', 25, 4 );
function tp_sms_trip_progress( $post_id, $old, $status, $notify ) {
	return tp_in_booking_lang( $post_id, static function () use ( $post_id, $old, $status, $notify ) {
		return tp_sms_trip_progress_run( $post_id, $old, $status, $notify );
	} );
}

function tp_sms_trip_progress_run( $post_id, $old, $status, $notify ) {
	if ( ! $notify || ! tp_setting( 'sms_customer_trip' ) || ! in_array( $status, array( 'on_way', 'arrived' ), true ) ) {
		return;
	}
	$b      = tp_get_booking( $post_id );
	$driver = $b['driver_id'] ? get_userdata( $b['driver_id'] ) : null;
	$name   = $driver ? ( $driver->first_name ?: strtok( $driver->display_name, ' ' ) ) : __( 'Your driver', 'taxi-peninsula' );
	$site   = tp_sms_site_name();
	if ( 'on_way' === $status ) {
		/* translators: 1: site, 2: driver first name, 3: reference */
		$msg = sprintf( __( '%1$s: %2$s is on the way to pick you up (booking %3$s).', 'taxi-peninsula' ), $site, $name, $b['reference'] );
		if ( $b['fleet_id'] ) {
			/* translators: %s: vehicle */
			$msg .= ' ' . sprintf( __( 'Vehicle: %s.', 'taxi-peninsula' ), tp_fleet_label( $b['fleet_id'] ) );
		}
	} else {
		/* translators: 1: site, 2: driver first name, 3: pick-up address */
		$msg = sprintf( __( '%1$s: %2$s has arrived at %3$s.', 'taxi-peninsula' ), $site, $name, $b['pickup'] );
	}
	/* translators: %s: phone */
	$msg .= ' ' . sprintf( __( 'Questions? Call %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) );
	tp_booking_sms( $post_id, $b['phone'], $msg, 'customer' );
}

/* ---- My week (driver timesheet) ---- */

/**
 * A driver's trips between two dates, ordered by pick-up time.
 *
 * @return array[] Bookings (tp_get_booking arrays).
 */
function tp_driver_trips( $driver_id, $from, $to ) {
	$ids = get_posts(
		array(
			'post_type'   => 'tp_booking',
			'numberposts' => 500,
			'fields'      => 'ids',
			'meta_key'    => '_tp_pickup_at',
			'orderby'     => 'meta_value',
			'order'       => 'ASC',
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array( 'key' => '_tp_driver_id', 'value' => (int) $driver_id ),
				array( 'key' => '_tp_date', 'value' => array( $from, $to ), 'compare' => 'BETWEEN' ),
			),
		)
	);
	return array_map( 'tp_get_booking', $ids );
}

/**
 * Minutes from "Passenger on board" to "Completed" (0 when unknown).
 */
function tp_trip_minutes( $post_id ) {
	$on   = tp_trip_time( $post_id, 'onboard' ) ?: tp_trip_time( $post_id, 'on_way' );
	$done = tp_trip_time( $post_id, 'completed' );
	return ( $on && $done > $on ) ? (int) round( ( $done - $on ) / 60 ) : 0;
}

/**
 * Average customer rating for a driver: [average, count].
 */
function tp_driver_rating( $driver_id ) {
	global $wpdb;
	$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT AVG(r.meta_value) AS avg_rating, COUNT(*) AS n
			 FROM {$wpdb->postmeta} r
			 INNER JOIN {$wpdb->postmeta} d ON (d.post_id = r.post_id AND d.meta_key = '_tp_driver_id' AND d.meta_value = %d)
			 WHERE r.meta_key = '_tp_rating' AND r.meta_value > 0",
			(int) $driver_id
		)
	);
	return array( $row && $row->n ? round( (float) $row->avg_rating, 1 ) : 0, $row ? (int) $row->n : 0 );
}

/**
 * Tell a customer something about their booking by email and (if set up) SMS.
 */
function tp_notify_customer( $post_id, $subject, $message ) {
	return tp_in_booking_lang( $post_id, static function () use ( $post_id, $subject, $message ) {
		return tp_notify_customer_run( $post_id, $subject, $message );
	} );
}

function tp_notify_customer_run( $post_id, $subject, $message ) {
	$b    = tp_get_booking( $post_id );
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	if ( tp_opt( 'customer_emails' ) && is_email( $b['email'] ) ) {
		/* translators: %s: passenger name */
		$body = sprintf( __( 'Hi %s,', 'taxi-peninsula' ), $b['name'] ) . "\n\n" . $message . "\n\n"
			. tp_booking_summary( $b ) . "\n"
			. __( 'View or manage your booking:', 'taxi-peninsula' ) . ' ' . tp_booking_manage_url( $post_id ) . "\n\n" . $site . "\n";
		wp_mail( $b['email'], $subject . ' — ' . $b['reference'], $body, tp_mail_headers( tp_opt( 'email' ) ) );
	}
	if ( tp_setting( 'sms_customer_status' ) ) {
		tp_booking_sms( $post_id, $b['phone'], $message, 'customer' );
	}
}
