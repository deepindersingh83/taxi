<?php
/**
 * Web push notifications — no app needed.
 *
 * On Manage My Booking (and the booking confirmation), passengers tap
 * "Notify me" to get a phone notification when the booking is confirmed,
 * the driver is on the way, has arrived, or the pick-up time moves.
 *
 * - Android / Windows / Mac: works in the browser.
 * - iPhone and iPad (iOS 16.4+): Apple only allows it once the site has been
 *   added to the Home Screen (Share → Add to Home Screen) — the page says so.
 *
 * Standard Web Push with VAPID keys made by the site itself; nothing to set
 * up and no third-party service. The notification has no data inside it:
 * the phone fetches the message from this site when the push arrives.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_b64url( $bin ) {
	return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/**
 * VAPID key pair (created once). Returns [private PEM, public key base64url] or null.
 */
function tp_vapid_keys() {
	$k = get_option( 'tp_vapid' );
	if ( is_array( $k ) && ! empty( $k['pem'] ) && ! empty( $k['pub'] ) ) {
		return array( $k['pem'], $k['pub'] );
	}
	if ( ! function_exists( 'openssl_pkey_new' ) ) {
		return null;
	}
	$res = openssl_pkey_new( array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ) );
	if ( ! $res ) {
		return null;
	}
	openssl_pkey_export( $res, $pem );
	$d   = openssl_pkey_get_details( $res );
	$pub = tp_b64url( "\x04" . str_pad( $d['ec']['x'], 32, "\0", STR_PAD_LEFT ) . str_pad( $d['ec']['y'], 32, "\0", STR_PAD_LEFT ) );
	update_option( 'tp_vapid', array( 'pem' => $pem, 'pub' => $pub ), false );
	return array( $pem, $pub );
}

function tp_push_available() {
	return (bool) tp_vapid_keys();
}

/**
 * DER ECDSA signature → raw 64-byte R||S (JWS ES256).
 */
function tp_der_to_raw( $der ) {
	$pos  = 2 + ( ord( $der[1] ) & 0x80 ? ( ord( $der[1] ) & 0x7f ) : 0 );
	$out  = '';
	for ( $i = 0; $i < 2; $i++ ) {
		$len  = ord( $der[ $pos + 1 ] );
		$int  = substr( $der, $pos + 2, $len );
		$out .= str_pad( ltrim( $int, "\0" ), 32, "\0", STR_PAD_LEFT );
		$pos += 2 + $len;
	}
	return $out;
}

/**
 * Send an empty push to one subscription. Returns HTTP status or 0.
 */
function tp_push_send( $endpoint ) {
	$keys = tp_vapid_keys();
	if ( ! $keys || ! wp_http_validate_url( $endpoint ) ) {
		return 0;
	}
	$parts = wp_parse_url( $endpoint );
	$aud   = $parts['scheme'] . '://' . $parts['host'];
	$head  = tp_b64url( wp_json_encode( array( 'typ' => 'JWT', 'alg' => 'ES256' ) ) );
	$body  = tp_b64url( wp_json_encode( array( 'aud' => $aud, 'exp' => time() + 12 * HOUR_IN_SECONDS, 'sub' => 'mailto:' . ( tp_opt( 'email' ) ?: get_option( 'admin_email' ) ) ) ) );
	if ( ! openssl_sign( $head . '.' . $body, $sig, $keys[0], OPENSSL_ALGO_SHA256 ) ) {
		return 0;
	}
	$jwt = $head . '.' . $body . '.' . tp_b64url( tp_der_to_raw( $sig ) );
	$r   = wp_remote_post(
		$endpoint,
		array(
			'timeout' => 10,
			'headers' => array(
				'Authorization'  => 'vapid t=' . $jwt . ', k=' . $keys[1],
				'TTL'            => '3600',
				'Urgency'        => 'high',
				'Content-Length' => '0',
			),
			'body'    => '',
		)
	);
	return is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
}

/**
 * Notify everyone who subscribed to a booking.
 */
function tp_push_booking( $post_id, $title, $body ) {
	$subs = (array) get_post_meta( $post_id, '_tp_push_subs', true );
	if ( ! $subs ) {
		return 0;
	}
	update_post_meta(
		$post_id,
		'_tp_push_msg',
		array(
			'title' => $title,
			'body'  => $body,
			'url'   => tp_booking_manage_url( $post_id ),
			'time'  => time(),
		)
	);
	$sent = 0;
	foreach ( $subs as $key => $endpoint ) {
		$code = tp_push_send( $endpoint );
		if ( in_array( $code, array( 404, 410 ), true ) ) {
			unset( $subs[ $key ] );
			delete_post_meta( $post_id, '_tp_push_key', $key );
		} elseif ( $code >= 200 && $code < 300 ) {
			++$sent;
		}
	}
	update_post_meta( $post_id, '_tp_push_subs', $subs );
	return $sent;
}

// Status changes → notification.
add_action(
	'tp_booking_status_changed',
	static function ( $post_id, $old, $status ) {
		$b    = tp_get_booking( $post_id );
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$msgs = array(
			/* translators: %s: pick-up time */
			'confirmed' => array( __( 'Booking confirmed', 'taxi-peninsula' ), sprintf( __( 'Your wheelchair taxi is booked for %s.', 'taxi-peninsula' ), tp_format_pickup( $b, 'D j M, g:i a' ) ) ),
			'on_way'    => array( __( 'Your driver is on the way', 'taxi-peninsula' ), __( 'Please be ready at the pick-up point.', 'taxi-peninsula' ) ),
			'arrived'   => array( __( 'Your driver has arrived', 'taxi-peninsula' ), __( 'Your driver is waiting at the pick-up point.', 'taxi-peninsula' ) ),
			'cancelled' => array( __( 'Booking cancelled', 'taxi-peninsula' ), $b['reference'] ),
		);
		if ( isset( $msgs[ $status ] ) ) {
			tp_push_booking( $post_id, $msgs[ $status ][0] . ' — ' . $site, $msgs[ $status ][1] );
		}
	},
	40,
	3
);

/* -------------------------------------------------------------------------
 * Service worker and REST endpoints.
 * ---------------------------------------------------------------------- */

function tp_sw_url() {
	return add_query_arg( 'tp_sw', 1, home_url( '/' ) );
}

add_action(
	'template_redirect',
	static function () {
		if ( empty( $_GET['tp_sw'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: /' );
		header( 'Cache-Control: no-cache' );
		$icon = function_exists( 'tp_brand_url' ) ? tp_brand_url( 'icon', has_site_icon() ? 'svg' : '192' ) : '';
		if ( has_site_icon() ) {
			$icon = get_site_icon_url( 192 );
		}
		$cfg = array(
			'msg'  => esc_url_raw( rest_url( 'taxi-peninsula/v1/push-message' ) ),
			'icon' => $icon,
			'home' => home_url( '/' ),
		);
		echo 'var TP = ' . wp_json_encode( $cfg ) . ";\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', function () {});
self.addEventListener('push', function (e) {
	e.waitUntil(self.registration.pushManager.getSubscription().then(function (sub) {
		var body = new FormData();
		body.append('endpoint', sub ? sub.endpoint : '');
		return fetch(TP.msg, { method: 'POST', body: body }).then(function (r) { return r.json(); });
	}).then(function (m) {
		return self.registration.showNotification(m.title || 'Taxi update', { body: m.body || '', icon: TP.icon, badge: TP.icon, data: { url: m.url || TP.home }, tag: 'tp-trip', renotify: true });
	}).catch(function () {
		return self.registration.showNotification('Taxi update', { body: 'Open for the latest on your trip.', icon: TP.icon, data: { url: TP.home } });
	}));
});
self.addEventListener('notificationclick', function (e) {
	e.notification.close();
	e.waitUntil(self.clients.openWindow(e.notification.data && e.notification.data.url ? e.notification.data.url : TP.home));
});
		<?php
		exit;
	},
	0
);

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'taxi-peninsula/v1',
			'/push-subscribe',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => static function ( WP_REST_Request $req ) {
					if ( ! tp_rate_limit( 'push', 20, HOUR_IN_SECONDS ) ) {
						return new WP_Error( 'tp_rl', 'Too many requests', array( 'status' => 429 ) );
					}
					$post_id  = tp_find_booking( (string) $req->get_param( 'ref' ) );
					$token    = sanitize_key( (string) $req->get_param( 't' ) );
					$endpoint = esc_url_raw( (string) $req->get_param( 'endpoint' ) );
					if ( ! $post_id || ! hash_equals( tp_booking_token( $post_id ), $token ) || ! $endpoint || 0 !== strpos( $endpoint, 'https://' ) ) {
						return new WP_Error( 'tp_push', 'Not allowed', array( 'status' => 403 ) );
					}
					$subs = (array) get_post_meta( $post_id, '_tp_push_subs', true );
					$key  = md5( $endpoint );
					if ( ! isset( $subs[ $key ] ) ) {
						$subs[ $key ] = $endpoint;
						$subs         = array_slice( array_filter( $subs ), -5, null, true );
						update_post_meta( $post_id, '_tp_push_subs', $subs );
						add_post_meta( $post_id, '_tp_push_key', $key );
						tp_add_log( $post_id, array( 'note' => __( 'Passenger turned on phone notifications', 'taxi-peninsula' ) ) );
					}
					return array( 'ok' => true );
				},
			)
		);
		register_rest_route(
			'taxi-peninsula/v1',
			'/push-message',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => static function ( WP_REST_Request $req ) {
					$key = md5( (string) $req->get_param( 'endpoint' ) );
					$ids = get_posts( array( 'post_type' => 'tp_booking', 'post_status' => 'publish', 'numberposts' => 5, 'fields' => 'ids', 'meta_key' => '_tp_push_key', 'meta_value' => $key ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
					$best = null;
					foreach ( $ids as $id ) {
						$m = get_post_meta( $id, '_tp_push_msg', true );
						if ( is_array( $m ) && time() - (int) $m['time'] < HOUR_IN_SECONDS && ( ! $best || $m['time'] > $best['time'] ) ) {
							$best = $m;
						}
					}
					return $best ? array( 'title' => $best['title'], 'body' => $best['body'], 'url' => $best['url'] ) : array( 'title' => get_bloginfo( 'name' ), 'body' => __( 'Open for the latest on your trip.', 'taxi-peninsula' ), 'url' => home_url( '/' ) );
				},
			)
		);
	}
);

/* -------------------------------------------------------------------------
 * "Notify me" box.
 * ---------------------------------------------------------------------- */

/**
 * Print the opt-in box for a booking.
 */
function tp_push_box( $post_id ) {
	$keys = tp_vapid_keys();
	if ( ! $keys || ! $post_id ) {
		return;
	}
	$b = tp_get_booking( $post_id );
	if ( ! in_array( $b['status'], tp_active_statuses(), true ) ) {
		return;
	}
	?>
	<div class="push-box" data-done="<?php esc_attr_e( 'Notifications are on for this trip.', 'taxi-peninsula' ); ?>" data-denied="<?php esc_attr_e( 'Notifications are blocked in your browser settings. We will still text you.', 'taxi-peninsula' ); ?>" data-failed="<?php esc_attr_e( 'Sorry, that did not work on this device. We will still text you.', 'taxi-peninsula' ); ?>" data-push data-key="<?php echo esc_attr( $keys[1] ); ?>" data-sw="<?php echo esc_url( tp_sw_url() ); ?>" data-url="<?php echo esc_url( rest_url( 'taxi-peninsula/v1/push-subscribe' ) ); ?>" data-ref="<?php echo esc_attr( $b['reference'] ); ?>" data-t="<?php echo esc_attr( tp_booking_token( $post_id ) ); ?>" hidden>
		<?php tp_the_icon( 'megaphone', 'push-box__icon' ); ?>
		<div class="push-box__text">
			<p class="push-box__title"><?php esc_html_e( 'Get a notification when your driver is on the way', 'taxi-peninsula' ); ?></p>
			<p class="push-box__ios" hidden><?php esc_html_e( 'On iPhone or iPad: tap Share, then "Add to Home Screen". Open the site from the new icon and tap the button again.', 'taxi-peninsula' ); ?></p>
			<p class="push-box__status" role="status" aria-live="polite"></p>
		</div>
		<div class="push-box__actions">
			<button type="button" class="btn btn--primary" data-push-on><?php esc_html_e( 'Notify me', 'taxi-peninsula' ); ?></button>
			<button type="button" class="btn btn--ghost" data-install hidden><?php esc_html_e( 'Add to home screen', 'taxi-peninsula' ); ?></button>
		</div>
	</div>
	<?php
}

add_action( 'tp_booking_status_after', 'tp_push_box' );
