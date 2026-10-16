<?php
/**
 * How to pay (BPAY, PayID, bank transfer) and clear cancellation fees.
 *
 * - BPAY: biller code from Taxi settings, and a customer reference number
 *   (CRN) for every invoice and quote: a prefix (1 = trip, 2 = quote), the
 *   number, and a check digit. Shown on tax invoices, quotes, quote emails
 *   and [tp_pay_options].
 * - PayID and bank transfer details beside it.
 * - Cancellation and no-show fees (Australian Consumer Law: prices and fees
 *   must be clear before people book): shown on the booking form next to
 *   the terms box, in confirmation emails, on the booking page, on quotes
 *   and with [tp_cancellation_policy]. What the customer was shown is kept
 *   with each booking.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['bpay_enabled']    = array( 'payments', __( 'Accept BPAY', 'taxi-peninsula' ), 'checkbox', true, __( 'When off, BPAY is not shown anywhere — invoices, quotes, emails or the website.', 'taxi-peninsula' ) );
		$s['bpay_biller']     = array( 'payments', __( 'BPAY biller code', 'taxi-peninsula' ), 'text', '', __( 'From your bank.', 'taxi-peninsula' ) );
		$s['bpay_check']      = array( 'payments', __( 'BPAY reference check digit', 'taxi-peninsula' ), 'select', 'luhn', __( 'Ask your bank which check-digit method your biller code uses. Most use MOD10 version 1 (Luhn).', 'taxi-peninsula' ), array( 'luhn' => __( 'MOD10 version 1 (Luhn)', 'taxi-peninsula' ), 'none' => __( 'No check digit', 'taxi-peninsula' ) ) );
		$s['payid']           = array( 'payments', __( 'PayID', 'taxi-peninsula' ), 'text', '', __( 'Your PayID (email, mobile or ABN) for instant bank payments. Leave empty to hide.', 'taxi-peninsula' ) );
		$s['payid_name']      = array( 'payments', __( 'PayID account name', 'taxi-peninsula' ), 'text', '', __( 'The name the payer\'s bank shows, so they know it is you.', 'taxi-peninsula' ) );
		$s['bank_enabled']    = array( 'payments', __( 'Accept bank transfer', 'taxi-peninsula' ), 'checkbox', true, __( 'Shows your bank details on invoices, quotes and emails. Needs the BSB and account number below.', 'taxi-peninsula' ) );
		$s['bank_bsb']        = array( 'payments', __( 'Bank transfer BSB', 'taxi-peninsula' ), 'text', '', '' );
		$s['bank_account']    = array( 'payments', __( 'Bank transfer account number', 'taxi-peninsula' ), 'text', '', '' );
		$s['bank_name']       = array( 'payments', __( 'Bank transfer account name', 'taxi-peninsula' ), 'text', '', '' );
		$s['bank_institution'] = array( 'payments', __( 'Bank (optional)', 'taxi-peninsula' ), 'text', '', __( 'e.g. Commonwealth Bank', 'taxi-peninsula' ) );
		$s['pay_on_website']  = array( 'payments', __( 'Show payment details on the website', 'taxi-peninsula' ), 'checkbox', true, __( 'On the booking form (when "Account / invoice" is chosen), on Manage My Booking for account trips, and on the "How to Pay" page. Details are never shown unless the method is switched on above.', 'taxi-peninsula' ) );
		$s['cancel_free_hours'] = array( 'recurring', __( 'Free cancellation up to (hours before pick-up)', 'taxi-peninsula' ), 'number', 2, '' );
		$s['cancel_fee']      = array( 'recurring', __( 'Late cancellation fee ($)', 'taxi-peninsula' ), 'number', '', __( 'Leave empty if you do not charge one.', 'taxi-peninsula' ) );
		$s['noshow_fee']      = array( 'recurring', __( 'No-show fee ($)', 'taxi-peninsula' ), 'number', '', __( 'Charged when the driver arrives and the passenger is not there. Leave empty if you do not charge one.', 'taxi-peninsula' ) );
		$s['wait_free_mins']  = array( 'recurring', __( 'Free waiting time at pick-up (minutes)', 'taxi-peninsula' ), 'number', 5, '' );
		$s['cancel_note']     = array( 'recurring', __( 'Anything else about cancellations', 'taxi-peninsula' ), 'textarea', '', __( 'e.g. "Hospital discharge delays are never charged."', 'taxi-peninsula' ) );
		return $s;
	}
);

/* -------------------------------------------------------------------------
 * BPAY / PayID / bank.
 * ---------------------------------------------------------------------- */

/**
 * MOD10 version 1 (Luhn) check digit.
 */
function tp_luhn_digit( $digits ) {
	$sum = 0;
	$alt = true;
	for ( $i = strlen( $digits ) - 1; $i >= 0; $i-- ) {
		$n = (int) $digits[ $i ];
		if ( $alt ) {
			$n *= 2;
			if ( $n > 9 ) {
				$n -= 9;
			}
		}
		$sum += $n;
		$alt  = ! $alt;
	}
	return (string) ( ( 10 - $sum % 10 ) % 10 );
}

/**
 * BPAY customer reference number.
 *
 * @param string $kind 'trip' or 'quote'.
 * @param int    $id   Booking or quote ID.
 */
function tp_bpay_crn( $kind, $id ) {
	$base = ( 'quote' === $kind ? '2' : '1' ) . str_pad( (string) absint( $id ), 7, '0', STR_PAD_LEFT );
	return 'none' === tp_setting( 'bpay_check' ) ? $base : $base . tp_luhn_digit( $base );
}

function tp_bpay_on() {
	return tp_setting( 'bpay_enabled' ) && '' !== trim( (string) tp_setting( 'bpay_biller' ) );
}

function tp_bank_on() {
	return tp_setting( 'bank_enabled' ) && '' !== trim( (string) tp_setting( 'bank_bsb' ) ) && '' !== trim( (string) tp_setting( 'bank_account' ) );
}

function tp_payid_on() {
	return '' !== trim( (string) tp_setting( 'payid' ) );
}

function tp_pay_options_available() {
	return tp_bpay_on() || tp_payid_on() || tp_bank_on();
}

/**
 * Bank details as one line: "BSB 063-000 · Account 1234 5678 · Taxi Peninsula Pty Ltd · Commonwealth Bank".
 */
function tp_bank_line( $sep = ' · ' ) {
	$parts = array( 'BSB ' . tp_setting( 'bank_bsb' ), __( 'Account', 'taxi-peninsula' ) . ' ' . tp_setting( 'bank_account' ) );
	foreach ( array( 'bank_name', 'bank_institution' ) as $k ) {
		if ( tp_setting( $k ) ) {
			$parts[] = tp_setting( $k );
		}
	}
	return implode( $sep, $parts );
}

/**
 * Payment instructions as HTML (for invoices, quotes and pages).
 *
 * @param string $kind   'trip', 'quote' or '' (generic, no reference).
 * @param int    $id     Booking or quote ID.
 * @param string $ref    Human reference to quote with PayID / bank (e.g. TP-ABC123).
 */
function tp_pay_options_html( $kind = '', $id = 0, $ref = '' ) {
	if ( ! tp_pay_options_available() ) {
		return '';
	}
	$crn  = $kind && $id ? tp_bpay_crn( $kind, $id ) : '';
	$html = '<div class="pay-options">';
	if ( tp_bpay_on() ) {
		$html .= '<div class="bpay-box" role="group" aria-label="BPAY">'
			. '<p class="bpay-box__brand">BPAY<sup>®</sup></p>'
			. '<p class="bpay-box__line"><span>' . esc_html__( 'Biller Code', 'taxi-peninsula' ) . '</span> <strong>' . esc_html( tp_setting( 'bpay_biller' ) ) . '</strong></p>'
			. '<p class="bpay-box__line"><span>' . esc_html__( 'Ref', 'taxi-peninsula' ) . '</span> <strong>' . esc_html( $crn ?: __( 'on your invoice', 'taxi-peninsula' ) ) . '</strong></p>'
			. '<p class="bpay-box__small">' . esc_html__( 'Contact your bank or financial institution to make this payment from your cheque, savings, debit or transaction account. More info: www.bpay.com.au', 'taxi-peninsula' ) . '</p>'
			. '</div>';
	}
	$lines = array();
	if ( tp_payid_on() ) {
		$lines[] = '<li><strong>PayID</strong> ' . esc_html( tp_setting( 'payid' ) ) . ( tp_setting( 'payid_name' ) ? ' (' . esc_html( tp_setting( 'payid_name' ) ) . ')' : '' ) . '</li>';
	}
	if ( tp_bank_on() ) {
		$lines[] = '<li><strong>' . esc_html__( 'Bank transfer', 'taxi-peninsula' ) . '</strong> ' . esc_html( tp_bank_line() ) . '</li>';
	}
	if ( $lines ) {
		$html .= '<ul class="pay-options__list">' . implode( '', $lines ) . '</ul>';
		if ( $ref ) {
			/* translators: %s: reference */
			$html .= '<p class="pay-options__ref">' . esc_html( sprintf( __( 'Please use %s as the payment description.', 'taxi-peninsula' ), $ref ) ) . '</p>';
		}
	}
	return $html . '</div>';
}

/**
 * The same as plain text for emails.
 */
function tp_pay_options_text( $kind = '', $id = 0, $ref = '' ) {
	if ( ! tp_pay_options_available() ) {
		return '';
	}
	$out = __( 'How to pay', 'taxi-peninsula' ) . ":\n";
	if ( tp_bpay_on() ) {
		$out .= '- BPAY® ' . __( 'Biller Code', 'taxi-peninsula' ) . ' ' . tp_setting( 'bpay_biller' ) . ', ' . __( 'Ref', 'taxi-peninsula' ) . ' ' . ( $kind && $id ? tp_bpay_crn( $kind, $id ) : __( 'on your invoice', 'taxi-peninsula' ) ) . "\n";
	}
	if ( tp_payid_on() ) {
		$out .= '- PayID ' . tp_setting( 'payid' ) . ( tp_setting( 'payid_name' ) ? ' (' . tp_setting( 'payid_name' ) . ')' : '' ) . "\n";
	}
	if ( tp_bank_on() ) {
		$out .= '- ' . __( 'Bank transfer', 'taxi-peninsula' ) . ' ' . tp_bank_line( ', ' ) . "\n";
	}
	if ( $ref && ( tp_payid_on() || tp_bank_on() ) ) {
		/* translators: %s: reference */
		$out .= sprintf( __( 'Please use %s as the payment description.', 'taxi-peninsula' ), $ref ) . "\n";
	}
	return $out;
}

add_shortcode(
	'tp_pay_options',
	static function () {
		return tp_pay_on_website() ? tp_pay_options_html() : '';
	}
);

/* -------------------------------------------------------------------------
 * On the website.
 * ---------------------------------------------------------------------- */

function tp_pay_on_website() {
	return tp_setting( 'pay_on_website' ) && tp_pay_options_available();
}

// "How to Pay" starter page.
add_filter(
	'tp_starter_pages',
	static function ( $p ) {
		$p['pay'] = array(
			'title'   => __( 'How to Pay', 'taxi-peninsula' ),
			'content' => "<!-- wp:paragraph -->\n<p>" . __( 'Pay the driver by card or cash at the end of the trip, use your MPTP card, or — for account customers — pay your invoice using the details below. Please use the reference on your invoice so we can match your payment.', 'taxi-peninsula' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[tp_pay_options]\n<!-- /wp:shortcode -->",
		);
		return $p;
	}
);
add_filter(
	'tp_feature_shortcodes',
	static function ( $c ) {
		$c['pay'] = 'tp_pay_options';
		return $c;
	}
);

// Footer: link to the How to Pay page.
add_action(
	'tp_footer_contact_items',
	static function () {
		$url = tp_pay_on_website() ? tp_page_url_by_template( 'pay' ) : '';
		if ( $url ) {
			printf( '<li>%s<a href="%s">%s</a></li>', tp_icon( 'check' ), esc_url( $url ), esc_html__( 'How to pay', 'taxi-peninsula' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon.
		}
	}
);

// Booking form: when "Account / invoice" is chosen.
add_action(
	'tp_booking_form_account_after',
	static function () {
		if ( ! tp_pay_on_website() ) {
			return;
		}
		echo '<div class="pay-options--form"><p class="field__hint"><strong>' . esc_html__( 'Ways to pay your invoice', 'taxi-peninsula' ) . '</strong> — ' . esc_html__( 'your invoice will show your own reference number.', 'taxi-peninsula' ) . '</p>';
		echo tp_pay_options_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		echo '</div>';
	}
);

// Manage My Booking: account trips show how to pay, with their reference.
add_action(
	'tp_booking_status_after',
	static function ( $post_id ) {
		$b = tp_get_booking( $post_id );
		if ( ! tp_pay_on_website() || 'account' !== $b['payment'] || 'cancelled' === $b['status'] || 'paid' === $b['invoice'] ) {
			return;
		}
		echo '<section class="pay-options-section"><h2>' . esc_html__( 'How to pay your invoice', 'taxi-peninsula' ) . '</h2>';
		echo tp_pay_options_html( 'trip', (int) $b['id'], $b['reference'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		echo '</section>';
	},
	25
);

// Tax invoices with money owing: how to pay.
add_action(
	'tp_receipt_after_totals',
	static function ( $b, $owing ) {
		if ( $owing > 0 ) {
			echo '<h2>' . esc_html__( 'How to pay', 'taxi-peninsula' ) . '</h2>';
			echo tp_pay_options_html( 'trip', (int) $b['id'], $b['reference'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		}
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * Cancellation and no-show fees.
 * ---------------------------------------------------------------------- */

/**
 * Plain sentences describing the cancellation policy.
 *
 * @return string[]
 */
function tp_cancellation_lines() {
	$hours  = (int) tp_setting( 'cancel_free_hours' );
	$cancel = (float) tp_setting( 'cancel_fee' );
	$noshow = (float) tp_setting( 'noshow_fee' );
	$wait   = (int) tp_setting( 'wait_free_mins' );
	$lines  = array();
	if ( $hours > 0 ) {
		/* translators: %d: hours */
		$lines[] = sprintf( _n( 'Free cancellation up to %d hour before pick-up.', 'Free cancellation up to %d hours before pick-up.', $hours, 'taxi-peninsula' ), $hours );
	} else {
		$lines[] = __( 'Free cancellation any time before the driver leaves to collect you.', 'taxi-peninsula' );
	}
	if ( $cancel > 0 ) {
		/* translators: 1: fee 2: hours */
		$lines[] = $hours > 0 ? sprintf( __( 'Cancelling later than that: %1$s fee.', 'taxi-peninsula' ), tp_money( $cancel ) ) : sprintf( __( 'Cancelling after the driver has left: %1$s fee.', 'taxi-peninsula' ), tp_money( $cancel ) );
	}
	if ( $noshow > 0 ) {
		/* translators: %s: fee */
		$lines[] = sprintf( __( 'No-show (the driver arrives and the passenger is not there and cannot be contacted): %s fee.', 'taxi-peninsula' ), tp_money( $noshow ) );
	}
	if ( $wait > 0 ) {
		/* translators: %d: minutes */
		$lines[] = sprintf( _n( 'The driver waits %d minute free of charge; after that, waiting time is charged at the normal rate.', 'The driver waits %d minutes free of charge; after that, waiting time is charged at the normal rate.', $wait, 'taxi-peninsula' ), $wait );
	}
	if ( trim( (string) tp_setting( 'cancel_note' ) ) ) {
		$lines[] = trim( (string) tp_setting( 'cancel_note' ) );
	}
	return $lines;
}

function tp_cancellation_html( $title = true ) {
	$html = '<div class="cancel-policy">';
	if ( $title ) {
		$html .= '<p class="cancel-policy__title">' . esc_html__( 'Cancellations and fees', 'taxi-peninsula' ) . '</p>';
	}
	$html .= '<ul>';
	foreach ( tp_cancellation_lines() as $l ) {
		$html .= '<li>' . esc_html( $l ) . '</li>';
	}
	return $html . '</ul></div>';
}

function tp_cancellation_text() {
	return __( 'Cancellations and fees', 'taxi-peninsula' ) . ":\n- " . implode( "\n- ", tp_cancellation_lines() ) . "\n";
}

add_shortcode( 'tp_cancellation_policy', static fn() => tp_cancellation_html( false ) );

// Booking form: right above the "I accept the terms" box.
add_action(
	'tp_booking_form_before_terms',
	static function () {
		echo tp_cancellation_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
	}
);

// Keep what the customer was shown with the booking.
add_action(
	'tp_booking_created',
	static function ( $post_id, $ids ) {
		$text = implode( ' ', tp_cancellation_lines() );
		foreach ( (array) $ids as $id ) {
			update_post_meta( $id, '_tp_fees_shown', $text );
		}
	},
	15,
	2
);

// Booking page.
add_action(
	'tp_booking_status_after',
	static function ( $post_id ) {
		$b = tp_get_booking( $post_id );
		if ( in_array( $b['status'], tp_active_statuses(), true ) ) {
			echo tp_cancellation_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		}
	},
	20
);

// Customer emails: confirmation and quotes.
add_filter(
	'tp_customer_email_footer',
	static function ( $text ) {
		return $text . "\n" . tp_cancellation_text();
	}
);
