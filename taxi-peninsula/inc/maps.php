<?php
/**
 * Address suggestions (Google Places API – New) and fare estimates (Google
 * Routes API), proxied through the site so the API key never reaches the browser.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_maps_available() {
	return (bool) tp_setting( 'google_maps_key' );
}

function tp_places_available() {
	return tp_maps_available() && tp_setting( 'places_enabled' );
}

/**
 * Fare estimates need the Maps key and at least a per-kilometre rate.
 */
function tp_fare_available() {
	return tp_maps_available() && tp_setting( 'fare_enabled' ) && (float) tp_setting( 'fare_per_km' ) > 0;
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'taxi-peninsula/v1',
			'/places',
			array(
				'methods'             => 'GET',
				'callback'            => 'tp_rest_places',
				'permission_callback' => '__return_true', // Public, rate limited.
				'args'                => array(
					'q'       => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'session' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
				),
			)
		);
		register_rest_route(
			'taxi-peninsula/v1',
			'/estimate',
			array(
				'methods'             => 'GET',
				'callback'            => 'tp_rest_estimate',
				'permission_callback' => '__return_true', // Public, rate limited.
				'args'                => array(
					'from' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'to'   => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'date' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'time' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
	}
);

function tp_rest_places( WP_REST_Request $req ) {
	if ( ! tp_places_available() ) {
		return new WP_REST_Response( array( 'suggestions' => array() ), 404 );
	}
	$q = trim( (string) $req['q'] );
	if ( mb_strlen( $q ) < 3 || mb_strlen( $q ) > 120 ) {
		return array( 'suggestions' => array() );
	}

	$cache_key = 'tp_pl_' . md5( strtolower( $q ) );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return array( 'suggestions' => $cached );
	}
	if ( ! tp_rate_limit( 'places', 120, 10 * MINUTE_IN_SECONDS ) ) {
		return new WP_REST_Response( array( 'suggestions' => array() ), 429 );
	}

	$body = array(
		'input'               => $q,
		'includedRegionCodes' => array( 'au' ),
		'languageCode'        => 'en-AU',
		// Bias towards Melbourne & the Mornington Peninsula (max radius 50 km).
		'locationBias'        => array(
			'circle' => array(
				'center' => array( 'latitude' => -38.05, 'longitude' => 145.05 ),
				'radius' => 50000.0,
			),
		),
	);
	if ( $req['session'] ) {
		$body['sessionToken'] = substr( $req['session'], 0, 36 );
	}

	$response = wp_remote_post(
		'https://places.googleapis.com/v1/places:autocomplete',
		array(
			'timeout' => 8,
			'headers' => array(
				'Content-Type'   => 'application/json',
				'X-Goog-Api-Key' => tp_setting( 'google_maps_key' ),
			),
			'body'    => wp_json_encode( $body ),
		)
	);
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return array( 'suggestions' => array() );
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	$out  = array();
	foreach ( (array) ( $data['suggestions'] ?? array() ) as $s ) {
		$p = $s['placePrediction'] ?? null;
		if ( ! $p ) {
			continue;
		}
		$out[] = array(
			'text'      => sanitize_text_field( $p['text']['text'] ?? '' ),
			'main'      => sanitize_text_field( $p['structuredFormat']['mainText']['text'] ?? '' ),
			'secondary' => sanitize_text_field( $p['structuredFormat']['secondaryText']['text'] ?? '' ),
		);
		if ( count( $out ) >= 6 ) {
			break;
		}
	}
	set_transient( $cache_key, $out, DAY_IN_SECONDS );
	return array( 'suggestions' => $out );
}

/**
 * Driving distance/time between two addresses. Returns [km, minutes] or null.
 */
function tp_route_distance( $from, $to ) {
	if ( ! tp_maps_available() || '' === trim( $from ) || '' === trim( $to ) ) {
		return null;
	}
	$cache_key = 'tp_rt_' . md5( strtolower( trim( $from ) ) . '|' . strtolower( trim( $to ) ) );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$response = wp_remote_post(
		'https://routes.googleapis.com/directions/v2:computeRoutes',
		array(
			'timeout' => 10,
			'headers' => array(
				'Content-Type'     => 'application/json',
				'X-Goog-Api-Key'   => tp_setting( 'google_maps_key' ),
				'X-Goog-FieldMask' => 'routes.distanceMeters,routes.duration',
			),
			'body'    => wp_json_encode(
				array(
					'origin'            => array( 'address' => $from ),
					'destination'       => array( 'address' => $to ),
					'travelMode'        => 'DRIVE',
					'routingPreference' => 'TRAFFIC_UNAWARE',
					'regionCode'        => 'au',
					'units'             => 'METRIC',
				)
			),
		)
	);
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}
	$data  = json_decode( wp_remote_retrieve_body( $response ), true );
	$route = $data['routes'][0] ?? null;
	if ( ! $route || empty( $route['distanceMeters'] ) ) {
		return null;
	}
	$result = array(
		round( $route['distanceMeters'] / 1000, 1 ),
		(int) ceil( ( (int) rtrim( (string) ( $route['duration'] ?? '0s' ), 's' ) ) / 60 ),
	);
	set_transient( $cache_key, $result, WEEK_IN_SECONDS );
	return $result;
}

/**
 * Apply the configured rates. Returns [low, high, mid] in dollars, or null.
 */
function tp_fare_from_distance( $km, $minutes, $date, $time, $from = '', $to = '' ) {
	$per_km = (float) tp_setting( 'fare_per_km' );
	if ( $per_km <= 0 ) {
		return null;
	}
	$fare = (float) tp_setting( 'fare_flagfall' )
		+ $km * $per_km
		+ $minutes * (float) tp_setting( 'fare_per_min' );

	if ( tp_fare_surcharge_applies( $date, $time ) ) {
		$fare *= 1 + (float) tp_setting( 'fare_surcharge_pct' ) / 100;
	}
	if ( preg_match( '/airport|tullamarine|avalon/i', $from . ' ' . $to ) ) {
		$fare += (float) tp_setting( 'fare_airport_fee' );
	}
	$fare += (float) tp_setting( 'fare_booking_fee' );
	$fare  = max( $fare, (float) tp_setting( 'fare_minimum' ) );

	$range = max( 0, min( 50, (float) tp_setting( 'fare_range_pct' ) ) ) / 100;
	return array(
		floor( $fare * ( 1 - $range ) ),
		ceil( $fare * ( 1 + $range ) ),
		round( $fare, 2 ),
	);
}

function tp_fare_surcharge_applies( $date, $time ) {
	if ( (float) tp_setting( 'fare_surcharge_pct' ) <= 0 || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
		return false;
	}
	$dt = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
	if ( $dt && tp_setting( 'fare_weekends' ) && (int) $dt->format( 'N' ) >= 6 ) {
		return true;
	}
	$from = (string) tp_setting( 'fare_night_from' );
	$to   = (string) tp_setting( 'fare_night_to' );
	if ( ! preg_match( '/^\d{2}:\d{2}$/', (string) $time ) || ! preg_match( '/^\d{2}:\d{2}$/', $from ) || ! preg_match( '/^\d{2}:\d{2}$/', $to ) ) {
		return false;
	}
	// Window may wrap past midnight (e.g. 22:00 → 06:00).
	return $from <= $to ? ( $time >= $from && $time < $to ) : ( $time >= $from || $time < $to );
}

function tp_rest_estimate( WP_REST_Request $req ) {
	if ( ! tp_fare_available() ) {
		return new WP_REST_Response( array( 'ok' => false ), 404 );
	}
	if ( ! tp_rate_limit( 'estimate', 40, 10 * MINUTE_IN_SECONDS ) ) {
		return new WP_REST_Response( array( 'ok' => false ), 429 );
	}
	$route = tp_route_distance( $req['from'], $req['to'] );
	if ( ! $route ) {
		return array( 'ok' => false );
	}
	$fare = tp_fare_from_distance( $route[0], $route[1], $req['date'], $req['time'], $req['from'], $req['to'] );
	if ( ! $fare ) {
		return array( 'ok' => false );
	}
	return array(
		'ok'      => true,
		'km'      => $route[0],
		'minutes' => $route[1],
		'low'     => $fare[0],
		'high'    => $fare[1],
		'text'    => tp_fare_text( $fare[0], $fare[1] ),
		'detail'  => sprintf(
			/* translators: 1: kilometres, 2: minutes */
			__( '%1$s km, about %2$d min drive', 'taxi-peninsula' ),
			number_format_i18n( $route[0], 1 ),
			$route[1]
		),
	);
}

function tp_fare_text( $low, $high ) {
	return $low === $high
		? '$' . number_format_i18n( $low )
		: '$' . number_format_i18n( $low ) . '–$' . number_format_i18n( $high );
}

/**
 * Store distance + estimate on a new booking (uses the cached route, so no extra API cost).
 */
add_action( 'tp_booking_created', 'tp_store_estimates', 5, 2 );
function tp_store_estimates( $first_id, $ids ) {
	if ( ! tp_fare_available() && ! tp_maps_available() ) {
		return;
	}
	$b     = tp_get_booking( $first_id );
	$route = tp_route_distance( $b['pickup'], $b['dropoff'] );
	if ( ! $route ) {
		return;
	}
	foreach ( $ids as $id ) {
		$trip = tp_get_booking( $id );
		update_post_meta( $id, '_tp_distance_km', $route[0] );
		update_post_meta( $id, '_tp_duration_min', $route[1] );
		if ( tp_fare_available() ) {
			$fare = tp_fare_from_distance( $route[0], $route[1], $trip['date'], $trip['time'], $trip['pickup'], $trip['dropoff'] );
			if ( $fare ) {
				update_post_meta( $id, '_tp_estimate', tp_fare_text( $fare[0], $fare[1] ) );
			}
		}
	}
}

/**
 * Front-end config for the booking form script.
 */
function tp_booking_script_config() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	wp_add_inline_script(
		'tp-main',
		'window.tpBooking = ' . wp_json_encode(
			array(
				'places'      => tp_places_available(),
				'fare'        => tp_fare_available(),
				'placesUrl'   => rest_url( 'taxi-peninsula/v1/places' ),
				'estimateUrl' => rest_url( 'taxi-peninsula/v1/estimate' ),
				'i18n'        => array(
					/* translators: %d: number of suggestions */
					'results'    => __( '%d address suggestions available. Use the up and down arrow keys to choose.', 'taxi-peninsula' ),
					'noResults'  => __( 'No address suggestions.', 'taxi-peninsula' ),
					'estimating' => __( 'Calculating fare estimate…', 'taxi-peninsula' ),
					'estimate'   => __( 'Estimated fare', 'taxi-peninsula' ),
					'noEstimate' => __( 'We could not estimate this trip online. We will confirm the fare with you.', 'taxi-peninsula' ),
					'powered'    => __( 'Address suggestions by Google', 'taxi-peninsula' ),
				),
			)
		) . ';',
		'before'
	);
}
