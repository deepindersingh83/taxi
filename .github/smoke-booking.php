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

// New accounts: unconfirmed bookings are marked; confirming by text clears the mark.
$s = (array) get_option( 'tp_settings', array() );
update_option( 'tp_settings', array_merge( $s, array( 'signup_confirm' => 1, 'referral_on' => 1, 'ads_conversions' => 1 ) ) );
$tp_new_booking = static function ( array $extra, array $meta = array() ) {
	$data = array_merge( array( 'pickup' => '2 Bay St, Frankston', 'dropoff' => 'Rosebud Hospital', 'date' => wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ), 'time' => '09:00', 'name' => 'Ann Smith', 'phone' => '0433 221 100', 'email' => 'ann@bigpond.com', 'passengers' => 1, 'wheelchairs' => 0 ), $extra );
	$ref  = tp_new_reference();
	$bid  = wp_insert_post( array_merge( array( 'post_type' => 'tp_booking', 'post_status' => 'publish' ), tp_booking_post_fields( $ref, $data ) ) );
	update_post_meta( $bid, '_tp_reference', $ref );
	tp_save_booking_meta( $bid, $data );
	foreach ( $meta as $k => $v ) {
		update_post_meta( $bid, $k, $v );
	}
	do_action( 'tp_booking_created', $bid, array( $bid ) );
	return $bid;
};
$uid = wp_insert_user( array( 'user_login' => 'ci-new', 'user_email' => 'ann@bigpond.com', 'user_pass' => wp_generate_password(), 'role' => 'tp_customer', 'first_name' => 'Ann' ) );
update_user_meta( $uid, 'tp_mobile', '0433 221 100' );
update_user_meta( $uid, 'tp_confirm_pending', time() );
$bid = $tp_new_booking( array(), array( '_tp_customer_id' => $uid ) );
if ( 'account' !== get_post_meta( $bid, '_tp_unverified', true ) ) {
	WP_CLI::error( 'Booking from an unconfirmed account was not marked.' );
}
tp_account_confirmed( $uid, 'email' );
if ( 'phone' !== get_post_meta( $bid, '_tp_unverified', true ) ) {
	WP_CLI::error( 'Email confirmation did not change the mark to "Phone not confirmed".' );
}
tp_account_confirmed( $uid, 'phone' );
if ( get_post_meta( $bid, '_tp_unverified', true ) || ! get_post_meta( $bid, '_tp_phone_verified', true ) ) {
	WP_CLI::error( 'Phone confirmation did not clear the mark.' );
}
WP_CLI::success( 'Account confirmation marks OK.' );

// Refer a friend: own code refused, friend's first trip earns a single-use thank-you code.
$code = tp_referral_code( $uid );
if ( '' === tp_promo_check( $code, array( 'email' => 'ann@bigpond.com', 'phone' => '' ) ) ) {
	WP_CLI::error( 'Customer could use their own referral code.' );
}
$n      = wp_rand( 100000, 999999 );
$friend = array( 'name' => 'Bob Jones', 'phone' => '0422 ' . substr( $n, 0, 3 ) . ' ' . substr( $n, 3 ), 'email' => "bob$n@optusnet.com.au" );
if ( '' !== tp_promo_check( $code, $friend ) || 10.0 !== tp_promo_discount( $code, 50 ) ) {
	WP_CLI::error( 'Friend could not use the referral code: ' . tp_promo_check( $code, $friend ) );
}
$fid = $tp_new_booking( $friend, array( '_tp_promo' => $code, '_tp_fare' => 45, '_tp_gclid' => 'gclid|CI-CLICK-1|' . ( time() - 2 * DAY_IN_SECONDS ) ) );
tp_set_status( $fid, 'completed', false );
$rewards = tp_referral_rewards( $uid );
if ( 1 !== count( $rewards ) || '' === tp_promo_check( $rewards[0][0], $friend ) || '' !== tp_promo_check( $rewards[0][0], array( 'email' => 'ann@bigpond.com' ) ) ) {
	WP_CLI::error( 'Thank-you code was not made, or not limited to its owner: ' . wp_json_encode( $rewards ) );
}
WP_CLI::success( 'Referral codes OK.' );

// Google Ads file: completed ad trips only, with click ID or hashed contacts.
$pid = $tp_new_booking( array( 'name' => 'Cath Lee', 'phone' => '0455 666 777', 'email' => 'Cath.Lee@gmail.com' ), array( '_tp_heard' => 'google_ad' ) );
tp_set_status( $pid, 'completed', false );
$csv = tp_ads_csv( tp_ads_rows() );
if ( false === strpos( $csv, 'CI-CLICK-1' ) || false === strpos( $csv, hash( 'sha256', 'cathlee@gmail.com' ) ) || false === strpos( $csv, hash( 'sha256', '+61455666777' ) ) || false !== strpos( $csv, $friend['email'] ) || 0 !== strpos( $csv, 'Parameters:TimeZone=' ) ) {
	WP_CLI::error( "Google Ads file is wrong:\n" . $csv );
}
WP_CLI::success( 'Google Ads conversions file OK.' );

// v2.13 short form: email optional (but checked when typed); "No wheelchair" picks a standard taxi.
list( $d, $e ) = tp_sanitize_booking( array( 'name' => 'Ann Smith', 'phone' => '0433 221 100', 'pickup' => '2 Bay St, Frankston', 'dropoff' => 'Rosebud Hospital', 'date' => wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ), 'time' => '10:00', 'chair' => 'no', 'vehicle' => 'wat', 'wheelchairs' => 1 ), true );
if ( isset( $e['email'] ) || 'sedan' !== $d['vehicle'] || 0 !== $d['wheelchairs'] ) {
	WP_CLI::error( 'Short form handling is wrong: ' . wp_json_encode( array( $d['vehicle'], $d['wheelchairs'], $e ) ) );
}
list( , $e ) = tp_sanitize_booking( array( 'name' => 'Ann Smith', 'phone' => '0433 221 100', 'pickup' => 'x', 'dropoff' => 'y', 'date' => '2030-01-01', 'time' => '10:00', 'email' => 'ann@' ), true );
if ( ! isset( $e['email'] ) ) {
	WP_CLI::error( 'A wrong email was accepted.' );
}
// Route pages: a place with a map position gets one per nearby suburb.
$pid = wp_insert_post( array( 'post_type' => 'tp_place', 'post_status' => 'publish', 'post_title' => 'CI Hospital', 'post_name' => 'ci-hospital' ) );
foreach ( array( 'type' => 'hospital', 'lat' => '-38.151', 'lng' => '145.131', 'address' => '2 Hastings Road, Frankston VIC 3199' ) as $k => $v ) {
	update_post_meta( $pid, '_tp_p_' . $k, $v );
}
$routes = tp_place_routes( $pid );
if ( ! $routes || ! preg_match( '#/routes/[a-z-]+-to-ci-hospital/$#', tp_route_url( $pid, $routes[0][0] ) ) ) {
	WP_CLI::error( 'Route pages were not made: ' . wp_json_encode( $routes ) );
}
tp_calls_to_follow(); // The follow-up query runs on this database.
WP_CLI::success( 'Short booking form, route pages and call follow-up OK.' );

// SEO plugins: the generated pages have their own sitemap for Rank Math / Yoast to list.
if ( count( tp_generated_urls() ) < 1 || 0 !== strpos( tp_generated_sitemap_url(), home_url() ) ) {
	WP_CLI::error( 'The sitemap of generated pages is empty.' );
}
WP_CLI::success( 'Generated pages sitemap OK (' . count( tp_generated_urls() ) . ' pages).' );

// v2.14: old addresses map to the new folders, and the content pack adds pages once.
$moves = array(
	'wheelchair-taxi/rye/'                            => 'locations/rye/',
	'wheelchair-taxi/rye/airport-transfers'           => 'locations/rye/airport-transfers/',
	'council/frankston-city/'                         => 'locations/council/frankston-city/',
	'wheelchair-taxi-to/frankston-hospital/'          => 'places/frankston-hospital/',
	'wheelchair-taxi-to/frankston-hospital/from-rye/' => 'routes/rye-to-frankston-hospital/',
	'wheelchair-taxi-from/frankston-station/to-rye/'  => 'routes/frankston-station-to-rye/',
	'council-news/'                                   => '',
);
foreach ( $moves as $old => $want ) {
	if ( tp_moved_path( $old ) !== $want ) {
		WP_CLI::error( "Old address $old maps to '" . tp_moved_path( $old ) . "', not '$want'." );
	}
}
if ( ! tp_route_parse( 'frankston-to-ci-hospital' ) || tp_route_parse( 'nowhere-to-anywhere' ) ) {
	WP_CLI::error( 'Route addresses are not read correctly.' );
}
list( $added ) = tp_pack_import();
$again         = tp_pack_import();
$red_hill      = get_page_by_path( 'red-hill', OBJECT, 'tp_area' );
if ( $added < 40 || $again[0] || ! $red_hill || ! get_post_meta( $red_hill->ID, '_tp_local_faq', true ) || array_sum( tp_pack_pending() ) ) {
	WP_CLI::error( 'Content pack import is wrong: ' . wp_json_encode( array( $added, $again, (bool) $red_hill ) ) );
}
$winery = get_page_by_path( 'winery-tours', OBJECT, 'tp_service' );
$airport = get_page_by_path( 'melbourne-airport', OBJECT, 'tp_place' );
if ( ! $winery || tp_local_pair_ok( $red_hill, $winery ) || ( $airport && false === strpos( tp_place_seo_title( $airport->ID ), 'Taxi' ) ) ) {
	WP_CLI::error( 'New suburbs and services must not get combined pages.' );
}
WP_CLI::success( "New addresses and content pack OK ($added pages)." );
