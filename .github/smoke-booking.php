<?php
/**
 * CI smoke test: save a booking the way the theme does, move it through the
 * trip statuses and check the report index follows. Run with wp eval-file.
 */
$d   = wp_date( 'Y-m-d', time() + 3 * DAY_IN_SECONDS );
$data = array( 'pickup' => '1 Main St, Frankston', 'dropoff' => 'Frankston Hospital', 'date' => $d, 'time' => '10:00', 'name' => 'CI Test', 'phone' => '0400000000', 'email' => 'ci@example.com', 'passengers' => 1, 'wheelchairs' => 1 );
$ref  = tp_new_reference();
$id   = wp_insert_post( array_merge( array( 'post_type' => 'tp_booking', 'post_status' => 'publish' ), tp_booking_post_fields( $ref, $data ) ), true );
if ( is_wp_error( $id ) ) {
	WP_CLI::error( $id->get_error_message() );
}
update_post_meta( $id, '_tp_reference', $ref );
tp_save_booking_meta( $id, $data );
foreach ( array( 'confirmed', 'on_way', 'arrived', 'completed' ) as $status ) {
	tp_set_status( $id, $status, true );
}
$b = tp_get_booking( $id );
if ( $ref !== $b['reference'] || 'completed' !== $b['status'] || $d !== $b['date'] ) {
	WP_CLI::error( 'Booking did not save as expected: ' . wp_json_encode( $b ) );
}
tp_trips_sync( $id );
update_option( 'tp_trips_ready', 1 );
if ( ! in_array( $id, (array) tp_trips_ids( array( 'from' => $d, 'status' => 'completed' ) ), true ) ) {
	WP_CLI::error( 'Trip index did not pick up the booking.' );
}
WP_CLI::success( "Booking $ref saved, completed and indexed." );

// Australian phone numbers: tidied when valid, refused when not.
$phones = array(
	'0412345678'      => '0412 345 678',
	'+61 412 345 678' => '0412 345 678',
	'9781 1234'       => '(03) 9781 1234',
	'1300 123 456'    => '1300 123 456',
	'12345'           => null,
	'0512 345 678'    => null,
);
foreach ( $phones as $in => $want ) {
	$got = tp_au_phone( $in );
	if ( ( $got['display'] ?? null ) !== $want ) {
		WP_CLI::error( "Phone check: $in gave " . wp_json_encode( $got ) );
	}
}
$secret = tp_encrypt( 'top secret' );
if ( 0 !== strpos( $secret, 'tpenc:1:' ) || 'top secret' !== tp_decrypt( $secret ) ) {
	WP_CLI::error( 'Secret encryption round trip failed.' );
}
WP_CLI::success( 'Phone checks and secret encryption OK.' );

// Fake-looking bookings are flagged; cancelling as fake records the reason and blocks on request.
$why = tp_suspect_reasons( array( 'name' => 'Test Test', 'phone' => '0400 000 000', 'email' => 'x@mailinator.com' ), '' );
if ( count( $why ) < 3 ) {
	WP_CLI::error( 'Suspicious checks missed: ' . wp_json_encode( $why ) );
}
if ( tp_suspect_reasons( array( 'name' => 'Margaret Wilson', 'phone' => '0433 221 100', 'email' => 'margaret@bigpond.com' ), '' ) ) {
	WP_CLI::error( 'A normal booking was flagged as suspicious.' );
}
tp_cancel_booking( $id, 'fake', '', true, true );
if ( 'cancelled' !== get_post_meta( $id, '_tp_status', true ) || 'fake' !== tp_cancel_reason( $id ) || ! tp_blocked_matches( '0400000000', 'ci@example.com' ) ) {
	WP_CLI::error( 'Cancel as fake + block did not work.' );
}
WP_CLI::success( 'Suspicious checks, cancel reasons and the blocked list OK.' );
