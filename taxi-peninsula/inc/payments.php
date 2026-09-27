<?php
/**
 * Online deposits with Stripe Checkout.
 *
 * Flow: customer chooses "Pay a deposit online" → booking is saved → they are
 * sent to Stripe's hosted checkout → Stripe calls our webhook → booking is
 * marked as paid. No card details ever touch this site.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_payments_available() {
	return (bool) tp_setting( 'payments_enabled' ) && tp_setting( 'stripe_secret_key' ) && (float) tp_setting( 'deposit_amount' ) > 0;
}

/**
 * Create a Stripe Checkout Session for a booking deposit. Returns the URL or WP_Error.
 */
function tp_stripe_checkout_url( $post_id ) {
	if ( ! tp_payments_available() ) {
		return new WP_Error( 'tp_pay_disabled', __( 'Online payment is not available.', 'taxi-peninsula' ) );
	}
	$b      = tp_get_booking( $post_id );
	$amount = (int) round( (float) tp_setting( 'deposit_amount' ) * 100 );
	$manage = tp_booking_manage_url( $post_id );

	$body = array(
		'mode'                                         => 'payment',
		'line_items[0][quantity]'                      => 1,
		'line_items[0][price_data][currency]'          => 'aud',
		'line_items[0][price_data][unit_amount]'       => $amount,
		/* translators: 1: site name, 2: reference */
		'line_items[0][price_data][product_data][name]' => sprintf( __( '%1$s booking deposit — %2$s', 'taxi-peninsula' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $b['reference'] ),
		'client_reference_id'                          => (string) $post_id,
		'metadata[booking_id]'                         => (string) $post_id,
		'metadata[reference]'                          => $b['reference'],
		'success_url'                                  => add_query_arg( 'paid', '1', $manage ),
		'cancel_url'                                   => add_query_arg( 'paid', '0', $manage ),
	);
	if ( is_email( $b['email'] ) ) {
		$body['customer_email'] = $b['email'];
	}

	$response = wp_remote_post(
		'https://api.stripe.com/v1/checkout/sessions',
		array(
			'timeout' => 20,
			'headers' => array( 'Authorization' => 'Bearer ' . tp_setting( 'stripe_secret_key' ) ),
			'body'    => $body,
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $data['url'] ) || empty( $data['id'] ) ) {
		$msg = $data['error']['message'] ?? __( 'Unknown Stripe error.', 'taxi-peninsula' );
		tp_add_log( $post_id, array( 'note' => 'Stripe: ' . $msg ) );
		return new WP_Error( 'tp_pay_failed', $msg );
	}

	update_post_meta( $post_id, '_tp_stripe_session', sanitize_text_field( $data['id'] ) );
	return $data['url'];
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'taxi-peninsula/v1',
			'/stripe',
			array(
				'methods'             => 'POST',
				'callback'            => 'tp_stripe_webhook',
				'permission_callback' => '__return_true', // Authenticated by Stripe's signature below.
			)
		);
	}
);

/**
 * Verify a Stripe-Signature header (v1 scheme, 5-minute tolerance).
 */
function tp_stripe_signature_valid( $payload, $header, $secret ) {
	if ( ! $header || ! $secret ) {
		return false;
	}
	$timestamp  = 0;
	$signatures = array();
	foreach ( explode( ',', $header ) as $part ) {
		$kv = explode( '=', trim( $part ), 2 );
		if ( 2 !== count( $kv ) ) {
			continue;
		}
		if ( 't' === $kv[0] ) {
			$timestamp = (int) $kv[1];
		} elseif ( 'v1' === $kv[0] ) {
			$signatures[] = $kv[1];
		}
	}
	if ( ! $timestamp || abs( time() - $timestamp ) > 300 ) {
		return false;
	}
	$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
	foreach ( $signatures as $sig ) {
		if ( hash_equals( $expected, $sig ) ) {
			return true;
		}
	}
	return false;
}

function tp_stripe_webhook( WP_REST_Request $request ) {
	$payload = $request->get_body();
	if ( ! tp_stripe_signature_valid( $payload, $request->get_header( 'stripe_signature' ), tp_setting( 'stripe_webhook_secret' ) ) ) {
		return new WP_REST_Response( array( 'error' => 'invalid signature' ), 400 );
	}

	$event = json_decode( $payload, true );
	if ( ( $event['type'] ?? '' ) !== 'checkout.session.completed' ) {
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	$session = $event['data']['object'] ?? array();
	$post_id = (int) ( $session['metadata']['booking_id'] ?? 0 );
	if ( ! $post_id || 'tp_booking' !== get_post_type( $post_id ) ) {
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}
	if ( get_post_meta( $post_id, '_tp_stripe_session', true ) !== ( $session['id'] ?? '' ) ) {
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}
	if ( 'paid' !== ( $session['payment_status'] ?? '' ) ) {
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	$amount = ( (int) ( $session['amount_total'] ?? 0 ) ) / 100;
	if ( (float) get_post_meta( $post_id, '_tp_paid', true ) <= 0 ) {
		update_post_meta( $post_id, '_tp_paid', $amount );
		/* translators: %s: amount */
		tp_add_log( $post_id, array( 'note' => sprintf( __( 'Deposit of %s paid online (Stripe).', 'taxi-peninsula' ), tp_money( $amount ) ) ) );

		$b = tp_get_booking( $post_id );
		wp_mail(
			tp_opt( 'notify_email' ) ?: get_option( 'admin_email' ),
			/* translators: 1: reference, 2: amount */
			sprintf( __( 'Deposit paid for %1$s (%2$s)', 'taxi-peninsula' ), $b['reference'], tp_money( $amount ) ),
			tp_booking_summary( $b ) . "\n" . admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
			tp_mail_headers()
		);
	}

	return new WP_REST_Response( array( 'received' => true ), 200 );
}
