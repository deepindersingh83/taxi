<?php
/**
 * Security hardening that a theme can safely apply, each switchable under
 * Bookings → Settings → Security hardening, plus a status panel for the
 * hosting-level checks it can't fix by itself.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/* ---- Security headers (front end, admin and login) ---- */

function tp_security_headers() {
	if ( headers_sent() || ! tp_setting( 'sec_headers' ) ) {
		return;
	}
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(self), payment=(self "https://checkout.stripe.com")' );
	if ( is_ssl() && tp_setting( 'sec_hsts' ) ) {
		header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
	}
}
add_action( 'send_headers', 'tp_security_headers' );
add_action( 'admin_init', 'tp_security_headers', 1 );
add_action( 'login_init', 'tp_security_headers', 1 );

/* ---- Theme / plugin file editors ---- */

add_filter(
	'map_meta_cap',
	static function ( $caps, $cap ) {
		if ( in_array( $cap, array( 'edit_themes', 'edit_plugins', 'edit_files' ), true ) && tp_setting( 'sec_disable_file_edit' ) ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	},
	10,
	2
);

/* ---- XML-RPC ---- */

add_action(
	'init',
	static function () {
		if ( ! tp_setting( 'sec_disable_xmlrpc' ) ) {
			return;
		}
		// Refuse xmlrpc.php outright (system.multicall is a password-guessing amplifier).
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			status_header( 403 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			exit( 'XML-RPC is disabled on this site.' );
		}
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array' );
		remove_action( 'wp_head', 'rsd_link' );
		add_filter(
			'wp_headers',
			static function ( $headers ) {
				unset( $headers['X-Pingback'] );
				return $headers;
			}
		);
	}
);

/* ---- Hide usernames ---- */

add_action(
	'template_redirect',
	static function () {
		if ( ! tp_setting( 'sec_block_user_enum' ) || is_user_logged_in() ) {
			return;
		}
		// ?author=1 scans and /author/login-name/ archives reveal login names.
		if ( isset( $_GET['author'] ) || is_author() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	},
	1
);

add_filter(
	'rest_endpoints',
	static function ( $endpoints ) {
		if ( tp_setting( 'sec_block_user_enum' ) && ! is_user_logged_in() ) {
			foreach ( array_keys( $endpoints ) as $route ) {
				if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
					unset( $endpoints[ $route ] );
				}
			}
		}
		return $endpoints;
	}
);

// Author links would point at the now-hidden archives.
add_filter(
	'author_link',
	static function ( $link ) {
		return ( tp_setting( 'sec_block_user_enum' ) && ! is_user_logged_in() ) ? home_url( '/' ) : $link;
	}
);

// Don't reveal whether a username exists on the login screen.
add_filter(
	'login_errors',
	static function ( $error ) {
		if ( ! tp_setting( 'sec_block_user_enum' ) ) {
			return $error;
		}
		global $errors;
		$codes = ( $errors instanceof WP_Error ) ? $errors->get_error_codes() : array();
		if ( array_intersect( $codes, array( 'invalid_username', 'invalid_email', 'incorrect_password' ) ) ) {
			return __( '<strong>Error:</strong> The username, email or password is incorrect.', 'taxi-peninsula' );
		}
		return $error;
	}
);

// oEmbed responses include the author's name and profile URL.
add_filter(
	'oembed_response_data',
	static function ( $data ) {
		if ( tp_setting( 'sec_block_user_enum' ) ) {
			unset( $data['author_name'], $data['author_url'] );
		}
		return $data;
	}
);

/* ---- Version number ---- */

add_action(
	'init',
	static function () {
		if ( tp_setting( 'sec_hide_version' ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
		}
	}
);

/* ---- Real visitor IP behind Cloudflare ---- */

/**
 * Cloudflare's published edge ranges (https://www.cloudflare.com/ips/).
 * Extend with the `tp_trusted_proxy_ranges` filter.
 */
function tp_trusted_proxy_ranges() {
	return apply_filters(
		'tp_trusted_proxy_ranges',
		array(
			'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
			'108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
			'162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
			'2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
			'2a06:98c0::/29', '2c0f:f248::/32',
		)
	);
}

/**
 * Is $ip inside CIDR $range? Works for IPv4 and IPv6.
 */
function tp_ip_in_range( $ip, $range ) {
	list( $subnet, $bits ) = array_pad( explode( '/', $range, 2 ), 2, null );
	$ip_bin     = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid input returns false.
	$subnet_bin = @inet_pton( $subnet ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( false === $ip_bin || false === $subnet_bin || strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
		return false;
	}
	$bits  = null === $bits ? strlen( $ip_bin ) * 8 : (int) $bits;
	$bytes = intdiv( $bits, 8 );
	if ( substr( $ip_bin, 0, $bytes ) !== substr( $subnet_bin, 0, $bytes ) ) {
		return false;
	}
	$rem = $bits % 8;
	if ( ! $rem ) {
		return true;
	}
	$mask = chr( ( 0xff << ( 8 - $rem ) ) & 0xff );
	return ( $ip_bin[ $bytes ] & $mask ) === ( $subnet_bin[ $bytes ] & $mask );
}

/**
 * The connecting IP, or Cloudflare's CF-Connecting-IP when the request really
 * came through Cloudflare (so it can't be spoofed by sending the header directly).
 */
function tp_real_ip() {
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
	if ( ! tp_setting( 'sec_trusted_proxy' ) || empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
		return $remote;
	}
	foreach ( tp_trusted_proxy_ranges() as $range ) {
		if ( tp_ip_in_range( $remote, $range ) ) {
			$client = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			return filter_var( $client, FILTER_VALIDATE_IP ) ? $client : $remote;
		}
	}
	return $remote;
}

/* ---- Status panel (Bookings → Setup) ---- */

/**
 * Security checks: [label, ok (bool|null for info), advice].
 */
function tp_security_status() {
	$secret_keys = array( 'stripe_secret_key', 'stripe_webhook_secret', 'clicksend_api_key', 'twilio_auth_token', 'captcha_secret_key', 'google_maps_key' );
	$in_db       = array();
	$saved       = (array) get_option( 'tp_settings', array() );
	foreach ( $secret_keys as $key ) {
		if ( ! empty( $saved[ $key ] ) && ! defined( 'TP_' . strtoupper( $key ) ) ) {
			$in_db[] = 'TP_' . strtoupper( $key );
		}
	}

	$admin_user = get_user_by( 'login', 'admin' );

	return array(
		array( __( 'Site uses HTTPS', 'taxi-peninsula' ), 0 === strpos( home_url(), 'https://' ), __( 'Install an SSL certificate (most hosts offer free Let’s Encrypt) and update the site address under Settings → General.', 'taxi-peninsula' ) ),
		array( __( 'Errors are not shown to visitors', 'taxi-peninsula' ), ! ( defined( 'WP_DEBUG' ) && WP_DEBUG && ( ! defined( 'WP_DEBUG_DISPLAY' ) || WP_DEBUG_DISPLAY ) ), __( "In wp-config.php set define( 'WP_DEBUG_DISPLAY', false ); on the live site.", 'taxi-peninsula' ) ),
		array( __( 'File editors disabled', 'taxi-peninsula' ), ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) || tp_setting( 'sec_disable_file_edit' ), __( "Turn on the setting above, or add define( 'DISALLOW_FILE_EDIT', true ); to wp-config.php.", 'taxi-peninsula' ) ),
		array( __( 'API secrets kept in wp-config.php', 'taxi-peninsula' ), empty( $in_db ), $in_db ? sprintf( /* translators: %s: constant names */ __( 'Move these from the database to wp-config.php constants: %s', 'taxi-peninsula' ), implode( ', ', $in_db ) ) : '' ),
		array( __( 'No user called "admin"', 'taxi-peninsula' ), ! $admin_user, __( '"admin" is the first username attackers guess. Create a new administrator with a different name, log in as them and delete "admin" (assigning its content to the new user).', 'taxi-peninsula' ) ),
		array( __( 'WordPress is up to date', 'taxi-peninsula' ), ! tp_core_update_available(), __( 'Update WordPress under Dashboard → Updates, and keep plugins updated too.', 'taxi-peninsula' ) ),
		array( __( 'PHP 8.1 or newer', 'taxi-peninsula' ), version_compare( PHP_VERSION, '8.1', '>=' ), __( 'Ask your host to switch to a supported PHP version.', 'taxi-peninsula' ) ),
		array( __( 'Two-factor login for staff', 'taxi-peninsula' ), null, __( 'Recommended: install the "Two Factor" plugin (by WordPress contributors) and enable it for every administrator and Booking Manager.', 'taxi-peninsula' ) ),
		array( __( 'Backups', 'taxi-peninsula' ), null, __( 'Recommended: daily off-site backups of files and database (your host, or a plugin such as UpdraftPlus). Bookings live in the database.', 'taxi-peninsula' ) ),
	);
}

function tp_core_update_available() {
	$updates = get_site_transient( 'update_core' );
	if ( ! is_object( $updates ) || empty( $updates->updates ) ) {
		return false;
	}
	foreach ( $updates->updates as $u ) {
		if ( isset( $u->response ) && 'upgrade' === $u->response ) {
			return true;
		}
	}
	return false;
}

function tp_render_security_status() {
	echo '<table class="widefat striped tp-sec"><tbody>';
	foreach ( tp_security_status() as $row ) {
		list( $label, $ok, $advice ) = $row;
		if ( null === $ok ) {
			$icon = '<span class="dashicons dashicons-info" style="color:#2271b1" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Recommendation', 'taxi-peninsula' ) . '</span>';
		} elseif ( $ok ) {
			$icon = '<span class="dashicons dashicons-yes-alt" style="color:#00a32a" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Passed', 'taxi-peninsula' ) . '</span>';
		} else {
			$icon = '<span class="dashicons dashicons-warning" style="color:#d63638" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Needs attention', 'taxi-peninsula' ) . '</span>';
		}
		printf(
			'<tr><td style="width:2em">%1$s</td><td><strong>%2$s</strong>%3$s</td></tr>',
			$icon, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
			esc_html( $label ),
			( $ok && null !== $ok ) || ! $advice ? '' : '<br><span class="description">' . esc_html( $advice ) . '</span>'
		);
	}
	echo '</tbody></table>';
}
