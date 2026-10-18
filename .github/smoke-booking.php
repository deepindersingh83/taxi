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
