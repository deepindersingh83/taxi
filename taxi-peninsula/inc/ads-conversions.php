<?php
/**
 * Google Ads offline conversions: tell Google Ads which ad clicks became
 * completed trips, including trips booked by phone, so it learns from real
 * bookings instead of form views.
 *
 * - Ad clicks: the Google click ID (gclid) in the landing URL is kept for 90
 *   days and saved on online bookings.
 * - Phone bookings: staff pick "How did they find us?" on the booking screen.
 *   Trips marked "Google Ads" are matched by Google from the customer's
 *   email and phone, which are sent only as SHA-256 hashes ("enhanced
 *   conversions for leads").
 * - Reports → Google Ads conversions shows a private link to a CSV file in
 *   Google Ads' import format. In Google Ads: Goals → Conversions → Uploads →
 *   Schedules → add a schedule with "HTTPS" and that link, daily.
 *
 * Only completed trips from the last 90 days are listed, never cancelled or
 * fake ones. Value = the fare.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'tp_settings_sections',
	static function ( $s ) {
		$s['ads'] = array( __( 'Google Ads conversions', 'taxi-peninsula' ), __( 'Sends completed trips (online and phone bookings) back to Google Ads. Setup steps are under Reports → Google Ads conversions.', 'taxi-peninsula' ) );
		return $s;
	}
);

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['ads_conversions'] = array( 'ads', __( 'Make the conversions file', 'taxi-peninsula' ), 'checkbox', false, '' );
		$s['ads_conv_name']   = array( 'ads', __( 'Conversion action name in Google Ads', 'taxi-peninsula' ), 'text', 'Completed trip', __( 'Must match the name of an “Import → Manual import using API or uploads” conversion action, letter for letter.', 'taxi-peninsula' ) );
		$s['ads_hashed']      = array( 'ads', __( 'Include hashed email and phone', 'taxi-peninsula' ), 'checkbox', true, __( 'Needed for phone bookings, which have no click ID. Only SHA-256 hashes are sent. Turn on “Enhanced conversions for leads” in Google Ads and mention it in your privacy policy.', 'taxi-peninsula' ) );
		return $s;
	}
);

add_filter(
	'tp_settings_tabs',
	static function ( $tabs ) {
		$tabs['marketing'][1][] = 'ads';
		return $tabs;
	}
);

/** Click ID from the URL or the 90-day cookie: [type, value, time] or null. */
function tp_ads_click() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only tags.
	foreach ( array( 'gclid', 'gbraid', 'wbraid' ) as $k ) {
		if ( ! empty( $_GET[ $k ] ) ) {
			$v = preg_replace( '/[^A-Za-z0-9_\-]/', '', sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) );
			if ( $v ) {
				return array( $k, substr( $v, 0, 200 ), time() );
			}
		}
	}
	// phpcs:enable
	if ( ! empty( $_COOKIE['tp_gclid'] ) ) {
		$p = explode( '|', sanitize_text_field( wp_unslash( $_COOKIE['tp_gclid'] ) ) );
		if ( 3 === count( $p ) && in_array( $p[0], array( 'gclid', 'gbraid', 'wbraid' ), true ) && (int) $p[2] > time() - 90 * DAY_IN_SECONDS ) {
			return array( $p[0], preg_replace( '/[^A-Za-z0-9_\-]/', '', $p[1] ), (int) $p[2] );
		}
	}
	return null;
}

add_action(
	'init',
	static function () {
		if ( is_admin() || wp_doing_ajax() || headers_sent() || empty( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$click = tp_ads_click();
		if ( $click && $click[2] >= time() - 5 ) {
			$value = implode( '|', $click );
			setcookie( 'tp_gclid', $value, array( 'expires' => time() + 90 * DAY_IN_SECONDS, 'path' => COOKIEPATH ?: '/', 'domain' => COOKIE_DOMAIN ?: '', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
			$_COOKIE['tp_gclid'] = $value;
		}
	},
	1
);

// Online bookings keep the click.
add_action(
	'tp_booking_created',
	static function ( $first_id, $ids ) {
		if ( is_admin() ) {
			return; // Staff entering a phone booking: their own browser's click is not the customer's.
		}
		$click = tp_ads_click();
		if ( $click ) {
			foreach ( $ids ?: array( $first_id ) as $id ) {
				update_post_meta( $id, '_tp_gclid', implode( '|', $click ) );
			}
		}
	},
	31,
	2
);

/* =========================================================================
 * Phone bookings: "How did they find us?"
 * ====================================================================== */

function tp_heard_options() {
	return array(
		''          => __( '— Not asked —', 'taxi-peninsula' ),
		'google_ad' => __( 'Google Ads (saw an ad)', 'taxi-peninsula' ),
		'google'    => __( 'Google search or Maps', 'taxi-peninsula' ),
		'returning' => __( 'Travelled with us before', 'taxi-peninsula' ),
		'friend'    => __( 'Friend or family', 'taxi-peninsula' ),
		'partner'   => __( 'Hospital, clinic or care provider', 'taxi-peninsula' ),
		'other'     => __( 'Other', 'taxi-peninsula' ),
	);
}

add_action(
	'tp_status_box_fields',
	static function ( $post ) {
		$heard = (string) get_post_meta( $post->ID, '_tp_heard', true );
		$click = get_post_meta( $post->ID, '_tp_gclid', true );
		echo '<p><label for="tp_heard"><strong>' . esc_html__( 'How did they find us?', 'taxi-peninsula' ) . '</strong></label><br><select id="tp_heard" name="tp_heard">';
		foreach ( tp_heard_options() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $heard, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		if ( $click ) {
			echo '<br><span class="description">' . esc_html__( 'Booked online after a Google ad click.', 'taxi-peninsula' ) . '</span>';
		}
		echo '</p>';
	},
	30
);

add_action(
	'save_post_tp_booking',
	static function ( $post_id ) {
		if ( ! isset( $_POST['_tp_admin_nonce'], $_POST['tp_heard'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_admin_nonce'] ) ), 'tp_save_booking' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$heard = sanitize_key( wp_unslash( $_POST['tp_heard'] ) );
		if ( ! isset( tp_heard_options()[ $heard ] ) ) {
			return;
		}
		if ( '' === $heard ) {
			delete_post_meta( $post_id, '_tp_heard' );
			return;
		}
		update_post_meta( $post_id, '_tp_heard', $heard );
		$sources = array( 'google_ad' => 'google / cpc / phone', 'google' => 'google / organic / phone', 'friend' => 'word of mouth / phone', 'partner' => 'partner / phone' );
		if ( isset( $sources[ $heard ] ) ) {
			update_post_meta( $post_id, '_tp_source', $sources[ $heard ] );
		}
	},
	20
);

/* =========================================================================
 * The conversions file.
 * ====================================================================== */

function tp_ads_feed_key( $renew = false ) {
	$key = (string) get_option( 'tp_ads_feed_key' );
	if ( '' === $key || $renew ) {
		$key = strtolower( wp_generate_password( 32, false ) );
		update_option( 'tp_ads_feed_key', $key, false );
	}
	return $key;
}

function tp_ads_feed_url() {
	return add_query_arg( 'tp_ads_conversions', tp_ads_feed_key(), home_url( '/' ) );
}

/** Google's normalising: trimmed, lower case; Gmail addresses without dots. */
function tp_ads_hash_email( $email ) {
	$email = strtolower( trim( (string) $email ) );
	if ( ! is_email( $email ) ) {
		return '';
	}
	list( $user, $domain ) = explode( '@', $email, 2 );
	if ( in_array( $domain, array( 'gmail.com', 'googlemail.com' ), true ) ) {
		$user = str_replace( '.', '', $user );
	}
	return hash( 'sha256', $user . '@' . $domain );
}

function tp_ads_hash_phone( $phone ) {
	$p = tp_au_phone( (string) $phone );
	return $p && $p['e164'] ? hash( 'sha256', $p['e164'] ) : '';
}

/**
 * Rows for the file: completed trips from the last 90 days that came from an ad.
 *
 * @return array[] Each: click, name, time, value, email, phone, id.
 */
function tp_ads_rows() {
	$since  = wp_date( 'Y-m-d', time() - 90 * DAY_IN_SECONDS );
	$name   = (string) tp_setting( 'ads_conv_name' ) ?: 'Completed trip';
	$hashed = (bool) tp_setting( 'ads_hashed' );
	$ids    = get_posts(
		array(
			'post_type'   => 'tp_booking',
			'post_status' => 'publish',
			'numberposts' => 2000,
			'fields'      => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_query'  => array(
				array( 'key' => '_tp_status', 'value' => 'completed' ),
				array( 'key' => '_tp_date', 'value' => $since, 'compare' => '>=' ),
				array(
					'relation' => 'OR',
					array( 'key' => '_tp_gclid', 'compare' => 'EXISTS' ),
					array( 'key' => '_tp_heard', 'value' => 'google_ad' ),
				),
			),
		)
	);
	$rows = array();
	foreach ( $ids as $id ) {
		if ( function_exists( 'tp_is_spam_booking' ) && tp_is_spam_booking( $id ) ) {
			continue;
		}
		$b     = tp_get_booking( $id );
		$click = explode( '|', (string) get_post_meta( $id, '_tp_gclid', true ) );
		$gclid = 'gclid' === $click[0] && ! empty( $click[1] ) ? $click[1] : '';
		$email = $hashed ? tp_ads_hash_email( $b['email'] ) : '';
		$phone = $hashed ? tp_ads_hash_phone( $b['phone'] ) : '';
		if ( '' === $gclid && '' === $email && '' === $phone ) {
			continue; // Nothing Google can match on.
		}
		$time = strtotime( trim( $b['date'] . ' ' . ( $b['time'] ?: '12:00' ) ) . ' ' . wp_timezone_string() );
		if ( ! empty( $click[2] ) && $time <= (int) $click[2] ) {
			$time = (int) $click[2] + HOUR_IN_SECONDS; // A conversion must come after its click.
		}
		$rows[] = array(
			'id'    => $id,
			'click' => $gclid,
			'name'  => $name,
			'time'  => wp_date( 'Y-m-d H:i:s', $time ),
			'value' => is_numeric( $b['fare'] ) ? number_format( (float) $b['fare'], 2, '.', '' ) : '',
			'email' => $email,
			'phone' => $phone,
		);
	}
	return $rows;
}

/** The CSV in Google Ads' "conversions from clicks" format. */
function tp_ads_csv( array $rows ) {
	$csv = static function ( array $cells ) {
		return implode( ',', array_map( static fn( $c ) => preg_match( '/[",\n]/', (string) $c ) ? '"' . str_replace( '"', '""', $c ) . '"' : (string) $c, $cells ) ) . "\n";
	};
	// Google wants a zone name (Australia/Melbourne) or an offset without the colon (+1100).
	$zone = wp_timezone_string();
	$out  = 'Parameters:TimeZone=' . ( false !== strpos( $zone, '/' ) ? $zone : str_replace( ':', '', $zone ) ) . "\n";
	$out .= $csv( array( 'Google Click ID', 'Conversion Name', 'Conversion Time', 'Conversion Value', 'Conversion Currency', 'Email', 'Phone Number' ) );
	foreach ( $rows as $r ) {
		$out .= $csv( array( $r['click'], $r['name'], $r['time'], $r['value'], '' === $r['value'] ? '' : 'AUD', $r['email'], $r['phone'] ) );
	}
	return $out;
}

add_action(
	'template_redirect',
	static function () {
		if ( ! isset( $_GET['tp_ads_conversions'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$given = sanitize_key( wp_unslash( $_GET['tp_ads_conversions'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the key is the check.
		$key   = (string) get_option( 'tp_ads_feed_key' );
		if ( ! tp_setting( 'ads_conversions' ) || '' === $key || ! hash_equals( $key, $given ) || ! is_ssl() && ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: inline; filename="google-ads-conversions.csv"' );
		header( 'X-Robots-Tag: noindex' );
		update_option( 'tp_ads_feed_fetched', time(), false );
		echo tp_ads_csv( tp_ads_rows() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV.
		exit;
	},
	0
);

/* =========================================================================
 * Reports → Google Ads conversions.
 * ====================================================================== */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( tp_admin_parent( 'reports' ), __( 'Google Ads conversions', 'taxi-peninsula' ), __( 'Google Ads conversions', 'taxi-peninsula' ), 'manage_options', 'tp-ads-conversions', 'tp_render_ads_page' );
	},
	45
);

add_action(
	'admin_post_tp_ads_feed',
	static function () {
		check_admin_referer( 'tp_ads_feed' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'taxi-peninsula' ) );
		}
		if ( isset( $_POST['download'] ) ) {
			nocache_headers();
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="google-ads-conversions-' . wp_date( 'Y-m-d' ) . '.csv"' );
			echo tp_ads_csv( tp_ads_rows() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV.
			exit;
		}
		tp_ads_feed_key( true );
		set_transient( 'tp_admin_notice_' . get_current_user_id(), array( 'success', __( 'New link made. Update the schedule in Google Ads — the old link no longer works.', 'taxi-peninsula' ) ), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=tp-ads-conversions' ) );
		exit;
	}
);

function tp_render_ads_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$on      = (bool) tp_setting( 'ads_conversions' );
	$rows    = $on ? tp_ads_rows() : array();
	$fetched = (int) get_option( 'tp_ads_feed_fetched' );
	$clicks  = count( array_filter( wp_list_pluck( $rows, 'click' ) ) );
	?>
	<div class="wrap tp-ads">
		<h1><?php esc_html_e( 'Google Ads conversions', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'Google Ads learns which searches bring real customers when it is told about completed trips — including people who saw an ad and then phoned. This page makes a file Google Ads fetches by itself every day.', 'taxi-peninsula' ); ?></p>
		<?php if ( ! $on ) : ?>
			<div class="notice notice-info inline"><p>
				<?php esc_html_e( 'Turn on “Make the conversions file” in Settings → Marketing & SEO → Google Ads conversions first.', 'taxi-peninsula' ); ?>
				<a href="<?php echo esc_url( tp_settings_url( 'ads' ) ); ?>"><?php esc_html_e( 'Open settings', 'taxi-peninsula' ); ?></a>
			</p></div>
		<?php else : ?>
			<h2><?php esc_html_e( 'Set it up once in Google Ads', 'taxi-peninsula' ); ?></h2>
			<ol>
				<li><?php echo esc_html( sprintf( /* translators: %s: conversion name */ __( 'Goals → Conversions → New conversion action → Import → “Manual import using API or uploads” → “Track conversions from clicks”. Name it exactly “%s”, category “Purchase”, value “Use different values for each conversion”.', 'taxi-peninsula' ), tp_setting( 'ads_conv_name' ) ?: 'Completed trip' ) ); ?></li>
				<li><?php esc_html_e( 'In the conversion action’s settings, turn on “Enhanced conversions for leads” (for phone bookings, which have no click ID).', 'taxi-peninsula' ); ?></li>
				<li><?php esc_html_e( 'Goals → Uploads → Schedules → New schedule → Source “HTTPS”, paste the link below, and choose Daily.', 'taxi-peninsula' ); ?></li>
				<li><?php esc_html_e( 'Ask staff to choose “How did they find us?” on phone bookings — “Google Ads (saw an ad)” is what counts.', 'taxi-peninsula' ); ?></li>
			</ol>
			<p><label for="tp-ads-url"><strong><?php esc_html_e( 'Private link (keep it secret)', 'taxi-peninsula' ); ?></strong></label><br>
				<input id="tp-ads-url" type="text" class="large-text code" readonly value="<?php echo esc_attr( tp_ads_feed_url() ); ?>" onfocus="this.select()"></p>
			<p class="description">
				<?php
				echo esc_html(
					$fetched
						/* translators: %s: time ago */
						? sprintf( __( 'Last fetched %s ago.', 'taxi-peninsula' ), human_time_diff( $fetched ) )
						: __( 'Not fetched yet.', 'taxi-peninsula' )
				);
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tp_ads_feed">
				<?php wp_nonce_field( 'tp_ads_feed' ); ?>
				<button class="button" name="download" value="1"><?php esc_html_e( 'Download the file now', 'taxi-peninsula' ); ?></button>
				<button class="button" name="renew" value="1" onclick="return confirm(this.dataset.confirm)" data-confirm="<?php esc_attr_e( 'Make a new link? The old one stops working, so the schedule in Google Ads must be updated.', 'taxi-peninsula' ); ?>"><?php esc_html_e( 'Make a new link', 'taxi-peninsula' ); ?></button>
			</form>
			<h2><?php esc_html_e( 'In the file now', 'taxi-peninsula' ); ?></h2>
			<p>
				<?php
				/* translators: 1: trips 2: with click ID 3: matched by email/phone */
				echo esc_html( sprintf( __( '%1$d completed trips from the last 90 days: %2$d with a Google click ID, %3$d matched by hashed email or phone.', 'taxi-peninsula' ), count( $rows ), $clicks, count( $rows ) - $clicks ) );
				?>
			</p>
			<?php if ( $rows ) : ?>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Booking', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Trip time', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Value', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Matched by', 'taxi-peninsula' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( array_slice( $rows, 0, 50 ) as $r ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( get_edit_post_link( $r['id'] ) ); ?>"><?php echo esc_html( get_post_meta( $r['id'], '_tp_reference', true ) ); ?></a></td>
								<td><?php echo esc_html( $r['time'] ); ?></td>
								<td><?php echo esc_html( '' === $r['value'] ? '—' : '$' . $r['value'] ); ?></td>
								<td><?php echo esc_html( $r['click'] ? __( 'Click ID (online booking)', 'taxi-peninsula' ) : __( 'Hashed email / phone (phone booking)', 'taxi-peninsula' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
