<?php
/**
 * Google review invitations after a customer rates a trip.
 *
 * Bookings → Settings → Google reviews decides who is invited (after a 4 or
 * 5 star rating, after any rating, or off) and whether an email follows.
 * The link goes through ?tp_review=ID&t=token so the office can see who
 * opened it (booking history and activity log).
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_review_write_url() {
	return tp_setting( 'review_write_url' ) ?: tp_setting( 'google_reviews_url' );
}

/**
 * Should this booking's customer be invited to review on Google?
 */
function tp_review_invite_due( array $b ) {
	$mode = tp_setting( 'review_request' );
	if ( 'off' === $mode || ! tp_review_write_url() || ! $b['rating'] ) {
		return false;
	}
	return 'all' === $mode || $b['rating'] >= 4;
}

/**
 * Tracked link to the Google review page.
 */
function tp_review_link( $post_id ) {
	return add_query_arg(
		array(
			'tp_review' => (int) $post_id,
			't'         => tp_booking_token( $post_id ),
		),
		home_url( '/' )
	);
}

/**
 * The invitation shown under a rated trip.
 */
function tp_review_invite( array $b ) {
	if ( ! tp_review_invite_due( $b ) ) {
		return;
	}
	$clicked = get_post_meta( $b['id'], '_tp_review_clicked', true );
	?>
	<div class="review-invite" role="note">
		<span class="review-invite__icon" aria-hidden="true"><?php echo tp_google_g(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
		<div class="review-invite__body">
			<?php if ( $clicked ) : ?>
				<p class="review-invite__title"><?php esc_html_e( 'Thanks for sharing your experience on Google!', 'taxi-peninsula' ); ?></p>
			<?php else : ?>
				<p class="review-invite__title"><?php esc_html_e( 'Would you share your experience on Google?', 'taxi-peninsula' ); ?></p>
				<p class="review-invite__text"><?php esc_html_e( 'A short review helps other families and carers find accessible transport they can trust. It takes about a minute.', 'taxi-peninsula' ); ?></p>
				<a class="btn btn--accent btn--sm" href="<?php echo esc_url( tp_review_link( $b['id'] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Write a Google review', 'taxi-peninsula' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'taxi-peninsula' ); ?></span></a>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Small Google "G" mark.
 */
function tp_google_g() {
	return '<svg viewBox="0 0 48 48" width="28" height="28" focusable="false"><path fill="#4285F4" d="M45.1 24.5c0-1.6-.1-3.1-.4-4.5H24v8.5h11.8c-.5 2.8-2.1 5.1-4.4 6.7v5.5h7.1c4.2-3.8 6.6-9.5 6.6-16.2z"/><path fill="#34A853" d="M24 46c5.9 0 10.9-2 14.5-5.3l-7.1-5.5c-2 1.3-4.5 2.1-7.4 2.1-5.7 0-10.5-3.8-12.2-9H4.5v5.7C8.1 41.1 15.5 46 24 46z"/><path fill="#FBBC05" d="M11.8 28.3c-.4-1.3-.7-2.8-.7-4.3s.3-3 .7-4.3V14H4.5C3 17 2.2 20.4 2.2 24s.8 7 2.3 10l7.3-5.7z"/><path fill="#EA4335" d="M24 10.7c3.2 0 6.1 1.1 8.4 3.3l6.3-6.3C34.9 4.1 29.9 2 24 2 15.5 2 8.1 6.9 4.5 14l7.3 5.7c1.7-5.2 6.5-9 12.2-9z"/></svg>';
}

// Tracked redirect to Google.
add_action(
	'template_redirect',
	static function () {
		if ( empty( $_GET['tp_review'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$post_id = absint( $_GET['tp_review'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token   = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$url     = tp_review_write_url();
		if ( ! $url ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
		if ( 'tp_booking' === get_post_type( $post_id ) && hash_equals( tp_booking_token( $post_id ), $token ) && ! get_post_meta( $post_id, '_tp_review_clicked', true ) ) {
			update_post_meta( $post_id, '_tp_review_clicked', current_time( 'mysql' ) );
			tp_in_site_lang(
				static function () use ( $post_id ) {
					tp_add_log( $post_id, array( 'note' => __( 'Customer opened the Google review link', 'taxi-peninsula' ) ) );
				}
			);
		}
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the business's own Google profile.
		exit;
	},
	1
);

/**
 * Email the invitation after a rating (at most once every 6 months per address).
 */
function tp_review_invite_email( $post_id ) {
	$b = tp_get_booking( $post_id );
	if ( ! tp_setting( 'review_email' ) || ! tp_review_invite_due( $b ) || ! is_email( $b['email'] ) ) {
		return;
	}
	$key = 'tp_rv_' . md5( strtolower( $b['email'] ) );
	if ( get_transient( $key ) ) {
		return;
	}
	set_transient( $key, 1, 180 * DAY_IN_SECONDS );
	$first = trim( strtok( (string) $b['name'], ' ' ) );
	$text  = sprintf(
		/* translators: %s: first name */
		__( 'Hi %s,', 'taxi-peninsula' ),
		$first ?: __( 'there', 'taxi-peninsula' )
	) . "\n\n"
		. __( 'Thank you for rating your trip with us — it really helps.', 'taxi-peninsula' ) . "\n\n"
		. __( 'Would you also share your experience on Google? A short review helps other families and carers find accessible transport they can trust.', 'taxi-peninsula' ) . "\n\n"
		. __( 'Write a Google review', 'taxi-peninsula' ) . ': ' . tp_review_link( $post_id ) . "\n\n"
		. __( 'Thank you for travelling with us.', 'taxi-peninsula' ) . "\n" . get_bloginfo( 'name' );
	wp_mail( $b['email'], __( 'Thank you for your rating', 'taxi-peninsula' ), $text, tp_mail_headers() );
	tp_in_site_lang(
		static function () use ( $post_id ) {
			tp_add_log( $post_id, array( 'note' => __( 'Google review invitation emailed', 'taxi-peninsula' ) ) );
		}
	);
}
