<?php
/**
 * Google Analytics 4 with a cookie consent banner.
 *
 * Privacy-first: nothing is loaded from Google until the visitor clicks
 * "Accept". "Reject" is as prominent as "Accept", and the choice can be
 * changed any time from the "Cookie settings" link in the footer.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_ga4_id() {
	$id = strtoupper( trim( (string) tp_setting( 'ga4_id' ) ) );
	return preg_match( '/^G-[A-Z0-9]{4,}$/', $id ) ? $id : '';
}

function tp_consent_required() {
	return tp_ga4_id() && tp_setting( 'consent_banner' );
}

add_action( 'wp_enqueue_scripts', static function () {
	$id = tp_ga4_id();
	if ( ! $id || ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) ) {
		return; // Don't count staff visits.
	}
	wp_add_inline_script(
		'tp-main',
		'window.tpConsent = ' . wp_json_encode(
			array(
				'ga'       => $id,
				'required' => tp_consent_required(),
			)
		) . ';',
		'before'
	);
}, 20 );

add_action( 'wp_footer', 'tp_render_consent_banner', 5 );
function tp_render_consent_banner() {
	if ( ! tp_consent_required() || ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) ) {
		return;
	}
	$privacy = get_privacy_policy_url();
	?>
	<section class="consent" id="tp-consent" role="region" aria-labelledby="tp-consent-title" hidden>
		<div class="consent__inner">
			<div class="consent__text">
				<h2 id="tp-consent-title" class="consent__title"><?php esc_html_e( 'Can we use analytics cookies?', 'taxi-peninsula' ); ?></h2>
				<p>
					<?php esc_html_e( 'We would like to use Google Analytics to understand how people use this site so we can improve it. These cookies are optional — the site and online booking work the same either way.', 'taxi-peninsula' ); ?>
					<?php if ( $privacy ) : ?>
						<a href="<?php echo esc_url( $privacy ); ?>"><?php esc_html_e( 'Privacy policy', 'taxi-peninsula' ); ?></a>
					<?php endif; ?>
				</p>
			</div>
			<div class="consent__actions">
				<button type="button" class="btn btn--accent" data-consent="granted"><?php esc_html_e( 'Accept analytics', 'taxi-peninsula' ); ?></button>
				<button type="button" class="btn btn--light" data-consent="denied"><?php esc_html_e( 'Reject', 'taxi-peninsula' ); ?></button>
			</div>
		</div>
	</section>
	<?php
}

/**
 * "Cookie settings" link for the footer.
 */
function tp_cookie_settings_link() {
	if ( tp_consent_required() ) {
		echo '<p><button type="button" class="link-button" data-consent-open>' . esc_html__( 'Cookie settings', 'taxi-peninsula' ) . '</button></p>';
	}
}
