<?php
/**
 * Live driver location. While a trip is "on the way" or "arrived", the
 * driver's phone shares its position (after the driver taps "Share my
 * location"), and the customer's booking page shows a map with "about N
 * minutes away". Positions are kept only on the booking and deleted when the
 * trip moves on, ends or is cancelled.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

const TP_LOC_MAX_AGE = 300; // Seconds before a position is too old to show.

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['live_location'] = array( 'live', __( 'Show customers where their driver is', 'taxi-peninsula' ), 'checkbox', true, __( 'While the driver is on the way, their phone shares its location and the customer’s booking page shows a map and the time to arrival. Drivers tap "Share my location" once; nothing is shared at other times.', 'taxi-peninsula' ) );
		return $s;
	}
);
add_filter(
	'tp_settings_sections',
	static function ( $s ) {
		$s['live'] = array( __( 'Live driver location', 'taxi-peninsula' ), '' );
		return $s;
	}
);

function tp_live_statuses() {
	return array( 'on_way', 'arrived' );
}

/**
 * Current position for a booking, or null: [lat, lng, time].
 */
function tp_driver_position( $post_id ) {
	$loc = get_post_meta( $post_id, '_tp_loc', true );
	if ( ! is_array( $loc ) || empty( $loc['t'] ) || time() - (int) $loc['t'] > TP_LOC_MAX_AGE ) {
		return null;
	}
	return $loc;
}

/**
 * Rough minutes to the pick-up: Google route time when available.
 */
function tp_driver_eta( $post_id, array $loc ) {
	if ( ! tp_maps_available() ) {
		return null;
	}
	$b     = tp_get_booking( $post_id );
	$from  = round( (float) $loc['lat'], 3 ) . ',' . round( (float) $loc['lng'], 3 );
	$route = tp_route_distance( $from, $b['pickup'] );
	return $route ? max( 1, (int) $route[1] ) : null;
}

/**
 * What the customer sees.
 */
function tp_location_payload( $post_id ) {
	$b = tp_get_booking( $post_id );
	if ( ! tp_setting( 'live_location' ) || ! in_array( $b['status'], tp_live_statuses(), true ) ) {
		return array( 'sharing' => false, 'status' => $b['status'] );
	}
	$loc = tp_driver_position( $post_id );
	if ( ! $loc ) {
		return array( 'sharing' => false, 'status' => $b['status'] );
	}
	$eta = 'arrived' === $b['status'] ? 0 : tp_driver_eta( $post_id, $loc );
	return array(
		'sharing' => true,
		'status'  => $b['status'],
		'lat'     => round( (float) $loc['lat'], 5 ),
		'lng'     => round( (float) $loc['lng'], 5 ),
		'age'     => time() - (int) $loc['t'],
		'eta'     => $eta,
		'text'    => 'arrived' === $b['status']
			? __( 'Your driver has arrived.', 'taxi-peninsula' )
			: ( null === $eta
				? __( 'Your driver is on the way.', 'taxi-peninsula' )
				/* translators: %d: minutes */
				: sprintf( _n( 'Your driver is about %d minute away.', 'Your driver is about %d minutes away.', $eta, 'taxi-peninsula' ), $eta ) ),
	);
}

function tp_osm_embed_url( $lat, $lng ) {
	$d = 0.012;
	return add_query_arg(
		array(
			'bbox'   => implode( ',', array( $lng - $d, $lat - $d / 1.6, $lng + $d, $lat + $d / 1.6 ) ),
			'layer'  => 'mapnik',
			'marker' => $lat . ',' . $lng,
		),
		'https://www.openstreetmap.org/export/embed.html'
	);
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'taxi-peninsula/v1',
			'/driver-location',
			array(
				'methods'             => 'POST',
				'callback'            => 'tp_rest_driver_location',
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'booking' => array( 'type' => 'integer', 'required' => true ),
					'lat'     => array( 'type' => 'number', 'required' => true, 'minimum' => -90, 'maximum' => 90 ),
					'lng'     => array( 'type' => 'number', 'required' => true, 'minimum' => -180, 'maximum' => 180 ),
				),
			)
		);
		register_rest_route(
			'taxi-peninsula/v1',
			'/booking-location',
			array(
				'methods'             => 'GET',
				'callback'            => 'tp_rest_booking_location',
				'permission_callback' => '__return_true', // Checked with the booking token.
				'args'                => array(
					'ref' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					't'   => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
	}
);

function tp_rest_driver_location( WP_REST_Request $req ) {
	$id = (int) $req['booking'];
	$b  = 'tp_booking' === get_post_type( $id ) ? tp_get_booking( $id ) : null;
	if ( ! $b || (int) $b['driver_id'] !== get_current_user_id() ) {
		return new WP_REST_Response( array( 'ok' => false ), 403 );
	}
	if ( ! tp_setting( 'live_location' ) || ! in_array( $b['status'], tp_live_statuses(), true ) ) {
		delete_post_meta( $id, '_tp_loc' );
		return array( 'ok' => false, 'stop' => true );
	}
	update_post_meta( $id, '_tp_loc', array( 'lat' => (float) $req['lat'], 'lng' => (float) $req['lng'], 't' => time() ) );
	return array( 'ok' => true );
}

function tp_rest_booking_location( WP_REST_Request $req ) {
	$id = tp_find_booking( $req['ref'] );
	if ( ! $id || ! hash_equals( tp_booking_token( $id ), (string) $req['t'] ) ) {
		return new WP_REST_Response( array( 'sharing' => false ), 403 );
	}
	$res = rest_ensure_response( tp_location_payload( $id ) );
	$res->header( 'Cache-Control', 'no-store' );
	return $res;
}

// Positions are only kept while the driver is on the way.
add_action(
	'tp_booking_status_changed',
	static function ( $post_id, $old, $status ) {
		if ( ! in_array( $status, tp_live_statuses(), true ) ) {
			delete_post_meta( $post_id, '_tp_loc' );
		}
	},
	10,
	3
);

/* -------------------------------------------------------------------------
 * Driver: "Share my location" on the job card.
 * ---------------------------------------------------------------------- */

add_action(
	'tp_driver_job_card_after',
	static function ( $b ) {
		if ( ! tp_setting( 'live_location' ) || (int) $b['driver_id'] !== get_current_user_id() || ! in_array( $b['status'], tp_live_statuses(), true ) ) {
			return;
		}
		tp_enqueue_live_script();
		?>
		<div class="job__share" data-share-location="<?php echo esc_attr( $b['id'] ); ?>">
			<p class="job__share-status" data-share-status aria-live="polite"><?php esc_html_e( 'The passenger can see you are on the way. Share your location so they can see where you are.', 'taxi-peninsula' ); ?></p>
			<button type="button" class="btn btn--ghost" data-share-start><?php tp_the_icon( 'pin' ); ?> <?php esc_html_e( 'Share my location', 'taxi-peninsula' ); ?></button>
			<button type="button" class="btn btn--ghost" data-share-stop hidden><?php esc_html_e( 'Stop sharing', 'taxi-peninsula' ); ?></button>
		</div>
		<?php
	}
);

/* -------------------------------------------------------------------------
 * Customer: map on the booking page.
 * ---------------------------------------------------------------------- */

add_action(
	'tp_booking_status_after',
	static function ( $post_id ) {
		if ( ! tp_setting( 'live_location' ) ) {
			return;
		}
		$b = tp_get_booking( $post_id );
		if ( ! in_array( $b['status'], array( 'assigned', 'accepted', 'on_way', 'arrived' ), true ) || $b['date'] !== wp_date( 'Y-m-d' ) ) {
			return;
		}
		$p = tp_location_payload( $post_id );
		tp_enqueue_live_script();
		?>
		<section class="card driver-live" data-driver-live data-ref="<?php echo esc_attr( $b['reference'] ); ?>" data-t="<?php echo esc_attr( tp_booking_token( $post_id ) ); ?>" aria-labelledby="driver-live-title">
			<h2 id="driver-live-title"><?php esc_html_e( 'Where is my driver?', 'taxi-peninsula' ); ?></h2>
			<p class="driver-live__text" data-live-text aria-live="polite"><?php echo esc_html( $p['sharing'] ? $p['text'] : __( 'You will see your driver here once they are on the way and sharing their location.', 'taxi-peninsula' ) ); ?></p>
			<div class="driver-live__map" data-live-map<?php echo $p['sharing'] ? '' : ' hidden'; ?>>
				<iframe title="<?php esc_attr_e( 'Map showing your driver’s location', 'taxi-peninsula' ); ?>" loading="lazy" referrerpolicy="no-referrer" src="<?php echo $p['sharing'] ? esc_url( tp_osm_embed_url( $p['lat'], $p['lng'] ) ) : 'about:blank'; ?>"></iframe>
			</div>
			<p class="driver-live__note"><?php esc_html_e( 'Updates every 20 seconds. Map © OpenStreetMap contributors.', 'taxi-peninsula' ); ?></p>
		</section>
		<?php
	},
	3
);

function tp_enqueue_live_script() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	wp_enqueue_script( 'tp-live', tp_asset( 'assets/js/live.js' ), array(), TP_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_localize_script(
		'tp-live',
		'tpLive',
		array(
			'post'    => rest_url( 'taxi-peninsula/v1/driver-location' ),
			'get'     => rest_url( 'taxi-peninsula/v1/booking-location' ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'mapBase' => 'https://www.openstreetmap.org/export/embed.html',
			'i18n'    => array(
				'sharing' => __( 'Sharing your location with the passenger.', 'taxi-peninsula' ),
				'sent'    => __( 'Location sent', 'taxi-peninsula' ),
				'denied'  => __( 'Location is blocked on this phone. Allow location for this website in your browser settings.', 'taxi-peninsula' ),
				'stopped' => __( 'Location sharing stopped.', 'taxi-peninsula' ),
				'waiting' => __( 'You will see your driver here once they are on the way and sharing their location.', 'taxi-peninsula' ),
			),
		)
	);
}
