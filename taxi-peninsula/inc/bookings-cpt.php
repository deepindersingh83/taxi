<?php
/**
 * Booking post type, capabilities and data helpers.
 *
 * Bookings are stored as a private custom post type (tp_booking) with post meta.
 * They're never shown on the front end.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

const TP_CAPS_VERSION = 1;

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
 * Primitive capabilities for the booking post type.
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
 * Grant booking caps to administrators and editors and create a "Booking Manager" role
 * that can manage bookings without touching the rest of the site.
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

	update_option( 'tp_caps_version', TP_CAPS_VERSION );
}

add_action( 'after_switch_theme', 'tp_install_caps' );
add_action(
	'admin_init',
	static function () {
		if ( (int) get_option( 'tp_caps_version' ) !== TP_CAPS_VERSION ) {
			tp_install_caps();
		}
	}
);

/**
 * Booking meta fields: key => [label, type].
 */
function tp_booking_fields() {
	return array(
		'name'         => array( __( 'Passenger name', 'taxi-peninsula' ), 'text' ),
		'phone'        => array( __( 'Mobile / phone', 'taxi-peninsula' ), 'tel' ),
		'email'        => array( __( 'Email', 'taxi-peninsula' ), 'email' ),
		'pickup'       => array( __( 'Pick-up address', 'taxi-peninsula' ), 'text' ),
		'dropoff'      => array( __( 'Drop-off address', 'taxi-peninsula' ), 'text' ),
		'date'         => array( __( 'Pick-up date', 'taxi-peninsula' ), 'date' ),
		'time'         => array( __( 'Pick-up time', 'taxi-peninsula' ), 'time' ),
		'passengers'   => array( __( 'Passengers', 'taxi-peninsula' ), 'number' ),
		'wheelchairs'  => array( __( 'Wheelchairs', 'taxi-peninsula' ), 'number' ),
		'vehicle'      => array( __( 'Vehicle', 'taxi-peninsula' ), 'vehicle' ),
		'mobility_aid' => array( __( 'Mobility aid', 'taxi-peninsula' ), 'mobility' ),
		'return_trip'  => array( __( 'Return trip', 'taxi-peninsula' ), 'checkbox' ),
		'return_time'  => array( __( 'Return pick-up time', 'taxi-peninsula' ), 'time' ),
		'mptp'         => array( __( 'MPTP member', 'taxi-peninsula' ), 'checkbox' ),
		'notes'        => array( __( 'Passenger notes', 'taxi-peninsula' ), 'textarea' ),
	);
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
	);
	foreach ( array_keys( tp_booking_fields() ) as $key ) {
		$data[ $key ] = get_post_meta( $post_id, '_tp_' . $key, true );
	}
	return $data;
}

/**
 * Sanitize raw booking input. Returns [clean data, errors].
 *
 * @param array $raw  Unslashed input.
 * @param bool  $strict Apply customer-facing rules (required fields, notice period).
 * @return array
 */
function tp_sanitize_booking( array $raw, $strict = true ) {
	$d = array(
		'name'         => sanitize_text_field( $raw['name'] ?? '' ),
		'phone'        => sanitize_text_field( $raw['phone'] ?? '' ),
		'email'        => sanitize_email( $raw['email'] ?? '' ),
		'pickup'       => sanitize_text_field( $raw['pickup'] ?? '' ),
		'dropoff'      => sanitize_text_field( $raw['dropoff'] ?? '' ),
		'date'         => sanitize_text_field( $raw['date'] ?? '' ),
		'time'         => sanitize_text_field( $raw['time'] ?? '' ),
		'passengers'   => max( 1, min( 11, absint( $raw['passengers'] ?? 1 ) ) ),
		'wheelchairs'  => min( 4, absint( $raw['wheelchairs'] ?? 0 ) ),
		'vehicle'      => sanitize_key( $raw['vehicle'] ?? 'wat' ),
		'mobility_aid' => sanitize_key( $raw['mobility_aid'] ?? 'manual' ),
		'return_trip'  => empty( $raw['return_trip'] ) ? 0 : 1,
		'return_time'  => sanitize_text_field( $raw['return_time'] ?? '' ),
		'mptp'         => empty( $raw['mptp'] ) ? 0 : 1,
		'notes'        => sanitize_textarea_field( $raw['notes'] ?? '' ),
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
 * Save sanitized booking data to post meta and keep title/search text in sync.
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
 * Generate a unique, human-friendly booking reference.
 */
function tp_new_reference() {
	$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	do {
		$ref = 'TP-';
		for ( $i = 0; $i < 6; $i++ ) {
			$ref .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
		}
		$exists = get_posts(
			array(
				'post_type'   => 'tp_booking',
				'post_status' => 'any',
				'meta_key'    => '_tp_reference',
				'meta_value'  => $ref,
				'fields'      => 'ids',
				'numberposts' => 1,
			)
		);
	} while ( $exists );
	return $ref;
}

/**
 * Human-readable pick-up date/time.
 */
function tp_format_pickup( array $b ) {
	if ( empty( $b['date'] ) ) {
		return '';
	}
	$dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $b['date'] . ' ' . ( $b['time'] ?: '00:00' ), wp_timezone() );
	return $dt ? wp_date( 'D j M Y, g:i a', $dt->getTimestamp() ) : $b['date'] . ' ' . $b['time'];
}

/**
 * Plain-text summary used in emails.
 */
function tp_booking_summary( array $b ) {
	$vehicles = tp_vehicle_types();
	$aids     = tp_mobility_aids();
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
	);
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
