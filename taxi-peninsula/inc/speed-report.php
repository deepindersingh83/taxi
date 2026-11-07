<?php
/**
 * Reports → Site speed: Google's Core Web Vitals for the home page and the
 * booking page on phones — what real visitors experienced over the last 28
 * days (Chrome User Experience Report), plus Google's lab score.
 *
 * Uses the free PageSpeed Insights API. An API key (Settings → Marketing &
 * SEO → Search & speed) is optional but avoids Google's shared limits.
 * Results are kept for a day; "Check again" refreshes them.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['psi_key'] = array( 'search', __( 'PageSpeed Insights API key (optional)', 'taxi-peninsula' ), 'secret', '', __( 'For Reports → Site speed. Works without a key, but Google may limit shared use. Create one free in Google Cloud → APIs → PageSpeed Insights API.', 'taxi-peninsula' ) );
		return $s;
	}
);

/** The pages checked: label => URL. */
function tp_speed_pages() {
	return array(
		__( 'Home page', 'taxi-peninsula' )    => home_url( '/' ),
		__( 'Booking page', 'taxi-peninsula' ) => tp_booking_page_url(),
	);
}

/**
 * Google's thresholds: metric => [label, good up to, poor from, unit].
 */
function tp_speed_metrics() {
	return array(
		'LARGEST_CONTENTFUL_PAINT_MS'  => array( __( 'Largest content shows (LCP)', 'taxi-peninsula' ), 2500, 4000, 's' ),
		'INTERACTION_TO_NEXT_PAINT'    => array( __( 'Reacts to taps (INP)', 'taxi-peninsula' ), 200, 500, 'ms' ),
		'CUMULATIVE_LAYOUT_SHIFT_SCORE' => array( __( 'Page stays still (CLS)', 'taxi-peninsula' ), 10, 25, 'cls' ),
	);
}

/**
 * Run PageSpeed Insights for one URL (phone).
 *
 * @return array|WP_Error { field: [metric => [p75, category]], origin: bool, score: int|null, at: int }
 */
function tp_speed_check( $url ) {
	$args = array(
		'url'      => $url,
		'strategy' => 'mobile',
		'category' => 'performance',
	);
	$key = (string) tp_setting( 'psi_key' );
	if ( '' !== $key ) {
		$args['key'] = $key;
	}
	$res = wp_remote_get( add_query_arg( $args, 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed' ), array( 'timeout' => 60 ) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( 200 !== wp_remote_retrieve_response_code( $res ) || ! is_array( $data ) ) {
		return new WP_Error( 'tp_psi', (string) ( $data['error']['message'] ?? __( 'Google did not answer. Try again later, or add an API key.', 'taxi-peninsula' ) ) );
	}
	// The page's own visitor data, or the whole site's when the page has too few visits.
	$exp    = ! empty( $data['loadingExperience']['metrics'] ) && empty( $data['loadingExperience']['origin_fallback'] ) ? $data['loadingExperience'] : ( $data['originLoadingExperience'] ?? array() );
	$field  = array();
	foreach ( array_keys( tp_speed_metrics() ) as $m ) {
		if ( isset( $exp['metrics'][ $m ]['percentile'] ) ) {
			$field[ $m ] = array( (float) $exp['metrics'][ $m ]['percentile'], (string) ( $exp['metrics'][ $m ]['category'] ?? '' ) );
		}
	}
	$score = $data['lighthouseResult']['categories']['performance']['score'] ?? null;
	return array(
		'field'  => $field,
		'origin' => empty( $data['loadingExperience']['metrics'] ) || ! empty( $data['loadingExperience']['origin_fallback'] ),
		'score'  => null === $score ? null : (int) round( $score * 100 ),
		'lcp_el' => (string) ( $data['lighthouseResult']['audits']['largest-contentful-paint-element']['details']['items'][0]['items'][0]['node']['nodeLabel'] ?? '' ),
		'at'     => time(),
	);
}

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( tp_admin_parent( 'reports' ), __( 'Site speed', 'taxi-peninsula' ), __( 'Site speed', 'taxi-peninsula' ), 'tp_view_leads', 'tp-speed', 'tp_render_speed_page' );
	},
	46
);

add_action(
	'admin_post_tp_speed_check',
	static function () {
		check_admin_referer( 'tp_speed_check' );
		if ( ! current_user_can( 'tp_view_leads' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'taxi-peninsula' ) );
		}
		$out = array();
		foreach ( tp_speed_pages() as $label => $url ) {
			$out[ $label ] = tp_speed_check( $url );
		}
		set_transient( 'tp_speed_results', $out, DAY_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=tp-speed' ) );
		exit;
	}
);

/** "2.1 s", "180 ms", "0.05". */
function tp_speed_value( $metric, $v ) {
	$unit = tp_speed_metrics()[ $metric ][3];
	if ( 's' === $unit ) {
		return number_format_i18n( $v / 1000, 1 ) . ' s';
	}
	return 'cls' === $unit ? number_format_i18n( $v / 100, 2 ) : number_format_i18n( $v ) . ' ms';
}

function tp_render_speed_page() {
	if ( ! current_user_can( 'tp_view_leads' ) ) {
		return;
	}
	$results = get_transient( 'tp_speed_results' );
	$words   = array(
		'FAST'    => __( 'Good', 'taxi-peninsula' ),
		'AVERAGE' => __( 'Needs work', 'taxi-peninsula' ),
		'SLOW'    => __( 'Poor', 'taxi-peninsula' ),
	);
	?>
	<div class="wrap tp-speed">
		<h1><?php esc_html_e( 'Site speed', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'How fast your pages are for real visitors on phones over the last 28 days, as Google measures them for search rankings (Core Web Vitals). All three should be “Good”.', 'taxi-peninsula' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tp_speed_check">
			<?php wp_nonce_field( 'tp_speed_check' ); ?>
			<button class="button button-primary"><?php echo $results ? esc_html__( 'Check again', 'taxi-peninsula' ) : esc_html__( 'Check now', 'taxi-peninsula' ); ?></button>
			<span class="description"><?php esc_html_e( 'Takes up to a minute.', 'taxi-peninsula' ); ?></span>
		</form>
		<?php if ( is_array( $results ) ) : ?>
			<?php foreach ( $results as $label => $r ) : ?>
				<h2><?php echo esc_html( $label ); ?></h2>
				<?php if ( is_wp_error( $r ) ) : ?>
					<div class="notice notice-error inline"><p><?php echo esc_html( $r->get_error_message() ); ?></p></div>
					<?php continue; ?>
				<?php endif; ?>
				<?php if ( ! $r['field'] ) : ?>
					<p><?php esc_html_e( 'Google does not have enough visits yet to report real-visitor speed. The lab score below is a single test from Google’s servers.', 'taxi-peninsula' ); ?></p>
				<?php else : ?>
					<?php if ( $r['origin'] ) : ?>
						<p class="description"><?php esc_html_e( 'Not enough visits to this page alone, so these are the figures for the whole site.', 'taxi-peninsula' ); ?></p>
					<?php endif; ?>
					<ul class="tp-speed__list">
						<?php foreach ( tp_speed_metrics() as $m => $info ) : ?>
							<?php
							if ( ! isset( $r['field'][ $m ] ) ) {
								continue;
							}
							list( $v, $cat ) = $r['field'][ $m ];
							?>
							<li class="tp-speed__item tp-speed__item--<?php echo esc_attr( strtolower( $cat ) ); ?>">
								<span class="tp-speed__label"><?php echo esc_html( $info[0] ); ?></span>
								<strong><?php echo esc_html( tp_speed_value( $m, $v ) ); ?></strong>
								<span class="tp-speed__cat"><?php echo esc_html( $words[ $cat ] ?? $cat ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( null !== $r['score'] ) : ?>
					<p>
						<?php
						/* translators: %d: score out of 100 */
						echo esc_html( sprintf( __( 'Lab score on a phone: %d / 100.', 'taxi-peninsula' ), $r['score'] ) );
						if ( $r['lcp_el'] ) {
							/* translators: %s: element name */
							echo ' ' . esc_html( sprintf( __( 'Largest thing on the first screen: %s.', 'taxi-peninsula' ), rtrim( $r['lcp_el'], '. ' ) ) );
						}
						?>
					</p>
				<?php endif; ?>
				<p class="description">
					<?php
					/* translators: %s: time ago */
					echo esc_html( sprintf( __( 'Checked %s ago.', 'taxi-peninsula' ), human_time_diff( $r['at'] ) ) );
					?>
				</p>
			<?php endforeach; ?>
			<h2><?php esc_html_e( 'If something is not “Good”', 'taxi-peninsula' ); ?></h2>
			<ul class="ul-disc">
				<li><?php esc_html_e( 'Largest content: use a smaller home page photo (under 200 KB) — new uploads are saved as AVIF or WebP automatically.', 'taxi-peninsula' ); ?></li>
				<li><?php esc_html_e( 'Reacts to taps: switch off plugins that add chat widgets, sliders or trackers you no longer use.', 'taxi-peninsula' ); ?></li>
				<li><?php esc_html_e( 'Page stays still: give images in your page content a width and height, and avoid adverts or banners that load late.', 'taxi-peninsula' ); ?></li>
			</ul>
		<?php endif; ?>
	</div>
	<?php
}
