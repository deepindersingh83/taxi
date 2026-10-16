<?php
/**
 * News and offers by email and SMS — built for the Spam Act 2003 (Cth):
 *
 * - Consent: people opt in (a box that is never pre-ticked). The wording,
 *   time, page and IP address of each consent are stored.
 * - Email addresses are confirmed by a link first (double opt-in).
 * - Every message names the business and has a working unsubscribe:
 *   a link in every email (plus the one-click List-Unsubscribe header) and
 *   "reply STOP" or a short link in every SMS. Unsubscribes take effect
 *   straight away (the law allows up to 5 business days).
 *
 * Sign-up: [tp_newsletter], the optional footer box, and the "Send me
 * occasional news and offers" box on the booking form.
 * Office: Bookings → News & offers (subscribers, send a message, CSV).
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

const TP_SUBS_DB = 1;

function tp_subs_table() {
	global $wpdb;
	return $wpdb->prefix . 'tp_subscribers';
}

add_action(
	'init',
	static function () {
		if ( (int) get_option( 'tp_subs_db' ) === TP_SUBS_DB ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = tp_subs_table();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				created datetime NOT NULL,
				name varchar(120) NOT NULL DEFAULT '',
				email varchar(190) NOT NULL DEFAULT '',
				phone varchar(30) NOT NULL DEFAULT '',
				email_ok tinyint(1) NOT NULL DEFAULT 0,
				sms_ok tinyint(1) NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT 'pending',
				token varchar(40) NOT NULL DEFAULT '',
				consent_text text NOT NULL,
				consent_page varchar(255) NOT NULL DEFAULT '',
				consent_ip varchar(45) NOT NULL DEFAULT '',
				confirmed_at datetime NULL DEFAULT NULL,
				unsubscribed_at datetime NULL DEFAULT NULL,
				source varchar(160) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				KEY email (email),
				KEY phone (phone),
				KEY token (token),
				KEY status (status)
			) {$wpdb->get_charset_collate()};"
		);
		update_option( 'tp_subs_db', TP_SUBS_DB, false );
	},
	5
);

add_filter(
	'tp_settings_sections',
	static function ( $s ) {
		$s['marketing'] = array( __( 'News & offers', 'taxi-peninsula' ), __( 'Email and SMS sign-up with consent records and unsubscribe, as the Spam Act 2003 requires. Only send offers to people who signed up.', 'taxi-peninsula' ) );
		return $s;
	}
);

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['marketing_enabled'] = array( 'marketing', __( 'Offer news & offers sign-up', 'taxi-peninsula' ), 'checkbox', false, __( 'Adds the sign-up box to the booking form, the footer (below) and the [tp_newsletter] shortcode.', 'taxi-peninsula' ) );
		$s['marketing_footer']  = array( 'marketing', __( 'Show the sign-up box in the footer', 'taxi-peninsula' ), 'checkbox', true, '' );
		$s['marketing_sms']     = array( 'marketing', __( 'Offer SMS as well as email', 'taxi-peninsula' ), 'checkbox', true, __( 'Needs SMS set up above.', 'taxi-peninsula' ) );
		$s['marketing_pitch']   = array( 'marketing', __( 'Sign-up text', 'taxi-peninsula' ), 'text', '', __( 'e.g. "Occasional offers and service news — about once a month."', 'taxi-peninsula' ) );
		$s['sms_stop_key']      = array( 'marketing', __( 'Secret for the SMS "STOP" reply address', 'taxi-peninsula' ), 'secret', '', __( 'Any random text. Then, in ClickSend or Twilio, set inbound SMS to be forwarded to the address shown on the News & offers page, so "STOP" replies unsubscribe people automatically.', 'taxi-peninsula' ) );
		return $s;
	}
);

function tp_marketing_enabled() {
	return (bool) tp_setting( 'marketing_enabled' );
}

function tp_marketing_sms_available() {
	return tp_setting( 'marketing_sms' ) && function_exists( 'tp_sms_enabled' ) && tp_sms_enabled();
}

/**
 * The exact wording people agree to (stored with each consent).
 */
function tp_marketing_consent_text( $sms = null ) {
	$sms = null === $sms ? tp_marketing_sms_available() : $sms;
	return $sms
		? __( 'Send me occasional news and offers by email and SMS. You can unsubscribe at any time.', 'taxi-peninsula' )
		: __( 'Send me occasional news and offers by email. You can unsubscribe at any time.', 'taxi-peninsula' );
}

/**
 * Find a subscriber by email or phone.
 */
function tp_sub_find( $email, $phone = '' ) {
	global $wpdb;
	$table = tp_subs_table();
	$phone = function_exists( 'tp_e164' ) ? (string) tp_e164( $phone ) : $phone;
	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $email ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s LIMIT 1", strtolower( $email ) ) );
		if ( $row ) {
			return $row;
		}
	}
	if ( $phone ) {
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE phone = %s LIMIT 1", $phone ) );
	}
	// phpcs:enable
	return null;
}

/**
 * Add or update a subscriber after they opted in.
 *
 * @param array $d name, email, phone, email_ok, sms_ok, page, source.
 * @return string 'pending' (confirmation email sent), 'active' or '' on error.
 */
function tp_subscribe( array $d ) {
	global $wpdb;
	$email = strtolower( sanitize_email( $d['email'] ?? '' ) );
	$phone = function_exists( 'tp_e164' ) ? (string) tp_e164( $d['phone'] ?? '' ) : '';
	$eok   = $email && ! empty( $d['email_ok'] );
	$sok   = $phone && ! empty( $d['sms_ok'] ) && tp_marketing_sms_available();
	if ( ! $eok && ! $sok ) {
		return '';
	}
	$row    = tp_sub_find( $email, $phone );
	$active = $row && 'active' === $row->status;
	// Email consent is confirmed by link; SMS-only sign-ups are active straight away.
	$status = $active ? 'active' : ( $eok ? 'pending' : 'active' );
	$data   = array(
		'name'         => mb_substr( sanitize_text_field( $d['name'] ?? '' ), 0, 120 ),
		'email'        => $email,
		'phone'        => $phone,
		'email_ok'     => $eok ? 1 : 0,
		'sms_ok'       => $sok ? 1 : 0,
		'status'       => $status,
		'consent_text' => tp_marketing_consent_text( $sok ),
		'consent_page' => mb_substr( (string) ( $d['page'] ?? '' ), 0, 255 ),
		'consent_ip'   => function_exists( 'tp_client_ip' ) ? tp_client_ip() : '',
		'source'       => mb_substr( (string) ( $d['source'] ?? '' ), 0, 160 ),
	);
	if ( 'active' === $status && ! $active ) {
		$data['confirmed_at'] = current_time( 'mysql' );
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	if ( $row ) {
		if ( $active ) {
			$data['email_ok'] = max( (int) $row->email_ok, $data['email_ok'] );
			$data['sms_ok']   = max( (int) $row->sms_ok, $data['sms_ok'] );
		}
		$data['unsubscribed_at'] = null;
		$wpdb->update( tp_subs_table(), $data, array( 'id' => $row->id ) );
		$token = $row->token;
	} else {
		$token           = strtolower( wp_generate_password( 24, false ) );
		$data['token']   = $token;
		$data['created'] = current_time( 'mysql' );
		$wpdb->insert( tp_subs_table(), $data );
	}
	// phpcs:enable
	if ( 'pending' === $status ) {
		tp_sub_send_confirm( $email, $data['name'], $token );
	}
	if ( function_exists( 'tp_track' ) ) {
		tp_track( 'signup', 0, (string) wp_parse_url( (string) ( $d['page'] ?? '' ), PHP_URL_PATH ) );
	}
	return $status;
}

function tp_sub_url( $action, $token ) {
	return add_query_arg( array( 'tp_sub' => $action, 't' => $token ), home_url( '/' ) );
}

function tp_sub_send_confirm( $email, $name, $token ) {
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	/* translators: %s: name */
	$body  = ( $name ? sprintf( __( 'Hi %s,', 'taxi-peninsula' ), $name ) : __( 'Hello,', 'taxi-peninsula' ) ) . "\n\n";
	/* translators: %s: site */
	$body .= sprintf( __( 'Please confirm that you would like news and offers from %s.', 'taxi-peninsula' ), $site ) . "\n\n";
	$body .= __( 'Yes, sign me up', 'taxi-peninsula' ) . ': ' . tp_sub_url( 'confirm', $token ) . "\n\n";
	$body .= __( 'If you did not ask for this, ignore this email and you will not hear from us.', 'taxi-peninsula' ) . "\n\n" . $site;
	wp_mail(
		$email,
		/* translators: %s: site */
		sprintf( __( 'Please confirm your sign-up — %s', 'taxi-peninsula' ), $site ),
		$body,
		tp_mail_headers( tp_opt( 'email' ) )
	);
}

/* -------------------------------------------------------------------------
 * Sign-up forms.
 * ---------------------------------------------------------------------- */

add_shortcode(
	'tp_newsletter',
	static function () {
		return tp_marketing_enabled() ? tp_newsletter_form( 'page' ) : '';
	}
);

/**
 * Sign-up form markup.
 *
 * @param string $context page|footer.
 */
function tp_newsletter_form( $context = 'page' ) {
	list( $errors, $old ) = tp_form_state();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$done  = isset( $_GET['signup'] ) ? sanitize_key( wp_unslash( $_GET['signup'] ) ) : '';
	$sms   = tp_marketing_sms_available();
	$uid   = 'nl-' . $context;
	$pitch = (string) tp_setting( 'marketing_pitch' );
	ob_start();
	?>
	<div class="newsletter newsletter--<?php echo esc_attr( $context ); ?>" id="<?php echo esc_attr( $uid ); ?>">
		<?php if ( 'footer' === $context ) : ?>
			<h2 class="site-footer__heading"><?php esc_html_e( 'News & offers', 'taxi-peninsula' ); ?></h2>
		<?php endif; ?>
		<?php if ( 'pending' === $done ) : ?>
			<p class="newsletter__done" role="status"><?php esc_html_e( 'Nearly done — please click the link in the email we just sent you.', 'taxi-peninsula' ); ?></p>
		<?php elseif ( 'active' === $done ) : ?>
			<p class="newsletter__done" role="status"><?php esc_html_e( 'Thanks — you are signed up.', 'taxi-peninsula' ); ?></p>
		<?php else : ?>
			<?php if ( $pitch ) : ?>
				<p class="newsletter__pitch"><?php echo esc_html( $pitch ); ?></p>
			<?php endif; ?>
			<?php if ( $errors && ( $old['nl_context'] ?? '' ) === $context ) : ?>
				<p class="field__error" role="alert"><?php echo esc_html( $errors['form'] ?? '' ); ?></p>
			<?php endif; ?>
			<form class="newsletter__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
				<input type="hidden" name="action" value="tp_signup">
				<input type="hidden" name="nl_context" value="<?php echo esc_attr( $context ); ?>">
				<input type="hidden" name="_tp_return" value="<?php echo esc_url( tp_form_return_url() ); ?>">
				<?php wp_nonce_field( 'tp_signup', '_tp_nonce', false ); ?>
				<div class="field">
					<label for="<?php echo esc_attr( $uid ); ?>-email"><?php esc_html_e( 'Email', 'taxi-peninsula' ); ?></label>
					<input id="<?php echo esc_attr( $uid ); ?>-email" name="email" type="email" autocomplete="email" value="<?php echo esc_attr( ( $old['nl_context'] ?? '' ) === $context ? ( $old['email'] ?? '' ) : '' ); ?>">
				</div>
				<?php if ( $sms ) : ?>
					<div class="field">
						<label for="<?php echo esc_attr( $uid ); ?>-phone"><?php esc_html_e( 'Mobile (for SMS offers, optional)', 'taxi-peninsula' ); ?></label>
						<input id="<?php echo esc_attr( $uid ); ?>-phone" name="phone" type="tel" autocomplete="tel" value="">
					</div>
				<?php endif; ?>
				<label class="field--check newsletter__consent"><input type="checkbox" name="consent" value="1" required> <span><?php echo esc_html( tp_marketing_consent_text() ); ?></span></label>
				<?php tp_spam_fields(); ?>
				<button type="submit" class="btn btn--primary"><?php esc_html_e( 'Sign up', 'taxi-peninsula' ); ?></button>
				<?php if ( function_exists( 'get_privacy_policy_url' ) && get_privacy_policy_url() ) : ?>
					<p class="newsletter__small"><a href="<?php echo esc_url( get_privacy_policy_url() ); ?>"><?php esc_html_e( 'How we use your details', 'taxi-peninsula' ); ?></a></p>
				<?php endif; ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

add_action(
	'tp_footer_columns',
	static function () {
		if ( tp_marketing_enabled() && tp_setting( 'marketing_footer' ) ) {
			echo '<div>' . tp_newsletter_form( 'footer' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the form.
		}
	}
);

add_action( 'admin_post_nopriv_tp_signup', 'tp_handle_signup' );
add_action( 'admin_post_tp_signup', 'tp_handle_signup' );
function tp_handle_signup() {
	$return = isset( $_POST['_tp_return'] ) ? esc_url_raw( wp_unslash( $_POST['_tp_return'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in tp_form_guard().
	$return = remove_query_arg( array( 'tp_form', 'signup' ), wp_validate_redirect( $return, home_url( '/' ) ) );
	$raw    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- sanitized in tp_subscribe().
	$anchor = '#nl-' . ( 'footer' === ( $raw['nl_context'] ?? '' ) ? 'footer' : 'page' );
	tp_form_guard( 'tp_signup', $return, $raw, $anchor, 'signup' );
	if ( ! tp_marketing_enabled() ) {
		wp_safe_redirect( $return );
		exit;
	}
	$email = sanitize_email( $raw['email'] ?? '' );
	$phone = sanitize_text_field( $raw['phone'] ?? '' );
	if ( empty( $raw['consent'] ) ) {
		tp_booking_fail( $return, array( 'form' => __( 'Please tick the box to agree to receive news and offers.', 'taxi-peninsula' ) ), $raw, $anchor );
	}
	if ( ! is_email( $email ) && ! ( $phone && function_exists( 'tp_e164' ) && tp_e164( $phone ) ) ) {
		tp_booking_fail( $return, array( 'form' => __( 'Please enter a valid email address or mobile number.', 'taxi-peninsula' ) ), $raw, $anchor );
	}
	$status = tp_subscribe(
		array(
			'email'    => $email,
			'phone'    => $phone,
			'email_ok' => is_email( $email ),
			'sms_ok'   => '' !== $phone,
			'page'     => $return,
			'source'   => function_exists( 'tp_visit_source' ) ? implode( ' / ', array_filter( tp_visit_source() ) ) : '',
		)
	);
	tp_rate_limit( 'tp_signup', 5, HOUR_IN_SECONDS, true );
	wp_safe_redirect( add_query_arg( 'signup', $status ?: 'active', $return ) . $anchor );
	exit;
}

// Booking form: "Send me occasional news and offers".
add_action(
	'tp_booking_created',
	static function ( $post_id ) {
		if ( ! tp_marketing_enabled() || empty( $_POST['offers'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by the booking handler.
			return;
		}
		$b = tp_get_booking( $post_id );
		tp_subscribe(
			array(
				'name'     => $b['name'],
				'email'    => $b['email'],
				'phone'    => $b['phone'],
				'email_ok' => (bool) $b['email'],
				'sms_ok'   => (bool) $b['phone'],
				'page'     => isset( $_POST['_tp_return'] ) ? esc_url_raw( wp_unslash( $_POST['_tp_return'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
				/* translators: %s: booking reference */
				'source'   => sprintf( __( 'Booking form (%s)', 'taxi-peninsula' ), $b['reference'] ),
			)
		);
	},
	50
);

/* -------------------------------------------------------------------------
 * Confirm and unsubscribe links.
 * ---------------------------------------------------------------------- */

function tp_sub_by_token( $token ) {
	global $wpdb;
	$token = sanitize_key( $token );
	if ( strlen( $token ) < 10 ) {
		return null;
	}
	$table = tp_subs_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s LIMIT 1", $token ) );
}

/**
 * Unsubscribe a subscriber from one or all channels.
 */
function tp_unsubscribe( $row, $channel = 'all', $how = '' ) {
	global $wpdb;
	$data = array();
	if ( 'all' === $channel || 'email' === $channel ) {
		$data['email_ok'] = 0;
	}
	if ( 'all' === $channel || 'sms' === $channel ) {
		$data['sms_ok'] = 0;
	}
	$left = ( isset( $data['email_ok'] ) ? 0 : (int) $row->email_ok ) + ( isset( $data['sms_ok'] ) ? 0 : (int) $row->sms_ok );
	if ( ! $left ) {
		$data['status']          = 'unsubscribed';
		$data['unsubscribed_at'] = current_time( 'mysql' );
	}
	$wpdb->update( tp_subs_table(), $data, array( 'id' => $row->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( function_exists( 'tp_activity' ) ) {
		/* translators: 1: channel 2: how */
		tp_activity( 'enquiry', sprintf( __( 'Unsubscribed from news & offers (%1$s) %2$s', 'taxi-peninsula' ), $channel, $how ) );
	}
}

add_action(
	'template_redirect',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification -- token links from emails/SMS.
		$action = isset( $_GET['tp_sub'] ) ? sanitize_key( wp_unslash( $_GET['tp_sub'] ) ) : '';
		$token  = isset( $_GET['t'] ) ? sanitize_key( wp_unslash( $_GET['t'] ) ) : ( isset( $_GET['tpu'] ) ? sanitize_key( wp_unslash( $_GET['tpu'] ) ) : '' );
		if ( isset( $_GET['tpu'] ) ) {
			$action = 'unsub';
		}
		if ( ! in_array( $action, array( 'confirm', 'unsub' ), true ) ) {
			return;
		}
		$row = tp_sub_by_token( $token );
		$msg = __( 'This link is not valid any more.', 'taxi-peninsula' );
		$ok  = false;
		if ( $row && 'confirm' === $action ) {
			if ( 'unsubscribed' !== $row->status ) {
				global $wpdb;
				$wpdb->update( tp_subs_table(), array( 'status' => 'active', 'confirmed_at' => $row->confirmed_at ?: current_time( 'mysql' ) ), array( 'id' => $row->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$msg = __( 'Thanks — you are signed up. You can unsubscribe at any time from the link in each message.', 'taxi-peninsula' );
				$ok  = true;
			}
		} elseif ( $row ) {
			// One-click: RFC 8058 POST from mail apps, or the button below.
			if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
				$channel = isset( $_POST['channel'] ) && in_array( $_POST['channel'], array( 'email', 'sms' ), true ) ? sanitize_key( $_POST['channel'] ) : 'all';
				tp_unsubscribe( $row, $channel, isset( $_POST['List-Unsubscribe'] ) ? '(one-click)' : '' );
				$msg = __( 'You have been unsubscribed. We will not send you any more news or offers.', 'taxi-peninsula' );
				if ( 'all' !== $channel ) {
					$msg = 'email' === $channel ? __( 'You will no longer get offers by email.', 'taxi-peninsula' ) : __( 'You will no longer get offers by SMS.', 'taxi-peninsula' );
				}
				$ok = true;
				if ( isset( $_POST['List-Unsubscribe'] ) ) {
					status_header( 200 );
					exit;
				}
			} else {
				tp_sub_page( null, $row );
				exit;
			}
		}
		// phpcs:enable
		tp_sub_page( $msg, null, $ok );
		exit;
	}
);

/**
 * Small themed page for confirm / unsubscribe.
 */
function tp_sub_page( $message, $row = null, $ok = false ) {
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );
	add_filter( 'wp_robots', 'wp_robots_no_robots' );
	add_filter(
		'pre_get_document_title',
		static function () {
			return __( 'News & offers', 'taxi-peninsula' ) . ' — ' . get_bloginfo( 'name' );
		}
	);
	get_header();
	?>
	<div class="container section">
		<div class="card sub-page">
			<?php if ( $row ) : ?>
				<h1><?php esc_html_e( 'Unsubscribe', 'taxi-peninsula' ); ?></h1>
				<p><?php esc_html_e( 'Stop receiving news and offers from us? Your bookings, confirmations and trip messages are not affected.', 'taxi-peninsula' ); ?></p>
				<form method="post">
					<input type="hidden" name="channel" value="all">
					<button type="submit" class="btn btn--primary btn--lg"><?php esc_html_e( 'Unsubscribe me', 'taxi-peninsula' ); ?></button>
				</form>
				<?php if ( (int) $row->email_ok && (int) $row->sms_ok ) : ?>
					<p class="sub-page__or"><?php esc_html_e( 'Or only stop one kind:', 'taxi-peninsula' ); ?></p>
					<form method="post" class="sub-page__inline"><input type="hidden" name="channel" value="sms"><button type="submit" class="btn btn--ghost"><?php esc_html_e( 'Stop SMS only', 'taxi-peninsula' ); ?></button></form>
					<form method="post" class="sub-page__inline"><input type="hidden" name="channel" value="email"><button type="submit" class="btn btn--ghost"><?php esc_html_e( 'Stop emails only', 'taxi-peninsula' ); ?></button></form>
				<?php endif; ?>
			<?php else : ?>
				<?php if ( $ok ) : ?>
					<span class="booking-success__icon"><?php tp_the_icon( 'check' ); ?></span>
				<?php endif; ?>
				<h1><?php esc_html_e( 'News & offers', 'taxi-peninsula' ); ?></h1>
				<p role="status"><?php echo esc_html( $message ); ?></p>
				<p><a class="btn btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the home page', 'taxi-peninsula' ); ?></a></p>
			<?php endif; ?>
		</div>
	</div>
	<?php
	get_footer();
}

// SMS replies: "STOP" unsubscribes. ClickSend / Twilio inbound webhook.
add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'taxi-peninsula/v1',
			'/sms-inbound',
			array(
				'methods'             => 'POST',
				'permission_callback' => static function ( WP_REST_Request $req ) {
					$key = (string) tp_setting( 'sms_stop_key' );
					return '' !== $key && hash_equals( $key, (string) $req->get_param( 'key' ) );
				},
				'callback'            => static function ( WP_REST_Request $req ) {
					$from = (string) ( $req->get_param( 'From' ) ?: $req->get_param( 'from' ) );
					$body = (string) ( $req->get_param( 'Body' ) ?: ( $req->get_param( 'body' ) ?: $req->get_param( 'message' ) ) );
					if ( $from && preg_match( '/^\s*(stop|unsubscribe|opt ?out|cancel offers)\b/i', $body ) ) {
						$row = tp_sub_find( '', $from );
						if ( $row ) {
							tp_unsubscribe( $row, 'sms', '(STOP reply)' );
						}
					}
					return new WP_REST_Response( array( 'ok' => true ), 200 );
				},
			)
		);
	}
);

/* -------------------------------------------------------------------------
 * Sending.
 * ---------------------------------------------------------------------- */

/**
 * Footer added to every offer email: who it is from and how to unsubscribe.
 */
function tp_marketing_email_footer( $row ) {
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$who  = $site . ( tp_setting( 'abn' ) ? ' · ABN ' . tp_setting( 'abn' ) : '' );
	$addr = trim( (string) tp_setting( 'business_address' ) ) ?: (string) tp_opt( 'location' );
	return "\n\n—\n" . sprintf(
		/* translators: 1: business 2: address */
		__( 'You are receiving this because you signed up for news and offers from %1$s, %2$s.', 'taxi-peninsula' ),
		$who,
		preg_replace( '/\s*\R\s*/', ', ', $addr )
	) . "\n" . __( 'Unsubscribe', 'taxi-peninsula' ) . ': ' . tp_sub_url( 'unsub', $row->token );
}

function tp_marketing_sms_footer( $row ) {
	$name = function_exists( 'tp_sms_site_name' ) ? tp_sms_site_name() : get_bloginfo( 'name' );
	/* translators: 1: business 2: link */
	return ' ' . sprintf( __( '- %1$s. Reply STOP to opt out or %2$s', 'taxi-peninsula' ), $name, add_query_arg( 'tpu', $row->token, home_url( '/' ) ) );
}

/**
 * Send one campaign message to one subscriber.
 */
function tp_marketing_send_one( $row, array $c ) {
	$sent = 0;
	if ( in_array( $c['channel'], array( 'email', 'both' ), true ) && (int) $row->email_ok && $row->email ) {
		$headers   = tp_mail_headers( tp_opt( 'email' ) );
		$unsub     = tp_sub_url( 'unsub', $row->token );
		$headers[] = 'List-Unsubscribe: <' . $unsub . '>';
		$headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
		/* translators: %s: name */
		$hello = $row->name ? sprintf( __( 'Hi %s,', 'taxi-peninsula' ), $row->name ) . "\n\n" : '';
		wp_mail( $row->email, $c['subject'], $hello . $c['message'] . tp_marketing_email_footer( $row ), $headers );
		++$sent;
	}
	if ( in_array( $c['channel'], array( 'sms', 'both' ), true ) && (int) $row->sms_ok && $row->phone && function_exists( 'tp_send_sms' ) ) {
		tp_send_sms( $row->phone, $c['sms'] . tp_marketing_sms_footer( $row ) );
		++$sent;
	}
	return $sent;
}

/**
 * Process the next batch of the current campaign (cron).
 */
add_action( 'tp_marketing_batch', 'tp_marketing_batch' );
function tp_marketing_batch() {
	global $wpdb;
	$c = get_option( 'tp_campaign_current' );
	if ( ! is_array( $c ) ) {
		return;
	}
	$table = tp_subs_table();
	$where = 'email' === $c['channel'] ? 'email_ok = 1' : ( 'sms' === $c['channel'] ? 'sms_ok = 1' : '(email_ok = 1 OR sms_ok = 1)' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'active' AND {$where} AND id > %d ORDER BY id ASC LIMIT 40", (int) $c['last_id'] ) );
	foreach ( $rows as $row ) {
		$c['sent']   += tp_marketing_send_one( $row, $c );
		$c['last_id'] = (int) $row->id;
	}
	if ( count( $rows ) < 40 ) {
		$c['finished'] = current_time( 'mysql' );
		$history       = array_slice( (array) get_option( 'tp_campaigns', array() ), 0, 19 );
		array_unshift( $history, $c );
		update_option( 'tp_campaigns', $history, false );
		delete_option( 'tp_campaign_current' );
		if ( function_exists( 'tp_activity' ) ) {
			/* translators: 1: subject 2: count */
			tp_activity( 'enquiry', sprintf( __( 'Offer "%1$s" sent (%2$d messages)', 'taxi-peninsula' ), $c['subject'], $c['sent'] ) );
		}
		return;
	}
	update_option( 'tp_campaign_current', $c, false );
	wp_schedule_single_event( time() + 120, 'tp_marketing_batch' );
}

/* -------------------------------------------------------------------------
 * Admin: Bookings → News & offers.
 * ---------------------------------------------------------------------- */


function tp_sub_counts() {
	global $wpdb;
	$table = tp_subs_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$r = $wpdb->get_row( "SELECT SUM(status='active' AND email_ok=1) email, SUM(status='active' AND sms_ok=1) sms, SUM(status='pending') pending, SUM(status='unsubscribed') unsub FROM {$table}" );
	return array( 'email' => (int) ( $r->email ?? 0 ), 'sms' => (int) ( $r->sms ?? 0 ), 'pending' => (int) ( $r->pending ?? 0 ), 'unsub' => (int) ( $r->unsub ?? 0 ) );
}

function tp_render_offers_page() {
	global $wpdb;
	if ( ! current_user_can( 'tp_manage_offers' ) ) {
		return;
	}
	$counts  = tp_sub_counts();
	$current = get_option( 'tp_campaign_current' );
	$history = (array) get_option( 'tp_campaigns', array() );
	$table   = tp_subs_table();
	$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $search
		? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE email LIKE %s OR phone LIKE %s OR name LIKE %s ORDER BY id DESC LIMIT 100", '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%' ) )
		: $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 50" );
	// phpcs:enable
	$notice = isset( $_GET['tp_msg'] ) ? sanitize_key( wp_unslash( $_GET['tp_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="wrap tp-offers">
		<h1><?php esc_html_e( 'News & offers', 'taxi-peninsula' ); ?></h1>
		<?php if ( ! tp_marketing_enabled() ) : ?>
			<div class="notice notice-warning"><p><?php printf( /* translators: %s: link */ esc_html__( 'Sign-up is switched off. Turn it on in %s.', 'taxi-peninsula' ), '<a href="' . esc_url( admin_url( 'admin.php?page=tp-settings#tp-section-marketing' ) ) . '">' . esc_html__( 'Bookings → Settings → News & offers', 'taxi-peninsula' ) . '</a>' ); ?></p></div>
		<?php endif; ?>
		<?php if ( 'queued' === $notice ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Your message is being sent in small batches over the next few minutes.', 'taxi-peninsula' ); ?></p></div>
		<?php elseif ( 'test' === $notice ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Test sent to you.', 'taxi-peninsula' ); ?></p></div>
		<?php elseif ( 'removed' === $notice ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Subscriber deleted.', 'taxi-peninsula' ); ?></p></div>
		<?php endif; ?>

		<ul class="tp-leads__totals">
			<li><strong><?php echo esc_html( number_format_i18n( $counts['email'] ) ); ?></strong><span><?php esc_html_e( 'Email subscribers', 'taxi-peninsula' ); ?></span></li>
			<li><strong><?php echo esc_html( number_format_i18n( $counts['sms'] ) ); ?></strong><span><?php esc_html_e( 'SMS subscribers', 'taxi-peninsula' ); ?></span></li>
			<li><strong><?php echo esc_html( number_format_i18n( $counts['pending'] ) ); ?></strong><span><?php esc_html_e( 'Waiting to confirm', 'taxi-peninsula' ); ?></span></li>
			<li><strong><?php echo esc_html( number_format_i18n( $counts['unsub'] ) ); ?></strong><span><?php esc_html_e( 'Unsubscribed', 'taxi-peninsula' ); ?></span></li>
		</ul>

		<h2><?php esc_html_e( 'Send a message', 'taxi-peninsula' ); ?></h2>
		<?php if ( is_array( $current ) ) : ?>
			<p><strong>
				<?php
				/* translators: 1: subject 2: count */
				echo esc_html( sprintf( __( 'Sending "%1$s" — %2$d sent so far.', 'taxi-peninsula' ), $current['subject'], $current['sent'] ) );
				?>
			</strong></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tp-offers__form">
				<input type="hidden" name="action" value="tp_offer_send">
				<?php wp_nonce_field( 'tp_offer_send' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="tp-offer-channel"><?php esc_html_e( 'Send by', 'taxi-peninsula' ); ?></label></th><td>
						<select id="tp-offer-channel" name="channel">
							<option value="email"><?php esc_html_e( 'Email', 'taxi-peninsula' ); ?></option>
							<?php if ( function_exists( 'tp_sms_enabled' ) && tp_sms_enabled() ) : ?>
								<option value="sms"><?php esc_html_e( 'SMS', 'taxi-peninsula' ); ?></option>
								<option value="both"><?php esc_html_e( 'Email and SMS', 'taxi-peninsula' ); ?></option>
							<?php endif; ?>
						</select></td></tr>
					<tr><th><label for="tp-offer-subject"><?php esc_html_e( 'Email subject', 'taxi-peninsula' ); ?></label></th><td><input type="text" class="large-text" id="tp-offer-subject" name="subject" maxlength="150"></td></tr>
					<tr><th><label for="tp-offer-message"><?php esc_html_e( 'Email message', 'taxi-peninsula' ); ?></label></th><td><textarea class="large-text" rows="7" id="tp-offer-message" name="message"></textarea>
						<p class="description"><?php esc_html_e( 'Plain text. A line like "Book now: https://…" becomes a button. Your business name, address and an unsubscribe link are added at the end automatically.', 'taxi-peninsula' ); ?></p></td></tr>
					<tr><th><label for="tp-offer-sms"><?php esc_html_e( 'SMS text', 'taxi-peninsula' ); ?></label></th><td><textarea class="large-text" rows="2" id="tp-offer-sms" name="sms" maxlength="300"></textarea>
						<p class="description"><?php esc_html_e( 'Keep it short. Your business name and "Reply STOP to opt out" plus an opt-out link are added automatically.', 'taxi-peninsula' ); ?></p></td></tr>
				</table>
				<p>
					<button type="submit" name="test" value="1" class="button"><?php esc_html_e( 'Send a test to me', 'taxi-peninsula' ); ?></button>
					<button type="submit" class="button button-primary" onclick="return confirm(<?php echo esc_attr( wp_json_encode( __( 'Send this to all subscribers now?', 'taxi-peninsula' ) ) ); ?>);"><?php esc_html_e( 'Send to subscribers', 'taxi-peninsula' ); ?></button>
				</p>
			</form>
		<?php endif; ?>

		<?php if ( $history ) : ?>
			<h2><?php esc_html_e( 'Sent', 'taxi-peninsula' ); ?></h2>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Finished', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Subject', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'By', 'taxi-peninsula' ); ?></th><th class="num"><?php esc_html_e( 'Messages', 'taxi-peninsula' ); ?></th></tr></thead><tbody>
				<?php foreach ( $history as $h ) : ?>
					<tr><td><?php echo esc_html( $h['finished'] ?? '' ); ?></td><td><?php echo esc_html( $h['subject'] ?: $h['sms'] ); ?></td><td><?php echo esc_html( $h['channel'] ); ?></td><td class="num"><?php echo esc_html( $h['sent'] ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Subscribers', 'taxi-peninsula' ); ?></h2>
		<form method="get" class="tp-filters">
			<input type="hidden" name="post_type" value="tp_booking"><input type="hidden" name="page" value="tp-offers">
			<label class="screen-reader-text" for="tp-sub-search"><?php esc_html_e( 'Search subscribers', 'taxi-peninsula' ); ?></label>
			<input type="search" id="tp-sub-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Email, mobile or name', 'taxi-peninsula' ); ?>">
			<?php submit_button( __( 'Search', 'taxi-peninsula' ), 'secondary', '', false ); ?>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tp_subs_export' ), 'tp_subs_export' ) ); ?>"><?php esc_html_e( 'Download CSV (with consent records)', 'taxi-peninsula' ); ?></a>
		</form>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Signed up', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Name', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Email', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Mobile', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Status', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Consent', 'taxi-peninsula' ); ?></th><th></th></tr></thead>
			<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No subscribers yet.', 'taxi-peninsula' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td><?php echo esc_html( $r->created ); ?></td>
						<td><?php echo esc_html( $r->name ); ?></td>
						<td><?php echo esc_html( $r->email ); ?><?php echo $r->email && ! (int) $r->email_ok ? ' <em>(' . esc_html__( 'off', 'taxi-peninsula' ) . ')</em>' : ''; ?></td>
						<td><?php echo esc_html( $r->phone ); ?><?php echo $r->phone && ! (int) $r->sms_ok ? ' <em>(' . esc_html__( 'off', 'taxi-peninsula' ) . ')</em>' : ''; ?></td>
						<td><?php echo esc_html( $r->status ); ?></td>
						<td><span title="<?php echo esc_attr( $r->consent_text . ' — ' . $r->consent_page . ' — ' . $r->consent_ip ); ?>"><?php echo esc_html( $r->source ?: $r->consent_page ); ?></span></td>
						<td><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tp_sub_delete&id=' . $r->id ), 'tp_sub_delete_' . $r->id ) ); ?>" onclick="return confirm(<?php echo esc_attr( wp_json_encode( __( 'Delete this subscriber and their consent record?', 'taxi-peninsula' ) ) ); ?>);"><?php esc_html_e( 'Delete', 'taxi-peninsula' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'SMS "STOP" replies', 'taxi-peninsula' ); ?></h2>
		<?php if ( tp_setting( 'sms_stop_key' ) ) : ?>
			<p><?php esc_html_e( 'In ClickSend (Messaging → Inbound rules → URL) or Twilio (your number → Messaging → A message comes in → Webhook), forward inbound SMS to:', 'taxi-peninsula' ); ?></p>
			<p><code><?php echo esc_html( add_query_arg( 'key', tp_setting( 'sms_stop_key' ), rest_url( 'taxi-peninsula/v1/sms-inbound' ) ) ); ?></code></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Set a secret under Bookings → Settings → News & offers to get the address for STOP replies. Until then, people can opt out with the link in each SMS.', 'taxi-peninsula' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'admin_post_tp_offer_send', 'tp_handle_offer_send' );
function tp_handle_offer_send() {
	check_admin_referer( 'tp_offer_send' );
	if ( ! current_user_can( 'tp_manage_offers' ) ) {
		wp_die( '', 403 );
	}
	$channel = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : 'email';
	$c       = array(
		'channel' => in_array( $channel, array( 'email', 'sms', 'both' ), true ) ? $channel : 'email',
		'subject' => isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '',
		'message' => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
		'sms'     => isset( $_POST['sms'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sms'] ) ) : '',
		'sent'    => 0,
		'last_id' => 0,
		'started' => current_time( 'mysql' ),
		'by'      => wp_get_current_user()->display_name,
	);
	$back = admin_url( 'admin.php?page=tp-offers' );
	if ( ( 'sms' !== $c['channel'] && ( '' === $c['subject'] || '' === $c['message'] ) ) || ( 'email' !== $c['channel'] && '' === $c['sms'] ) ) {
		wp_die( esc_html__( 'Please fill in the subject and message for each way you are sending.', 'taxi-peninsula' ), '', array( 'back_link' => true ) );
	}
	if ( ! empty( $_POST['test'] ) ) {
		$me = wp_get_current_user();
		$fake = (object) array( 'name' => $me->first_name ?: $me->display_name, 'email' => $me->user_email, 'phone' => get_user_meta( $me->ID, 'tp_mobile', true ), 'email_ok' => 1, 'sms_ok' => 1, 'token' => 'test-preview-link' );
		$c['subject'] = '[TEST] ' . $c['subject'];
		tp_marketing_send_one( $fake, $c );
		wp_safe_redirect( add_query_arg( 'tp_msg', 'test', $back ) );
		exit;
	}
	update_option( 'tp_campaign_current', $c, false );
	wp_schedule_single_event( time(), 'tp_marketing_batch' );
	wp_safe_redirect( add_query_arg( 'tp_msg', 'queued', $back ) );
	exit;
}

add_action(
	'admin_post_tp_sub_delete',
	static function () {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'tp_sub_delete_' . $id );
		if ( ! current_user_can( 'tp_manage_offers' ) ) {
			wp_die( '', 403 );
		}
		global $wpdb;
		$wpdb->delete( tp_subs_table(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_safe_redirect( admin_url( 'admin.php?page=tp-offers&tp_msg=removed' ) );
		exit;
	}
);

add_action(
	'admin_post_tp_subs_export',
	static function () {
		check_admin_referer( 'tp_subs_export' );
		if ( ! current_user_can( 'tp_manage_offers' ) ) {
			wp_die( '', 403 );
		}
		global $wpdb;
		$table = tp_subs_table();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="subscribers-' . wp_date( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Signed up', 'Name', 'Email', 'Email OK', 'Mobile', 'SMS OK', 'Status', 'Confirmed', 'Unsubscribed', 'Consent wording', 'Consent page', 'Consent IP', 'Source' ), ',', '"', '' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC" ) as $r ) {
			$row = array( $r->created, $r->name, $r->email, $r->email_ok, $r->phone, $r->sms_ok, $r->status, $r->confirmed_at, $r->unsubscribed_at, $r->consent_text, $r->consent_page, $r->consent_ip, $r->source );
			fputcsv( $out, array_map( static fn( $v ) => ( '' !== (string) $v && in_array( ( (string) $v )[0], array( '=', '+', '-', '@' ), true ) ) ? "'" . $v : (string) $v, $row ), ',', '"', '' );
		}
		exit;
	}
);

/* -------------------------------------------------------------------------
 * Privacy: export and erase.
 * ---------------------------------------------------------------------- */

add_filter(
	'wp_privacy_personal_data_exporters',
	static function ( $e ) {
		$e['taxi-peninsula-offers'] = array(
			'exporter_friendly_name' => __( 'News & offers sign-up', 'taxi-peninsula' ),
			'callback'               => static function ( $email ) {
				$row  = tp_sub_find( $email );
				$data = array();
				if ( $row ) {
					foreach ( array( 'name' => __( 'Name', 'taxi-peninsula' ), 'email' => __( 'Email', 'taxi-peninsula' ), 'phone' => __( 'Mobile', 'taxi-peninsula' ), 'status' => __( 'Status', 'taxi-peninsula' ), 'created' => __( 'Signed up', 'taxi-peninsula' ), 'consent_text' => __( 'Consent wording', 'taxi-peninsula' ), 'consent_ip' => __( 'IP address', 'taxi-peninsula' ) ) as $k => $label ) {
						$data[] = array( 'name' => $label, 'value' => (string) $row->$k );
					}
				}
				return array(
					'data' => $data ? array( array( 'group_id' => 'tp-offers', 'group_label' => __( 'News & offers', 'taxi-peninsula' ), 'item_id' => 'tp-sub', 'data' => $data ) ) : array(),
					'done' => true,
				);
			},
		);
		return $e;
	}
);

add_filter(
	'wp_privacy_personal_data_erasers',
	static function ( $e ) {
		$e['taxi-peninsula-offers'] = array(
			'eraser_friendly_name' => __( 'News & offers sign-up', 'taxi-peninsula' ),
			'callback'             => static function ( $email ) {
				global $wpdb;
				$row = tp_sub_find( $email );
				if ( $row ) {
					$wpdb->delete( tp_subs_table(), array( 'id' => $row->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				}
				return array( 'items_removed' => (bool) $row, 'items_retained' => false, 'messages' => array(), 'done' => true );
			},
		);
		return $e;
	}
);
