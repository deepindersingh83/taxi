<?php
/**
 * Marketing → AI & Bing search: get recommended by Bing, ChatGPT search and
 * Microsoft Copilot, and see the calls and bookings they bring.
 *
 * - Results: calls, bookings, quotes and revenue from AI assistants and Bing.
 * - Checklist: Bing verification, IndexNow, /llms.txt, AI crawlers allowed,
 *   business details, Bing Places and Apple Business Connect listings, FAQs.
 * - Actions: save the Bing code and AI settings, send every page to Bing now.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( tp_admin_parent( 'marketing' ), __( 'AI & Bing search', 'taxi-peninsula' ), __( 'AI & Bing search', 'taxi-peninsula' ), 'tp_manage_offers', 'tp-ai-search', 'tp_render_ai_search_page', 1 );
	},
	20
);

/**
 * Leads from AI assistants and Bing in the last N days.
 *
 * @return array { rows: source => [type => n], bookings: n, revenue: float }
 */
function tp_ai_search_results( $days ) {
	global $wpdb;
	$since = wp_date( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
	$rows  = array();
	if ( function_exists( 'tp_track_table' ) && (int) get_option( 'tp_track_db' ) ) {
		$table = tp_track_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT source, medium, type, COUNT(*) n FROM {$table} WHERE created >= %s AND ( medium = 'ai' OR source = 'bing' ) GROUP BY source, medium, type", $since ) ) as $r ) {
			$names                       = array( 'chatgpt' => 'ChatGPT', 'copilot' => 'Microsoft Copilot', 'perplexity' => 'Perplexity', 'gemini' => 'Google Gemini', 'claude' => 'Claude', 'meta-ai' => 'Meta AI', 'you' => 'You.com' );
			$label                       = 'bing' === $r->source ? ( 'cpc' === $r->medium ? 'Bing Ads' : 'Bing' ) : ( $names[ $r->source ] ?? ucfirst( $r->source ) );
			$rows[ $label ][ $r->type ] = ( $rows[ $label ][ $r->type ] ?? 0 ) + (int) $r->n;
		}
	}
	// Revenue from bookings that came from AI assistants or Bing.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$money = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT COUNT(*) n, SUM(CAST(f.meta_value AS DECIMAL(10,2))) total
			FROM {$wpdb->posts} p
			JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_tp_source'
			LEFT JOIN {$wpdb->postmeta} f ON f.post_id = p.ID AND f.meta_key = '_tp_fare'
			WHERE p.post_type = 'tp_booking' AND p.post_status = 'publish' AND p.post_date >= %s
			AND ( s.meta_value LIKE %s OR s.meta_value LIKE %s )",
			$since,
			'% / ai%',
			'bing /%'
		)
	);
	return array( 'rows' => $rows, 'bookings' => (int) ( $money->n ?? 0 ), 'revenue' => (float) ( $money->total ?? 0 ) );
}

/**
 * Checklist items: key => [done bool, label, how, link, manual].
 */
function tp_ai_checklist() {
	$manual = (array) get_option( 'tp_ai_manual', array() );
	$log    = (array) get_option( 'tp_indexnow_log', array() );
	$sent   = (bool) array_filter( $log, static fn( $l ) => in_array( (int) $l['code'], array( 200, 202 ), true ) );
	$faqs   = (int) ( wp_count_posts( 'tp_faq' )->publish ?? 0 );
	$https  = 0 === strpos( home_url(), 'https://' );
	return array(
		'bing'      => array( (bool) tp_setting( 'bing_site_verification' ), __( 'Verify the site in Bing Webmaster Tools', 'taxi-peninsula' ), __( 'Sign in at bing.com/webmasters (you can import from Google Search Console in one click), choose the HTML meta tag and paste the code below. Then submit your sitemap: ', 'taxi-peninsula' ) . home_url( '/wp-sitemap.xml' ), 'https://www.bing.com/webmasters', false ),
		'indexnow'  => array( tp_setting( 'indexnow' ) && $sent, __( 'Send your pages to Bing (IndexNow)', 'taxi-peninsula' ), __( 'Turn IndexNow on and click "Send all pages to Bing now" once. After that, new and changed pages are sent automatically within a minute.', 'taxi-peninsula' ), '', false ),
		'llms'      => array( (bool) tp_setting( 'llms_txt' ), __( 'Publish a summary for AI assistants (/llms.txt)', 'taxi-peninsula' ), __( 'A plain description of your services, areas, prices and FAQs, written for AI tools.', 'taxi-peninsula' ), home_url( '/llms.txt' ), false ),
		'bots'      => array( 'none' !== tp_setting( 'ai_crawlers' ), __( 'Let AI search bots read the site', 'taxi-peninsula' ), __( 'ChatGPT search, Copilot and Perplexity only recommend sites they are allowed to read.', 'taxi-peninsula' ), '', false ),
		'nap'       => array( (bool) ( tp_setting( 'biz_suburb' ) && tp_opt( 'phone_display' ) ), __( 'Business name, address and phone filled in', 'taxi-peninsula' ), __( 'Taxi settings → Settings → Search engines & AI. Use exactly the same details as your Google and Bing listings.', 'taxi-peninsula' ), admin_url( 'admin.php?page=tp-settings' ), false ),
		'https'     => array( $https, __( 'Site uses https://', 'taxi-peninsula' ), __( 'Search engines and browsers prefer secure sites. Ask your host for a free SSL certificate.', 'taxi-peninsula' ), '', false ),
		'faqs'      => array( $faqs >= 8, __( 'At least 8 clear FAQs', 'taxi-peninsula' ), sprintf( /* translators: %d: count */ __( 'AI assistants answer questions — "Can I take my power chair?", "Do you accept MPTP?". You have %d published.', 'taxi-peninsula' ), $faqs ), admin_url( 'edit.php?post_type=tp_faq' ), false ),
		'places'    => array( ! empty( $manual['places'] ), __( 'Claim your Bing Places for Business listing', 'taxi-peninsula' ), __( 'Copilot and Bing Maps use it for "wheelchair taxi near me". You can import your Google Business Profile.', 'taxi-peninsula' ), 'https://www.bingplaces.com/', true ),
		'apple'     => array( ! empty( $manual['apple'] ), __( 'Claim your Apple Business Connect listing', 'taxi-peninsula' ), __( 'Siri and Apple Maps on iPhones use it.', 'taxi-peninsula' ), 'https://businessconnect.apple.com/', true ),
		'reviews'   => array( ! empty( $manual['reviews'] ), __( 'Ask for reviews and reply to every one', 'taxi-peninsula' ), __( 'AI assistants repeat what reviews say ("patient drivers", "always on time"). Review invites are already sent after rated trips.', 'taxi-peninsula' ), '', true ),
		'directory' => array( ! empty( $manual['directory'] ), __( 'List the business in local and disability directories', 'taxi-peninsula' ), __( 'e.g. your council\'s community directory, True Local, Yellow Pages, and disability-service directories. The same name, address and phone everywhere.', 'taxi-peninsula' ), '', true ),
	);
}

function tp_render_ai_search_page() {
	if ( ! current_user_can( 'tp_manage_offers' ) ) {
		return;
	}
	$days  = isset( $_GET['days'] ) && in_array( (int) $_GET['days'], array( 30, 90, 365 ), true ) ? (int) $_GET['days'] : 90; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$res   = tp_ai_search_results( $days );
	$list  = tp_ai_checklist();
	$done  = count( array_filter( $list, static fn( $i ) => $i[0] ) );
	$msg   = isset( $_GET['tp_msg'] ) ? sanitize_key( wp_unslash( $_GET['tp_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$log   = array_reverse( (array) get_option( 'tp_indexnow_log', array() ) );
	$types = array( 'call' => __( 'Phone taps', 'taxi-peninsula' ), 'whatsapp' => __( 'WhatsApp', 'taxi-peninsula' ), 'booking' => __( 'Bookings', 'taxi-peninsula' ), 'quote' => __( 'Quotes', 'taxi-peninsula' ), 'signup' => __( 'Sign-ups', 'taxi-peninsula' ) );
	?>
	<div class="wrap tp-ai">
		<h1><?php esc_html_e( 'AI & Bing search', 'taxi-peninsula' ); ?></h1>
		<p class="tp-ai__lead"><?php esc_html_e( 'More people now ask ChatGPT, Microsoft Copilot or Bing "who does wheelchair taxis near me?" instead of searching Google. ChatGPT search and Copilot get much of their information from Bing. This page helps you appear in those answers — and shows the calls and bookings they bring.', 'taxi-peninsula' ); ?></p>

		<?php if ( 'saved' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'taxi-peninsula' ); ?></p></div>
		<?php elseif ( 'sent' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( /* translators: %d: pages */ __( 'Sent %d pages to Bing. They are usually checked within a day or two.', 'taxi-peninsula' ), (int) ( $log[0]['count'] ?? 0 ) ) ); ?></p></div>
		<?php elseif ( 'failed' === $msg ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( sprintf( /* translators: %s: code */ __( 'Bing did not accept the list (code %s). The site must be on its public address with https:// for IndexNow to work.', 'taxi-peninsula' ), (string) ( $log[0]['code'] ?? '?' ) ) ); ?></p></div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'What AI assistants and Bing bring you', 'taxi-peninsula' ); ?></h2>
		<p class="tp-leads__range">
			<?php foreach ( array( 30, 90, 365 ) as $d ) : ?>
				<a class="button<?php echo $d === $days ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'days', $d ) ); ?>"><?php echo esc_html( sprintf( /* translators: %d: days */ __( 'Last %d days', 'taxi-peninsula' ), $d ) ); ?></a>
			<?php endforeach; ?>
		</p>
		<ul class="tp-kpis">
			<li class="tp-kpi"><span class="tp-kpi__label"><?php esc_html_e( 'Bookings', 'taxi-peninsula' ); ?></span><strong class="tp-kpi__n"><?php echo esc_html( number_format_i18n( $res['bookings'] ) ); ?></strong></li>
			<li class="tp-kpi"><span class="tp-kpi__label"><?php esc_html_e( 'Revenue (fares entered)', 'taxi-peninsula' ); ?></span><strong class="tp-kpi__n"><?php echo esc_html( tp_money( $res['revenue'] ) ); ?></strong></li>
			<li class="tp-kpi"><span class="tp-kpi__label"><?php esc_html_e( 'Phone taps', 'taxi-peninsula' ); ?></span><strong class="tp-kpi__n"><?php echo esc_html( number_format_i18n( array_sum( array_column( $res['rows'], 'call' ) ) ) ); ?></strong></li>
		</ul>
		<?php if ( $res['rows'] ) : ?>
			<table class="widefat striped tp-ai__table">
				<thead><tr><th><?php esc_html_e( 'Came from', 'taxi-peninsula' ); ?></th>
					<?php foreach ( $types as $t ) : ?>
						<th class="num"><?php echo esc_html( $t ); ?></th>
					<?php endforeach; ?>
				</tr></thead>
				<tbody>
					<?php foreach ( $res['rows'] as $src => $counts ) : ?>
						<tr><td><?php echo esc_html( $src ); ?></td>
							<?php foreach ( array_keys( $types ) as $k ) : ?>
								<td class="num"><?php echo esc_html( $counts[ $k ] ?? 0 ); ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Nothing yet. Visits from ChatGPT, Copilot, Perplexity, Gemini, Claude and Bing are recognised automatically and appear here (and in Reports → Leads & calls) once people start arriving.', 'taxi-peninsula' ); ?></p>
		<?php endif; ?>

		<h2>
			<?php
			/* translators: 1: done 2: total */
			echo esc_html( sprintf( __( 'Checklist — %1$d of %2$d done', 'taxi-peninsula' ), $done, count( $list ) ) );
			?>
		</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tp_ai_save">
			<?php wp_nonce_field( 'tp_ai_save' ); ?>
			<ol class="tp-ai__list">
				<?php foreach ( $list as $key => $item ) : ?>
					<li class="tp-ai__item<?php echo $item[0] ? ' is-done' : ''; ?>">
						<span class="tp-ai__mark" aria-hidden="true"><?php echo $item[0] ? '✓' : ''; ?></span>
						<div>
							<strong><?php echo esc_html( $item[1] ); ?></strong>
							<span class="screen-reader-text"><?php echo esc_html( $item[0] ? __( '(done)', 'taxi-peninsula' ) : __( '(to do)', 'taxi-peninsula' ) ); ?></span>
							<p><?php echo esc_html( $item[2] ); ?>
								<?php if ( $item[3] ) : ?>
									<a href="<?php echo esc_url( $item[3] ); ?>"<?php echo 0 === strpos( $item[3], admin_url() ) ? '' : ' target="_blank" rel="noopener"'; ?>><?php esc_html_e( 'Open', 'taxi-peninsula' ); ?></a>
								<?php endif; ?>
							</p>
							<?php if ( $item[4] ) : ?>
								<label><input type="checkbox" name="manual[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $item[0] ); ?>> <?php esc_html_e( 'Done', 'taxi-peninsula' ); ?></label>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ol>

			<h2><?php esc_html_e( 'Settings', 'taxi-peninsula' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="tp-ai-bing"><?php esc_html_e( 'Bing verification code', 'taxi-peninsula' ); ?></label></th>
					<td><input type="text" class="regular-text" id="tp-ai-bing" name="bing_site_verification" value="<?php echo esc_attr( tp_setting( 'bing_site_verification' ) ); ?>" placeholder="<?php esc_attr_e( 'Paste the content="…" value or the whole meta tag', 'taxi-peninsula' ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'IndexNow', 'taxi-peninsula' ); ?></th>
					<td><label><input type="checkbox" name="indexnow" value="1" <?php checked( (bool) tp_setting( 'indexnow' ) ); ?>> <?php esc_html_e( 'Send new and changed pages to Bing automatically', 'taxi-peninsula' ); ?></label></td></tr>
				<tr><th><?php esc_html_e( 'Summary for AI', 'taxi-peninsula' ); ?></th>
					<td><label><input type="checkbox" name="llms_txt" value="1" <?php checked( (bool) tp_setting( 'llms_txt' ) ); ?>> <?php esc_html_e( 'Publish /llms.txt', 'taxi-peninsula' ); ?></label></td></tr>
				<tr><th><label for="tp-ai-bots"><?php esc_html_e( 'AI crawlers', 'taxi-peninsula' ); ?></label></th>
					<td><select id="tp-ai-bots" name="ai_crawlers">
						<?php foreach ( tp_settings_schema()['ai_crawlers'][5] as $k => $l ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( tp_setting( 'ai_crawlers' ), $k ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select></td></tr>
			</table>
			<?php submit_button( __( 'Save', 'taxi-peninsula' ) ); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tp-ai__send">
			<input type="hidden" name="action" value="tp_ai_send_all">
			<?php wp_nonce_field( 'tp_ai_send_all' ); ?>
			<?php submit_button( sprintf( /* translators: %d: pages */ __( 'Send all %d pages to Bing now', 'taxi-peninsula' ), count( tp_all_public_urls() ) ), 'secondary', 'submit', false, tp_setting( 'indexnow' ) ? array() : array( 'disabled' => 'disabled' ) ); ?>
		</form>
		<?php if ( $log ) : ?>
			<details class="tp-ai__log"><summary><?php esc_html_e( 'Recent IndexNow submissions', 'taxi-peninsula' ); ?></summary>
				<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'When', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Pages', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Result', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'First page', 'taxi-peninsula' ); ?></th></tr></thead><tbody>
					<?php foreach ( array_slice( $log, 0, 10 ) as $l ) : ?>
						<tr><td><?php echo esc_html( $l['time'] ); ?></td><td><?php echo esc_html( $l['count'] ); ?></td><td><?php echo esc_html( in_array( (int) $l['code'], array( 200, 202 ), true ) ? __( 'Accepted', 'taxi-peninsula' ) : sprintf( /* translators: %s: code */ __( 'Not accepted (%s)', 'taxi-peninsula' ), $l['code'] ) ); ?></td><td><?php echo esc_html( $l['first'] ); ?></td></tr>
					<?php endforeach; ?>
				</tbody></table>
			</details>
		<?php endif; ?>

		<?php if ( tp_setting( 'llms_txt' ) ) : ?>
			<h2><?php esc_html_e( 'What AI assistants read about you', 'taxi-peninsula' ); ?></h2>
			<p><?php esc_html_e( 'Built automatically from your services, areas, hours and FAQs. Improve it by improving those.', 'taxi-peninsula' ); ?> <a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open /llms.txt', 'taxi-peninsula' ); ?></a></p>
			<pre class="tp-pre tp-ai__preview"><?php echo esc_html( implode( "\n", array_slice( explode( "\n", tp_llms_txt() ), 0, 40 ) ) ); ?></pre>
		<?php endif; ?>
	</div>
	<?php
}

add_action(
	'admin_post_tp_ai_save',
	static function () {
		check_admin_referer( 'tp_ai_save' );
		if ( ! current_user_can( 'tp_manage_offers' ) ) {
			wp_die( '', 403 );
		}
		$s                           = (array) get_option( 'tp_settings', array() );
		$s['bing_site_verification'] = isset( $_POST['bing_site_verification'] ) ? sanitize_text_field( tp_verification_value( wp_unslash( $_POST['bing_site_verification'] ) ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$s['indexnow']               = ! empty( $_POST['indexnow'] );
		$s['llms_txt']               = ! empty( $_POST['llms_txt'] );
		$mode                        = isset( $_POST['ai_crawlers'] ) ? sanitize_key( wp_unslash( $_POST['ai_crawlers'] ) ) : 'search';
		$s['ai_crawlers']            = in_array( $mode, array( 'search', 'all', 'none' ), true ) ? $mode : 'search';
		update_option( 'tp_settings', $s );
		$manual = array();
		foreach ( array( 'places', 'apple', 'reviews', 'directory' ) as $k ) {
			$manual[ $k ] = ! empty( $_POST['manual'][ $k ] );
		}
		update_option( 'tp_ai_manual', $manual, false );
		wp_safe_redirect( admin_url( 'admin.php?page=tp-ai-search&tp_msg=saved' ) );
		exit;
	}
);

add_action(
	'admin_post_tp_ai_send_all',
	static function () {
		check_admin_referer( 'tp_ai_send_all' );
		if ( ! current_user_can( 'tp_manage_offers' ) ) {
			wp_die( '', 403 );
		}
		$code = tp_indexnow_send( tp_all_public_urls() );
		wp_safe_redirect( admin_url( 'admin.php?page=tp-ai-search&tp_msg=' . ( in_array( $code, array( 200, 202 ), true ) ? 'sent' : 'failed' ) ) );
		exit;
	}
);
