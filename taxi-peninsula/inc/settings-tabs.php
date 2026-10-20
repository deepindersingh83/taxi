<?php
/**
 * Taxi settings, split into tabs. Each tab saves only its own fields and has
 * a "Test & troubleshoot" box: plain-English checks of what is set up, a
 * live test for each connection, and recent results (e.g. the SMS log).
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tabs: key => [label, section ids].
 */
function tp_settings_tabs() {
	$tabs = array(
		'overview'  => array( __( 'Overview', 'taxi-peninsula' ), array() ),
		'sms'       => array( __( 'SMS', 'taxi-peninsula' ), array( 'sms' ) ),
		'email'     => array( __( 'Email', 'taxi-peninsula' ), array( 'email' ) ),
		'payments'  => array( __( 'Payments & invoices', 'taxi-peninsula' ), array( 'payments' ) ),
		'bookings'  => array( __( 'Bookings & fees', 'taxi-peninsula' ), array( 'recurring', 'live', 'flights', 'alerts' ) ),
		'maps'      => array( __( 'Maps & addresses', 'taxi-peninsula' ), array( 'maps' ) ),
		'reviews'   => array( __( 'Google reviews', 'taxi-peninsula' ), array( 'reviews', 'gbp' ) ),
		'security'  => array( __( 'Spam & security', 'taxi-peninsula' ), array( 'captcha', 'security' ) ),
		'privacy'   => array( __( 'Privacy', 'taxi-peninsula' ), array( 'privacy' ) ),
		'marketing' => array( __( 'Marketing & SEO', 'taxi-peninsula' ), array( 'analytics', 'tracking', 'marketing', 'local', 'search' ) ),
	);
	$tabs = apply_filters( 'tp_settings_tabs', $tabs );
	// Sections added later by other modules go in "More".
	$used  = array_merge( ...array_values( wp_list_pluck( $tabs, 1 ) ) );
	$extra = array_diff( array_keys( tp_settings_sections() ), $used );
	if ( $extra ) {
		$tabs['more'] = array( __( 'More', 'taxi-peninsula' ), array_values( $extra ) );
	}
	return $tabs;
}

/**
 * Tab that holds a section (or a tab key itself).
 */
function tp_settings_tab_for( $section ) {
	$tabs = tp_settings_tabs();
	if ( isset( $tabs[ $section ] ) ) {
		return $section;
	}
	foreach ( $tabs as $key => $t ) {
		if ( in_array( $section, $t[1], true ) ) {
			return $key;
		}
	}
	return 'overview';
}

/**
 * Link to the tab holding a section, e.g. tp_settings_url( 'privacy' ).
 */
function tp_settings_url( $section = 'overview', $anchor = '' ) {
	return admin_url( 'admin.php?page=tp-settings&tab=' . tp_settings_tab_for( $section ) ) . ( $anchor ? '#' . $anchor : '' );
}

function tp_settings_current_tab() {
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return isset( tp_settings_tabs()[ $tab ] ) ? $tab : 'overview';
}

/* -------------------------------------------------------------------------
 * Checks: [status ok|warn|fail|off, label, text] per tab. Fast — no network.
 * ---------------------------------------------------------------------- */

function tp_settings_checks( $tab ) {
	$c = array();
	switch ( $tab ) {
		case 'sms':
			$p = tp_setting( 'sms_provider' );
			if ( 'none' === $p ) {
				$c[] = array( 'off', __( 'Provider', 'taxi-peninsula' ), __( 'SMS is off. Choose ClickSend or Twilio to text customers and drivers.', 'taxi-peninsula' ) );
				break;
			}
			$c[] = tp_sms_enabled()
				/* translators: %s: provider */
				? array( 'ok', __( 'Provider', 'taxi-peninsula' ), sprintf( __( '%s details are filled in. Use "Check login" below to make sure they are right.', 'taxi-peninsula' ), 'clicksend' === $p ? 'ClickSend' : 'Twilio' ) )
				: array( 'fail', __( 'Provider', 'taxi-peninsula' ), 'clicksend' === $p ? __( 'Enter the ClickSend username and API key (ClickSend dashboard → Developers → API Credentials).', 'taxi-peninsula' ) : __( 'Enter the Twilio Account SID, Auth Token and a "from" number (Twilio Console → Account info).', 'taxi-peninsula' ) );
			$from = trim( (string) tp_setting( 'sms_from' ) );
			if ( '' === $from ) {
				$c[] = array( 'clicksend' === $p ? 'warn' : 'fail', __( 'Sender', 'taxi-peninsula' ), __( 'No sender set. ClickSend will use a shared number, so replies may not reach you; Twilio needs one.', 'taxi-peninsula' ) );
			} elseif ( preg_match( '/^\+?\d{8,15}$/', preg_replace( '/\s/', '', $from ) ) ) {
				/* translators: %s: number */
				$c[] = array( 'ok', __( 'Sender', 'taxi-peninsula' ), sprintf( __( 'Texts come from %s, so customers can reply.', 'taxi-peninsula' ), $from ) );
			} elseif ( ! preg_match( '/^[A-Za-z0-9 ]{1,11}$/', $from ) || ! preg_match( '/[A-Za-z]/', $from ) ) {
				$c[] = array( 'fail', __( 'Sender', 'taxi-peninsula' ), __( 'A sender name must be 1–11 letters or numbers (including at least one letter), with no symbols, e.g. TaxiPen. Otherwise messages are refused.', 'taxi-peninsula' ) );
			} else {
				/* translators: %s: sender */
				$c[] = array( 'warn', __( 'Sender', 'taxi-peninsula' ), sprintf( __( 'Texts come from "%s". Customers cannot reply to a name. Sender names may also need registering with your provider (Australian SMS Sender ID rules) or messages can be blocked or marked "unverified".', 'taxi-peninsula' ), $from ) );
			}
			$office = trim( (string) tp_setting( 'sms_office_number' ) );
			if ( '' !== $office ) {
				$c[] = tp_e164( $office )
					/* translators: %s: number */
					? array( 'ok', __( 'Office alerts', 'taxi-peninsula' ), sprintf( __( 'New bookings are texted to %s.', 'taxi-peninsula' ), tp_e164( $office ) ) )
					: array( 'fail', __( 'Office alerts', 'taxi-peninsula' ), __( 'The office mobile is not a valid Australian mobile (e.g. 0412 345 678).', 'taxi-peninsula' ) );
			}
			$on = array_filter(
				array(
					tp_setting( 'sms_customer_received' ) ? __( 'booking received', 'taxi-peninsula' ) : '',
					tp_setting( 'sms_customer_status' ) ? __( 'confirmed / driver / cancelled', 'taxi-peninsula' ) : '',
					tp_setting( 'sms_customer_trip' ) ? __( 'on my way / arrived', 'taxi-peninsula' ) : '',
					tp_setting( 'sms_driver_assigned' ) ? __( 'new job (to drivers)', 'taxi-peninsula' ) : '',
				)
			);
			/* translators: %s: list */
			$c[] = array( $on ? 'ok' : 'warn', __( 'Messages switched on', 'taxi-peninsula' ), $on ? sprintf( __( 'Texts are sent for: %s.', 'taxi-peninsula' ), implode( ', ', $on ) ) : __( 'Every message type is switched off.', 'taxi-peninsula' ) );
			$c   = array_merge( $c, tp_log_checks( 'tp_sms_log', __( 'Recent texts', 'taxi-peninsula' ) ) );
			break;

		case 'email':
			$c = function_exists( 'tp_mail_checks' ) ? tp_mail_checks() : array();
			break;

		case 'payments':
			if ( ! tp_setting( 'payments_enabled' ) ) {
				$c[] = array( 'off', __( 'Online deposits', 'taxi-peninsula' ), __( 'Off — customers pay the driver or by invoice.', 'taxi-peninsula' ) );
			} else {
				$key = (string) tp_setting( 'stripe_secret_key' );
				$c[] = '' === $key
					? array( 'fail', __( 'Stripe key', 'taxi-peninsula' ), __( 'Online deposits are on but there is no Stripe secret key, so the option is hidden.', 'taxi-peninsula' ) )
					: ( 0 === strpos( $key, 'sk_test_' )
						? array( 'warn', __( 'Stripe key', 'taxi-peninsula' ), __( 'This is a TEST key — no real money is taken. Switch to the sk_live_ key before going live.', 'taxi-peninsula' ) )
						: array( 'ok', __( 'Stripe key', 'taxi-peninsula' ), __( 'A live key is saved. Use "Check Stripe" below to confirm it works.', 'taxi-peninsula' ) ) );
				$c[] = tp_setting( 'stripe_webhook_secret' )
					? array( 'ok', __( 'Stripe webhook', 'taxi-peninsula' ), __( 'Signing secret saved. If paid deposits are not marked as paid, check the webhook in Stripe → Developers → Webhooks shows recent "200" responses.', 'taxi-peninsula' ) )
					/* translators: %s: URL */
					: array( 'fail', __( 'Stripe webhook', 'taxi-peninsula' ), sprintf( __( 'No signing secret: paid deposits will not be marked as paid. In Stripe add a webhook to %s for "checkout.session.completed" and paste its signing secret.', 'taxi-peninsula' ), rest_url( 'taxi-peninsula/v1/stripe' ) ) );
			}
			$c[] = tp_setting( 'invoice_enabled' )
				? array( tp_setting( 'abn' ) ? 'ok' : 'warn', __( 'Account / invoice', 'taxi-peninsula' ), tp_setting( 'abn' ) ? __( 'On. Receipts show your ABN, so they are valid tax invoices.', 'taxi-peninsula' ) : __( 'On, but there is no ABN, so receipts are not valid tax invoices.', 'taxi-peninsula' ) )
				: array( 'off', __( 'Account / invoice', 'taxi-peninsula' ), __( 'Off.', 'taxi-peninsula' ) );
			if ( function_exists( 'tp_bpay_on' ) ) {
				$ways = array_filter( array( tp_bpay_on() ? 'BPAY' : '', tp_payid_on() ? 'PayID' : '', tp_bank_on() ? __( 'bank transfer', 'taxi-peninsula' ) : '' ) );
				if ( tp_setting( 'bpay_enabled' ) && ! tp_setting( 'bpay_biller' ) ) {
					$c[] = array( 'warn', 'BPAY', __( '"Accept BPAY" is ticked but there is no biller code, so BPAY is not shown.', 'taxi-peninsula' ) );
				}
				if ( tp_setting( 'bank_enabled' ) && ! tp_bank_on() ) {
					$c[] = array( 'warn', __( 'Bank transfer', 'taxi-peninsula' ), __( '"Accept bank transfer" is ticked but the BSB or account number is missing, so it is not shown.', 'taxi-peninsula' ) );
				}
				/* translators: %s: list */
				$c[] = array( $ways ? 'ok' : 'off', __( 'Ways to pay invoices', 'taxi-peninsula' ), $ways ? sprintf( __( 'Shown on invoices: %s.', 'taxi-peninsula' ), implode( ', ', $ways ) ) : __( 'None set up.', 'taxi-peninsula' ) );
			}
			break;

		case 'bookings':
			$terms = (int) tp_setting( 'terms_page_id' ) ?: (int) ( ( (array) get_option( 'tp_pages', array() ) )['terms'] ?? 0 );
			$c[]   = $terms && 'publish' === get_post_status( $terms )
				? array( 'ok', __( 'Terms page', 'taxi-peninsula' ), __( 'Published — customers tick "I accept" before booking.', 'taxi-peninsula' ) )
				: array( 'warn', __( 'Terms page', 'taxi-peninsula' ), __( 'No published terms page, so customers are not asked to accept terms. Finish and publish it (Pages → Terms & Cancellation Policy).', 'taxi-peninsula' ) );
			$fees = (float) tp_setting( 'cancel_fee' ) + (float) tp_setting( 'noshow_fee' );
			$c[]  = array( $fees ? 'ok' : 'off', __( 'Cancellation fees', 'taxi-peninsula' ), $fees ? __( 'Shown on the booking form and in emails. Make sure they match your terms page.', 'taxi-peninsula' ) : __( 'No fees set. The free-cancellation window is still shown.', 'taxi-peninsula' ) );
			$c[]  = tp_setting( 'flight_api_key' )
				? array( 'ok', __( 'Flight tracking', 'taxi-peninsula' ), __( 'API key saved. Use "Check flight data" below.', 'taxi-peninsula' ) )
				: array( 'off', __( 'Flight tracking', 'taxi-peninsula' ), __( 'No API key — drivers see a live-status link only.', 'taxi-peninsula' ) );
			$w   = (array) get_option( 'tp_weather_alerts', array() );
			$c[] = ! tp_setting( 'alerts_weather' )
				? array( 'off', __( 'Weather warnings', 'taxi-peninsula' ), __( 'Off.', 'taxi-peninsula' ) )
				: ( ! empty( $w['error'] )
					/* translators: %s: error */
					? array( 'fail', __( 'Weather warnings', 'taxi-peninsula' ), sprintf( __( 'The last check failed: %s', 'taxi-peninsula' ), $w['error'] ) )
					: ( empty( $w['checked'] )
						? array( 'warn', __( 'Weather warnings', 'taxi-peninsula' ), __( 'Not checked yet (it runs hourly). Use "Check weather now".', 'taxi-peninsula' ) )
						/* translators: 1: time 2: count */
						: array( 'ok', __( 'Weather warnings', 'taxi-peninsula' ), sprintf( __( 'Last checked %1$s — %2$d warnings for your area.', 'taxi-peninsula' ), wp_date( 'D g:ia', (int) $w['checked'] ), count( (array) $w['items'] ) ) ) ) );
			break;

		case 'maps':
			if ( ! tp_maps_available() && ! ( function_exists( 'tp_addressfinder_on' ) && tp_addressfinder_on() ) ) {
				$c[] = array( 'off', __( 'Address suggestions', 'taxi-peninsula' ), __( 'No Google Maps key or Addressfinder key — customers type addresses without suggestions.', 'taxi-peninsula' ) );
			} else {
				$c[] = tp_places_available()
					/* translators: %s: provider */
					? array( 'ok', __( 'Address suggestions', 'taxi-peninsula' ), sprintf( __( 'On (%s). Use "Test address suggestions" below.', 'taxi-peninsula' ), tp_places_credit() ) )
					: array( 'warn', __( 'Address suggestions', 'taxi-peninsula' ), __( 'A key is saved but "Suggest addresses as customers type" is off, or the provider you chose has no key.', 'taxi-peninsula' ) );
			}
			if ( tp_setting( 'fare_enabled' ) ) {
				$c[] = tp_fare_available()
					? array( 'ok', __( 'Fare estimates', 'taxi-peninsula' ), __( 'On. Check an estimate below against a real fare.', 'taxi-peninsula' ) )
					: array( 'fail', __( 'Fare estimates', 'taxi-peninsula' ), __( 'Switched on but hidden: it needs the Google Maps key and a rate per kilometre.', 'taxi-peninsula' ) );
			} else {
				$c[] = array( 'off', __( 'Fare estimates', 'taxi-peninsula' ), __( 'Off.', 'taxi-peninsula' ) );
			}
			break;

		case 'reviews':
			if ( ! tp_setting( 'google_place_id' ) ) {
				$c[] = array( tp_maps_available() ? 'warn' : 'off', __( 'Live Google reviews', 'taxi-peninsula' ), tp_maps_available() ? __( 'No Place ID yet. Use "Find your Google Place ID" below.', 'taxi-peninsula' ) : __( 'Needs the Google Maps key (Maps & addresses tab) and your Place ID.', 'taxi-peninsula' ) );
			} else {
				$cache = get_option( 'tp_google_place_cache' );
				$c[]   = is_array( $cache ) && ! empty( $cache['rating'] )
					/* translators: 1: rating 2: count */
					? array( 'ok', __( 'Live Google reviews', 'taxi-peninsula' ), sprintf( __( 'Working: %1$s stars from %2$d reviews.', 'taxi-peninsula' ), $cache['rating'], (int) ( $cache['count'] ?? 0 ) ) )
					: array( 'warn', __( 'Live Google reviews', 'taxi-peninsula' ), __( 'Place ID saved but no reviews loaded yet. Use "Fetch reviews now".', 'taxi-peninsula' ) );
			}
			$c[] = tp_setting( 'review_write_url' ) || tp_setting( 'google_reviews_url' )
				? array( 'ok', __( 'Review invitations', 'taxi-peninsula' ), __( 'Customers are sent to your Google review link after rating a trip.', 'taxi-peninsula' ) )
				: array( 'warn', __( 'Review invitations', 'taxi-peninsula' ), __( 'No review link, so customers are not invited to review you on Google.', 'taxi-peninsula' ) );
			break;

		case 'security':
			$p   = tp_setting( 'captcha_provider' );
			$c[] = 'none' === $p
				? array( 'warn', __( 'Spam protection', 'taxi-peninsula' ), __( 'Off. Forms still have hidden spam traps and rate limits; turn on Turnstile if you get spam bookings.', 'taxi-peninsula' ) )
				: ( tp_setting( 'captcha_site_key' ) && tp_setting( 'captcha_secret_key' )
					? array( 'ok', __( 'Spam protection', 'taxi-peninsula' ), __( 'On. Open the booking form and check the widget appears; if bookings are refused, the keys are for a different domain.', 'taxi-peninsula' ) )
					: array( 'fail', __( 'Spam protection', 'taxi-peninsula' ), __( 'A provider is chosen but a key is missing — forms may refuse every submission.', 'taxi-peninsula' ) ) );
			$c[] = is_ssl() || 0 === strpos( home_url(), 'https://' )
				? array( 'ok', 'HTTPS', __( 'The site uses https://.', 'taxi-peninsula' ) )
				: array( 'fail', 'HTTPS', __( 'The site is not on https://. Ask your host for a free SSL certificate.', 'taxi-peninsula' ) );
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$c[] = function_exists( 'tpc_theme_active' )
				? array( 'ok', __( 'Taxi Peninsula Core plugin', 'taxi-peninsula' ), __( 'Active: bookings and records stay visible even if the theme is ever switched off, and Tools → Taxi data export downloads them.', 'taxi-peninsula' ) )
				: array( 'warn', __( 'Taxi Peninsula Core plugin', 'taxi-peninsula' ), __( 'Not installed. Without it, switching themes (even by accident) hides every booking from the admin until the theme is back. Install taxi-peninsula-core.zip under Plugins → Add New → Upload.', 'taxi-peninsula' ) );
			$off = array_filter( array( 'sec_headers', 'sec_disable_file_edit', 'sec_disable_xmlrpc', 'sec_block_user_enum', 'sec_hide_version' ), static fn( $k ) => ! tp_setting( $k ) );
			/* translators: %d: count */
			$c[] = array( $off ? 'warn' : 'ok', __( 'Hardening', 'taxi-peninsula' ), $off ? sprintf( _n( '%d protection is switched off.', '%d protections are switched off.', count( $off ), 'taxi-peninsula' ), count( $off ) ) : __( 'All recommended protections are on.', 'taxi-peninsula' ) );
			break;

		case 'privacy':
			$c[] = tp_setting( 'privacy_cleanup' )
				? array( 'ok', __( 'Automatic clean-up', 'taxi-peninsula' ), wp_next_scheduled( 'tp_privacy_cleanup' ) ? sprintf( /* translators: %s: time */ __( 'On. Next run %s.', 'taxi-peninsula' ), wp_date( 'D j M g:ia', wp_next_scheduled( 'tp_privacy_cleanup' ) ) ) : __( 'On, but not scheduled yet — it starts after the next page visit.', 'taxi-peninsula' ) )
				: array( 'warn', __( 'Automatic clean-up', 'taxi-peninsula' ), __( 'Off — old personal details are kept until you remove them.', 'taxi-peninsula' ) );
			$c[] = tp_setting( 'privacy_contact' ) || tp_setting( 'privacy_email' )
				? array( 'ok', __( 'Privacy contact', 'taxi-peninsula' ), __( 'Shown in the privacy policy.', 'taxi-peninsula' ) )
				: array( 'warn', __( 'Privacy contact', 'taxi-peninsula' ), __( 'Not set — the policy uses the business name and main email.', 'taxi-peninsula' ) );
			$c[] = tp_setting( 'breach_team' )
				? array( 'ok', __( 'Data breach team', 'taxi-peninsula' ), __( 'Listed in the data breach plan.', 'taxi-peninsula' ) )
				: array( 'warn', __( 'Data breach team', 'taxi-peninsula' ), __( 'Add who to call if personal information is lost or stolen.', 'taxi-peninsula' ) );
			break;

		case 'marketing':
			$ga  = function_exists( 'tp_ga4_id' ) ? tp_ga4_id() : '';
			$c[] = $ga
				? array( 'ok', 'Google Analytics', sprintf( /* translators: %s: id */ __( 'On (%s). Visits show in GA4 → Reports → Realtime within a minute — accept the cookie banner first.', 'taxi-peninsula' ), $ga ) )
				: ( tp_setting( 'ga4_id' )
					? array( 'fail', 'Google Analytics', __( 'The measurement ID is not in the G-XXXXXXX format, so analytics is not loaded.', 'taxi-peninsula' ) )
					: array( 'off', 'Google Analytics', __( 'Off.', 'taxi-peninsula' ) ) );
			$c[] = array( 'ok', __( 'Search engines', 'taxi-peninsula' ), __( 'See Marketing → AI & Bing search and Reports → Search performance for checks and results.', 'taxi-peninsula' ) );
			break;
	}
	return $c;
}

/**
 * Summary of a result log (mail or SMS).
 */
function tp_log_checks( $option, $label ) {
	$log = (array) get_option( $option, array() );
	if ( ! $log ) {
		return array( array( 'off', $label, __( 'Nothing sent yet.', 'taxi-peninsula' ) ) );
	}
	$failed = array_filter( $log, static fn( $e ) => empty( $e['ok'] ) && $e['time'] > time() - 7 * DAY_IN_SECONDS );
	if ( $failed ) {
		$first = reset( $failed );
		/* translators: 1: count 2: error */
		return array( array( 'fail', $label, sprintf( _n( '%1$d failed in the last 7 days. Latest error: %2$s', '%1$d failed in the last 7 days. Latest error: %2$s', count( $failed ), 'taxi-peninsula' ), count( $failed ), $first['error'] ) ) );
	}
	return array( array( 'ok', $label, __( 'Every recent message was accepted.', 'taxi-peninsula' ) ) );
}

/**
 * Status icon (dashicon + screen-reader text) as HTML.
 */
function tp_settings_status_icon( $s ) {
	$map = array(
		'ok'   => array( 'yes-alt', __( 'OK', 'taxi-peninsula' ) ),
		'warn' => array( 'warning', __( 'Check', 'taxi-peninsula' ) ),
		'fail' => array( 'dismiss', __( 'Needs fixing', 'taxi-peninsula' ) ),
		'off'  => array( 'minus', __( 'Off', 'taxi-peninsula' ) ),
	);
	if ( ! isset( $map[ $s ] ) ) {
		return '';
	}
	return '<span class="dashicons dashicons-' . esc_attr( $map[ $s ][0] ) . ' tp-status tp-status--' . esc_attr( $s ) . '" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html( $map[ $s ][1] ) . '</span>';
}

/* -------------------------------------------------------------------------
 * Live tests (run on request).
 * ---------------------------------------------------------------------- */

function tp_settings_tests( $tab ) {
	$t = array(
		'sms'      => array( 'sms_login' => __( 'Check login', 'taxi-peninsula' ) ),
		'payments' => array( 'stripe' => __( 'Check Stripe', 'taxi-peninsula' ) ),
		'bookings' => array( 'flight' => __( 'Check flight data', 'taxi-peninsula' ), 'weather' => __( 'Check weather now', 'taxi-peninsula' ) ),
		'maps'     => array( 'places' => __( 'Test address suggestions', 'taxi-peninsula' ), 'fare' => __( 'Test a fare estimate', 'taxi-peninsula' ) ),
		'reviews'  => array( 'reviews' => __( 'Fetch reviews now', 'taxi-peninsula' ) ),
	);
	return $t[ $tab ] ?? array();
}

/**
 * Run one test: [ok bool, message].
 */
function tp_settings_run_test( $test ) {
	switch ( $test ) {
		case 'sms_login':
			if ( ! tp_sms_enabled() ) {
				return array( false, __( 'SMS details are missing.', 'taxi-peninsula' ) );
			}
			$p = tp_setting( 'sms_provider' );
			if ( 'clicksend' === $p ) {
				$r = wp_remote_get( 'https://rest.clicksend.com/v3/account', array( 'timeout' => 12, 'headers' => array( 'Authorization' => 'Basic ' . base64_encode( tp_setting( 'clicksend_username' ) . ':' . tp_setting( 'clicksend_api_key' ) ) ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			} else {
				$r = wp_remote_get( 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode( tp_setting( 'twilio_account_sid' ) ) . '.json', array( 'timeout' => 12, 'headers' => array( 'Authorization' => 'Basic ' . base64_encode( tp_setting( 'twilio_account_sid' ) . ':' . tp_setting( 'twilio_auth_token' ) ) ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			}
			if ( is_wp_error( $r ) ) {
				/* translators: %s: error */
				return array( false, sprintf( __( 'Could not reach the provider: %s', 'taxi-peninsula' ), $r->get_error_message() ) );
			}
			$code = wp_remote_retrieve_response_code( $r );
			if ( 200 !== $code ) {
				/* translators: %d: status */
				return array( false, sprintf( __( 'The provider refused the login (%d). Copy the username/API key (or SID/token) again, with no spaces.', 'taxi-peninsula' ), $code ) );
			}
			$body = json_decode( wp_remote_retrieve_body( $r ), true );
			$bal  = 'clicksend' === $p ? ( $body['data']['balance'] ?? null ) : null;
			/* translators: %s: balance */
			return array( true, __( 'Login accepted.', 'taxi-peninsula' ) . ( null !== $bal ? ' ' . sprintf( __( 'Account balance: %s.', 'taxi-peninsula' ), $bal ) : '' ) . ' ' . __( 'Now send a test text to your own phone.', 'taxi-peninsula' ) );

		case 'stripe':
			if ( ! tp_setting( 'stripe_secret_key' ) ) {
				return array( false, __( 'No Stripe secret key saved.', 'taxi-peninsula' ) );
			}
			$r = wp_remote_get( 'https://api.stripe.com/v1/balance', array( 'timeout' => 12, 'headers' => array( 'Authorization' => 'Bearer ' . tp_setting( 'stripe_secret_key' ) ) ) );
			if ( is_wp_error( $r ) ) {
				return array( false, $r->get_error_message() );
			}
			$code = wp_remote_retrieve_response_code( $r );
			return 200 === $code
				? array( true, __( 'Stripe accepted the key.', 'taxi-peninsula' ) )
				/* translators: %d: status */
				: array( false, sprintf( __( 'Stripe refused the key (%d). Copy the secret key again from Stripe → Developers → API keys.', 'taxi-peninsula' ), $code ) );

		case 'flight':
			if ( ! tp_setting( 'flight_api_key' ) ) {
				return array( false, __( 'No flight API key saved.', 'taxi-peninsula' ) );
			}
			$r    = wp_remote_get( 'https://aerodatabox.p.rapidapi.com/flights/number/QF1/' . wp_date( 'Y-m-d' ), array( 'timeout' => 12, 'headers' => array( 'X-RapidAPI-Key' => (string) tp_setting( 'flight_api_key' ), 'X-RapidAPI-Host' => 'aerodatabox.p.rapidapi.com' ) ) );
			$code = is_wp_error( $r ) ? 0 : wp_remote_retrieve_response_code( $r );
			if ( in_array( $code, array( 200, 204, 404 ), true ) ) {
				return array( true, __( 'The flight API key works.', 'taxi-peninsula' ) );
			}
			return array( false, 429 === $code ? __( 'The key works but this month’s limit is used up.', 'taxi-peninsula' ) : ( in_array( $code, array( 401, 403 ), true ) ? __( 'The key was refused. Check you are subscribed to AeroDataBox in RapidAPI.', 'taxi-peninsula' ) : sprintf( /* translators: %s: error */ __( 'Could not reach the flight service (%s).', 'taxi-peninsula' ), is_wp_error( $r ) ? $r->get_error_message() : $code ) ) );

		case 'weather':
			if ( ! function_exists( 'tp_alerts_fetch_weather' ) ) {
				return array( false, '' );
			}
			tp_alerts_fetch_weather();
			$w = (array) get_option( 'tp_weather_alerts', array() );
			return ! empty( $w['error'] )
				/* translators: %s: error */
				? array( false, sprintf( __( 'The Bureau feed could not be read: %s', 'taxi-peninsula' ), $w['error'] ) )
				/* translators: %d: count */
				: array( true, sprintf( _n( 'Checked: %d warning for your area.', 'Checked: %d warnings for your area.', count( (array) ( $w['items'] ?? array() ) ), 'taxi-peninsula' ), count( (array) ( $w['items'] ?? array() ) ) ) );

		case 'places':
			if ( ! tp_places_available() ) {
				return array( false, __( 'Address suggestions are not switched on.', 'taxi-peninsula' ) );
			}
			delete_transient( 'tp_pl_' . md5( '2 hastings road frankston' ) );
			$req = new WP_REST_Request( 'GET', '/taxi-peninsula/v1/places' );
			$req->set_param( 'q', '2 Hastings Road Frankston' );
			$res  = rest_ensure_response( tp_rest_places( $req ) );
			$list = (array) ( $res->get_data()['suggestions'] ?? array() );
			if ( ! $list ) {
				return array( false, __( 'No suggestions came back. Check the key is right and, for Google, that "Places API (New)" is enabled in Google Cloud and the key is not restricted to another website.', 'taxi-peninsula' ) );
			}
			/* translators: 1: count 2: first suggestion */
			return array( true, sprintf( __( '%1$d suggestions for "2 Hastings Road Frankston", e.g. %2$s.', 'taxi-peninsula' ), count( $list ), $list[0]['text'] ) );

		case 'fare':
			if ( ! tp_fare_available() ) {
				return array( false, __( 'Fare estimates need the Google Maps key, a per-kilometre rate and the switch turned on.', 'taxi-peninsula' ) );
			}
			$route = tp_route_distance( 'Frankston VIC', 'Melbourne Airport VIC' );
			if ( ! $route ) {
				return array( false, __( 'Google did not return a route. Check the Routes API is enabled for your key in Google Cloud.', 'taxi-peninsula' ) );
			}
			$fare = tp_fare_from_distance( $route[0], $route[1], wp_date( 'Y-m-d' ), '10:00', 'Frankston VIC', 'Melbourne Airport VIC' );
			/* translators: 1: km 2: minutes 3: low 4: high */
			return array( true, sprintf( __( 'Frankston → Melbourne Airport: %1$s km, %2$d min — estimate $%3$s–$%4$s. Compare it with a real fare.', 'taxi-peninsula' ), number_format_i18n( $route[0], 1 ), (int) $route[1], $fare[0], $fare[1] ) );

		case 'reviews':
			if ( ! tp_setting( 'google_place_id' ) || ! tp_maps_available() ) {
				return array( false, __( 'Needs the Google Maps key and a Place ID.', 'taxi-peninsula' ) );
			}
			$data = tp_fetch_google_place();
			if ( is_wp_error( $data ) ) {
				/* translators: %s: error */
				return array( false, sprintf( __( 'Google refused: %s — check the Places API (New) is enabled for your key.', 'taxi-peninsula' ), $data->get_error_message() ) );
			}
			update_option( 'tp_google_place_cache', $data, false );
			/* translators: 1: rating 2: count */
			return array( true, sprintf( __( 'Loaded: %1$s stars, %2$d reviews.', 'taxi-peninsula' ), $data['rating'] ?? '?', (int) ( $data['count'] ?? 0 ) ) );
	}
	return array( false, __( 'Unknown test.', 'taxi-peninsula' ) );
}

add_action(
	'admin_post_tp_settings_test',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'tp_settings_test' );
		$test = isset( $_POST['test'] ) ? sanitize_key( wp_unslash( $_POST['test'] ) ) : '';
		$tab  = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'overview';
		list( $ok, $msg ) = tp_settings_run_test( $test );
		set_transient( 'tp_admin_notice_' . get_current_user_id(), array( $ok ? 'success' : 'error', $msg ), 60 );
		wp_safe_redirect( tp_settings_url( $tab, 'tp-troubleshoot' ) );
		exit;
	}
);

/* -------------------------------------------------------------------------
 * Page.
 * ---------------------------------------------------------------------- */

function tp_render_settings_tabs_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tabs = tp_settings_tabs();
	$tab  = tp_settings_current_tab();
	?>
	<div class="wrap tp-settings">
		<h1><?php esc_html_e( 'Booking settings', 'taxi-peninsula' ); ?></h1>
		<?php settings_errors(); ?>
		<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Settings sections', 'taxi-peninsula' ); ?>">
			<?php foreach ( $tabs as $key => $t ) : ?>
				<?php
				$worst = '';
				if ( 'overview' !== $key ) {
					$st    = wp_list_pluck( 'email' === $key ? array() : tp_settings_checks( $key ), 0 );
					$worst = in_array( 'fail', $st, true ) ? 'fail' : ( in_array( 'warn', $st, true ) ? 'warn' : '' );
				}
				?>
				<a href="<?php echo esc_url( tp_settings_url( $key ) ); ?>" class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>"<?php echo $tab === $key ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $t[0] ); ?><?php echo $worst ? ' <span class="tp-tab-flag tp-tab-flag--' . esc_attr( $worst ) . '" title="' . esc_attr( 'fail' === $worst ? __( 'Needs attention', 'taxi-peninsula' ) : __( 'Check', 'taxi-peninsula' ) ) . '">' . ( 'fail' === $worst ? '!' : '•' ) . '</span>' : ''; ?></a>
			<?php endforeach; ?>
		</nav>

		<?php if ( 'overview' === $tab ) : ?>
			<?php tp_render_settings_overview(); ?>
		<?php else : ?>
			<div class="tp-settings__layout">
				<form method="post" action="options.php" class="tp-settings__form">
					<?php settings_fields( 'tp_settings' ); ?>
					<input type="hidden" name="tp_settings[_tab]" value="<?php echo esc_attr( $tab ); ?>">
					<?php
					global $wp_settings_sections;
					foreach ( $tabs[ $tab ][1] as $section ) {
						$s = $wp_settings_sections['tp-settings'][ 'tp_' . $section ] ?? null;
						if ( ! $s ) {
							continue;
						}
						echo '<h2 id="tp-section-' . esc_attr( $section ) . '">' . esc_html( $s['title'] ) . '</h2>';
						if ( $s['callback'] ) {
							call_user_func( $s['callback'], $s );
						}
						echo '<table class="form-table" role="presentation">';
						do_settings_fields( 'tp-settings', 'tp_' . $section );
						echo '</table>';
					}
					submit_button();
					?>
				</form>
				<?php tp_render_settings_troubleshoot( $tab ); ?>
			</div>
			<?php
			if ( 'reviews' === $tab && function_exists( 'tp_render_place_finder' ) ) {
				tp_render_place_finder();
			}
			?>
		<?php endif; ?>
	</div>
	<?php
}

function tp_render_settings_overview() {
	?>
	<p><?php esc_html_e( 'Each area of the booking system and whether it is ready. Open a tab to change its settings, run a test, or see what to fix. Contact details, hero text and service areas are under Appearance → Customize.', 'taxi-peninsula' ); ?></p>
	<table class="widefat striped tp-settings-overview" style="max-width:1000px">
		<tbody>
			<?php foreach ( tp_settings_tabs() as $key => $t ) : ?>
				<?php
				if ( 'overview' === $key || 'more' === $key ) {
					continue;
				}
				$checks = tp_settings_checks( $key );
				$st     = wp_list_pluck( $checks, 0 );
				$worst  = in_array( 'fail', $st, true ) ? 'fail' : ( in_array( 'warn', $st, true ) ? 'warn' : ( in_array( 'ok', $st, true ) ? 'ok' : 'off' ) );
				$first  = array_values( array_filter( $checks, static fn( $c ) => $c[0] === $worst ) );
				?>
				<tr>
					<td style="width:2em"><?php echo tp_settings_status_icon( $worst ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></td>
					<td style="width:13em"><a href="<?php echo esc_url( tp_settings_url( $key ) ); ?>"><strong><?php echo esc_html( $t[0] ); ?></strong></a></td>
					<td><?php echo $first ? esc_html( $first[0][1] . ': ' . $first[0][2] ) : ''; ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=tp-health' ) ); ?>"><?php esc_html_e( 'Run every connection check', 'taxi-peninsula' ); ?></a></p>
	<?php
}

function tp_render_settings_troubleshoot( $tab ) {
	$checks = tp_settings_checks( $tab );
	$tests  = tp_settings_tests( $tab );
	?>
	<aside class="tp-settings__help" id="tp-troubleshoot" aria-labelledby="tp-troubleshoot-title">
		<h2 id="tp-troubleshoot-title"><?php esc_html_e( 'Test & troubleshoot', 'taxi-peninsula' ); ?></h2>
		<?php if ( 'email' === $tab ) : ?>
			<p><?php esc_html_e( 'Sender checks, DNS checks, a test email and the list of recent emails are on the Email check page.', 'taxi-peninsula' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=tp-email-check' ) ); ?>"><?php esc_html_e( 'Open Email check', 'taxi-peninsula' ); ?></a></p>
		<?php elseif ( $checks ) : ?>
			<ul class="tp-checks">
				<?php foreach ( $checks as $c ) : ?>
					<li class="tp-checks__item tp-checks__item--<?php echo esc_attr( $c[0] ); ?>"><?php echo tp_settings_status_icon( $c[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?> <strong><?php echo esc_html( $c[1] ); ?>:</strong> <?php echo esc_html( $c[2] ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e( 'Nothing to check here — save your changes and check the website.', 'taxi-peninsula' ); ?></p>
		<?php endif; ?>

		<?php foreach ( $tests as $test => $label ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tp-settings__test">
				<input type="hidden" name="action" value="tp_settings_test"><input type="hidden" name="test" value="<?php echo esc_attr( $test ); ?>"><input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
				<?php wp_nonce_field( 'tp_settings_test' ); ?>
				<?php submit_button( $label, 'secondary', 'submit', false ); ?>
			</form>
		<?php endforeach; ?>

		<?php if ( 'sms' === $tab && tp_sms_enabled() ) : ?>
			<h3><?php esc_html_e( 'Send a test text', 'taxi-peninsula' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tp_test_sms">
				<?php wp_nonce_field( 'tp_test_sms' ); ?>
				<label for="tp_test_to"><?php esc_html_e( 'Mobile number', 'taxi-peninsula' ); ?></label><br>
				<input type="tel" id="tp_test_to" name="to" placeholder="04xx xxx xxx" required>
				<?php submit_button( __( 'Send test', 'taxi-peninsula' ), 'primary', 'submit', false ); ?>
			</form>
			<?php tp_render_sms_log(); ?>
			<h3><?php esc_html_e( 'Text not arriving?', 'taxi-peninsula' ); ?></h3>
			<ul class="ul-disc">
				<li><?php esc_html_e( '"Accepted" above but nothing on the phone: look the message up in your ClickSend/Twilio dashboard — it shows whether the network delivered or rejected it.', 'taxi-peninsula' ); ?></li>
				<li><?php esc_html_e( 'Out of credit: top up your SMS account ("Check login" shows the ClickSend balance).', 'taxi-peninsula' ); ?></li>
				<li><?php esc_html_e( 'Using a sender name: try your mobile number as the sender — some networks block unregistered names.', 'taxi-peninsula' ); ?></li>
				<li><?php esc_html_e( 'Twilio trial accounts can only text numbers you have verified in Twilio.', 'taxi-peninsula' ); ?></li>
				<li><?php esc_html_e( 'Customer replied STOP: they will not get marketing texts (trip texts still go).', 'taxi-peninsula' ); ?></li>
			</ul>
		<?php endif; ?>

		<?php if ( 'payments' === $tab && function_exists( 'tp_pay_options_html' ) && tp_pay_options_available() ) : ?>
			<h3><?php esc_html_e( 'How payment details look on an invoice', 'taxi-peninsula' ); ?></h3>
			<div class="tp-settings__preview"><?php echo tp_pay_options_html( 'trip', 1234, 'TP-SAMPLE' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></div>
		<?php endif; ?>

		<?php if ( 'bookings' === $tab && function_exists( 'tp_cancellation_html' ) ) : ?>
			<h3><?php esc_html_e( 'What customers see on the booking form', 'taxi-peninsula' ); ?></h3>
			<div class="tp-settings__preview"><?php echo tp_cancellation_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></div>
		<?php endif; ?>
	</aside>
	<?php
}

function tp_render_sms_log() {
	$log = array_slice( (array) get_option( 'tp_sms_log', array() ), 0, 10 );
	echo '<h3>' . esc_html__( 'Recent texts', 'taxi-peninsula' ) . '</h3>';
	if ( ! $log ) {
		echo '<p>' . esc_html__( 'None sent yet.', 'taxi-peninsula' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped"><tbody>';
	foreach ( $log as $e ) {
		printf(
			'<tr><td>%s</td><td>%s<br><small>%s</small></td><td>%s</td></tr>',
			esc_html( wp_date( 'j M g:ia', (int) $e['time'] ) ),
			esc_html( $e['to'] ),
			esc_html( $e['text'] ),
			$e['ok'] ? tp_settings_status_icon( 'ok' ) : tp_settings_status_icon( 'fail' ) . ' ' . esc_html( $e['error'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		);
	}
	echo '</tbody></table>';
}
