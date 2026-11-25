<?php
/**
 * Live Google reviews (Places API – New), refreshed daily.
 *
 * Reviews are shown exactly as Google returns them (no filtering by rating),
 * with author attribution and a link to your Google listing, as required by
 * the Google Maps Platform terms. Cached data is kept for at most 30 days.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_google_reviews_enabled() {
	return '' !== tp_google_server_key() && tp_setting( 'google_place_id' ) && tp_setting( 'google_reviews_live' );
}

/**
 * Fetch place rating + reviews from Google. Returns array or WP_Error.
 *
 * Uses Places API (New); if that API is not switched on for the key, the
 * older Places API is tried. The last error is kept for Settings → Google
 * reviews → Test & troubleshoot.
 */
function tp_fetch_google_place() {
	$data = tp_fetch_google_place_new();
	if ( is_wp_error( $data ) && preg_match( '/not been used|is disabled|not enabled|SERVICE_DISABLED|API_KEY_SERVICE_BLOCKED|not authorized to use this API/i', $data->get_error_message() ) ) {
		$legacy = tp_fetch_google_place_legacy();
		if ( ! is_wp_error( $legacy ) ) {
			$data = $legacy;
		}
	}
	if ( is_wp_error( $data ) ) {
		update_option( 'tp_google_place_error', array( 'msg' => $data->get_error_message(), 'at' => time() ), false );
	} else {
		delete_option( 'tp_google_place_error' );
	}
	return $data;
}

/** The key used for Google calls from the server. */
function tp_google_server_key() {
	return trim( (string) ( tp_setting( 'google_reviews_key' ) ?: tp_setting( 'google_maps_key' ) ) );
}

function tp_fetch_google_place_new() {
	$id       = rawurlencode( trim( (string) tp_setting( 'google_place_id' ) ) );
	$response = wp_remote_get(
		'https://places.googleapis.com/v1/places/' . $id . '?languageCode=en-AU',
		array(
			'timeout' => 10,
			'headers' => array(
				'X-Goog-Api-Key'   => tp_google_server_key(),
				'X-Goog-FieldMask' => 'displayName,rating,userRatingCount,googleMapsUri,reviews',
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 200 !== wp_remote_retrieve_response_code( $response ) || ! is_array( $data ) ) {
		return new WP_Error( 'tp_places', $data['error']['message'] ?? sprintf( /* translators: %d: HTTP status */ __( 'Google answered with error %d.', 'taxi-peninsula' ), wp_remote_retrieve_response_code( $response ) ) );
	}

	$reviews = array();
	foreach ( (array) ( $data['reviews'] ?? array() ) as $r ) {
		$text      = $r['text']['text'] ?? ( $r['originalText']['text'] ?? '' );
		$reviews[] = array(
			'author'   => sanitize_text_field( $r['authorAttribution']['displayName'] ?? __( 'Google user', 'taxi-peninsula' ) ),
			'author_u' => esc_url_raw( $r['authorAttribution']['uri'] ?? '' ),
			'rating'   => (int) ( $r['rating'] ?? 0 ),
			'when'     => sanitize_text_field( $r['relativePublishTimeDescription'] ?? '' ),
			'time'     => sanitize_text_field( $r['publishTime'] ?? '' ),
			'text'     => sanitize_textarea_field( $text ),
			'uri'      => esc_url_raw( $r['googleMapsUri'] ?? '' ),
		);
	}

	return array(
		'name'    => sanitize_text_field( $data['displayName']['text'] ?? '' ),
		'rating'  => isset( $data['rating'] ) ? round( (float) $data['rating'], 1 ) : null,
		'count'   => (int) ( $data['userRatingCount'] ?? 0 ),
		'url'     => esc_url_raw( $data['googleMapsUri'] ?? '' ),
		'reviews' => $reviews,
		'fetched' => time(),
	);
}

/** The older Places API (Place Details), for keys that only have it switched on. */
function tp_fetch_google_place_legacy() {
	$url      = add_query_arg(
		array(
			'place_id' => rawurlencode( trim( (string) tp_setting( 'google_place_id' ) ) ),
			'fields'   => 'name,rating,user_ratings_total,url,reviews',
			'language' => 'en-AU',
			'key'      => rawurlencode( tp_google_server_key() ),
		),
		'https://maps.googleapis.com/maps/api/place/details/json'
	);
	$response = wp_remote_get( $url, array( 'timeout' => 10 ) );
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) || 'OK' !== ( $data['status'] ?? '' ) ) {
		return new WP_Error( 'tp_places', trim( ( $data['status'] ?? '' ) . ' ' . ( $data['error_message'] ?? '' ) ) ?: __( 'Google did not return your reviews.', 'taxi-peninsula' ) );
	}
	$p       = $data['result'] ?? array();
	$reviews = array();
	foreach ( (array) ( $p['reviews'] ?? array() ) as $r ) {
		$reviews[] = array(
			'author'   => sanitize_text_field( $r['author_name'] ?? __( 'Google user', 'taxi-peninsula' ) ),
			'author_u' => esc_url_raw( $r['author_url'] ?? '' ),
			'rating'   => (int) ( $r['rating'] ?? 0 ),
			'when'     => sanitize_text_field( $r['relative_time_description'] ?? '' ),
			'time'     => isset( $r['time'] ) ? gmdate( 'c', (int) $r['time'] ) : '',
			'text'     => sanitize_textarea_field( $r['text'] ?? '' ),
			'uri'      => '',
		);
	}
	return array(
		'name'    => sanitize_text_field( $p['name'] ?? '' ),
		'rating'  => isset( $p['rating'] ) ? round( (float) $p['rating'], 1 ) : null,
		'count'   => (int) ( $p['user_ratings_total'] ?? 0 ),
		'url'     => esc_url_raw( $p['url'] ?? '' ),
		'reviews' => $reviews,
		'fetched' => time(),
	);
}

/**
 * What to do about a Google error, in plain words.
 */
function tp_google_error_help( $msg ) {
	$checks = array(
		'/referer|referrer/i'                                       => __( 'Your key only works in web browsers (it has "Website" restrictions). Reviews are fetched by your server, so in Google Cloud → Credentials either change this key\'s Application restriction to "IP addresses" (your server\'s IP) or "None", or make a second key and paste it in "Google key for reviews" below.', 'taxi-peninsula' ),
		'/IP address|ip_address|not authorized to use this API key/i' => __( 'Your key is restricted to IP addresses, and your server\'s address is not on the list. Add your server\'s IP (ask your host) in Google Cloud → Credentials → this key, or use "None" while testing.', 'taxi-peninsula' ),
		'/not been used|is disabled|not enabled|SERVICE_DISABLED|REQUEST_DENIED/i' => __( 'The Places API is not switched on for this key\'s project. In Google Cloud → APIs & Services → Library, enable "Places API (New)", then make sure the key\'s API restrictions include it.', 'taxi-peninsula' ),
		'/billing/i'                                                => __( 'Google needs a billing account on the project (Google Cloud → Billing). Review look-ups for one business stay inside the free monthly allowance.', 'taxi-peninsula' ),
		'/API key not valid|API_KEY_INVALID|INVALID_ARGUMENT.*key/i' => __( 'The key is not valid. Copy it again from Google Cloud → Credentials (no spaces).', 'taxi-peninsula' ),
		'/not found|NOT_FOUND|INVALID_REQUEST|Place ID|placeId/i'   => __( 'Google does not recognise the Place ID. Use "Find your Google Place ID" at the bottom of this page and save the ID it gives you.', 'taxi-peninsula' ),
	);
	foreach ( $checks as $re => $help ) {
		if ( preg_match( $re, (string) $msg ) ) {
			return $help;
		}
	}
	return __( 'Check the key has Places API (New) enabled and is not restricted to websites.', 'taxi-peninsula' );
}

/**
 * Cached place data, refreshed at most once a day. Stale data is dropped after 30 days.
 *
 * @return array|null
 */
function tp_google_place() {
	if ( ! tp_google_reviews_enabled() ) {
		return null;
	}
	$cache = get_option( 'tp_google_place_cache' );
	$fresh = is_array( $cache ) && ( time() - (int) $cache['fetched'] ) < DAY_IN_SECONDS;
	if ( ! $fresh && ! get_transient( 'tp_google_place_lock' ) ) {
		set_transient( 'tp_google_place_lock', 1, HOUR_IN_SECONDS ); // Retry failures hourly, not every page view.
		$data = tp_fetch_google_place();
		if ( ! is_wp_error( $data ) ) {
			update_option( 'tp_google_place_cache', $data, false );
			delete_transient( 'tp_google_place_lock' );
			$cache = $data;
		}
	}
	if ( ! is_array( $cache ) || ( time() - (int) $cache['fetched'] ) > 30 * DAY_IN_SECONDS ) {
		return null;
	}
	return $cache;
}

add_action( 'init', static function () {
	if ( tp_google_reviews_enabled() && ! wp_next_scheduled( 'tp_refresh_google_place' ) ) {
		wp_schedule_event( time() + 600, 'daily', 'tp_refresh_google_place' );
	}
} );
add_action( 'tp_refresh_google_place', static function () {
	delete_transient( 'tp_google_place_lock' );
	delete_option( 'tp_google_place_cache' );
	tp_google_place();
} );

/**
 * Rating + count to display: live Google data, falling back to the manual settings.
 *
 * @return array [rating string, count int, url]
 */
function tp_google_rating_summary() {
	$place = tp_google_place();
	if ( $place && $place['rating'] ) {
		return array( number_format_i18n( $place['rating'], 1 ), $place['count'], $place['url'] ?: tp_setting( 'google_reviews_url' ) );
	}
	return array( trim( (string) tp_setting( 'google_rating' ) ), (int) tp_setting( 'google_review_count' ), tp_setting( 'google_reviews_url' ) );
}

/* ---- Admin: find your Place ID ---- */

add_action( 'admin_post_tp_find_place', static function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '', 403 );
	}
	check_admin_referer( 'tp_find_place' );
	$query   = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
	$results = array();
	$error   = '';
	if ( $query && '' !== tp_google_server_key() ) {
		$response = wp_remote_post(
			'https://places.googleapis.com/v1/places:searchText',
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type'     => 'application/json',
					'X-Goog-Api-Key'   => tp_google_server_key(),
					'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress',
				),
				'body'    => wp_json_encode( array( 'textQuery' => $query, 'regionCode' => 'au' ) ),
			)
		);
		$data  = is_wp_error( $response ) ? array() : json_decode( wp_remote_retrieve_body( $response ), true );
		$error = is_wp_error( $response ) ? $response->get_error_message() : (string) ( $data['error']['message'] ?? '' );
		foreach ( (array) ( $data['places'] ?? array() ) as $p ) {
			$results[] = array(
				'id'      => sanitize_text_field( $p['id'] ?? '' ),
				'name'    => sanitize_text_field( $p['displayName']['text'] ?? '' ),
				'address' => sanitize_text_field( $p['formattedAddress'] ?? '' ),
			);
		}
	}
	set_transient( 'tp_place_search_' . get_current_user_id(), array( 'q' => $query, 'results' => $results, 'error' => $error ), 10 * MINUTE_IN_SECONDS );
	wp_safe_redirect( tp_settings_url( 'reviews', 'tp-find-place' ) );
	exit;
} );

add_action( 'admin_post_tp_use_place', static function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '', 403 );
	}
	check_admin_referer( 'tp_use_place' );
	$id                         = isset( $_POST['place_id'] ) ? sanitize_text_field( wp_unslash( $_POST['place_id'] ) ) : '';
	$settings                   = (array) get_option( 'tp_settings', array() );
	$settings['google_place_id'] = $id;
	update_option( 'tp_settings', $settings );
	delete_option( 'tp_google_place_cache' );
	delete_transient( 'tp_google_place_lock' );
	set_transient( 'tp_admin_notice_' . get_current_user_id(), array( 'success', __( 'Place saved. Your Google reviews will appear on the site shortly.', 'taxi-peninsula' ) ), 60 );
	wp_safe_redirect( tp_settings_url( 'reviews' ) );
	exit;
} );

/**
 * Finder UI shown on the settings page.
 */
function tp_render_place_finder() {
	if ( ! tp_maps_available() ) {
		return;
	}
	$search = get_transient( 'tp_place_search_' . get_current_user_id() );
	$query  = is_array( $search ) ? $search['q'] : get_bloginfo( 'name' ) . ' ' . tp_opt( 'location' );
	?>
	<hr>
	<h2 id="tp-find-place"><?php esc_html_e( 'Find your Google Place ID', 'taxi-peninsula' ); ?></h2>
	<p><?php esc_html_e( 'Search for your business as it appears on Google Maps, then choose it. This connects live reviews and your star rating.', 'taxi-peninsula' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="tp_find_place">
		<?php wp_nonce_field( 'tp_find_place' ); ?>
		<label for="tp_place_q" class="screen-reader-text"><?php esc_html_e( 'Business name and suburb', 'taxi-peninsula' ); ?></label>
		<input type="text" id="tp_place_q" name="q" class="regular-text" value="<?php echo esc_attr( $query ); ?>">
		<?php submit_button( __( 'Search Google', 'taxi-peninsula' ), 'secondary', 'submit', false ); ?>
	</form>
	<?php if ( is_array( $search ) ) : ?>
		<?php if ( ! empty( $search['error'] ) ) : ?>
			<div class="notice notice-error inline"><p><strong><?php esc_html_e( 'Google said:', 'taxi-peninsula' ); ?></strong> <?php echo esc_html( $search['error'] ); ?></p><p><?php echo esc_html( tp_google_error_help( $search['error'] ) ); ?></p></div>
		<?php elseif ( ! $search['results'] ) : ?>
			<p><?php esc_html_e( 'No matches. Try the exact name shown on your Google Business Profile.', 'taxi-peninsula' ); ?></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:48rem;margin-top:1em"><tbody>
				<?php foreach ( $search['results'] as $r ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $r['name'] ); ?></strong><br><span class="description"><?php echo esc_html( $r['address'] ); ?></span></td>
						<td style="width:8em">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="tp_use_place">
								<input type="hidden" name="place_id" value="<?php echo esc_attr( $r['id'] ); ?>">
								<?php wp_nonce_field( 'tp_use_place' ); ?>
								<?php submit_button( __( 'Use this', 'taxi-peninsula' ), 'primary small', 'submit', false ); ?>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>
	<?php endif; ?>
	<?php
	$place = tp_google_place();
	if ( $place ) {
		/* translators: 1: place name, 2: rating, 3: count, 4: number of reviews shown */
		echo '<p><strong>' . esc_html( sprintf( __( 'Connected: %1$s — %2$s★ from %3$d reviews (%4$d shown on the site).', 'taxi-peninsula' ), $place['name'], $place['rating'], $place['count'], count( $place['reviews'] ) ) ) . '</strong></p>';
	}
}
