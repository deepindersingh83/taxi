<?php
/**
 * Email check (Taxi settings → Email check): find out why customers are not
 * receiving emails.
 *
 * - Sender address settings, so emails come from your own domain
 *   (bookings@yourdomain) instead of wordpress@yourdomain.
 * - A log of every email the site tries to send: who, what, when, and
 *   whether the server accepted it (with the error if not).
 * - Each booking's history says whether the "booking received" email went.
 * - DNS checks for the sender domain (SPF, DMARC, MX), SMTP plugin
 *   detection, and a test email that looks like a real booking email.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['mail_from']      = array( 'email', __( 'Send emails from (address)', 'taxi-peninsula' ), 'text', '', __( 'An address at your own domain, e.g. bookings@taxipeninsula.com.au. Leave empty to use WordPress’s default (wordpress@your-domain), which many inboxes treat as spam. An SMTP plugin’s own "From" setting overrides this.', 'taxi-peninsula' ) );
		$s['mail_from_name'] = array( 'email', __( 'Send emails from (name)', 'taxi-peninsula' ), 'text', '', __( 'Leave empty to use the site name.', 'taxi-peninsula' ) );
		return $s;
	}
);
add_filter(
	'tp_settings_sections',
	static function ( $s ) {
		$s['email'] = array( __( 'Email sending', 'taxi-peninsula' ), __( 'See Taxi settings → Email check if customers are not getting emails.', 'taxi-peninsula' ) );
		return $s;
	}
);

add_filter(
	'wp_mail_from',
	static function ( $from ) {
		$set = sanitize_email( (string) tp_setting( 'mail_from' ) );
		return is_email( $set ) ? $set : $from;
	},
	5
);
add_filter(
	'wp_mail_from_name',
	static function ( $name ) {
		$set = trim( (string) tp_setting( 'mail_from_name' ) );
		return '' !== $set ? $set : ( 'WordPress' === $name ? wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) : $name );
	},
	5
);

/* -------------------------------------------------------------------------
 * Mail log.
 * ---------------------------------------------------------------------- */

function tp_mail_log_add( array $mail, $ok, $error = '' ) {
	$to  = is_array( $mail['to'] ?? '' ) ? implode( ', ', $mail['to'] ) : (string) ( $mail['to'] ?? '' );
	$log = (array) get_option( 'tp_mail_log', array() );
	array_unshift(
		$log,
		array(
			'time'    => time(),
			'to'      => mb_substr( $to, 0, 200 ),
			'subject' => mb_substr( (string) ( $mail['subject'] ?? '' ), 0, 200 ),
			'ok'      => (bool) $ok,
			'error'   => mb_substr( (string) $error, 0, 300 ),
		)
	);
	update_option( 'tp_mail_log', array_slice( $log, 0, 100 ), false );
	$GLOBALS['tp_last_mail'] = array( 'ok' => (bool) $ok, 'error' => (string) $error );
}

add_action(
	'wp_mail_succeeded',
	static function ( $mail ) {
		tp_mail_log_add( (array) $mail, true );
	}
);
add_action(
	'wp_mail_failed',
	static function ( $error ) {
		$data = (array) $error->get_error_data();
		tp_mail_log_add( $data, false, $error->get_error_message() );
	}
);

// Some email plugins send the mail themselves and skip WordPress's own result hooks.
add_filter(
	'pre_wp_mail',
	static function ( $return, $atts ) {
		if ( null !== $return ) {
			tp_mail_log_add( (array) $atts, (bool) $return, $return ? '' : __( 'Refused by an email plugin.', 'taxi-peninsula' ) );
		}
		return $return;
	},
	PHP_INT_MAX,
	2
);

// Old log entries contain customer email addresses: keep them 30 days.
add_action(
	'tp_privacy_cleanup',
	static function () {
		$log = array_filter( (array) get_option( 'tp_mail_log', array() ), static fn( $e ) => ( $e['time'] ?? 0 ) > time() - 30 * DAY_IN_SECONDS );
		update_option( 'tp_mail_log', array_values( $log ), false );
	}
);

// Each booking records whether its "booking received" email was sent.
add_action(
	'tp_booking_created',
	static function ( $post_id ) {
		$b = tp_get_booking( $post_id );
		if ( ! tp_opt( 'customer_emails' ) ) {
			$note = __( 'No confirmation email sent: customer emails are switched off (Customize → Bookings).', 'taxi-peninsula' );
		} elseif ( ! is_email( $b['email'] ) ) {
			$note = __( 'No confirmation email sent: the booking has no valid email address.', 'taxi-peninsula' );
		} else {
			$found = null;
			foreach ( array_slice( (array) get_option( 'tp_mail_log', array() ), 0, 5 ) as $e ) {
				if ( false !== stripos( $e['to'], $b['email'] ) && $e['time'] >= time() - 120 ) {
					$found = $e;
					break;
				}
			}
			if ( null === $found ) {
				$note = __( 'Confirmation email: not confirmed (an email plugin may send it later — see Taxi settings → Email check).', 'taxi-peninsula' );
			} elseif ( $found['ok'] ) {
				/* translators: %s: email */
				$note = sprintf( __( 'Confirmation email handed to the mail server for %s.', 'taxi-peninsula' ), $b['email'] );
			} else {
				/* translators: 1: email 2: error */
				$note = sprintf( __( 'Confirmation email to %1$s FAILED: %2$s', 'taxi-peninsula' ), $b['email'], $found['error'] );
			}
		}
		tp_add_log( $post_id, array( 'note' => $note ) );
	},
	20
);

/* -------------------------------------------------------------------------
 * Checks.
 * ---------------------------------------------------------------------- */

function tp_mail_smtp_plugin() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$known = array(
		'wp-mail-smtp/wp_mail_smtp.php'             => 'WP Mail SMTP',
		'wp-mail-smtp-pro/wp_mail_smtp.php'         => 'WP Mail SMTP Pro',
		'fluent-smtp/fluent-smtp.php'               => 'FluentSMTP',
		'post-smtp/postman-smtp.php'                => 'Post SMTP',
		'easy-wp-smtp/easy-wp-smtp.php'             => 'Easy WP SMTP',
		'smtp-mailer/main.php'                      => 'SMTP Mailer',
		'gosmtp/gosmtp.php'                         => 'GoSMTP',
		'wp-ses/wp-ses.php'                         => 'WP Offload SES',
		'mailpoet/mailpoet.php'                     => '',
		'sendinblue-mailin/sendinblue.php'          => 'Brevo',
		'suremails/suremails.php'                   => 'SureMail',
		'site-mailer/site-mailer.php'               => 'Site Mailer',
	);
	foreach ( $known as $file => $name ) {
		if ( $name && is_plugin_active( $file ) ) {
			return $name;
		}
	}
	return '';
}

/**
 * WP Mail SMTP's settings (constants in wp-config.php win), or null.
 *
 * @return array|null mailer, from, force, host, user
 */
function tp_wpms_settings() {
	if ( 'WP Mail SMTP' !== tp_mail_smtp_plugin() && 'WP Mail SMTP Pro' !== tp_mail_smtp_plugin() ) {
		return null;
	}
	$o   = (array) get_option( 'wp_mail_smtp', array() );
	$get = static function ( $group, $key, $const ) use ( $o ) {
		return defined( $const ) ? constant( $const ) : ( $o[ $group ][ $key ] ?? '' );
	};
	return array(
		'mailer' => (string) $get( 'mail', 'mailer', 'WPMS_MAILER' ),
		'from'   => (string) $get( 'mail', 'from_email', 'WPMS_MAIL_FROM' ),
		'force'  => (bool) $get( 'mail', 'from_email_force', 'WPMS_MAIL_FROM_FORCE' ),
		'host'   => (string) $get( 'smtp', 'host', 'WPMS_SMTP_HOST' ),
		'user'   => (string) $get( 'smtp', 'user', 'WPMS_SMTP_USER' ),
	);
}

/**
 * The address WordPress will send from right now.
 */
function tp_mail_from_now() {
	$w = tp_wpms_settings();
	if ( $w && $w['force'] && is_email( $w['from'] ) ) {
		return $w['from'];
	}
	$domain = wp_parse_url( network_home_url(), PHP_URL_HOST );
	$domain = preg_replace( '/^www\./', '', (string) $domain );
	return (string) apply_filters( 'wp_mail_from', 'wordpress@' . $domain );
}

/**
 * Extra checks for WP Mail SMTP: is it really using SMTP, and does the
 * mailbox it logs into match the address it sends from?
 */
function tp_wpms_checks( $from ) {
	$w = tp_wpms_settings();
	if ( ! $w ) {
		return array();
	}
	$out   = array();
	$label = __( 'WP Mail SMTP setup', 'taxi-peninsula' );
	if ( '' === $w['mailer'] || 'mail' === $w['mailer'] ) {
		return array( array( 'fail', $label, __( 'The plugin is installed but its Mailer is "Default (none)", so email still goes out through the web server’s PHP mail. In WP Mail SMTP → Settings choose "Other SMTP" (or your provider) and enter the mailbox details.', 'taxi-peninsula' ) ) );
	}
	$names = array( 'smtp' => 'Other SMTP', 'gmail' => 'Google / Gmail', 'outlook' => 'Microsoft 365 / Outlook', 'smtpcom' => 'SMTP.com', 'sendinblue' => 'Brevo', 'mailgun' => 'Mailgun', 'sendgrid' => 'SendGrid', 'postmark' => 'Postmark', 'sparkpost' => 'SparkPost', 'amazonses' => 'Amazon SES', 'zoho' => 'Zoho Mail', 'smtp2go' => 'SMTP2GO', 'elasticemail' => 'Elastic Email', 'mailjet' => 'Mailjet' );
	$what  = $names[ $w['mailer'] ] ?? $w['mailer'];
	if ( 'smtp' === $w['mailer'] ) {
		/* translators: 1: host 2: user */
		$what .= ' — ' . sprintf( __( 'server %1$s, logged in as %2$s', 'taxi-peninsula' ), $w['host'] ?: '?', $w['user'] ?: __( '(no login)', 'taxi-peninsula' ) );
	}
	$out[] = array( 'ok', $label, $what );

	$from_domain = strtolower( (string) substr( strrchr( $from, '@' ), 1 ) );
	$user_domain = strtolower( (string) substr( strrchr( $w['user'], '@' ), 1 ) );
	if ( 'smtp' === $w['mailer'] && $user_domain && $from_domain && $user_domain !== $from_domain ) {
		/* translators: 1: login 2: from address */
		$out[] = array( 'fail', __( 'Mailbox and sender match', 'taxi-peninsula' ), sprintf( __( 'The plugin logs in as %1$s but sends as %2$s. Mail sent "as" a different domain fails that domain’s SPF/DKIM checks, so Gmail and Outlook junk it or drop it — and some servers silently discard it. Log in with the same mailbox you send from (change both together).', 'taxi-peninsula' ), $w['user'], $from ) );
	}
	if ( $w['force'] && is_email( $w['from'] ) && tp_setting( 'mail_from' ) && strtolower( $w['from'] ) !== strtolower( (string) tp_setting( 'mail_from' ) ) ) {
		/* translators: 1: forced address 2: theme setting */
		$out[] = array( 'warn', __( 'Two "from" settings', 'taxi-peninsula' ), sprintf( __( 'WP Mail SMTP has "Force From Email" on, so every email goes from %1$s and the theme’s "Send emails from" (%2$s) is ignored. Change the address in WP Mail SMTP → Settings.', 'taxi-peninsula' ), $w['from'], tp_setting( 'mail_from' ) ) );
	}
	return $out;
}

/**
 * TXT records for a host, or null if DNS lookups don't work here.
 */
function tp_mail_txt( $host ) {
	if ( ! function_exists( 'dns_get_record' ) ) {
		return null;
	}
	$recs = @dns_get_record( $host, DNS_TXT ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- DNS failures are reported below.
	if ( false === $recs ) {
		return null;
	}
	$out = array();
	foreach ( $recs as $r ) {
		$out[] = isset( $r['entries'] ) ? implode( '', $r['entries'] ) : ( $r['txt'] ?? '' );
	}
	return $out;
}

/**
 * Checks: [status ok|warn|fail|off, label, text].
 */
function tp_mail_checks() {
	$out    = array();
	$from   = tp_mail_from_now();
	$domain = strtolower( substr( strrchr( $from, '@' ), 1 ) );
	$site   = strtolower( preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
	$smtp   = tp_mail_smtp_plugin();

	$out[] = tp_opt( 'customer_emails' )
		? array( 'ok', __( 'Customer emails', 'taxi-peninsula' ), __( 'Switched on: customers get a "booking received" email and status updates.', 'taxi-peninsula' ) )
		: array( 'fail', __( 'Customer emails', 'taxi-peninsula' ), __( 'Switched OFF. Turn on "Email customers a confirmation and status updates" in Appearance → Customize → Bookings.', 'taxi-peninsula' ) );

	$out[] = $smtp
		/* translators: %s: plugin */
		? array( 'ok', __( 'Sending service', 'taxi-peninsula' ), sprintf( __( '%s is active, so email goes through your email provider. Use its own test and email log if messages still go missing.', 'taxi-peninsula' ), $smtp ) )
		: array( 'warn', __( 'Sending service', 'taxi-peninsula' ), __( 'No SMTP plugin: email is sent by the web server itself (PHP mail). Gmail, Outlook and Bigpond often junk or silently drop this mail. This is the most common reason customers get nothing. Install a free SMTP plugin (WP Mail SMTP or FluentSMTP) and connect it to the mailbox you already use (Microsoft 365, Google Workspace, or your host’s email).', 'taxi-peninsula' ) );

	if ( 0 === strpos( $from, 'wordpress@' ) && ! tp_setting( 'mail_from' ) ) {
		/* translators: %s: address */
		$out[] = array( 'warn', __( 'Sender address', 'taxi-peninsula' ), sprintf( __( 'Emails come from %s — an address nobody reads, and one spam filters distrust. Set a real address at your domain in Taxi settings → Settings → Email sending.', 'taxi-peninsula' ), $from ) );
	} elseif ( $domain && $site && $domain !== $site && ! str_ends_with( $site, '.' . $domain ) && ! $smtp ) {
		/* translators: 1: address 2: website domain */
		$out[] = array( 'warn', __( 'Sender address', 'taxi-peninsula' ), sprintf( __( 'Emails come from %1$s but the website is %2$s. Without an SMTP plugin, mail "from" another domain is usually rejected. Use an address at %2$s, or connect an SMTP plugin to %1$s’s mailbox.', 'taxi-peninsula' ), $from, $site ) );
	} else {
		/* translators: %s: address */
		$out[] = array( 'ok', __( 'Sender address', 'taxi-peninsula' ), sprintf( __( 'Emails come from %s.', 'taxi-peninsula' ), $from ) );
	}

	$out = array_merge( $out, tp_wpms_checks( $from ) );

	if ( in_array( $domain, array( 'gmail.com', 'outlook.com', 'hotmail.com', 'bigpond.com', 'yahoo.com', 'icloud.com', 'live.com' ), true ) && ! $smtp ) {
		/* translators: %s: domain */
		$out[] = array( 'fail', __( 'Free email address', 'taxi-peninsula' ), sprintf( __( 'Mail "from" a %s address sent by your web server fails the provider’s security checks and is rejected. Use an address at your own domain, or connect an SMTP plugin to that mailbox.', 'taxi-peninsula' ), $domain ) );
	}

	// DNS.
	$spf = $domain ? tp_mail_txt( $domain ) : null;
	if ( null === $spf ) {
		$out[] = array( 'off', __( 'SPF record', 'taxi-peninsula' ), __( 'This server cannot look up DNS. Check SPF at mxtoolbox.com instead.', 'taxi-peninsula' ) );
	} else {
		$rec   = array_values( array_filter( $spf, static fn( $t ) => 0 === stripos( $t, 'v=spf1' ) ) );
		$out[] = $rec
			/* translators: 1: domain 2: record */
			? array( 'ok', __( 'SPF record', 'taxi-peninsula' ), sprintf( __( '%1$s has: %2$s — make sure it includes whoever actually sends your mail (your host or SMTP provider).', 'taxi-peninsula' ), $domain, $rec[0] ) )
			/* translators: %s: domain */
			: array( 'fail', __( 'SPF record', 'taxi-peninsula' ), sprintf( __( '%s has no SPF record, so receiving servers cannot tell your emails are genuine. Ask whoever manages your domain to add one for your email provider.', 'taxi-peninsula' ), $domain ) );
		$dmarc = tp_mail_txt( '_dmarc.' . $domain );
		$has   = $dmarc && array_filter( $dmarc, static fn( $t ) => 0 === stripos( $t, 'v=DMARC1' ) );
		$out[] = $has
			? array( 'ok', __( 'DMARC record', 'taxi-peninsula' ), __( 'Found.', 'taxi-peninsula' ) )
			/* translators: %s: domain */
			: array( 'warn', __( 'DMARC record', 'taxi-peninsula' ), sprintf( __( '%s has no DMARC record. Gmail and Yahoo now expect one from businesses that send email.', 'taxi-peninsula' ), $domain ) );
	}

	// Recent results.
	$log    = (array) get_option( 'tp_mail_log', array() );
	$failed = array_filter( $log, static fn( $e ) => empty( $e['ok'] ) && $e['time'] > time() - 7 * DAY_IN_SECONDS );
	if ( ! $log ) {
		$out[] = array( 'off', __( 'Recent emails', 'taxi-peninsula' ), __( 'Nothing recorded yet. Send a test below.', 'taxi-peninsula' ) );
	} elseif ( $failed ) {
		$first = reset( $failed );
		/* translators: 1: count 2: error */
		$out[] = array( 'fail', __( 'Recent emails', 'taxi-peninsula' ), sprintf( _n( '%1$d email failed in the last 7 days. Latest error: %2$s', '%1$d emails failed in the last 7 days. Latest error: %2$s', count( $failed ), 'taxi-peninsula' ), count( $failed ), $first['error'] ) );
	} else {
		$out[] = array( 'ok', __( 'Recent emails', 'taxi-peninsula' ), __( 'The mail server accepted every recent email, so they left your website. If customers still get nothing, the mail server or the customer’s provider is junking or dropping them. Your host’s control panel shows what happened to each one: in cPanel, Email → Track Delivery (look for "rejected", "deferred" or "bounced"). Also check the sending mailbox for bounce messages.', 'taxi-peninsula' ) );
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Page.
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( tp_admin_parent( 'settings' ), __( 'Email check', 'taxi-peninsula' ), __( 'Email check', 'taxi-peninsula' ), 'manage_options', 'tp-email-check', 'tp_render_mail_check' );
	},
	30
);

add_action(
	'admin_post_tp_mail_test',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'tp_mail_test' );
		$to = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
		if ( ! is_email( $to ) ) {
			set_transient( 'tp_admin_notice_' . get_current_user_id(), array( 'error', __( 'Please enter a valid email address.', 'taxi-peninsula' ) ), 60 );
		} else {
			$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$GLOBALS['tp_last_mail'] = null;
			$ok   = wp_mail(
				$to,
				/* translators: %s: site name */
				sprintf( __( '%s booking received — TEST', 'taxi-peninsula' ), $site ),
				__( 'Hi,', 'taxi-peninsula' ) . "\n\n" . __( 'This is a test of the email your customers get when they book. If it arrived in your inbox (not junk), customer emails are working.', 'taxi-peninsula' ) . "\n\n" . __( 'Reference', 'taxi-peninsula' ) . ": TP-TEST\n\n" . $site . "\n",
				tp_mail_headers( tp_opt( 'email' ) )
			);
			$err = $GLOBALS['tp_last_mail']['error'] ?? '';
			set_transient(
				'tp_admin_notice_' . get_current_user_id(),
				$ok
					/* translators: %s: email */
					? array( 'success', sprintf( __( 'The test was handed to the mail server for %s. Check the inbox AND the junk folder in the next few minutes. If it is in junk or missing, see the warnings below.', 'taxi-peninsula' ), $to ) )
					/* translators: %s: error */
					: array( 'error', sprintf( __( 'The server refused to send the test: %s', 'taxi-peninsula' ), $err ?: __( 'no reason given (PHP mail may be disabled on this host).', 'taxi-peninsula' ) ) ),
				60
			);
		}
		wp_safe_redirect( admin_url( 'admin.php?page=tp-email-check' ) );
		exit;
	}
);

add_action(
	'admin_post_tp_mail_log_clear',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'tp_mail_log_clear' );
		delete_option( 'tp_mail_log' );
		wp_safe_redirect( admin_url( 'admin.php?page=tp-email-check' ) );
		exit;
	}
);

function tp_render_mail_check() {
	$icons = array( 'ok' => '✅', 'warn' => '⚠️', 'fail' => '❌', 'off' => '➖' );
	$log   = (array) get_option( 'tp_mail_log', array() );
	?>
	<div class="wrap tp-mail-check">
		<h1><?php esc_html_e( 'Email check', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'Why customers might not be getting your emails, and what to fix. Emails leave your site in two steps: the site hands them to a mail server (shown in the log below), then that server delivers them — or the customer’s provider junks or drops them. Most problems are in the second step.', 'taxi-peninsula' ); ?></p>

		<table class="widefat striped" style="max-width:1000px">
			<tbody>
				<?php foreach ( tp_mail_checks() as $c ) : ?>
					<tr><td style="width:2em"><?php echo esc_html( $icons[ $c[0] ] ); ?></td><td style="width:12em"><strong><?php echo esc_html( $c[1] ); ?></strong></td><td><?php echo esc_html( $c[2] ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Send a test booking email', 'taxi-peninsula' ); ?></h2>
		<p><?php esc_html_e( 'Try a Gmail or Outlook address too — they are the strictest.', 'taxi-peninsula' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tp_mail_test">
			<?php wp_nonce_field( 'tp_mail_test' ); ?>
			<label for="tp-mail-to" class="screen-reader-text"><?php esc_html_e( 'Send to', 'taxi-peninsula' ); ?></label>
			<input type="email" id="tp-mail-to" name="to" class="regular-text" required value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
			<?php submit_button( __( 'Send test', 'taxi-peninsula' ), 'primary', 'submit', false ); ?>
		</form>

		<h2><?php esc_html_e( 'Recent emails', 'taxi-peninsula' ); ?></h2>
		<?php if ( ! $log ) : ?>
			<p><?php esc_html_e( 'None yet. Every email the site sends from now on is listed here for 30 days.', 'taxi-peninsula' ); ?></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:1000px">
				<thead><tr><th><?php esc_html_e( 'When', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'To', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Subject', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Result', 'taxi-peninsula' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( array_slice( $log, 0, 50 ) as $e ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( 'D j M g:ia', (int) $e['time'] ) ); ?></td>
							<td><?php echo esc_html( $e['to'] ); ?></td>
							<td><?php echo esc_html( $e['subject'] ); ?></td>
							<td><?php echo $e['ok'] ? '✅ ' . esc_html__( 'Accepted by mail server', 'taxi-peninsula' ) : '❌ ' . esc_html( $e['error'] ?: __( 'Failed', 'taxi-peninsula' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px">
				<input type="hidden" name="action" value="tp_mail_log_clear"><?php wp_nonce_field( 'tp_mail_log_clear' ); ?>
				<button class="button-link button-link-delete"><?php esc_html_e( 'Clear the list', 'taxi-peninsula' ); ?></button>
			</form>
		<?php endif; ?>

		<h2><?php esc_html_e( 'How to fix it (most reliable first)', 'taxi-peninsula' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Install the free "WP Mail SMTP" or "FluentSMTP" plugin and connect it to the mailbox you already use for support@ (Microsoft 365 or Google Workspace), or to your host’s SMTP server. Send its test email.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Set "Send emails from" to an address at your domain (Taxi settings → Settings → Email sending) — the same mailbox the SMTP plugin uses.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Ask whoever manages your domain to add SPF, DKIM and DMARC records for that email provider. Your provider’s help pages give the exact values.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Send a test here to a Gmail and an Outlook address, and check the junk folder.', 'taxi-peninsula' ); ?></li>
		</ol>
	</div>
	<?php
}

// Dashboard warning when recent emails failed.
add_action(
	'admin_notices',
	static function () {
		$s = get_current_screen();
		if ( ! $s || 'dashboard' !== $s->id || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$failed = array_filter( (array) get_option( 'tp_mail_log', array() ), static fn( $e ) => empty( $e['ok'] ) && $e['time'] > time() - 2 * DAY_IN_SECONDS );
		if ( $failed ) {
			printf(
				'<div class="notice notice-error"><p>%s <a href="%s">%s</a></p></div>',
				/* translators: %d: count */
				esc_html( sprintf( _n( '%d email from the website failed to send in the last 2 days — customers may not be getting booking emails.', '%d emails from the website failed to send in the last 2 days — customers may not be getting booking emails.', count( $failed ), 'taxi-peninsula' ), count( $failed ) ) ),
				esc_url( admin_url( 'admin.php?page=tp-email-check' ) ),
				esc_html__( 'Open Email check', 'taxi-peninsula' )
			);
		}
	}
);
