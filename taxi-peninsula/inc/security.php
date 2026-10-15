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
	if ( function_exists( 'tp_lang' ) && 'en' !== tp_lang() ) {
		echo '<input type="hidden" name="tp_lang" value="' . esc_attr( tp_lang() ) . '">';
	}
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

/**
 * Shared checks for the theme's public forms: nonce, honeypot, rate limit and
 * captcha. Redirects back with an error (or a silent "success" for bots).
 *
 * @param string $action  Nonce action (field `_tp_nonce`).
 * @param string $return  Page to return to.
 * @param array  $raw     Unslashed input, kept for redisplay.
 * @param string $anchor  Anchor on the return page.
 * @param string $ok_arg  Query arg that shows the success message.
 * @param int    $limit   Submissions allowed per hour.
 */
function tp_form_guard( $action, $return, array $raw, $anchor, $ok_arg = 'sent', $limit = 5 ) {
	if ( ! isset( $_POST['_tp_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_nonce'] ) ), $action ) ) {
		tp_booking_fail( $return, array( 'form' => __( 'Your session expired. Please try again.', 'taxi-peninsula' ) ), $raw, $anchor );
	}
	if ( tp_is_honeypot_hit() ) {
		wp_safe_redirect( add_query_arg( $ok_arg, 1, $return ) . $anchor );
		exit;
	}
	if ( ! tp_rate_limit( $action, $limit, HOUR_IN_SECONDS, false ) ) {
		/* translators: %s: phone */
		tp_booking_fail( $return, array( 'form' => sprintf( __( 'Too many messages from your connection. Please call %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) ), $raw, $anchor );
	}
	if ( ! tp_verify_captcha() ) {
		tp_booking_fail( $return, array( 'form' => tp_captcha_error_message() ), $raw, $anchor );
	}
}

/**
 * Form state from a failed submission (?tp_form=…): [errors, old].
 */
function tp_form_state() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$stored = isset( $_GET['tp_form'] ) ? get_transient( 'tp_form_' . sanitize_key( wp_unslash( $_GET['tp_form'] ) ) ) : false;
	return is_array( $stored ) ? array( (array) $stored['errors'], (array) $stored['old'] ) : array( array(), array() );
}

/**
 * Current page URL for a form's return address.
 */
function tp_form_return_url() {
	return ( is_singular() && ! is_front_page() ) ? get_permalink() : home_url( '/' );
}

/**
 * Print one labelled form field (text, email, tel, date, time, number, textarea, select, checkbox).
 *
 * @param string $prefix  ID prefix.
 * @param string $name    Field name.
 * @param string $label   Label.
 * @param array  $o       type, required, value, errors, choices, hint, wide, attrs.
 */
function tp_form_field( $prefix, $name, $label, array $o = array() ) {
	$type   = $o['type'] ?? 'text';
	$id     = $prefix . '-' . $name;
	$value  = (string) ( $o['value'] ?? '' );
	$req    = ! empty( $o['required'] );
	$bad    = ! empty( $o['errors'][ $name ] );
	$attrs  = ( $req ? ' required' : '' ) . ( $bad ? ' aria-invalid="true" aria-describedby="err-' . esc_attr( $name ) . '"' : '' ) . ( isset( $o['attrs'] ) ? ' ' . $o['attrs'] : '' );
	$star   = $req ? ' <span class="req" aria-hidden="true">*</span>' : '';
	echo '<div class="field' . ( ! empty( $o['wide'] ) || 'textarea' === $type ? ' field--wide' : '' ) . ( 'checkbox' === $type ? ' field--check' : '' ) . '">';
	if ( 'checkbox' === $type ) {
		printf( '<label><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s%4$s> %5$s</label>', esc_attr( $id ), esc_attr( $name ), checked( '' !== $value && '0' !== $value, true, false ), $attrs, wp_kses_post( $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs built from escaped parts.
	} else {
		printf( '<label for="%1$s">%2$s%3$s</label>', esc_attr( $id ), esc_html( $label ), $star ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( 'textarea' === $type ) {
			printf( '<textarea id="%1$s" name="%2$s" rows="4"%3$s>%4$s</textarea>', esc_attr( $id ), esc_attr( $name ), $attrs, esc_textarea( $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'select' === $type ) {
			printf( '<select id="%1$s" name="%2$s"%3$s>', esc_attr( $id ), esc_attr( $name ), $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			foreach ( (array) ( $o['choices'] ?? array() ) as $k => $l ) {
				printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $k ), selected( $value, (string) $k, false ), esc_html( $l ) );
			}
			echo '</select>';
		} else {
			printf( '<input id="%1$s" name="%2$s" type="%3$s" value="%4$s"%5$s>', esc_attr( $id ), esc_attr( $name ), esc_attr( $type ), esc_attr( $value ), $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
	if ( ! empty( $o['hint'] ) ) {
		printf( '<p class="field__hint">%s</p>', esc_html( $o['hint'] ) );
	}
	if ( $bad && is_string( $o['errors'][ $name ] ) ) {
		printf( '<p class="field__error" id="err-%1$s">%2$s</p>', esc_attr( $name ), esc_html( $o['errors'][ $name ] ) );
	}
	echo '</div>';
}

/**
 * Success panel shared by the theme's forms.
 */
function tp_form_success( $title, $text ) {
	printf(
		'<div class="booking-success" role="status" tabindex="-1" data-focus><span class="booking-success__icon">%1$s</span><h2>%2$s</h2><p>%3$s</p></div>',
		tp_icon( 'check' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		esc_html( $title ),
		esc_html( $text )
	);
}
