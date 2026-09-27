<?php
/**
 * Booking post type, roles, capabilities and data helpers.
 *
 * Bookings are stored as a private custom post type (tp_booking) with post meta.
 * They're never shown on the front end except through the booking lookup,
 * which requires the reference and the phone number.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

const TP_CAPS_VERSION = 2;

add_action( 'init', 'tp_register_booking_cpt' );
function tp_register_booking_cpt() {
	register_post_type(
		'tp_booking',
		array(
			'labels'              => array(
				'name'               => __( 'Bookings', 'taxi-peninsula' ),
				'singular_name'      => __( 'Booking', 'taxi-peninsula' ),
				'menu_name'          => __( 'Bookings', 'taxi-peninsula' ),
				'add_new'            => __( 'Add booking', 'taxi-peninsula' ),
				'add_new_item'       => __( 'Add booking', 'taxi-peninsula' ),
				'edit_item'          => __( 'Booking details', 'taxi-peninsula' ),
				'search_items'       => __( 'Search bookings', 'taxi-peninsula' ),
				'not_found'          => __( 'No bookings found.', 'taxi-peninsula' ),
				'not_found_in_trash' => __( 'No bookings in the bin.', 'taxi-peninsula' ),
				'all_items'          => __( 'All bookings', 'taxi-peninsula' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'menu_position'       => 3,
			'menu_icon'           => 'dashicons-car',
			'supports'            => false,
			'capability_type'     => array( 'tp_booking', 'tp_bookings' ),
			'map_meta_cap'        => true,
			'rewrite'             => false,
			'query_var'           => false,
		)
	);
}

/**
 * Primitive capabilities for the booking post type (also used for enquiries).
 */
function tp_booking_caps() {
	return array(
		'edit_tp_bookings',
		'edit_others_tp_bookings',
		'edit_published_tp_bookings',
		'edit_private_tp_bookings',
		'publish_tp_bookings',
		'read_private_tp_bookings',
		'delete_tp_bookings',
		'delete_others_tp_bookings',
		'delete_published_tp_bookings',
		'delete_private_tp_bookings',
	);
}

/**
 * Grant booking caps to administrators and editors, and create:
 * - "Booking Manager": manages bookings and enquiries only.
 * - "Driver": can only see their own jobs on the front-end Driver Jobs page.
 */
function tp_install_caps() {
	foreach ( array( 'administrator', 'editor' ) as $role_name ) {
		$role = get_role( $role_name );
		if ( $role ) {
			foreach ( tp_booking_caps() as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	$caps = array( 'read' => true );
	foreach ( tp_booking_caps() as $cap ) {
		$caps[ $cap ] = true;
	}
	remove_role( 'tp_booking_manager' );
	add_role( 'tp_booking_manager', __( 'Booking Manager', 'taxi-peninsula' ), $caps );

	remove_role( 'tp_driver' );
	add_role(
		'tp_driver',
		__( 'Driver', 'taxi-peninsula' ),
		array(
			'read'           => true,
			'tp_driver_jobs' => true,
		)
	);

	update_option( 'tp_caps_version', TP_CAPS_VERSION );
}

add_action( 'after_switch_theme', 'tp_install_caps' );
add_action(
	'init',
	static function () {
		if ( (int) get_option( 'tp_caps_version' ) !== TP_CAPS_VERSION ) {
			tp_install_caps();
		}
	},
	20
);

/**
 * Customer-facing booking fields: key => [label, type].
 */
function tp_booking_fields() {
	return array(
		'name'          => array( __( 'Passenger name', 'taxi-peninsula' ), 'text' ),
		'phone'         => array( __( 'Mobile / phone', 'taxi-peninsula' ), 'tel' ),
		'email'         => array( __( 'Email', 'taxi-peninsula' ), 'email' ),
		'pickup'        => array( __( 'Pick-up address', 'taxi-peninsula' ), 'text' ),
		'dropoff'       => array( __( 'Drop-off address', 'taxi-peninsula' ), 'text' ),
		'date'          => array( __( 'Pick-up date', 'taxi-peninsula' ), 'date' ),
		'time'          => array( __( 'Pick-up time', 'taxi-peninsula' ), 'time' ),
		'passengers'    => array( __( 'Passengers', 'taxi-peninsula' ), 'number' ),
		'wheelchairs'   => array( __( 'Wheelchairs', 'taxi-peninsula' ), 'number' ),
		'vehicle'       => array( __( 'Vehicle', 'taxi-peninsula' ), 'vehicle' ),
		'mobility_aid'  => array( __( 'Mobility aid', 'taxi-peninsula' ), 'mobility' ),
		'return_trip'   => array( __( 'Return trip', 'taxi-peninsula' ), 'checkbox' ),
		'return_time'   => array( __( 'Return pick-up time', 'taxi-peninsula' ), 'time' ),
		'mptp'          => array( __( 'MPTP member', 'taxi-peninsula' ), 'checkbox' ),
		'payment'       => array( __( 'Payment', 'taxi-peninsula' ), 'payment' ),
		'invoice_name'  => array( __( 'Invoice to (organisation / plan manager)', 'taxi-peninsula' ), 'text' ),
		'invoice_email' => array( __( 'Invoice email', 'taxi-peninsula' ), 'email' ),
		'ndis_number'   => array( __( 'NDIS number / reference', 'taxi-peninsula' ), 'text' ),
		'notes'         => array( __( 'Passenger notes', 'taxi-peninsula' ), 'textarea' ),
	);
}

function tp_payment_methods() {
	$methods = array(
		'driver' => __( 'Pay the driver (card, cash or MPTP)', 'taxi-peninsula' ),
	);
	if ( tp_setting( 'invoice_enabled' ) ) {
		$methods['account'] = __( 'Account / invoice (NDIS, aged care, business)', 'taxi-peninsula' );
	}
	if ( tp_payments_available() ) {
		$methods['online'] = sprintf(
			/* translators: %s: deposit amount */
			__( 'Pay a %s deposit online now', 'taxi-peninsula' ),
			tp_money( (float) tp_setting( 'deposit_amount' ) )
		);
	}
	return $methods;
}

function tp_money( $amount ) {
	return '$' . number_format_i18n( (float) $amount, 2 );
}

/**
 * Load a booking as an associative array.
 *
 * @param int $post_id Booking ID.
 * @return array
 */
function tp_get_booking( $post_id ) {
	$data = array(
		'id'          => (int) $post_id,
		'reference'   => get_post_meta( $post_id, '_tp_reference', true ),
		'status'      => get_post_meta( $post_id, '_tp_status', true ) ?: 'pending',
		'admin_notes' => get_post_meta( $post_id, '_tp_admin_notes', true ),
		'driver_id'   => (int) get_post_meta( $post_id, '_tp_driver_id', true ),
		'fleet_id'    => (int) get_post_meta( $post_id, '_tp_fleet_id', true ),
		'series'      => get_post_meta( $post_id, '_tp_series', true ),
		'paid'        => (float) get_post_meta( $post_id, '_tp_paid', true ),
		'request'     => get_post_meta( $post_id, '_tp_request', true ),
		'estimate'    => get_post_meta( $post_id, '_tp_estimate', true ),
		'distance_km' => (float) get_post_meta( $post_id, '_tp_distance_km', true ),
		'duration'    => (int) get_post_meta( $post_id, '_tp_duration_min', true ),
	);
	foreach ( array_keys( tp_booking_fields() ) as $key ) {
		$data[ $key ] = get_post_meta( $post_id, '_tp_' . $key, true );
	}
	if ( '' === $data['payment'] ) {
		$data['payment'] = 'driver';
	}
	return $data;
}

/**
 * Find a booking post ID by reference.
 */
function tp_find_booking( $reference ) {
	$reference = strtoupper( trim( (string) $reference ) );
	if ( ! preg_match( '/^TP-[A-Z0-9]{6}$/', $reference ) ) {
		return 0;
	}
	$ids = get_posts(
		array(
			'post_type'   => 'tp_booking',
			'post_status' => 'publish',
			'meta_key'    => '_tp_reference',
			'meta_value'  => $reference,
			'fields'      => 'ids',
			'numberposts' => 1,
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Sanitize raw booking input. Returns [clean data, errors].
 *
 * @param array $raw    Unslashed input.
 * @param bool  $strict Apply customer-facing rules (required fields, notice period).
 * @return array
 */
function tp_sanitize_booking( array $raw, $strict = true ) {
	$d = array(
		'name'          => sanitize_text_field( $raw['name'] ?? '' ),
		'phone'         => sanitize_text_field( $raw['phone'] ?? '' ),
		'email'         => sanitize_email( $raw['email'] ?? '' ),
		'pickup'        => sanitize_text_field( $raw['pickup'] ?? '' ),
		'dropoff'       => sanitize_text_field( $raw['dropoff'] ?? '' ),
		'date'          => sanitize_text_field( $raw['date'] ?? '' ),
		'time'          => sanitize_text_field( $raw['time'] ?? '' ),
		'passengers'    => max( 1, min( 11, absint( $raw['passengers'] ?? 1 ) ) ),
		'wheelchairs'   => min( 4, absint( $raw['wheelchairs'] ?? 0 ) ),
		'vehicle'       => sanitize_key( $raw['vehicle'] ?? 'wat' ),
		'mobility_aid'  => sanitize_key( $raw['mobility_aid'] ?? 'manual' ),
		'return_trip'   => empty( $raw['return_trip'] ) ? 0 : 1,
		'return_time'   => sanitize_text_field( $raw['return_time'] ?? '' ),
		'mptp'          => empty( $raw['mptp'] ) ? 0 : 1,
		'payment'       => sanitize_key( $raw['payment'] ?? 'driver' ),
		'invoice_name'  => sanitize_text_field( $raw['invoice_name'] ?? '' ),
		'invoice_email' => sanitize_email( $raw['invoice_email'] ?? '' ),
		'ndis_number'   => sanitize_text_field( $raw['ndis_number'] ?? '' ),
		'notes'         => sanitize_textarea_field( $raw['notes'] ?? '' ),
	);

	$errors = array();

	foreach ( array( 'name', 'phone', 'pickup', 'dropoff', 'date', 'time' ) as $req ) {
		if ( '' === $d[ $req ] ) {
			$errors[ $req ] = __( 'This field is required.', 'taxi-peninsula' );
		}
	}

	if ( $strict && '' === $d['email'] ) {
		$errors['email'] = __( 'Please enter a valid email address.', 'taxi-peninsula' );
	}

	if ( '' !== $d['phone'] && ! preg_match( '/^[0-9 +()\-]{8,20}$/', $d['phone'] ) ) {
		$errors['phone'] = __( 'Please enter a valid phone number.', 'taxi-peninsula' );
	}

	if ( ! isset( tp_vehicle_types()[ $d['vehicle'] ] ) ) {
		$d['vehicle'] = 'wat';
	}
	if ( ! isset( tp_mobility_aids()[ $d['mobility_aid'] ] ) ) {
		$d['mobility_aid'] = 'none';
	}

	// Admins can record any method; customers only those currently offered.
	$methods = $strict ? tp_payment_methods() : array( 'driver' => 1, 'account' => 1, 'online' => 1 );
	if ( ! isset( $methods[ $d['payment'] ] ) ) {
		$d['payment'] = 'driver';
	}
	if ( 'account' === $d['payment'] ) {
		if ( $strict && '' === $d['invoice_name'] ) {
			$errors['invoice_name'] = __( 'Tell us who to invoice.', 'taxi-peninsula' );
		}
		if ( $strict && '' === $d['invoice_email'] ) {
			$errors['invoice_email'] = __( 'Please enter the email address for invoices.', 'taxi-peninsula' );
		}
	} else {
		$d['invoice_name']  = '';
		$d['invoice_email'] = '';
		$d['ndis_number']   = '';
	}

	$date_ok = (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d['date'] );
	$time_ok = (bool) preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $d['time'] );
	if ( '' !== $d['date'] && ! $date_ok ) {
		$errors['date'] = __( 'Please choose a valid date.', 'taxi-peninsula' );
	}
	if ( '' !== $d['time'] && ! $time_ok ) {
		$errors['time'] = __( 'Please choose a valid time.', 'taxi-peninsula' );
	}

	if ( $strict && $date_ok && $time_ok ) {
		$pickup = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $d['date'] . ' ' . $d['time'], wp_timezone() );
		$min    = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+' . absint( tp_opt( 'min_notice' ) ) . ' minutes' );
		if ( ! $pickup || $pickup < $min ) {
			$errors['time'] = sprintf(
				/* translators: %s: phone number */
				__( 'Online bookings need a little notice. For an urgent pick-up please call %s.', 'taxi-peninsula' ),
				tp_opt( 'phone_display' )
			);
		}
	}

	if ( $d['return_trip'] ) {
		if ( '' !== $d['return_time'] && ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $d['return_time'] ) ) {
			$errors['return_time'] = __( 'Please choose a valid time.', 'taxi-peninsula' );
		}
	} else {
		$d['return_time'] = '';
	}

	if ( 'sedan' === $d['vehicle'] ) {
		$d['wheelchairs'] = 0;
	}

	return array( $d, $errors );
}

/**
 * Validate the "repeat this trip" part of the form. Returns [dates[], error|null].
 * The first date is always the booking's own date.
 *
 * @param array  $raw  Unslashed input.
 * @param string $date First pick-up date (Y-m-d).
 * @return array
 */
function tp_recurring_dates( array $raw, $date ) {
	if ( empty( $raw['repeat'] ) || ! tp_setting( 'recurring_enabled' ) ) {
		return array( array( $date ), null );
	}

	$days  = array_values( array_intersect( array_map( 'absint', (array) ( $raw['repeat_days'] ?? array() ) ), range( 1, 7 ) ) );
	$until = sanitize_text_field( $raw['repeat_until'] ?? '' );

	if ( ! $days ) {
		return array( array( $date ), __( 'Choose which days the trip repeats.', 'taxi-peninsula' ) );
	}

	$tz    = wp_timezone();
	$start = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $tz );
	$end   = DateTimeImmutable::createFromFormat( '!Y-m-d', $until, $tz );
	if ( ! $start || ! $end || $end <= $start ) {
		return array( array( $date ), __( 'Choose an end date after the first trip.', 'taxi-peninsula' ) );
	}

	$max_weeks = max( 1, (int) tp_setting( 'recurring_max_weeks' ) );
	$limit     = $start->modify( '+' . $max_weeks . ' weeks' );
	if ( $end > $limit ) {
		/* translators: %d: number of weeks */
		return array( array( $date ), sprintf( __( 'Repeat bookings can run for up to %d weeks. Call us to arrange a longer schedule.', 'taxi-peninsula' ), $max_weeks ) );
	}

	$dates = array( $date );
	for ( $d = $start->modify( '+1 day' ); $d <= $end; $d = $d->modify( '+1 day' ) ) {
		if ( in_array( (int) $d->format( 'N' ), $days, true ) ) {
			$dates[] = $d->format( 'Y-m-d' );
		}
	}
	return array( $dates, null );
}

/**
 * Save sanitized booking data to post meta.
 *
 * @param int   $post_id Booking ID.
 * @param array $d       Sanitized data.
 */
function tp_save_booking_meta( $post_id, array $d ) {
	foreach ( array_keys( tp_booking_fields() ) as $key ) {
		if ( array_key_exists( $key, $d ) ) {
			update_post_meta( $post_id, '_tp_' . $key, $d[ $key ] );
		}
	}
	// Sortable pick-up datetime, e.g. "2026-10-01 14:30".
	update_post_meta( $post_id, '_tp_pickup_at', trim( ( $d['date'] ?? '' ) . ' ' . ( $d['time'] ?? '' ) ) );
}

/**
 * Title and searchable excerpt for a booking.
 */
function tp_booking_post_fields( $ref, array $d ) {
	return array(
		'post_title'   => $ref . ' — ' . $d['name'],
		'post_excerpt' => implode( ' | ', array( $d['phone'], $d['email'], $d['pickup'], $d['dropoff'] ) ),
	);
}

/**
 * Generate a unique, human-friendly booking reference.
 */
function tp_new_reference() {
	$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	do {
		$ref = 'TP-';
		for ( $i = 0; $i < 6; $i++ ) {
			$ref .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
		}
	} while ( tp_find_booking( $ref ) );
	return $ref;
}

/**
 * Append an entry to a booking's history.
 *
 * @param int   $post_id Booking ID.
 * @param array $entry   Keys: to (status) or note (free text), notify (bool).
 */
function tp_add_log( $post_id, array $entry ) {
	$log   = array_filter( (array) get_post_meta( $post_id, '_tp_log', true ) );
	$user  = wp_get_current_user();
	$log[] = array_merge(
		array(
			'time'   => current_time( 'mysql' ),
			'user'   => $user->exists() ? $user->display_name : '',
			'from'   => '',
			'to'     => '',
			'note'   => '',
			'notify' => false,
		),
		$entry
	);
	update_post_meta( $post_id, '_tp_log', $log );
}

/**
 * Change a booking's status, log it and fire `tp_booking_status_changed`.
 *
 * @param int    $post_id Booking ID.
 * @param string $status  New status.
 * @param bool   $notify  Whether to notify the customer.
 */
function tp_set_status( $post_id, $status, $notify ) {
	$old = get_post_meta( $post_id, '_tp_status', true );
	if ( $old === $status || ! isset( tp_statuses()[ $status ] ) ) {
		return;
	}
	update_post_meta( $post_id, '_tp_status', $status );
	tp_add_log(
		$post_id,
		array(
			'from'   => $old,
			'to'     => $status,
			'notify' => (bool) $notify,
		)
	);

	do_action( 'tp_booking_status_changed', $post_id, $old, $status, (bool) $notify );
}

/**
 * Assign (or clear) a driver and vehicle. Fires `tp_driver_assigned` when the driver changes.
 */
function tp_assign( $post_id, $driver_id, $fleet_id, $notify ) {
	$old_driver = (int) get_post_meta( $post_id, '_tp_driver_id', true );
	update_post_meta( $post_id, '_tp_driver_id', (int) $driver_id );
	update_post_meta( $post_id, '_tp_fleet_id', (int) $fleet_id );

	if ( $driver_id && $driver_id !== $old_driver ) {
		$driver = get_userdata( $driver_id );
		tp_add_log(
			$post_id,
			/* translators: %s: driver name */
			array( 'note' => sprintf( __( 'Driver assigned: %s', 'taxi-peninsula' ), $driver ? $driver->display_name : '#' . $driver_id ) )
		);
		do_action( 'tp_driver_assigned', $post_id, $driver_id, $notify );

		$status = get_post_meta( $post_id, '_tp_status', true );
		if ( in_array( $status, array( 'pending', 'confirmed' ), true ) ) {
			tp_set_status( $post_id, 'assigned', $notify );
		}
	}
}

/**
 * Human-readable pick-up date/time.
 */
function tp_format_pickup( array $b, $format = 'D j M Y, g:i a' ) {
	if ( empty( $b['date'] ) ) {
		return '';
	}
	$dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $b['date'] . ' ' . ( $b['time'] ?: '00:00' ), wp_timezone() );
	return $dt ? wp_date( $format, $dt->getTimestamp() ) : $b['date'] . ' ' . $b['time'];
}

/**
 * Status badge markup (admin + booking lookup).
 */
function tp_status_badge( $status ) {
	$labels = tp_statuses();
	return sprintf(
		'<span class="tp-badge tp-badge--%1$s">%2$s</span>',
		esc_attr( $status ),
		esc_html( $labels[ $status ] ?? $status )
	);
}

/**
 * Plain-text summary used in emails.
 */
function tp_booking_summary( array $b ) {
	$vehicles = tp_vehicle_types();
	$aids     = tp_mobility_aids();
	$payments = array_merge( array( 'driver' => '', 'account' => '', 'online' => '' ), tp_payment_methods() );
	$lines    = array(
		__( 'Reference', 'taxi-peninsula' )    => $b['reference'],
		__( 'Status', 'taxi-peninsula' )       => tp_statuses()[ $b['status'] ] ?? $b['status'],
		__( 'Name', 'taxi-peninsula' )         => $b['name'],
		__( 'Phone', 'taxi-peninsula' )        => $b['phone'],
		__( 'Email', 'taxi-peninsula' )        => $b['email'],
		__( 'Pick-up', 'taxi-peninsula' )      => tp_format_pickup( $b ),
		__( 'From', 'taxi-peninsula' )         => $b['pickup'],
		__( 'To', 'taxi-peninsula' )           => $b['dropoff'],
		__( 'Vehicle', 'taxi-peninsula' )      => $vehicles[ $b['vehicle'] ] ?? $b['vehicle'],
		__( 'Passengers', 'taxi-peninsula' )   => $b['passengers'],
		__( 'Wheelchairs', 'taxi-peninsula' )  => $b['wheelchairs'],
		__( 'Mobility aid', 'taxi-peninsula' ) => $aids[ $b['mobility_aid'] ] ?? $b['mobility_aid'],
		__( 'Return trip', 'taxi-peninsula' )  => $b['return_trip'] ? ( $b['return_time'] ? $b['return_time'] : __( 'Yes – time to be arranged', 'taxi-peninsula' ) ) : __( 'No', 'taxi-peninsula' ),
		__( 'Payment', 'taxi-peninsula' )      => $payments[ $b['payment'] ] ?: $b['payment'],
	);
	if ( 'account' === $b['payment'] ) {
		$lines[ __( 'Invoice to', 'taxi-peninsula' ) ] = trim( $b['invoice_name'] . ' <' . $b['invoice_email'] . '>' );
		if ( $b['ndis_number'] ) {
			$lines[ __( 'NDIS number / reference', 'taxi-peninsula' ) ] = $b['ndis_number'];
		}
	}
	if ( $b['estimate'] ) {
		$lines[ __( 'Estimated fare', 'taxi-peninsula' ) ] = $b['estimate'] . ' ' . __( '(estimate only)', 'taxi-peninsula' );
	}
	if ( $b['paid'] > 0 ) {
		$lines[ __( 'Paid online', 'taxi-peninsula' ) ] = tp_money( $b['paid'] );
	}
	if ( tp_opt( 'show_mptp' ) ) {
		$lines[ __( 'MPTP member', 'taxi-peninsula' ) ] = $b['mptp'] ? __( 'Yes', 'taxi-peninsula' ) : __( 'No', 'taxi-peninsula' );
	}
	if ( '' !== (string) $b['notes'] ) {
		$lines[ __( 'Notes', 'taxi-peninsula' ) ] = $b['notes'];
	}

	$out = '';
	foreach ( $lines as $label => $value ) {
		$out .= $label . ': ' . $value . "\n";
	}
	return $out;
}

/**
 * Secret token that lets a customer open their booking from an email/SMS link
 * without re-typing their phone number.
 */
function tp_booking_token( $post_id ) {
	$ref = get_post_meta( $post_id, '_tp_reference', true );
	return substr( hash_hmac( 'sha256', $post_id . '|' . $ref, wp_salt( 'auth' ) ), 0, 20 );
}

/**
 * Customer link to view/manage a booking.
 */
function tp_booking_manage_url( $post_id ) {
	return add_query_arg(
		array(
			'ref' => get_post_meta( $post_id, '_tp_reference', true ),
			't'   => tp_booking_token( $post_id ),
		),
		tp_page_url_by_template( 'lookup', home_url( '/' ) )
	);
}

/**
 * Normalise Australian phone numbers to digits only for comparison.
 */
function tp_phone_digits( $phone ) {
	$digits = preg_replace( '/\D+/', '', (string) $phone );
	if ( 0 === strpos( $digits, '61' ) && strlen( $digits ) === 11 ) {
		$digits = '0' . substr( $digits, 2 );
	}
	return $digits;
}

/**
 * Every payment method, for staff (regardless of what customers are offered).
 */
function tp_all_payment_methods() {
	return array(
		'driver'  => __( 'Pay the driver', 'taxi-peninsula' ),
		'account' => __( 'Account / invoice', 'taxi-peninsula' ),
		'online'  => __( 'Online deposit (Stripe)', 'taxi-peninsula' ),
	);
}
