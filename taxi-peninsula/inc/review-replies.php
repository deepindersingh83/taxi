<?php
/**
 * Google reviews in the admin (Marketing → Google reviews): read every review
 * from your Google Business Profile and reply from the website. Replies are
 * public on Google. Uses the same Google connection as profile posts.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reviews from Google (cached 15 minutes): [ reviews[], average, total ] or WP_Error.
 */
function tp_gbp_reviews( $fresh = false ) {
	if ( ! function_exists( 'tp_gbp_connected' ) || ! tp_gbp_connected() ) {
		return new WP_Error( 'tp_gbp_off', __( 'Connect your Google profile first (Marketing → Google profile).', 'taxi-peninsula' ) );
	}
	$cached = get_transient( 'tp_gbp_reviews' );
	if ( ! $fresh && is_array( $cached ) ) {
		return $cached;
	}
	$s   = tp_gbp_state();
	$res = tp_gbp_api( 'GET', 'https://mybusiness.googleapis.com/v4/' . $s['account'] . '/' . $s['location'] . '/reviews?pageSize=50' );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$stars = array( 'ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5 );
	$list  = array();
	foreach ( (array) ( $res['reviews'] ?? array() ) as $r ) {
		$list[] = array(
			'name'    => (string) ( $r['name'] ?? '' ),
			'id'      => (string) ( $r['reviewId'] ?? '' ),
			'author'  => (string) ( $r['reviewer']['displayName'] ?? __( 'Google user', 'taxi-peninsula' ) ),
			'stars'   => $stars[ $r['starRating'] ?? '' ] ?? 0,
			'comment' => (string) ( $r['comment'] ?? '' ),
			'time'    => strtotime( (string) ( $r['createTime'] ?? '' ) ) ?: 0,
			'reply'   => (string) ( $r['reviewReply']['comment'] ?? '' ),
			'replied' => strtotime( (string) ( $r['reviewReply']['updateTime'] ?? '' ) ) ?: 0,
		);
	}
	$out = array(
		'reviews' => $list,
		'average' => (float) ( $res['averageRating'] ?? 0 ),
		'total'   => (int) ( $res['totalReviewCount'] ?? count( $list ) ),
	);
	set_transient( 'tp_gbp_reviews', $out, 15 * MINUTE_IN_SECONDS );
	update_option( 'tp_gbp_unreplied', count( array_filter( $list, static fn( $r ) => '' === $r['reply'] ) ), false );
	return $out;
}

/**
 * Suggested replies — a starting point to edit, never sent as is.
 */
function tp_review_reply_suggestion( array $r ) {
	$first = trim( strtok( $r['author'], ' ' ) );
	$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	if ( $r['stars'] >= 4 ) {
		/* translators: 1: first name 2: business */
		return sprintf( __( 'Thank you so much, %1$s! We really appreciate you taking the time to share this, and we look forward to driving you again. — The %2$s team', 'taxi-peninsula' ), $first, $site );
	}
	/* translators: 1: first name 2: phone */
	return sprintf( __( 'Hi %1$s, thank you for telling us, and we are sorry your trip was not what it should have been. We would like to put this right — please call us on %2$s or reply to your booking email so we can look into it.', 'taxi-peninsula' ), $first, wp_strip_all_tags( tp_opt( 'phone_display' ) ) );
}

add_action(
	'admin_menu',
	static function () {
		$n     = (int) get_option( 'tp_gbp_unreplied', 0 );
		$title = __( 'Google reviews', 'taxi-peninsula' ) . ( $n ? ' <span class="awaiting-mod"><span class="pending-count">' . $n . '</span></span>' : '' );
		add_submenu_page( tp_admin_parent( 'marketing' ), __( 'Google reviews', 'taxi-peninsula' ), $title, 'tp_manage_offers', 'tp-reviews', 'tp_render_reviews_page' );
	},
	30
);

add_action(
	'admin_post_tp_review_reply',
	static function () {
		if ( ! current_user_can( 'tp_manage_offers' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'tp_review_reply' );
		$name  = isset( $_POST['review'] ) ? sanitize_text_field( wp_unslash( $_POST['review'] ) ) : '';
		$text  = isset( $_POST['reply'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reply'] ) ) : '';
		$s     = tp_gbp_state();
		$valid = 0 === strpos( $name, $s['account'] . '/' . $s['location'] . '/reviews/' );
		if ( ! $valid ) {
			wp_die( '', 400 );
		}
		if ( ! empty( $_POST['delete'] ) ) {
			$res = tp_gbp_api( 'DELETE', 'https://mybusiness.googleapis.com/v4/' . $name . '/reply' );
			$msg = __( 'Reply removed from Google.', 'taxi-peninsula' );
		} elseif ( '' === trim( $text ) ) {
			$res = new WP_Error( 'empty', __( 'Write a reply first.', 'taxi-peninsula' ) );
			$msg = '';
		} else {
			$res = tp_gbp_api( 'PUT', 'https://mybusiness.googleapis.com/v4/' . $name . '/reply', array( 'comment' => mb_substr( $text, 0, 4000 ) ) );
			$msg = __( 'Reply posted on Google. It can take a few minutes to appear.', 'taxi-peninsula' );
		}
		if ( ! is_wp_error( $res ) && function_exists( 'tp_activity' ) ) {
			tp_activity( 'settings', __( 'Replied to a Google review', 'taxi-peninsula' ) );
		}
		delete_transient( 'tp_gbp_reviews' );
		set_transient( 'tp_admin_notice_' . get_current_user_id(), is_wp_error( $res ) ? array( 'error', $res->get_error_message() ) : array( 'success', $msg ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=tp-reviews' ) . '#' . sanitize_html_class( basename( $name ) ) );
		exit;
	}
);

function tp_render_reviews_page() {
	$fresh  = ! empty( $_GET['refresh'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$filter = isset( $_GET['show'] ) ? sanitize_key( wp_unslash( $_GET['show'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$data   = tp_gbp_reviews( $fresh );
	?>
	<div class="wrap tp-reviews">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Google reviews', 'taxi-peninsula' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=tp-reviews&refresh=1' ) ); ?>"><?php esc_html_e( 'Refresh', 'taxi-peninsula' ); ?></a>
		<hr class="wp-header-end">
		<?php if ( is_wp_error( $data ) ) : ?>
			<div class="notice notice-warning inline"><p><?php echo esc_html( $data->get_error_message() ); ?></p></div>
			<?php
			echo '</div>';
			return;
			?>
		<?php endif; ?>
		<p>
			<?php
			/* translators: 1: average 2: total */
			echo esc_html( sprintf( __( '%1$s★ average from %2$d reviews.', 'taxi-peninsula' ), number_format_i18n( $data['average'], 1 ), $data['total'] ) );
			echo ' ' . esc_html__( 'Replying to every review — especially the unhappy ones — shows future customers you listen, and Google counts responsiveness in local rankings. Replies are public: never mention health details, addresses or anything private.', 'taxi-peninsula' );
			?>
		</p>
		<ul class="subsubsub">
			<?php
			$counts = array(
				'all'       => count( $data['reviews'] ),
				'unreplied' => count( array_filter( $data['reviews'], static fn( $r ) => '' === $r['reply'] ) ),
				'low'       => count( array_filter( $data['reviews'], static fn( $r ) => $r['stars'] && $r['stars'] <= 3 ) ),
			);
			$labels = array( 'all' => __( 'All', 'taxi-peninsula' ), 'unreplied' => __( 'Not replied', 'taxi-peninsula' ), 'low' => __( '1–3 stars', 'taxi-peninsula' ) );
			$links  = array();
			foreach ( $labels as $k => $l ) {
				$links[] = sprintf( '<li><a href="%s"%s>%s <span class="count">(%d)</span></a>', esc_url( admin_url( 'admin.php?page=tp-reviews&show=' . $k ) ), $filter === $k ? ' class="current" aria-current="page"' : '', esc_html( $l ), $counts[ $k ] );
			}
			echo implode( ' | </li>', $links ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			?>
		</ul>
		<div class="tp-reviews__list" style="clear:both">
			<?php foreach ( $data['reviews'] as $r ) : ?>
				<?php
				if ( ( 'unreplied' === $filter && '' !== $r['reply'] ) || ( 'low' === $filter && ( ! $r['stars'] || $r['stars'] > 3 ) ) ) {
					continue;
				}
				$anchor = sanitize_html_class( basename( $r['name'] ) );
				?>
				<article class="tp-review card" id="<?php echo esc_attr( $anchor ); ?>">
					<header><strong><?php echo esc_html( $r['author'] ); ?></strong> · <span class="tp-review__stars" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: stars */ _n( '%d star', '%d stars', $r['stars'], 'taxi-peninsula' ), $r['stars'] ) ); ?>"><?php echo esc_html( str_repeat( '★', $r['stars'] ) . str_repeat( '☆', 5 - $r['stars'] ) ); ?></span> · <span class="description"><?php echo esc_html( $r['time'] ? wp_date( 'j M Y', $r['time'] ) : '' ); ?></span></header>
					<p><?php echo $r['comment'] ? nl2br( esc_html( $r['comment'] ) ) : '<em>' . esc_html__( '(rating only, no comment)', 'taxi-peninsula' ) . '</em>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="tp_review_reply"><input type="hidden" name="review" value="<?php echo esc_attr( $r['name'] ); ?>">
						<?php wp_nonce_field( 'tp_review_reply' ); ?>
						<label for="tp-reply-<?php echo esc_attr( $anchor ); ?>"><strong><?php echo '' === $r['reply'] ? esc_html__( 'Your reply', 'taxi-peninsula' ) : esc_html__( 'Your reply (public on Google)', 'taxi-peninsula' ); ?></strong></label>
						<textarea class="large-text" rows="3" id="tp-reply-<?php echo esc_attr( $anchor ); ?>" name="reply" maxlength="4000"><?php echo esc_textarea( '' !== $r['reply'] ? $r['reply'] : tp_review_reply_suggestion( $r ) ); ?></textarea>
						<?php if ( '' === $r['reply'] ) : ?>
							<p class="description"><?php esc_html_e( 'A suggested reply is filled in — make it personal before posting.', 'taxi-peninsula' ); ?></p>
						<?php endif; ?>
						<p>
							<button class="button button-primary"><?php echo '' === $r['reply'] ? esc_html__( 'Post reply', 'taxi-peninsula' ) : esc_html__( 'Update reply', 'taxi-peninsula' ); ?></button>
							<?php if ( '' !== $r['reply'] ) : ?>
								<button class="button-link button-link-delete" name="delete" value="1" onclick="return confirm(<?php echo esc_attr( wp_json_encode( __( 'Remove this reply from Google?', 'taxi-peninsula' ) ) ); ?>)"><?php esc_html_e( 'Remove reply', 'taxi-peninsula' ); ?></button>
							<?php endif; ?>
						</p>
					</form>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

// Keep the "not replied" count fresh for the menu bubble.
add_action(
	'tp_refresh_google_place',
	static function () {
		if ( function_exists( 'tp_gbp_connected' ) && tp_gbp_connected() ) {
			tp_gbp_reviews( true );
		}
	}
);
