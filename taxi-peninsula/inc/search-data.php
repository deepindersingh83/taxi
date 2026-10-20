<?php
/**
 * Search data and tracking:
 *
 * - Reports → Search performance: Google Search Console data in the
 *   dashboard (searches, clicks, position, landing pages), refreshed weekly,
 *   with "almost page 1" searches worth improving.
 * - Bing Webmaster data (searches and clicks) on Marketing → AI & Bing search.
 * - AI crawler visits: when ChatGPT, Perplexity, Claude, Bing, Google and
 *   others read the site, and which pages.
 * - Partner links: track which partners link to you and check the link.
 * - Monthly AI check: a tracked list of questions to ask ChatGPT, Copilot,
 *   Perplexity and Google, with a monthly reminder.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['bing_api_key'] = array( 'search', __( 'Bing Webmaster API key', 'taxi-peninsula' ), 'secret', '', __( 'Bing Webmaster Tools → Settings (cog) → API access → API key. Lets the site show your Bing searches and clicks.', 'taxi-peninsula' ) );
		return $s;
	}
);

/* =========================================================================
 * Google Search Console.
 * ====================================================================== */

function tp_gsc_state() {
	return wp_parse_args( (array) get_option( 'tp_gsc', array() ), array( 'refresh' => '', 'sa' => array(), 'access' => '', 'expires' => 0, 'site' => '' ) );
}

// Keys saved before v2.9 were stored as plain text: encrypt them.
add_action(
	'admin_init',
	static function () {
		$s = tp_gsc_state();
		if ( ! empty( $s['sa']['private_key'] ) && 0 !== strpos( $s['sa']['private_key'], 'tpenc:' ) ) {
			$s['sa']['private_key'] = tp_encrypt( $s['sa']['private_key'] );
			update_option( 'tp_gsc', $s, false );
		}
	}
);

/**
 * Connected to the Search Console API (Google sign-in or a service-account key)?
 */
function tp_gsc_connected() {
	$s = tp_gsc_state();
	return $s['refresh'] || ! empty( $s['sa']['client_email'] );
}

function tp_gsc_redirect_uri() {
	return admin_url( 'admin-post.php?action=tp_gsc_oauth' );
}

function tp_gsc_token() {
	$s = tp_gsc_state();
	if ( ! tp_gsc_connected() ) {
		return new WP_Error( 'tp_gsc_off', __( 'Search Console is not connected.', 'taxi-peninsula' ) );
	}
	if ( $s['access'] && $s['expires'] > time() + 60 ) {
		return $s['access'];
	}
	if ( ! empty( $s['sa']['client_email'] ) ) {
		$jwt = tp_gsc_service_jwt( $s['sa'] );
		if ( is_wp_error( $jwt ) ) {
			return $jwt;
		}
		$r = wp_remote_post( 'https://oauth2.googleapis.com/token', array( 'timeout' => 15, 'body' => array( 'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt ) ) );
	} else {
		$r = wp_remote_post( 'https://oauth2.googleapis.com/token', array( 'timeout' => 15, 'body' => array( 'client_id' => tp_setting( 'gbp_client_id' ), 'client_secret' => tp_setting( 'gbp_client_secret' ), 'refresh_token' => $s['refresh'], 'grant_type' => 'refresh_token' ) ) );
	}
	$body = is_wp_error( $r ) ? array() : json_decode( wp_remote_retrieve_body( $r ), true );
	if ( empty( $body['access_token'] ) ) {
		return new WP_Error( 'tp_gsc_token', __( 'Could not sign in to Google. Please connect again.', 'taxi-peninsula' ) );
	}
	$s['access']  = $body['access_token'];
	$s['expires'] = time() + (int) ( $body['expires_in'] ?? 3000 );
	update_option( 'tp_gsc', $s, false );
	return $s['access'];
}

/**
 * Signed sign-in request for a service-account key (no Google sign-in screen needed).
 */
function tp_gsc_service_jwt( array $sa ) {
	if ( ! function_exists( 'openssl_sign' ) ) {
		return new WP_Error( 'tp_gsc_openssl', __( 'Your hosting is missing the PHP OpenSSL extension, which the key needs. Use the upload option instead, or ask your host to enable OpenSSL.', 'taxi-peninsula' ) );
	}
	$b64   = static fn( $v ) => rtrim( strtr( base64_encode( $v ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	$now   = time();
	$input = $b64( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) ) . '.' . $b64(
		wp_json_encode(
			array(
				'iss'   => $sa['client_email'],
				'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
				'aud'   => 'https://oauth2.googleapis.com/token',
				'iat'   => $now,
				'exp'   => $now + HOUR_IN_SECONDS,
			)
		)
	);
	$sig = '';
	$pem = tp_decrypt( $sa['private_key'] );
	if ( '' === $pem ) {
		return new WP_Error( 'tp_gsc_key', __( 'The saved key can no longer be read (the site’s security keys changed). Please upload the JSON key again.', 'taxi-peninsula' ) );
	}
	if ( ! openssl_sign( $input, $sig, $pem, OPENSSL_ALGO_SHA256 ) ) {
		return new WP_Error( 'tp_gsc_key', __( 'The key could not be read. Download a new JSON key and paste it again.', 'taxi-peninsula' ) );
	}
	return $input . '.' . $b64( $sig );
}

function tp_gsc_api( $method, $path, $body = null ) {
	$token = tp_gsc_token();
	if ( is_wp_error( $token ) ) {
		return $token;
	}
	$args = array( 'method' => $method, 'timeout' => 25, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ) );
	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}
	$r = wp_remote_request( 'https://www.googleapis.com/webmasters/v3/' . $path, $args );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$data = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( wp_remote_retrieve_response_code( $r ) >= 300 ) {
		/* translators: %s: message */
		return new WP_Error( 'tp_gsc_api', sprintf( __( 'Search Console said: %s', 'taxi-peninsula' ), $data['error']['message'] ?? wp_remote_retrieve_response_code( $r ) ) );
	}
	return is_array( $data ) ? $data : array();
}

/**
 * Pull the last 28 days (Search Console data is about 3 days behind).
 */
function tp_gsc_refresh() {
	$s = tp_gsc_state();
	if ( ! $s['site'] ) {
		return new WP_Error( 'tp_gsc_site', __( 'Choose your Search Console property first.', 'taxi-peninsula' ) );
	}
	$end   = wp_date( 'Y-m-d', time() - 3 * DAY_IN_SECONDS );
	$start = wp_date( 'Y-m-d', time() - 30 * DAY_IN_SECONDS );
	$path  = 'sites/' . rawurlencode( $s['site'] ) . '/searchAnalytics/query';
	$q     = tp_gsc_api( 'POST', $path, array( 'startDate' => $start, 'endDate' => $end, 'dimensions' => array( 'query' ), 'rowLimit' => 250 ) );
	if ( is_wp_error( $q ) ) {
		return $q;
	}
	$qp = tp_gsc_api( 'POST', $path, array( 'startDate' => $start, 'endDate' => $end, 'dimensions' => array( 'query', 'page' ), 'rowLimit' => 500 ) );
	$p  = tp_gsc_api( 'POST', $path, array( 'startDate' => $start, 'endDate' => $end, 'dimensions' => array( 'page' ), 'rowLimit' => 100 ) );
	$t  = tp_gsc_api( 'POST', $path, array( 'startDate' => $start, 'endDate' => $end ) );
	$data = array(
		'fetched' => time(),
		'start'   => $start,
		'end'     => $end,
		'queries' => (array) ( $q['rows'] ?? array() ),
		'pairs'   => is_wp_error( $qp ) ? array() : (array) ( $qp['rows'] ?? array() ),
		'pages'   => is_wp_error( $p ) ? array() : (array) ( $p['rows'] ?? array() ),
		'totals'  => is_wp_error( $t ) ? array() : ( $t['rows'][0] ?? array() ),
	);
	update_option( 'tp_gsc_data', $data, false );
	return $data;
}

/**
 * Searches on positions 8–20 with real impressions: one push from page 1.
 */
function tp_gsc_almost( array $data ) {
	$best = array();
	foreach ( (array) $data['pairs'] as $r ) {
		$best[ $r['keys'][0] ] = $best[ $r['keys'][0] ] ?? $r['keys'][1];
	}
	$out = array();
	foreach ( (array) $data['queries'] as $r ) {
		if ( $r['position'] >= 8 && $r['position'] <= 20 && $r['impressions'] >= 10 ) {
			$out[] = $r + array( 'page' => $best[ $r['keys'][0] ] ?? '' );
		}
	}
	usort( $out, static fn( $a, $b ) => $b['impressions'] <=> $a['impressions'] );
	return array_slice( $out, 0, 25 );
}

/**
 * Read a Search Console export (Performance → Export → Download CSV): the zip,
 * or the CSV files inside it. Uses Queries.csv, Pages.csv and Chart.csv.
 *
 * @param array $files File name => contents.
 * @return array|WP_Error Data in the same shape as tp_gsc_refresh().
 */
function tp_gsc_parse_export( array $files ) {
	$num  = static fn( $v ) => (float) str_replace( array( ',', ' ', '%' ), '', (string) $v );
	$data = array( 'source' => 'upload', 'fetched' => time(), 'start' => '', 'end' => '', 'queries' => array(), 'pairs' => array(), 'pages' => array(), 'totals' => array() );
	$days = array();
	foreach ( $files as $name => $csv ) {
		$csv  = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $csv );
		$rows = array_map( static fn( $line ) => str_getcsv( $line, ',', '"', '\\' ), preg_split( '/\r\n|\n|\r/', trim( $csv ) ) );
		if ( count( $rows ) < 2 || count( $rows[0] ) < 5 ) {
			continue; // Not a table with clicks, impressions, CTR and position.
		}
		array_shift( $rows );
		$first = (string) $rows[0][0];
		$name  = strtolower( basename( $name ) );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $first ) ) {
			$kind = 'chart';
		} elseif ( preg_match( '#^https?://#', $first ) ) {
			$kind = 'pages';
		} elseif ( false !== strpos( $name, 'quer' ) ) {
			$kind = 'queries';
		} else {
			continue; // Countries, devices, search appearance…
		}
		foreach ( $rows as $r ) {
			if ( count( $r ) < 5 || '' === trim( (string) $r[0] ) ) {
				continue;
			}
			$row = array( 'keys' => array( (string) $r[0] ), 'clicks' => $num( $r[1] ), 'impressions' => $num( $r[2] ), 'ctr' => $num( $r[3] ) / 100, 'position' => $num( $r[4] ) );
			if ( 'chart' === $kind ) {
				$days[] = $row;
			} else {
				$data[ $kind ][] = $row;
			}
		}
	}
	if ( ! $data['queries'] && ! $data['pages'] ) {
		return new WP_Error( 'tp_gsc_csv', __( 'No searches or pages found. In Search Console open Performance → Search results, click Export → Download CSV, and upload that zip file (or the Queries.csv and Pages.csv inside it).', 'taxi-peninsula' ) );
	}
	$by_clicks = static function ( $a, $b ) {
		return $b['clicks'] <=> $a['clicks'] ?: $b['impressions'] <=> $a['impressions'];
	};
	usort( $data['queries'], $by_clicks );
	usort( $data['pages'], $by_clicks );
	// Totals: the daily chart has the true totals; without it, add up the searches.
	$sum = $days ?: $data['queries'];
	$c   = array_sum( array_column( $sum, 'clicks' ) );
	$i   = array_sum( array_column( $sum, 'impressions' ) );
	$w   = 0;
	foreach ( $sum as $r ) {
		$w += $r['position'] * $r['impressions'];
	}
	$data['totals'] = array( 'clicks' => $c, 'impressions' => $i, 'ctr' => $i ? $c / $i : 0, 'position' => $i ? $w / $i : 0 );
	if ( $days ) {
		$dates         = array_map( static fn( $r ) => $r['keys'][0], $days );
		$data['start'] = min( $dates );
		$data['end']   = max( $dates );
	}
	return $data;
}

add_action(
	'admin_post_tp_gsc_upload',
	static function () {
		check_admin_referer( 'tp_gsc_upload' );
		if ( ! current_user_can( 'tp_view_leads' ) ) {
			wp_die( '', 403 );
		}
		$up    = $_FILES['gsc'] ?? array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- read as data, never stored as a file.
		$files = array();
		foreach ( (array) ( $up['tmp_name'] ?? array() ) as $n => $tmp ) {
			if ( ! $tmp || ! is_uploaded_file( $tmp ) || filesize( $tmp ) > 20 * MB_IN_BYTES ) {
				continue;
			}
			$name = sanitize_file_name( $up['name'][ $n ] ?? 'file.csv' );
			if ( preg_match( '/\.zip$/i', $name ) ) {
				if ( ! class_exists( 'ZipArchive' ) ) {
					tp_gsc_back( __( 'Your hosting cannot open zip files. Unzip the export on your computer and upload Queries.csv and Pages.csv instead.', 'taxi-peninsula' ) );
				}
				$zip = new ZipArchive();
				if ( true === $zip->open( $tmp ) ) {
					for ( $k = 0; $k < min( $zip->numFiles, 20 ); $k++ ) {
						$entry = $zip->getNameIndex( $k );
						if ( preg_match( '/\.csv$/i', $entry ) ) {
							$files[ $entry ] = (string) $zip->getFromIndex( $k );
						}
					}
					$zip->close();
				}
			} elseif ( preg_match( '/\.csv$/i', $name ) ) {
				$files[ $name ] = (string) file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}
		if ( ! $files ) {
			tp_gsc_back( __( 'Please choose the zip file from Search Console (or its CSV files).', 'taxi-peninsula' ) );
		}
		$data = tp_gsc_parse_export( $files );
		if ( is_wp_error( $data ) ) {
			tp_gsc_back( $data->get_error_message() );
		}
		update_option( 'tp_gsc_data', $data, false );
		/* translators: 1: number of searches 2: number of pages */
		tp_gsc_back( sprintf( __( 'Loaded %1$d searches and %2$d pages from your export.', 'taxi-peninsula' ), count( $data['queries'] ), count( $data['pages'] ) ) );
	}
);

add_action(
	'admin_post_tp_gsc_key',
	static function () {
		check_admin_referer( 'tp_gsc_key' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		$json = isset( $_POST['key'] ) ? trim( wp_unslash( $_POST['key'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON key, validated below.
		if ( '' === $json && ! empty( $_FILES['keyfile']['tmp_name'] ) && is_uploaded_file( $_FILES['keyfile']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$json = (string) file_get_contents( $_FILES['keyfile']['tmp_name'], false, null, 0, 20000 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,WordPress.Security.ValidatedSanitizedInput
		}
		$k = json_decode( $json, true );
		if ( ! is_array( $k ) || 'service_account' !== ( $k['type'] ?? '' ) || ! is_email( $k['client_email'] ?? '' ) || false === strpos( (string) ( $k['private_key'] ?? '' ), 'PRIVATE KEY' ) ) {
			tp_gsc_back( __( 'That is not a service-account key. In Google Cloud open the service account → Keys → Add key → JSON, and paste the whole downloaded file.', 'taxi-peninsula' ) );
		}
		update_option( 'tp_gsc', array( 'refresh' => '', 'sa' => array( 'client_email' => $k['client_email'], 'private_key' => tp_encrypt( $k['private_key'] ) ), 'access' => '', 'expires' => 0, 'site' => '' ), false );
		$token = tp_gsc_token();
		if ( is_wp_error( $token ) ) {
			delete_option( 'tp_gsc' );
			tp_gsc_back( $token->get_error_message() );
		}
		tp_gsc_pick_site();
		tp_gsc_back( tp_gsc_state()['site'] ? __( 'Key saved and data loaded. It will refresh every week.', 'taxi-peninsula' ) : __( 'Key saved. Now add the service-account email as a user in Search Console (see step 3), then reload this page.', 'taxi-peninsula' ) );
	}
);

/**
 * Pick the Search Console property that matches this site and load its data.
 */
function tp_gsc_pick_site() {
	$sites = tp_gsc_api( 'GET', 'sites' );
	$host  = preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	foreach ( is_wp_error( $sites ) ? array() : (array) ( $sites['siteEntry'] ?? array() ) as $e ) {
		if ( 'sc-domain:' . $host === $e['siteUrl'] || false !== strpos( $e['siteUrl'], '//' . $host ) || false !== strpos( $e['siteUrl'], '//www.' . $host ) ) {
			$st         = tp_gsc_state();
			$st['site'] = $e['siteUrl'];
			update_option( 'tp_gsc', $st, false );
			tp_gsc_refresh();
			return true;
		}
	}
	return false;
}

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( tp_admin_parent( 'reports' ), __( 'Search performance', 'taxi-peninsula' ), __( 'Search performance', 'taxi-peninsula' ), 'tp_view_leads', 'tp-search', 'tp_render_search_page', 1 );
	},
	20
);

function tp_render_search_page() {
	if ( ! current_user_can( 'tp_view_leads' ) ) {
		return;
	}
	$s    = tp_gsc_state();
	$data = get_option( 'tp_gsc_data' );
	$msg  = isset( $_GET['tp_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['tp_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$pct  = static fn( $v ) => number_format_i18n( 100 * (float) $v, 1 ) . '%';
	$pos  = static fn( $v ) => number_format_i18n( (float) $v, 1 );
	$path = static fn( $u ) => (string) wp_parse_url( $u, PHP_URL_PATH ) ?: '/';
	?>
	<div class="wrap tp-search">
		<h1><?php esc_html_e( 'Search performance', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'What people type into Google when your site appears, how often they click, and where you rank — from Google Search Console.', 'taxi-peninsula' ); ?></p>
		<?php if ( $msg ) : ?>
			<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
		<?php endif; ?>

		<?php if ( tp_gsc_connected() && ! $s['site'] ) : ?>
			<?php $sites = tp_gsc_api( 'GET', 'sites' ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tp_gsc_site"><?php wp_nonce_field( 'tp_gsc_site' ); ?>
				<label for="tp-gsc-site"><?php esc_html_e( 'Choose your property', 'taxi-peninsula' ); ?></label>
				<select id="tp-gsc-site" name="site">
					<?php foreach ( is_wp_error( $sites ) ? array() : (array) ( $sites['siteEntry'] ?? array() ) as $e ) : ?>
						<option value="<?php echo esc_attr( $e['siteUrl'] ); ?>"><?php echo esc_html( $e['siteUrl'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Use this property', 'taxi-peninsula' ), 'primary', '', false ); ?>
			</form>
			<?php if ( is_wp_error( $sites ) ) : ?>
				<p class="notice notice-error inline"><?php echo esc_html( $sites->get_error_message() ); ?></p>
			<?php elseif ( empty( $sites['siteEntry'] ) && ! empty( $s['sa']['client_email'] ) ) : ?>
				<p class="notice notice-warning inline">
					<?php
					/* translators: %s: service-account email */
					echo esc_html( sprintf( __( 'No properties yet. In Search Console open Settings → Users and permissions → Add user, enter %s with "Restricted" permission, then reload this page.', 'taxi-peninsula' ), $s['sa']['client_email'] ) );
					?>
				</p>
			<?php endif; ?>
		<?php else : ?>
			<?php if ( tp_gsc_connected() ) : ?>
			<p>
				<?php
				/* translators: 1: property 2: from 3: to */
				echo esc_html( sprintf( __( 'Property: %1$s · %2$s to %3$s', 'taxi-peninsula' ), $s['site'], is_array( $data ) ? wp_date( 'j M', strtotime( $data['start'] ) ) : '—', is_array( $data ) ? wp_date( 'j M Y', strtotime( $data['end'] ) ) : '—' ) );
				?>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tp_gsc_refresh' ), 'tp_gsc_refresh' ) ); ?>"><?php esc_html_e( 'Refresh now', 'taxi-peninsula' ); ?></a>
			</p>
			<?php elseif ( is_array( $data ) ) : ?>
			<p>
				<?php
				echo esc_html(
					$data['start']
						/* translators: 1: from 2: to 3: upload date */
						? sprintf( __( 'From your Search Console export: %1$s to %2$s (uploaded %3$s).', 'taxi-peninsula' ), wp_date( 'j M', strtotime( $data['start'] ) ), wp_date( 'j M Y', strtotime( $data['end'] ) ), wp_date( 'j M Y', $data['fetched'] ) )
						/* translators: %s: upload date */
						: sprintf( __( 'From your Search Console export (uploaded %s).', 'taxi-peninsula' ), wp_date( 'j M Y', $data['fetched'] ) )
				);
				?>
			</p>
			<?php endif; ?>
			<?php if ( is_array( $data ) ) : ?>
				<?php $tot = (array) $data['totals']; ?>
				<ul class="tp-kpis">
					<li class="tp-kpi"><span class="tp-kpi__label"><?php esc_html_e( 'Clicks from Google', 'taxi-peninsula' ); ?></span><strong class="tp-kpi__n"><?php echo esc_html( number_format_i18n( (float) ( $tot['clicks'] ?? 0 ) ) ); ?></strong></li>
					<li class="tp-kpi"><span class="tp-kpi__label"><?php esc_html_e( 'Times shown', 'taxi-peninsula' ); ?></span><strong class="tp-kpi__n"><?php echo esc_html( number_format_i18n( (float) ( $tot['impressions'] ?? 0 ) ) ); ?></strong></li>
					<li class="tp-kpi"><span class="tp-kpi__label"><?php esc_html_e( 'Click rate', 'taxi-peninsula' ); ?></span><strong class="tp-kpi__n"><?php echo esc_html( $pct( $tot['ctr'] ?? 0 ) ); ?></strong></li>
					<li class="tp-kpi"><span class="tp-kpi__label"><?php esc_html_e( 'Average position', 'taxi-peninsula' ); ?></span><strong class="tp-kpi__n"><?php echo esc_html( $pos( $tot['position'] ?? 0 ) ); ?></strong></li>
				</ul>

				<?php $almost = tp_gsc_almost( $data ); ?>
				<h2><?php esc_html_e( 'Almost on page 1 — worth improving', 'taxi-peninsula' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Searches where you rank 8–20. A little work moves them onto the first page: use the words in the page title and first paragraph, add a question and answer about it, and link to the page from related pages.', 'taxi-peninsula' ); ?></p>
				<?php if ( $almost ) : ?>
					<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Search', 'taxi-peninsula' ); ?></th><th class="num"><?php esc_html_e( 'Position', 'taxi-peninsula' ); ?></th><th class="num"><?php esc_html_e( 'Shown', 'taxi-peninsula' ); ?></th><?php if ( $data['pairs'] ) : ?><th><?php esc_html_e( 'Page to improve', 'taxi-peninsula' ); ?></th><?php endif; ?></tr></thead><tbody>
						<?php foreach ( $almost as $r ) : ?>
							<?php $pid = $r['page'] ? url_to_postid( $r['page'] ) : 0; ?>
							<tr><td><?php echo esc_html( $r['keys'][0] ); ?></td><td class="num"><?php echo esc_html( $pos( $r['position'] ) ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( $r['impressions'] ) ); ?></td>
								<?php if ( $data['pairs'] ) : ?><td><?php echo esc_html( $r['page'] ? $path( $r['page'] ) : '—' ); ?><?php if ( $pid && current_user_can( 'edit_post', $pid ) ) : ?> · <a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php esc_html_e( 'Edit', 'taxi-peninsula' ); ?></a><?php endif; ?></td><?php endif; ?></tr>
						<?php endforeach; ?>
					</tbody></table>
				<?php else : ?>
					<p><?php esc_html_e( 'None right now.', 'taxi-peninsula' ); ?></p>
				<?php endif; ?>

				<div class="tp-owner__grid">
					<div>
						<h2><?php esc_html_e( 'Top searches', 'taxi-peninsula' ); ?></h2>
						<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Search', 'taxi-peninsula' ); ?></th><th class="num"><?php esc_html_e( 'Clicks', 'taxi-peninsula' ); ?></th><th class="num"><?php esc_html_e( 'Shown', 'taxi-peninsula' ); ?></th><th class="num"><?php esc_html_e( 'Position', 'taxi-peninsula' ); ?></th></tr></thead><tbody>
							<?php foreach ( array_slice( (array) $data['queries'], 0, 25 ) as $r ) : ?>
								<tr><td><?php echo esc_html( $r['keys'][0] ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( $r['clicks'] ) ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( $r['impressions'] ) ); ?></td><td class="num"><?php echo esc_html( $pos( $r['position'] ) ); ?></td></tr>
							<?php endforeach; ?>
						</tbody></table>
					</div>
					<div>
						<h2><?php esc_html_e( 'Top landing pages', 'taxi-peninsula' ); ?></h2>
						<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Page', 'taxi-peninsula' ); ?></th><th class="num"><?php esc_html_e( 'Clicks', 'taxi-peninsula' ); ?></th><th class="num"><?php esc_html_e( 'Shown', 'taxi-peninsula' ); ?></th></tr></thead><tbody>
							<?php foreach ( array_slice( (array) $data['pages'], 0, 25 ) as $r ) : ?>
								<tr><td><?php echo esc_html( $path( $r['keys'][0] ) ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( $r['clicks'] ) ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( $r['impressions'] ) ); ?></td></tr>
							<?php endforeach; ?>
						</tbody></table>
					</div>
				</div>
			<?php elseif ( tp_gsc_connected() ) : ?>
				<p><?php esc_html_e( 'No data yet — click "Refresh now". New sites can take a few days to show data in Search Console.', 'taxi-peninsula' ); ?></p>
			<?php endif; ?>
			<?php if ( ! tp_gsc_connected() ) : ?>
				<?php tp_gsc_get_data_cards( is_array( $data ) ); ?>
			<?php endif; ?>
			<?php if ( current_user_can( 'manage_options' ) && ( tp_gsc_connected() || is_array( $data ) ) ) : ?>
				<p><a class="button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tp_gsc_disconnect' ), 'tp_gsc_disconnect' ) ); ?>"><?php echo esc_html( tp_gsc_connected() ? __( 'Disconnect Search Console', 'taxi-peninsula' ) : __( 'Clear the uploaded data', 'taxi-peninsula' ) ); ?></a></p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Ways to get Search Console data in, simplest first.
 *
 * @param bool $has_data Data from an earlier upload is showing.
 */
function tp_gsc_get_data_cards( $has_data ) {
	$admin = current_user_can( 'manage_options' );
	?>
	<div class="card tp-gsc-way">
		<h2><?php echo esc_html( $has_data ? __( 'Upload a newer export', 'taxi-peninsula' ) : __( 'Easiest: upload your Search Console export', 'taxi-peninsula' ) ); ?></h2>
		<p><?php esc_html_e( 'No Google setup needed. Takes about a minute — repeat it whenever you want fresh numbers (monthly is plenty).', 'taxi-peninsula' ); ?></p>
		<ol>
			<li><?php echo wp_kses( __( 'Open <a href="https://search.google.com/search-console/performance/search-analytics" target="_blank" rel="noopener">Search Console → Performance</a> and pick your site.', 'taxi-peninsula' ), array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ) ) ); ?></li>
			<li><?php esc_html_e( 'Set the date to "Last 3 months" (or 28 days).', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Click Export (top right) → Download CSV. You get a zip file.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Choose that zip file below and click Upload. (No need to unzip it.)', 'taxi-peninsula' ); ?></li>
		</ol>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tp_gsc_upload"><?php wp_nonce_field( 'tp_gsc_upload' ); ?>
			<label class="screen-reader-text" for="tp-gsc-file"><?php esc_html_e( 'Search Console export', 'taxi-peninsula' ); ?></label>
			<input id="tp-gsc-file" type="file" name="gsc[]" accept=".zip,.csv" multiple required>
			<?php submit_button( __( 'Upload', 'taxi-peninsula' ), 'primary', '', false ); ?>
		</form>
	</div>
	<?php if ( $admin ) : ?>
		<details class="card tp-gsc-way">
			<summary><strong><?php esc_html_e( 'Automatic weekly updates: paste a service-account key', 'taxi-peninsula' ); ?></strong> — <?php esc_html_e( 'about 10 minutes, once', 'taxi-peninsula' ); ?></summary>
			<ol>
				<li><?php echo wp_kses( __( 'In <a href="https://console.cloud.google.com/apis/library/searchconsole.googleapis.com" target="_blank" rel="noopener">Google Cloud</a>, create a project (any name) and click Enable on the "Google Search Console API".', 'taxi-peninsula' ), array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ) ) ); ?></li>
				<li><?php echo wp_kses( __( 'Go to <a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank" rel="noopener">IAM → Service accounts</a> → Create service account (no roles needed). Open it → Keys → Add key → JSON. A file downloads.', 'taxi-peninsula' ), array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ) ) ); ?></li>
				<li><?php esc_html_e( 'In Search Console → Settings → Users and permissions → Add user: paste the service account’s email (it ends in .iam.gserviceaccount.com) with "Restricted" permission.', 'taxi-peninsula' ); ?></li>
				<li><?php esc_html_e( 'Choose the downloaded JSON file below (or paste its contents) and click Save.', 'taxi-peninsula' ); ?></li>
			</ol>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tp_gsc_key"><?php wp_nonce_field( 'tp_gsc_key' ); ?>
				<p><label for="tp-gsc-keyfile"><?php esc_html_e( 'Key file (.json)', 'taxi-peninsula' ); ?></label><br><input id="tp-gsc-keyfile" type="file" name="keyfile" accept=".json,application/json"></p>
				<p><label for="tp-gsc-key"><?php esc_html_e( 'Or paste the key', 'taxi-peninsula' ); ?></label><br><textarea id="tp-gsc-key" name="key" rows="4" class="large-text code" autocomplete="off" spellcheck="false" placeholder='{"type": "service_account", ...}'></textarea></p>
				<?php submit_button( __( 'Save key', 'taxi-peninsula' ), 'secondary', '', false ); ?>
				<p class="description"><?php esc_html_e( 'The key only allows reading Search Console data. It is stored in your WordPress database and never shown again.', 'taxi-peninsula' ); ?></p>
			</form>
		</details>
		<details class="card tp-gsc-way">
			<summary><strong><?php esc_html_e( 'Or sign in with Google', 'taxi-peninsula' ); ?></strong> — <?php esc_html_e( 'if you already set up Google profile posts', 'taxi-peninsula' ); ?></summary>
			<?php if ( ! tp_setting( 'gbp_client_id' ) || ! tp_setting( 'gbp_client_secret' ) ) : ?>
				<p><?php esc_html_e( 'This uses the same Google OAuth client as Google profile posts. Create it first (Marketing → Google profile shows how), then come back.', 'taxi-peninsula' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'In Google Cloud, enable the "Google Search Console API" for your project and add this authorised redirect URI to the OAuth client:', 'taxi-peninsula' ); ?> <code><?php echo esc_html( tp_gsc_redirect_uri() ); ?></code></p>
				<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tp_gsc_connect' ), 'tp_gsc_connect' ) ); ?>"><?php esc_html_e( 'Connect Search Console', 'taxi-peninsula' ); ?></a></p>
			<?php endif; ?>
		</details>
	<?php endif; ?>
	<?php
}

function tp_gsc_back( $msg ) {
	wp_safe_redirect( add_query_arg( 'tp_msg', rawurlencode( $msg ), admin_url( 'admin.php?page=tp-search' ) ) );
	exit;
}

add_action(
	'admin_post_tp_gsc_connect',
	static function () {
		check_admin_referer( 'tp_gsc_connect' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		$state = wp_generate_password( 20, false );
		set_transient( 'tp_gsc_oauth_' . get_current_user_id(), $state, 15 * MINUTE_IN_SECONDS );
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Google sign-in.
		wp_redirect( add_query_arg( array( 'client_id' => rawurlencode( tp_setting( 'gbp_client_id' ) ), 'redirect_uri' => rawurlencode( tp_gsc_redirect_uri() ), 'response_type' => 'code', 'scope' => rawurlencode( 'https://www.googleapis.com/auth/webmasters.readonly' ), 'access_type' => 'offline', 'prompt' => 'consent', 'state' => $state ), 'https://accounts.google.com/o/oauth2/v2/auth' ) );
		exit;
	}
);

add_action(
	'admin_post_tp_gsc_oauth',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth state is checked instead.
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$want  = get_transient( 'tp_gsc_oauth_' . get_current_user_id() );
		delete_transient( 'tp_gsc_oauth_' . get_current_user_id() );
		if ( ! $want || ! hash_equals( (string) $want, $state ) || ! empty( $_GET['error'] ) ) {
			tp_gsc_back( __( 'Google sign-in was cancelled or expired. Please try again.', 'taxi-peninsula' ) );
		}
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		// phpcs:enable
		$r    = wp_remote_post( 'https://oauth2.googleapis.com/token', array( 'timeout' => 15, 'body' => array( 'code' => $code, 'client_id' => tp_setting( 'gbp_client_id' ), 'client_secret' => tp_setting( 'gbp_client_secret' ), 'redirect_uri' => tp_gsc_redirect_uri(), 'grant_type' => 'authorization_code' ) ) );
		$body = is_wp_error( $r ) ? array() : json_decode( wp_remote_retrieve_body( $r ), true );
		if ( empty( $body['refresh_token'] ) ) {
			tp_gsc_back( __( 'Google did not give access. Check the redirect URI and that the Search Console API is enabled.', 'taxi-peninsula' ) );
		}
		update_option( 'tp_gsc', array( 'refresh' => $body['refresh_token'], 'sa' => array(), 'access' => $body['access_token'] ?? '', 'expires' => time() + (int) ( $body['expires_in'] ?? 3000 ), 'site' => '' ), false );
		tp_gsc_pick_site();
		tp_gsc_back( __( 'Search Console connected.', 'taxi-peninsula' ) );
	}
);

add_action(
	'admin_post_tp_gsc_site',
	static function () {
		check_admin_referer( 'tp_gsc_site' );
		if ( ! current_user_can( 'tp_view_leads' ) ) {
			wp_die( '', 403 );
		}
		$st         = tp_gsc_state();
		$st['site'] = isset( $_POST['site'] ) ? sanitize_text_field( wp_unslash( $_POST['site'] ) ) : '';
		update_option( 'tp_gsc', $st, false );
		$res = tp_gsc_refresh();
		tp_gsc_back( is_wp_error( $res ) ? $res->get_error_message() : __( 'Property saved and data loaded.', 'taxi-peninsula' ) );
	}
);

add_action(
	'admin_post_tp_gsc_refresh',
	static function () {
		check_admin_referer( 'tp_gsc_refresh' );
		if ( ! current_user_can( 'tp_view_leads' ) ) {
			wp_die( '', 403 );
		}
		$res = tp_gsc_refresh();
		tp_gsc_back( is_wp_error( $res ) ? $res->get_error_message() : __( 'Updated.', 'taxi-peninsula' ) );
	}
);

add_action(
	'admin_post_tp_gsc_disconnect',
	static function () {
		check_admin_referer( 'tp_gsc_disconnect' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		delete_option( 'tp_gsc' );
		delete_option( 'tp_gsc_data' );
		tp_gsc_back( __( 'Disconnected.', 'taxi-peninsula' ) );
	}
);

/* =========================================================================
 * Bing Webmaster data.
 * ====================================================================== */

function tp_bing_refresh() {
	$key = (string) tp_setting( 'bing_api_key' );
	if ( '' === $key ) {
		return new WP_Error( 'tp_bing_off', __( 'Add your Bing Webmaster API key first.', 'taxi-peninsula' ) );
	}
	$out = array( 'fetched' => time() );
	foreach ( array( 'queries' => 'GetQueryStats', 'pages' => 'GetPageStats' ) as $k => $method ) {
		$r = wp_remote_get( add_query_arg( array( 'siteUrl' => rawurlencode( home_url( '/' ) ), 'apikey' => rawurlencode( $key ) ), 'https://ssl.bing.com/webmaster/api.svc/json/' . $method ), array( 'timeout' => 20 ) );
		if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) {
			/* translators: %s: code */
			return new WP_Error( 'tp_bing_api', sprintf( __( 'Bing did not return data (%s). Check the API key and that the site is verified in Bing Webmaster Tools.', 'taxi-peninsula' ), is_wp_error( $r ) ? $r->get_error_message() : wp_remote_retrieve_response_code( $r ) ) );
		}
		$rows = (array) ( json_decode( wp_remote_retrieve_body( $r ), true )['d'] ?? array() );
		$sum  = array();
		foreach ( $rows as $row ) {
			$q = (string) ( $row['Query'] ?? '' );
			if ( '' === $q ) {
				continue;
			}
			$sum[ $q ]['clicks']      = ( $sum[ $q ]['clicks'] ?? 0 ) + (int) ( $row['Clicks'] ?? 0 );
			$sum[ $q ]['impressions'] = ( $sum[ $q ]['impressions'] ?? 0 ) + (int) ( $row['Impressions'] ?? 0 );
			$sum[ $q ]['position']    = (float) ( $row['AvgImpressionPosition'] ?? 0 );
		}
		uasort( $sum, static fn( $a, $b ) => array( $b['clicks'], $b['impressions'] ) <=> array( $a['clicks'], $a['impressions'] ) );
		$out[ $k ] = array_slice( $sum, 0, 50, true );
	}
	update_option( 'tp_bing_data', $out, false );
	return $out;
}

add_action(
	'admin_post_tp_bing_refresh',
	static function () {
		check_admin_referer( 'tp_bing_refresh' );
		if ( ! current_user_can( 'tp_manage_offers' ) ) {
			wp_die( '', 403 );
		}
		$res = tp_bing_refresh();
		wp_safe_redirect( add_query_arg( 'tp_note', rawurlencode( is_wp_error( $res ) ? $res->get_error_message() : __( 'Bing data updated.', 'taxi-peninsula' ) ), admin_url( 'admin.php?page=tp-ai-search' ) ) . '#tp-bing' );
		exit;
	}
);

/* =========================================================================
 * AI crawler visits.
 * ====================================================================== */

const TP_BOTS_DB = 1;

function tp_bots_table() {
	global $wpdb;
	return $wpdb->prefix . 'tp_bots';
}

/**
 * Crawlers we count: user-agent token => [name, kind].
 */
function tp_known_bots() {
	return array(
		'OAI-SearchBot'      => array( 'ChatGPT search', 'ai-search' ),
		'ChatGPT-User'       => array( 'ChatGPT (reading for a user)', 'ai-search' ),
		'GPTBot'             => array( 'OpenAI training', 'ai-training' ),
		'PerplexityBot'      => array( 'Perplexity', 'ai-search' ),
		'Perplexity-User'    => array( 'Perplexity (reading for a user)', 'ai-search' ),
		'Claude-SearchBot'   => array( 'Claude search', 'ai-search' ),
		'Claude-User'        => array( 'Claude (reading for a user)', 'ai-search' ),
		'ClaudeBot'          => array( 'Anthropic training', 'ai-training' ),
		'DuckAssistBot'      => array( 'DuckDuckGo AI', 'ai-search' ),
		'Applebot'           => array( 'Apple (Siri, Spotlight)', 'search' ),
		'bingbot'            => array( 'Bing (also feeds Copilot and ChatGPT search)', 'search' ),
		'Googlebot'          => array( 'Google', 'search' ),
		'Meta-ExternalAgent' => array( 'Meta AI', 'ai-training' ),
		'Bytespider'         => array( 'ByteDance', 'ai-training' ),
		'CCBot'              => array( 'Common Crawl', 'ai-training' ),
		'Amazonbot'          => array( 'Amazon (Alexa)', 'ai-search' ),
	);
}

add_action(
	'init',
	static function () {
		if ( (int) get_option( 'tp_bots_db' ) !== TP_BOTS_DB ) {
			global $wpdb;
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$table = tp_bots_table();
			dbDelta(
				"CREATE TABLE {$table} (
					id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					day date NOT NULL,
					bot varchar(40) NOT NULL DEFAULT '',
					path varchar(191) NOT NULL DEFAULT '',
					hits int(10) unsigned NOT NULL DEFAULT 0,
					last_seen datetime NULL DEFAULT NULL,
					PRIMARY KEY  (id),
					KEY day_bot (day, bot),
					KEY bot_path (bot, path)
				) {$wpdb->get_charset_collate()};"
			);
			update_option( 'tp_bots_db', TP_BOTS_DB, false );
		}
		if ( is_admin() || wp_doing_cron() || wp_doing_ajax() ) {
			return;
		}
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only matched.
		foreach ( array_keys( tp_known_bots() ) as $token ) {
			if ( false !== stripos( $ua, $token ) ) {
				tp_bot_hit( $token );
				return;
			}
		}
	},
	2
);

function tp_bot_hit( $bot ) {
	global $wpdb;
	$path  = isset( $_SERVER['REQUEST_URI'] ) ? mb_substr( (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ), 0, 191 ) : '/';
	$day   = wp_date( 'Y-m-d' );
	$table = tp_bots_table();
	$now   = current_time( 'mysql' );
	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$done = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET hits = hits + 1, last_seen = %s WHERE day = %s AND bot = %s AND path = %s", $now, $day, $bot, $path ) );
	if ( ! $done ) {
		$wpdb->insert( $table, array( 'day' => $day, 'bot' => $bot, 'path' => $path, 'hits' => 1, 'last_seen' => $now ) );
	}
	// phpcs:enable
}

/**
 * Crawler summary for the last N days: token => [hits, last, pages[path => hits]].
 */
function tp_bot_summary( $days = 30 ) {
	global $wpdb;
	$table = tp_bots_table();
	$since = wp_date( 'Y-m-d', time() - $days * DAY_IN_SECONDS );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT bot, path, SUM(hits) n, MAX(last_seen) last FROM {$table} WHERE day >= %s GROUP BY bot, path", $since ) );
	$out  = array();
	foreach ( (array) $rows as $r ) {
		$out[ $r->bot ]['hits']             = ( $out[ $r->bot ]['hits'] ?? 0 ) + (int) $r->n;
		$out[ $r->bot ]['last']             = max( $out[ $r->bot ]['last'] ?? '', (string) $r->last );
		$out[ $r->bot ]['pages'][ $r->path ] = (int) $r->n;
	}
	foreach ( $out as &$b ) {
		arsort( $b['pages'] );
		$b['pages'] = array_slice( $b['pages'], 0, 5, true );
	}
	uasort( $out, static fn( $a, $b ) => $b['hits'] <=> $a['hits'] );
	return $out;
}

/* =========================================================================
 * Partner links.
 * ====================================================================== */

function tp_partner_link_statuses() {
	return array(
		''         => __( 'Not asked yet', 'taxi-peninsula' ),
		'asked'    => __( 'Asked', 'taxi-peninsula' ),
		'linked'   => __( 'Linked', 'taxi-peninsula' ),
		'declined' => __( 'Declined', 'taxi-peninsula' ),
	);
}

/**
 * Fetch the partner's page and look for a link to this site.
 *
 * @return bool|null True found, false not found, null could not check.
 */
function tp_check_partner_link( $venue_id ) {
	$url = (string) get_post_meta( $venue_id, '_tp_v_link_page', true );
	if ( ! $url || ! wp_http_validate_url( $url ) ) {
		return null;
	}
	$r = wp_remote_get( $url, array( 'timeout' => 15, 'redirection' => 3, 'user-agent' => 'Mozilla/5.0 (link check; ' . home_url( '/' ) . ')' ) );
	if ( is_wp_error( $r ) || wp_remote_retrieve_response_code( $r ) >= 400 ) {
		update_post_meta( $venue_id, '_tp_v_link_checked', array( 'time' => time(), 'ok' => null ) );
		return null;
	}
	$host = preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$ok   = (bool) preg_match( '#href=["\']https?://(www\.)?' . preg_quote( $host, '#' ) . '#i', wp_remote_retrieve_body( $r ) );
	update_post_meta( $venue_id, '_tp_v_link_checked', array( 'time' => time(), 'ok' => $ok ) );
	if ( $ok ) {
		update_post_meta( $venue_id, '_tp_v_link_status', 'linked' );
	}
	return $ok;
}

add_action(
	'tp_venue_box_after',
	static function ( $post ) {
		$status  = (string) get_post_meta( $post->ID, '_tp_v_link_status', true );
		$page    = (string) get_post_meta( $post->ID, '_tp_v_link_page', true );
		$checked = get_post_meta( $post->ID, '_tp_v_link_checked', true );
		?>
		<table class="form-table" role="presentation">
			<tr><th><label for="tp_v_link_status"><?php esc_html_e( 'Link to us', 'taxi-peninsula' ); ?></label></th><td>
				<select id="tp_v_link_status" name="tp_v_link_status">
					<?php foreach ( tp_partner_link_statuses() as $k => $l ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $status, $k ); ?>><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
				</select></td></tr>
			<tr><th><label for="tp_v_link_page"><?php esc_html_e( 'Their page that links to you', 'taxi-peninsula' ); ?></label></th><td>
				<input type="url" class="large-text" id="tp_v_link_page" name="tp_v_link_page" value="<?php echo esc_attr( $page ); ?>" placeholder="https://">
				<p class="description">
					<?php
					if ( is_array( $checked ) ) {
						/* translators: %s: date */
						echo esc_html( sprintf( null === $checked['ok'] ? __( 'Could not open the page on %s.', 'taxi-peninsula' ) : ( $checked['ok'] ? __( 'Link found on %s.', 'taxi-peninsula' ) : __( 'No link to your site found on %s.', 'taxi-peninsula' ) ), wp_date( 'j M Y', $checked['time'] ) ) );
					} else {
						esc_html_e( 'When saved, the page is checked for a link to your site — and re-checked every week.', 'taxi-peninsula' );
					}
					?>
				</p></td></tr>
		</table>
		<?php
	}
);

add_action(
	'save_post_tp_venue',
	static function ( $post_id ) {
		if ( ! isset( $_POST['tp_venue_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tp_venue_nonce'] ), 'tp_venue_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$status = isset( $_POST['tp_v_link_status'] ) ? sanitize_key( wp_unslash( $_POST['tp_v_link_status'] ) ) : '';
		$old    = (string) get_post_meta( $post_id, '_tp_v_link_status', true );
		update_post_meta( $post_id, '_tp_v_link_status', isset( tp_partner_link_statuses()[ $status ] ) ? $status : '' );
		if ( 'asked' === $status && 'asked' !== $old ) {
			update_post_meta( $post_id, '_tp_v_link_asked', current_time( 'mysql' ) );
		}
		$page = isset( $_POST['tp_v_link_page'] ) ? esc_url_raw( wp_unslash( $_POST['tp_v_link_page'] ) ) : '';
		update_post_meta( $post_id, '_tp_v_link_page', $page );
		if ( $page ) {
			tp_check_partner_link( $post_id );
		}
	},
	20
);

add_filter(
	'manage_tp_venue_posts_columns',
	static function ( $c ) {
		$c['tp_link'] = __( 'Links to you', 'taxi-peninsula' );
		return $c;
	},
	20
);
add_action(
	'manage_tp_venue_posts_custom_column',
	static function ( $col, $id ) {
		if ( 'tp_link' === $col ) {
			$st = (string) get_post_meta( $id, '_tp_v_link_status', true );
			echo esc_html( tp_partner_link_statuses()[ $st ] ?? '' );
		}
	},
	10,
	2
);

/**
 * [linked, total] partners.
 */
function tp_partner_link_counts() {
	$all    = get_posts( array( 'post_type' => 'tp_venue', 'post_status' => 'publish', 'numberposts' => 300, 'fields' => 'ids' ) );
	$linked = array_filter( $all, static fn( $id ) => 'linked' === get_post_meta( $id, '_tp_v_link_status', true ) );
	return array( count( $linked ), count( $all ) );
}

/* =========================================================================
 * Monthly AI check.
 * ====================================================================== */

function tp_ai_assistants() {
	return array(
		'chatgpt'    => array( 'ChatGPT', 'https://chatgpt.com/?q=%s' ),
		'copilot'    => array( 'Copilot', 'https://copilot.microsoft.com/?q=%s' ),
		'perplexity' => array( 'Perplexity', 'https://www.perplexity.ai/search?q=%s' ),
		'google'     => array( 'Google (AI Overview)', 'https://www.google.com/search?q=%s' ),
	);
}

function tp_ai_prompts() {
	$saved = trim( (string) get_option( 'tp_ai_prompts', '' ) );
	if ( '' !== $saved ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', $saved ) ) ) );
	}
	$areas = array_slice( tp_service_areas(), 0, 2 );
	$first = $areas[0] ?? 'Melbourne';
	return array(
		/* translators: %s: suburb */
		sprintf( __( 'best wheelchair taxi in %s', 'taxi-peninsula' ), $first ),
		__( 'wheelchair accessible taxi Mornington Peninsula', 'taxi-peninsula' ),
		/* translators: %s: suburb */
		sprintf( __( 'NDIS wheelchair taxi near %s', 'taxi-peninsula' ), $first ),
		/* translators: %s: suburb */
		sprintf( __( 'wheelchair taxi from %s to Melbourne Airport', 'taxi-peninsula' ), $areas[1] ?? $first ),
	);
}

/**
 * Check results: month (Y-m) => [ "i|assistant" => yes|no, note ].
 */
function tp_ai_checks() {
	return (array) get_option( 'tp_ai_checks', array() );
}

function tp_ai_check_due() {
	$month = wp_date( 'Y-m' );
	$c     = tp_ai_checks()[ $month ] ?? array();
	return ! array_filter( (array) ( $c['r'] ?? array() ) );
}

add_action(
	'admin_post_tp_ai_check_save',
	static function () {
		check_admin_referer( 'tp_ai_check_save' );
		if ( ! current_user_can( 'tp_manage_offers' ) ) {
			wp_die( '', 403 );
		}
		if ( isset( $_POST['prompts'] ) ) {
			update_option( 'tp_ai_prompts', sanitize_textarea_field( wp_unslash( $_POST['prompts'] ) ), false );
		}
		$month  = wp_date( 'Y-m' );
		$checks = tp_ai_checks();
		$res    = array();
		foreach ( (array) ( $_POST['r'] ?? array() ) as $k => $v ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
			$v = sanitize_key( $v );
			if ( in_array( $v, array( 'yes', 'no' ), true ) ) {
				$res[ sanitize_text_field( (string) $k ) ] = $v;
			}
		}
		$checks[ $month ] = array( 'r' => $res, 'note' => isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '', 'by' => wp_get_current_user()->display_name, 'prompts' => tp_ai_prompts() );
		krsort( $checks );
		update_option( 'tp_ai_checks', array_slice( $checks, 0, 24, true ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=tp-ai-search&tp_msg=saved#tp-ai-check' ) );
		exit;
	}
);

// Reminder on the 1st of each month, and a notice until it is done.
add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'tp_monthly_seo' ) ) {
			wp_schedule_event( strtotime( 'first day of next month 09:00' ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ), 'daily', 'tp_monthly_seo' );
		}
		if ( ! wp_next_scheduled( 'tp_weekly_seo' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', 'tp_weekly_seo' );
		}
	}
);

add_action(
	'tp_monthly_seo',
	static function () {
		global $wpdb;
		// Keep 90 days of crawler visits.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . tp_bots_table() . ' WHERE day < %s', wp_date( 'Y-m-d', time() - 90 * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		if ( '01' !== wp_date( 'd' ) || get_option( 'tp_ai_reminded' ) === wp_date( 'Y-m' ) ) {
			return;
		}
		update_option( 'tp_ai_reminded', wp_date( 'Y-m' ), false );
		$to = array_unique( array_filter( array( get_option( 'admin_email' ), tp_opt( 'notify_email' ) ) ) );
		foreach ( get_users( array( 'role' => 'tp_marketing', 'fields' => array( 'user_email' ) ) ) as $u ) {
			$to[] = $u->user_email;
		}
		wp_mail(
			array_unique( $to ),
			__( 'Time for your monthly AI check', 'taxi-peninsula' ),
			__( 'Once a month, ask ChatGPT, Copilot, Perplexity and Google a few questions your customers ask, and note whether your business is named. It takes about 10 minutes and shows whether the AI and Bing work is paying off.', 'taxi-peninsula' ) . "\n\n" . __( 'Start the check', 'taxi-peninsula' ) . ': ' . admin_url( 'admin.php?page=tp-ai-search#tp-ai-check' ),
			tp_mail_headers()
		);
	}
);

add_action(
	'tp_weekly_seo',
	static function () {
		if ( tp_gsc_state()['site'] ) {
			tp_gsc_refresh();
		}
		if ( tp_setting( 'bing_api_key' ) ) {
			tp_bing_refresh();
		}
		foreach ( get_posts( array( 'post_type' => 'tp_venue', 'post_status' => 'publish', 'numberposts' => 100, 'fields' => 'ids', 'meta_key' => '_tp_v_link_page', 'meta_compare' => '!=', 'meta_value' => '' ) ) as $id ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			tp_check_partner_link( $id );
		}
	}
);

add_action(
	'admin_notices',
	static function () {
		$screen = get_current_screen();
		if ( ! current_user_can( 'tp_manage_offers' ) || ! $screen || 'dashboard' !== $screen->id || ! tp_ai_check_due() || (int) wp_date( 'j' ) < 1 ) {
			return;
		}
		printf( '<div class="notice notice-info"><p>%1$s <a href="%2$s">%3$s</a></p></div>', esc_html__( 'This month’s AI check has not been done yet.', 'taxi-peninsula' ), esc_url( admin_url( 'admin.php?page=tp-ai-search#tp-ai-check' ) ), esc_html__( 'Do it now (about 10 minutes)', 'taxi-peninsula' ) );
	}
);

/**
 * Sections added to Marketing → AI & Bing search.
 */
add_action(
	'tp_ai_search_after',
	static function () {
		$bots = tp_bot_summary( 30 );
		$kb   = tp_known_bots();
		$kind = array( 'ai-search' => __( 'AI search', 'taxi-peninsula' ), 'ai-training' => __( 'AI training', 'taxi-peninsula' ), 'search' => __( 'Search engine', 'taxi-peninsula' ) );
		$bing = get_option( 'tp_bing_data' );
		$note = isset( $_GET['tp_note'] ) ? sanitize_text_field( wp_unslash( $_GET['tp_note'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		list( $linked, $partners ) = tp_partner_link_counts();
		?>
		<h2 id="tp-bots"><?php esc_html_e( 'Which AI and search bots read your site (last 30 days)', 'taxi-peninsula' ); ?></h2>
		<p class="description"><?php esc_html_e( 'The first sign that AI assistants know about you. Bots identify themselves, so these numbers are a guide.', 'taxi-peninsula' ); ?></p>
		<?php if ( $bots ) : ?>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Bot', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Type', 'taxi-peninsula' ); ?></th><th class="num"><?php esc_html_e( 'Pages read', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Last visit', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Most-read pages', 'taxi-peninsula' ); ?></th></tr></thead><tbody>
				<?php foreach ( $bots as $token => $b ) : ?>
					<tr><td><?php echo esc_html( $kb[ $token ][0] ?? $token ); ?></td><td><?php echo esc_html( $kind[ $kb[ $token ][1] ?? 'search' ] ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( $b['hits'] ) ); ?></td><td><?php echo esc_html( $b['last'] ? wp_date( 'j M, g:i a', strtotime( $b['last'] ) ) : '' ); ?></td><td><?php echo esc_html( implode( ', ', array_keys( $b['pages'] ) ) ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>
		<?php else : ?>
			<p><?php esc_html_e( 'No bot visits recorded yet.', 'taxi-peninsula' ); ?></p>
		<?php endif; ?>

		<h2 id="tp-bing"><?php esc_html_e( 'Bing searches that show your site', 'taxi-peninsula' ); ?></h2>
		<?php if ( $note ) : ?>
			<div class="notice notice-info inline"><p><?php echo esc_html( $note ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! tp_setting( 'bing_api_key' ) ) : ?>
			<p><?php esc_html_e( 'Add your Bing Webmaster API key (Taxi settings → Settings → Search engines & AI) to see your Bing searches and clicks here. Bing results also power Copilot and much of ChatGPT search.', 'taxi-peninsula' ); ?></p>
		<?php else : ?>
			<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tp_bing_refresh' ), 'tp_bing_refresh' ) ); ?>"><?php esc_html_e( 'Refresh Bing data', 'taxi-peninsula' ); ?></a> <?php echo is_array( $bing ) ? esc_html( sprintf( /* translators: %s: date */ __( 'Updated %s', 'taxi-peninsula' ), wp_date( 'j M Y', $bing['fetched'] ) ) ) : ''; ?></p>
			<?php if ( is_array( $bing ) ) : ?>
				<div class="tp-owner__grid">
					<?php foreach ( array( 'queries' => __( 'Search', 'taxi-peninsula' ), 'pages' => __( 'Page', 'taxi-peninsula' ) ) as $k => $label ) : ?>
						<div><table class="widefat striped"><thead><tr><th><?php echo esc_html( $label ); ?></th><th class="num"><?php esc_html_e( 'Clicks', 'taxi-peninsula' ); ?></th><th class="num"><?php esc_html_e( 'Shown', 'taxi-peninsula' ); ?></th></tr></thead><tbody>
							<?php foreach ( array_slice( (array) ( $bing[ $k ] ?? array() ), 0, 20, true ) as $q => $r ) : ?>
								<tr><td><?php echo esc_html( 'pages' === $k ? ( (string) wp_parse_url( $q, PHP_URL_PATH ) ?: '/' ) : $q ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( $r['clicks'] ) ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( $r['impressions'] ) ); ?></td></tr>
							<?php endforeach; ?>
						</tbody></table></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<h2 id="tp-links"><?php esc_html_e( 'Partner links', 'taxi-peninsula' ); ?></h2>
		<p>
			<?php
			/* translators: 1: linked 2: partners */
			echo esc_html( sprintf( __( '%1$d of %2$d partners link to your website.', 'taxi-peninsula' ), $linked, $partners ) );
			?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=tp_venue' ) ); ?>"><?php esc_html_e( 'Open partner pages', 'taxi-peninsula' ); ?></a>
			— <?php esc_html_e( 'each one has a "link to us" snippet to send and a place to record their link (checked weekly).', 'taxi-peninsula' ); ?>
		</p>

		<?php
		$prompts = tp_ai_prompts();
		$asst    = tp_ai_assistants();
		$checks  = tp_ai_checks();
		$month   = wp_date( 'Y-m' );
		$now     = $checks[ $month ]['r'] ?? array();
		?>
		<h2 id="tp-ai-check"><?php esc_html_e( 'Monthly AI check', 'taxi-peninsula' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Once a month: click each "Ask" link, read the answer, and mark whether your business is named. A reminder is emailed on the 1st of each month.', 'taxi-peninsula' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tp_ai_check_save"><?php wp_nonce_field( 'tp_ai_check_save' ); ?>
			<table class="widefat striped tp-ai__check">
				<thead><tr><th><?php echo esc_html( wp_date( 'F Y' ) ); ?></th>
					<?php foreach ( $asst as $a ) : ?>
						<th><?php echo esc_html( $a[0] ); ?></th>
					<?php endforeach; ?>
				</tr></thead>
				<tbody>
					<?php foreach ( $prompts as $i => $p ) : ?>
						<tr><th scope="row"><?php echo esc_html( $p ); ?></th>
							<?php foreach ( $asst as $ak => $a ) : ?>
								<?php $key = $i . '|' . $ak; ?>
								<td>
									<a href="<?php echo esc_url( sprintf( $a[1], rawurlencode( $p ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ask', 'taxi-peninsula' ); ?></a>
									<label class="screen-reader-text" for="r-<?php echo esc_attr( $i . '-' . $ak ); ?>"><?php echo esc_html( $a[0] . ': ' . $p ); ?></label>
									<select id="r-<?php echo esc_attr( $i . '-' . $ak ); ?>" name="r[<?php echo esc_attr( $key ); ?>]">
										<option value="">—</option>
										<option value="yes" <?php selected( $now[ $key ] ?? '', 'yes' ); ?>><?php esc_html_e( 'Named us', 'taxi-peninsula' ); ?></option>
										<option value="no" <?php selected( $now[ $key ] ?? '', 'no' ); ?>><?php esc_html_e( 'Not named', 'taxi-peninsula' ); ?></option>
									</select>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><label for="tp-ai-note"><?php esc_html_e( 'Notes (who was named instead, what the answer said)', 'taxi-peninsula' ); ?></label><br><textarea id="tp-ai-note" name="note" class="large-text" rows="2"><?php echo esc_textarea( $checks[ $month ]['note'] ?? '' ); ?></textarea></p>
			<details><summary><?php esc_html_e( 'Change the questions', 'taxi-peninsula' ); ?></summary>
				<p><label for="tp-ai-prompts" class="screen-reader-text"><?php esc_html_e( 'Questions', 'taxi-peninsula' ); ?></label><textarea id="tp-ai-prompts" name="prompts" class="large-text" rows="5"><?php echo esc_textarea( implode( "\n", $prompts ) ); ?></textarea></p>
				<p class="description"><?php esc_html_e( 'One per line — the way customers would ask. Changing questions starts fresh comparisons.', 'taxi-peninsula' ); ?></p>
			</details>
			<?php submit_button( __( 'Save this month’s check', 'taxi-peninsula' ) ); ?>
		</form>
		<?php if ( $checks ) : ?>
			<table class="widefat striped tp-ai__history"><thead><tr><th><?php esc_html_e( 'Month', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Named in', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'By', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Notes', 'taxi-peninsula' ); ?></th></tr></thead><tbody>
				<?php foreach ( $checks as $m => $c ) : ?>
					<?php
					$r   = (array) ( $c['r'] ?? array() );
					$yes = count( array_filter( $r, static fn( $v ) => 'yes' === $v ) );
					?>
					<tr><td><?php echo esc_html( wp_date( 'F Y', strtotime( $m . '-01' ) ) ); ?></td><td><?php echo esc_html( sprintf( /* translators: 1: yes 2: checked */ __( '%1$d of %2$d answers', 'taxi-peninsula' ), $yes, count( $r ) ) ); ?></td><td><?php echo esc_html( $c['by'] ?? '' ); ?></td><td><?php echo esc_html( $c['note'] ?? '' ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>
		<?php
	}
);
