<?php
/**
 * Spam protection shared by the public forms: honeypot, rate limiting and
 * optional Cloudflare Turnstile / Google reCAPTCHA.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_captcha_enabled() {
	return in_array( tp_setting( 'captcha_provider' ), array( 'turnstile', 'recaptcha' ), true )
		&& tp_setting( 'captcha_site_key' ) && tp_setting( 'captcha_secret_key' );
}

/**
 * Output honeypot + captcha widget inside a form.
 */
function tp_spam_fields() {
	echo '<div class="hp" aria-hidden="true"><label for="' . esc_attr( wp_unique_id( 'tp-hp-' ) ) . '">Website</label><input type="text" name="tp_website" tabindex="-1" autocomplete="off"></div>';

	if ( ! tp_captcha_enabled() ) {
		return;
	}
	$provider = tp_setting( 'captcha_provider' );
	if ( 'turnstile' === $provider ) {
		wp_enqueue_script( 'tp-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, array( 'strategy' => 'defer' ) );
		printf( '<div class="captcha cf-turnstile" data-sitekey="%s" data-theme="light"></div>', esc_attr( tp_setting( 'captcha_site_key' ) ) );
	} else {
		wp_enqueue_script( 'tp-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, array( 'strategy' => 'defer' ) );
		printf( '<div class="captcha g-recaptcha" data-sitekey="%s"></div>', esc_attr( tp_setting( 'captcha_site_key' ) ) );
	}
}

/**
 * True when the submission looks like a bot (honeypot filled).
 */
function tp_is_honeypot_hit() {
	return ! empty( $_POST['tp_website'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- caller verifies nonce.
}

/**
 * Verify the captcha response. Returns true when disabled.
 */
function tp_verify_captcha() {
	if ( ! tp_captcha_enabled() ) {
		return true;
	}
	$provider = tp_setting( 'captcha_provider' );
	$field    = 'turnstile' === $provider ? 'cf-turnstile-response' : 'g-recaptcha-response';
	$token    = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- caller verifies nonce.
	if ( '' === $token ) {
		return false;
	}
	$url      = 'turnstile' === $provider
		? 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
		: 'https://www.google.com/recaptcha/api/siteverify';
	$response = wp_remote_post(
		$url,
		array(
			'timeout' => 10,
			'body'    => array(
				'secret'   => tp_setting( 'captcha_secret_key' ),
				'response' => $token,
				'remoteip' => tp_client_ip(),
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		// Fail open on network errors so a provider outage doesn't block real passengers.
		return true;
	}
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	return ! empty( $body['success'] );
}

/**
 * Simple per-IP rate limit. Returns false when the limit is exceeded.
 *
 * @param string $bucket  Name of the action being limited.
 * @param int    $limit   Allowed attempts per window.
 * @param int    $window  Window in seconds.
 * @param bool   $consume Count this attempt.
 * @return bool
 */
function tp_rate_limit( $bucket, $limit, $window, $consume = true ) {
	$key   = 'tp_rl_' . $bucket . '_' . md5( tp_client_ip() );
	$count = (int) get_transient( $key );
	if ( $count >= $limit ) {
		return false;
	}
	if ( $consume ) {
		set_transient( $key, $count + 1, $window );
	}
	return true;
}

function tp_captcha_error_message() {
	return __( 'Please complete the "I am human" check and try again.', 'taxi-peninsula' );
}
