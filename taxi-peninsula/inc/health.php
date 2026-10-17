<?php
/**
 * Bookings → Check connections: one button that tests the outside services
 * the site depends on — flight data, Google Business Profile, SMS sending,
 * the SMS "STOP" reply address, phone notifications and email — and says in
 * plain words what to fix.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( tp_admin_parent( 'settings' ), __( 'Check connections', 'taxi-peninsula' ), __( 'Check connections', 'taxi-peninsula' ), 'manage_options', 'tp-health', 'tp_render_health_page' );
	},
	60
);

/**
 * Run every check. Each result: [state ok|warn|fail|off, label, message, fix-link].
 */
function tp_health_checks() {
	$out = array();

	// Flight data (AeroDataBox).
	if ( ! tp_setting( 'flight_api_key' ) ) {
		$out['flight'] = array( 'off', __( 'Flight tracking', 'taxi-peninsula' ), __( 'No API key — drivers and the office see a live-status link only.', 'taxi-peninsula' ), 'tp-settings' );
	} else {
		$r    = wp_remote_get(
			'https://aerodatabox.p.rapidapi.com/flights/number/QF1/' . wp_date( 'Y-m-d' ),
			array(
				'timeout' => 12,
				'headers' => array( 'X-RapidAPI-Key' => (string) tp_setting( 'flight_api_key' ), 'X-RapidAPI-Host' => 'aerodatabox.p.rapidapi.com' ),
			)
		);
		$code = is_wp_error( $r ) ? 0 : wp_remote_retrieve_response_code( $r );
		if ( in_array( $code, array( 200, 204, 404 ), true ) ) {
			$out['flight'] = array( 'ok', __( 'Flight tracking', 'taxi-peninsula' ), __( 'The API key works.', 'taxi-peninsula' ), '' );
		} elseif ( in_array( $code, array( 401, 403 ), true ) ) {
			$out['flight'] = array( 'fail', __( 'Flight tracking', 'taxi-peninsula' ), __( 'The API key was refused. Check it in RapidAPI and that you are subscribed to AeroDataBox.', 'taxi-peninsula' ), 'tp-settings' );
		} elseif ( 429 === $code ) {
			$out['flight'] = array( 'warn', __( 'Flight tracking', 'taxi-peninsula' ), __( 'The key works but the monthly limit is used up. Upgrade the plan or wait until it resets.', 'taxi-peninsula' ), '' );
		} else {
			/* translators: %s: error */
			$out['flight'] = array( 'fail', __( 'Flight tracking', 'taxi-peninsula' ), sprintf( __( 'Could not reach the flight data service (%s).', 'taxi-peninsula' ), is_wp_error( $r ) ? $r->get_error_message() : $code ), '' );
		}
	}

	// Google Business Profile.
	if ( ! function_exists( 'tp_gbp_state' ) || ! tp_setting( 'gbp_client_id' ) ) {
		$out['gbp'] = array( 'off', __( 'Google profile posts', 'taxi-peninsula' ), __( 'Not set up.', 'taxi-peninsula' ), 'tp-gbp' );
	} elseif ( ! tp_gbp_state()['refresh'] ) {
		$out['gbp'] = array( 'warn', __( 'Google profile posts', 'taxi-peninsula' ), __( 'Client ID saved but not connected yet. Click "Connect to Google".', 'taxi-peninsula' ), 'tp-gbp' );
	} else {
		delete_transient( 'tp_gbp_locations' );
		$token = tp_gbp_token();
		$locs  = is_wp_error( $token ) ? $token : tp_gbp_locations();
		if ( is_wp_error( $locs ) ) {
			$out['gbp'] = array( 'fail', __( 'Google profile posts', 'taxi-peninsula' ), $locs->get_error_message(), 'tp-gbp' );
		} elseif ( ! tp_gbp_state()['location'] ) {
			$out['gbp'] = array( 'warn', __( 'Google profile posts', 'taxi-peninsula' ), __( 'Connected, but no business location chosen.', 'taxi-peninsula' ), 'tp-gbp' );
		} else {
			/* translators: %s: business */
			$out['gbp'] = array( 'ok', __( 'Google profile posts', 'taxi-peninsula' ), sprintf( __( 'Connected to %s.', 'taxi-peninsula' ), tp_gbp_state()['title'] ), '' );
		}
	}

	// SMS provider.
	$provider = tp_setting( 'sms_provider' );
	if ( ! tp_sms_enabled() ) {
		$out['sms'] = array( 'none' === $provider ? 'off' : 'fail', __( 'SMS sending', 'taxi-peninsula' ), 'none' === $provider ? __( 'Off.', 'taxi-peninsula' ) : __( 'Details are missing for the chosen provider.', 'taxi-peninsula' ), 'tp-settings' );
	} else {
		if ( 'clicksend' === $provider ) {
			$r = wp_remote_get( 'https://rest.clicksend.com/v3/account', array( 'timeout' => 12, 'headers' => array( 'Authorization' => 'Basic ' . base64_encode( tp_setting( 'clicksend_username' ) . ':' . tp_setting( 'clicksend_api_key' ) ) ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		} else {
			$r = wp_remote_get( 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode( tp_setting( 'twilio_account_sid' ) ) . '.json', array( 'timeout' => 12, 'headers' => array( 'Authorization' => 'Basic ' . base64_encode( tp_setting( 'twilio_account_sid' ) . ':' . tp_setting( 'twilio_auth_token' ) ) ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}
		$code       = is_wp_error( $r ) ? 0 : wp_remote_retrieve_response_code( $r );
		$out['sms'] = 200 === $code
			/* translators: %s: provider */
			? array( 'ok', __( 'SMS sending', 'taxi-peninsula' ), sprintf( __( '%s accepted the login. Use "Send a test SMS" on the Settings page to check delivery to a phone.', 'taxi-peninsula' ), 'clicksend' === $provider ? 'ClickSend' : 'Twilio' ), '' )
			/* translators: %s: code */
			: array( 'fail', __( 'SMS sending', 'taxi-peninsula' ), sprintf( __( 'The provider refused the login (%s). Check the username / API key or SID / token.', 'taxi-peninsula' ), is_wp_error( $r ) ? $r->get_error_message() : $code ), 'tp-settings' );
	}

	// SMS STOP replies.
	if ( ! tp_setting( 'sms_stop_key' ) ) {
		$out['stop'] = array( function_exists( 'tp_marketing_enabled' ) && tp_marketing_enabled() && tp_sms_enabled() ? 'warn' : 'off', __( 'SMS "STOP" replies', 'taxi-peninsula' ), __( 'No secret set, so STOP replies are not handled automatically (the opt-out link in each SMS still works).', 'taxi-peninsula' ), 'tp-settings' );
	} else {
		$req = new WP_REST_Request( 'POST', '/taxi-peninsula/v1/sms-inbound' );
		$req->set_param( 'key', tp_setting( 'sms_stop_key' ) );
		$req->set_param( 'From', '+61400000000' );
		$req->set_param( 'Body', 'connection check' );
		$res    = rest_do_request( $req );
		$public = 0 === strpos( home_url(), 'https://' ) && ! preg_match( '#//(localhost|127\.|10\.|192\.168\.)#', home_url() );
		if ( 200 !== $res->get_status() ) {
			/* translators: %d: status */
			$out['stop'] = array( 'fail', __( 'SMS "STOP" replies', 'taxi-peninsula' ), sprintf( __( 'The reply address answered with an error (%d).', 'taxi-peninsula' ), $res->get_status() ), '' );
		} elseif ( ! $public ) {
			$out['stop'] = array( 'warn', __( 'SMS "STOP" replies', 'taxi-peninsula' ), __( 'The reply address works, but this site is not on a public https:// address, so ClickSend or Twilio cannot reach it.', 'taxi-peninsula' ), '' );
		} else {
			$out['stop'] = array( 'ok', __( 'SMS "STOP" replies', 'taxi-peninsula' ), __( 'The reply address works. Make sure it is set as the inbound SMS URL with your provider (see News & offers).', 'taxi-peninsula' ), '' );
		}
	}

	// Phone notifications.
	$keys        = function_exists( 'tp_vapid_keys' ) ? tp_vapid_keys() : null;
	$out['push'] = $keys
		? ( is_ssl() || 0 === strpos( home_url(), 'https://' )
			? array( 'ok', __( 'Phone notifications', 'taxi-peninsula' ), __( 'Ready. Passengers can turn them on from their booking page.', 'taxi-peninsula' ), '' )
			: array( 'warn', __( 'Phone notifications', 'taxi-peninsula' ), __( 'Keys are ready, but browsers only allow notifications on https:// sites.', 'taxi-peninsula' ), '' ) )
		: array( 'fail', __( 'Phone notifications', 'taxi-peninsula' ), __( 'This server cannot create notification keys (the PHP OpenSSL extension is missing). Ask your host to enable it.', 'taxi-peninsula' ), '' );

	// Email: the site can hand mail to the server.
	$ok           = wp_mail( wp_get_current_user()->user_email, __( 'Connection check: email works', 'taxi-peninsula' ), __( 'This is a test from Bookings → Check connections. If you can read this, the website can send email.', 'taxi-peninsula' ), tp_mail_headers() );
	$out['email'] = $ok
		/* translators: %s: email */
		? array( 'ok', __( 'Email', 'taxi-peninsula' ), sprintf( __( 'A test email was handed to the mail server for %s. If it does not arrive within a few minutes, set up an SMTP plugin with your email provider.', 'taxi-peninsula' ), wp_get_current_user()->user_email ), 'tp-email-check' )
		: array( 'fail', __( 'Email', 'taxi-peninsula' ), __( 'The server refused to send email. Install an SMTP plugin and connect it to your email provider.', 'taxi-peninsula' ), 'tp-email-check' );

	return array_merge( $out, tp_seo_checks() );
}

/**
 * Search checks: visibility, verification, sitemap, descriptions, alt text,
 * duplicate titles, business details, llms.txt and IndexNow.
 */
function tp_seo_checks() {
	global $wpdb;
	$out = array();
	$out['seo_visible'] = get_option( 'blog_public' )
		? array( 'ok', __( 'Search engines allowed', 'taxi-peninsula' ), __( 'The site is open to search engines.', 'taxi-peninsula' ), '' )
		: array( 'fail', __( 'Search engines allowed', 'taxi-peninsula' ), __( 'Settings → Reading → "Discourage search engines" is ticked, so Google and Bing will not list the site. Untick it when you go live.', 'taxi-peninsula' ), '' );
	$g = (bool) tp_setting( 'google_site_verification' );
	$b = (bool) tp_setting( 'bing_site_verification' );
	$out['seo_verify'] = array( $g && $b ? 'ok' : 'warn', __( 'Google & Bing verification', 'taxi-peninsula' ), $g && $b ? __( 'Both codes are set. Submit your sitemap in each: ', 'taxi-peninsula' ) . home_url( '/wp-sitemap.xml' ) : ( $g ? __( 'Google is set; add Bing (Marketing → AI & Bing search).', 'taxi-peninsula' ) : ( $b ? __( 'Bing is set; add the Google Search Console code.', 'taxi-peninsula' ) : __( 'Add your Google Search Console and Bing Webmaster Tools codes.', 'taxi-peninsula' ) ) ), 'tp-settings' );
	$sitemaps = function_exists( 'wp_sitemaps_get_server' ) && wp_sitemaps_get_server()->sitemaps_enabled();
	$out['seo_sitemap'] = $sitemaps
		? array( 'ok', __( 'Sitemap', 'taxi-peninsula' ), home_url( '/wp-sitemap.xml' ), '' )
		: array( 'fail', __( 'Sitemap', 'taxi-peninsula' ), __( 'The built-in sitemap is switched off — usually because an SEO plugin provides its own. Submit that plugin\'s sitemap address to Google and Bing instead.', 'taxi-peninsula' ), '' );
	// Pages with no excerpt use an automatic description; list key pages without their own.
	$missing = get_posts( array( 'post_type' => array( 'page', 'tp_service', 'tp_area' ), 'post_status' => 'publish', 'numberposts' => 200, 'fields' => 'ids', 'has_password' => false ) );
	$missing = array_filter( $missing, static fn( $id ) => ! has_excerpt( $id ) );
	$out['seo_desc'] = count( $missing ) <= 3
		? array( 'ok', __( 'Page descriptions', 'taxi-peninsula' ), __( 'Your main pages have their own search descriptions.', 'taxi-peninsula' ), '' )
		/* translators: %d: count */
		: array( 'warn', __( 'Page descriptions', 'taxi-peninsula' ), sprintf( __( '%d pages, services or areas use an automatic description. Write a 1–2 sentence "Excerpt" for each — it becomes the text under your link in Google.', 'taxi-peninsula' ), count( $missing ) ), '' );
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$no_alt = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attachment_image_alt' WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%' AND ( m.meta_value IS NULL OR m.meta_value = '' )" );
	$dupes  = $wpdb->get_col( "SELECT post_title FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('page','post','tp_service','tp_area') GROUP BY post_title HAVING COUNT(*) > 1 LIMIT 5" );
	// phpcs:enable
	$out['seo_alt'] = $no_alt
		/* translators: %d: count */
		? array( 'warn', __( 'Image descriptions (alt text)', 'taxi-peninsula' ), sprintf( __( '%d images have no alt text. Describe each in Media → the image → "Alternative text" (e.g. "Driver securing a wheelchair in the van"). It helps blind visitors and Google Images.', 'taxi-peninsula' ), $no_alt ), '' )
		: array( 'ok', __( 'Image descriptions (alt text)', 'taxi-peninsula' ), __( 'Every image has alt text.', 'taxi-peninsula' ), '' );
	$out['seo_dupes'] = $dupes
		/* translators: %s: titles */
		? array( 'warn', __( 'Duplicate page titles', 'taxi-peninsula' ), sprintf( __( 'More than one page is called: %s. Give each a different title.', 'taxi-peninsula' ), implode( ', ', $dupes ) ), '' )
		: array( 'ok', __( 'Duplicate page titles', 'taxi-peninsula' ), __( 'Every page has its own title.', 'taxi-peninsula' ), '' );
	$out['seo_nap'] = tp_setting( 'biz_suburb' )
		? array( 'ok', __( 'Business details for Google', 'taxi-peninsula' ), __( 'Address details are set and shown in the footer and business data.', 'taxi-peninsula' ), '' )
		: array( 'warn', __( 'Business details for Google', 'taxi-peninsula' ), __( 'Add your suburb (and street address if customers can visit) so the footer and business data match your Google profile.', 'taxi-peninsula' ), 'tp-settings' );
	$out['seo_ai'] = tp_setting( 'llms_txt' ) && tp_setting( 'indexnow' ) && 'none' !== tp_setting( 'ai_crawlers' )
		? array( 'ok', __( 'AI & Bing search', 'taxi-peninsula' ), __( 'llms.txt, IndexNow and AI search bots are on.', 'taxi-peninsula' ), 'tp-ai-search' )
		: array( 'warn', __( 'AI & Bing search', 'taxi-peninsula' ), __( 'Some AI search features are off. See Marketing → AI & Bing search.', 'taxi-peninsula' ), 'tp-ai-search' );
	return $out;
}

function tp_render_health_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$results = get_transient( 'tp_health_' . get_current_user_id() );
	$labels  = array(
		'ok'   => __( 'Working', 'taxi-peninsula' ),
		'warn' => __( 'Needs attention', 'taxi-peninsula' ),
		'fail' => __( 'Not working', 'taxi-peninsula' ),
		'off'  => __( 'Not set up', 'taxi-peninsula' ),
	);
	?>
	<div class="wrap tp-health">
		<h1><?php esc_html_e( 'Check connections', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'Tests the outside services the website uses, without sending anything to customers. A test email goes to you.', 'taxi-peninsula' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tp_health_run">
			<?php wp_nonce_field( 'tp_health_run' ); ?>
			<?php submit_button( __( 'Run the checks', 'taxi-peninsula' ), 'primary', '', false ); ?>
		</form>
		<?php if ( is_array( $results ) ) : ?>
			<p class="description">
				<?php
				/* translators: %s: time */
				echo esc_html( sprintf( __( 'Checked %s', 'taxi-peninsula' ), wp_date( 'j M Y, g:i a', $results['_time'] ) ) );
				?>
			</p>
			<table class="widefat striped tp-health__table">
				<thead><tr><th><?php esc_html_e( 'Service', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Result', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Details', 'taxi-peninsula' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $results as $key => $r ) : ?>
						<?php
						if ( '_time' === $key ) {
							continue;
						}
						?>
						<tr>
							<th scope="row"><?php echo esc_html( $r[1] ); ?></th>
							<td><span class="tp-health__state tp-health__state--<?php echo esc_attr( $r[0] ); ?>"><span aria-hidden="true"><?php echo esc_html( array( 'ok' => '✓', 'warn' => '!', 'fail' => '✕', 'off' => '–' )[ $r[0] ] ); ?></span> <?php echo esc_html( $labels[ $r[0] ] ); ?></span></td>
							<td><?php echo esc_html( $r[2] ); ?>
								<?php if ( $r[3] && 'ok' !== $r[0] ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $r[3] ) ); ?>"><?php esc_html_e( 'Fix it', 'taxi-peninsula' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

add_action(
	'admin_post_tp_health_run',
	static function () {
		check_admin_referer( 'tp_health_run' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		$results          = tp_health_checks();
		$results['_time'] = time();
		set_transient( 'tp_health_' . get_current_user_id(), $results, DAY_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=tp-health' ) );
		exit;
	}
);
