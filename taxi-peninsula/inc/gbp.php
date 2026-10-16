<?php
/**
 * Google Business Profile posts: publish announcements, promo codes and
 * blog posts to your Google profile (the "Updates" and "Offers" shown on
 * Google Search and Maps) straight from WordPress.
 *
 * Setup (once): Bookings → Google profile. You need a Google Cloud project
 * with the Business Profile APIs enabled — Google must approve access to
 * these APIs for your project first (request it in the Google Cloud
 * console; approval can take a few days). Create an OAuth client
 * ("Web application") with the redirect address shown on that page, paste
 * the client ID and secret into Bookings → Settings → Google profile,
 * then click "Connect" and choose your business location.
 *
 * Then tick "Post to Google" on an announcement, promo code or blog post.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'tp_settings_sections',
	static function ( $s ) {
		$s['gbp'] = array( __( 'Google profile posts', 'taxi-peninsula' ), __( 'Connect under Bookings → Google profile after saving these.', 'taxi-peninsula' ) );
		return $s;
	}
);
add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['gbp_client_id']     = array( 'gbp', __( 'Google OAuth client ID', 'taxi-peninsula' ), 'text', '', '' );
		$s['gbp_client_secret'] = array( 'gbp', __( 'Google OAuth client secret', 'taxi-peninsula' ), 'secret', '', '' );
		return $s;
	}
);

function tp_gbp_state() {
	return wp_parse_args( (array) get_option( 'tp_gbp', array() ), array( 'refresh' => '', 'access' => '', 'expires' => 0, 'account' => '', 'location' => '', 'title' => '' ) );
}

function tp_gbp_save_state( array $s ) {
	update_option( 'tp_gbp', $s, false );
}

function tp_gbp_connected() {
	$s = tp_gbp_state();
	return $s['refresh'] && $s['location'];
}

function tp_gbp_redirect_uri() {
	return admin_url( 'admin-post.php?action=tp_gbp_oauth' );
}

/**
 * Valid access token, refreshing it when needed. Returns string or WP_Error.
 */
function tp_gbp_token() {
	$s = tp_gbp_state();
	if ( ! $s['refresh'] ) {
		return new WP_Error( 'tp_gbp_off', __( 'Google profile is not connected.', 'taxi-peninsula' ) );
	}
	if ( $s['access'] && $s['expires'] > time() + 60 ) {
		return $s['access'];
	}
	$r = wp_remote_post(
		'https://oauth2.googleapis.com/token',
		array(
			'timeout' => 15,
			'body'    => array(
				'client_id'     => tp_setting( 'gbp_client_id' ),
				'client_secret' => tp_setting( 'gbp_client_secret' ),
				'refresh_token' => $s['refresh'],
				'grant_type'    => 'refresh_token',
			),
		)
	);
	$body = is_wp_error( $r ) ? array() : json_decode( wp_remote_retrieve_body( $r ), true );
	if ( empty( $body['access_token'] ) ) {
		return new WP_Error( 'tp_gbp_token', __( 'Could not sign in to Google. Please connect again.', 'taxi-peninsula' ) );
	}
	$s['access']  = $body['access_token'];
	$s['expires'] = time() + (int) ( $body['expires_in'] ?? 3000 );
	tp_gbp_save_state( $s );
	return $s['access'];
}

/**
 * Call a Google API. Returns decoded body or WP_Error.
 */
function tp_gbp_api( $method, $url, $body = null ) {
	$token = tp_gbp_token();
	if ( is_wp_error( $token ) ) {
		return $token;
	}
	$args = array(
		'method'  => $method,
		'timeout' => 20,
		'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
	);
	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}
	$r = wp_remote_request( $url, $args );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$code = wp_remote_retrieve_response_code( $r );
	$data = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( $code >= 300 ) {
		/* translators: 1: HTTP code 2: message */
		return new WP_Error( 'tp_gbp_api', sprintf( __( 'Google said no (%1$d): %2$s', 'taxi-peninsula' ), $code, $data['error']['message'] ?? '' ) );
	}
	return is_array( $data ) ? $data : array();
}

/* -------------------------------------------------------------------------
 * Connect.
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( tp_admin_parent( 'marketing' ), __( 'Google profile', 'taxi-peninsula' ), __( 'Google profile', 'taxi-peninsula' ), 'tp_manage_offers', 'tp-gbp', 'tp_render_gbp_page' );
	},
	44
);

function tp_render_gbp_page() {
	if ( ! current_user_can( 'tp_manage_offers' ) ) {
		return;
	}
	$s       = tp_gbp_state();
	$has_app = tp_setting( 'gbp_client_id' ) && tp_setting( 'gbp_client_secret' );
	$msg     = isset( $_GET['tp_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['tp_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Google Business Profile', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'Post announcements, offers and blog posts to your Google profile, where people see them on Google Search and Maps.', 'taxi-peninsula' ); ?></p>
		<?php if ( $msg ) : ?>
			<div class="notice notice-info"><p><?php echo esc_html( $msg ); ?></p></div>
		<?php endif; ?>
		<h2><?php esc_html_e( '1. Google Cloud project', 'taxi-peninsula' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'In the Google Cloud console create a project and request access to the Business Profile APIs (Google reviews each request; it can take a few days).', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Enable "My Business Account Management API", "My Business Business Information API" and "Google My Business API".', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Create OAuth credentials of type "Web application" with this authorised redirect URI:', 'taxi-peninsula' ); ?> <code><?php echo esc_html( tp_gbp_redirect_uri() ); ?></code></li>
			<li>
				<?php
				printf(
					/* translators: %s: link */
					esc_html__( 'Paste the client ID and secret in %s.', 'taxi-peninsula' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=tp-settings' ) ) . '">' . esc_html__( 'Bookings → Settings → Google profile posts', 'taxi-peninsula' ) . '</a>'
				);
				?>
			</li>
		</ol>
		<h2><?php esc_html_e( '2. Connect', 'taxi-peninsula' ); ?></h2>
		<?php if ( ! $has_app ) : ?>
			<p><em><?php esc_html_e( 'Add the client ID and secret first.', 'taxi-peninsula' ); ?></em></p>
		<?php elseif ( ! $s['refresh'] ) : ?>
			<p><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tp_gbp_connect' ), 'tp_gbp_connect' ) ); ?>"><?php esc_html_e( 'Connect to Google', 'taxi-peninsula' ); ?></a></p>
		<?php else : ?>
			<p>
				<?php if ( $s['location'] ) : ?>
					<?php
					/* translators: %s: business name */
					echo esc_html( sprintf( __( 'Connected. Posting to: %s', 'taxi-peninsula' ), $s['title'] ?: $s['location'] ) );
					?>
				<?php else : ?>
					<?php esc_html_e( 'Connected. Now choose your business location:', 'taxi-peninsula' ); ?>
				<?php endif; ?>
			</p>
			<?php $locs = tp_gbp_locations(); ?>
			<?php if ( is_wp_error( $locs ) ) : ?>
				<p class="notice notice-error inline"><?php echo esc_html( $locs->get_error_message() ); ?></p>
			<?php elseif ( $locs ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="tp_gbp_location">
					<?php wp_nonce_field( 'tp_gbp_location' ); ?>
					<label for="tp-gbp-loc" class="screen-reader-text"><?php esc_html_e( 'Location', 'taxi-peninsula' ); ?></label>
					<select id="tp-gbp-loc" name="location">
						<?php foreach ( $locs as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['account'] . '|' . $s['location'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Use this location', 'taxi-peninsula' ), 'secondary', '', false ); ?>
				</form>
			<?php endif; ?>
			<p><a class="button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tp_gbp_disconnect' ), 'tp_gbp_disconnect' ) ); ?>"><?php esc_html_e( 'Disconnect', 'taxi-peninsula' ); ?></a></p>
		<?php endif; ?>
		<h2><?php esc_html_e( '3. Post', 'taxi-peninsula' ); ?></h2>
		<p><?php esc_html_e( 'Edit an announcement, a promo code or a blog post and tick "Post to Google" in the "Google profile" box. Offers use the promo code’s dates and code.', 'taxi-peninsula' ); ?></p>
		<?php
		$recent = get_posts( array( 'post_type' => array( 'post', 'tp_notice', 'tp_promo' ), 'numberposts' => 10, 'meta_key' => '_tp_gbp_at', 'orderby' => 'meta_value', 'order' => 'DESC' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( $recent ) :
			?>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Item', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Posted', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Result', 'taxi-peninsula' ); ?></th></tr></thead><tbody>
				<?php foreach ( $recent as $p ) : ?>
					<tr><td><a href="<?php echo esc_url( get_edit_post_link( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a></td><td><?php echo esc_html( get_post_meta( $p->ID, '_tp_gbp_at', true ) ); ?></td><td><?php echo esc_html( get_post_meta( $p->ID, '_tp_gbp_status', true ) ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>
	</div>
	<?php
}

function tp_gbp_back( $msg ) {
	wp_safe_redirect( add_query_arg( 'tp_msg', rawurlencode( $msg ), admin_url( 'admin.php?page=tp-gbp' ) ) );
	exit;
}

add_action(
	'admin_post_tp_gbp_connect',
	static function () {
		check_admin_referer( 'tp_gbp_connect' );
		if ( ! current_user_can( 'tp_manage_offers' ) ) {
			wp_die( '', 403 );
		}
		$state = wp_generate_password( 20, false );
		set_transient( 'tp_gbp_oauth_' . get_current_user_id(), $state, 15 * MINUTE_IN_SECONDS );
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Google sign-in.
		wp_redirect(
			add_query_arg(
				array(
					'client_id'     => rawurlencode( tp_setting( 'gbp_client_id' ) ),
					'redirect_uri'  => rawurlencode( tp_gbp_redirect_uri() ),
					'response_type' => 'code',
					'scope'         => rawurlencode( 'https://www.googleapis.com/auth/business.manage' ),
					'access_type'   => 'offline',
					'prompt'        => 'consent',
					'state'         => $state,
				),
				'https://accounts.google.com/o/oauth2/v2/auth'
			)
		);
		exit;
	}
);

add_action(
	'admin_post_tp_gbp_oauth',
	static function () {
		if ( ! current_user_can( 'tp_manage_offers' ) ) {
			wp_die( '', 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth state is checked instead.
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$want  = get_transient( 'tp_gbp_oauth_' . get_current_user_id() );
		delete_transient( 'tp_gbp_oauth_' . get_current_user_id() );
		if ( ! $want || ! hash_equals( (string) $want, $state ) ) {
			tp_gbp_back( __( 'The Google sign-in expired. Please try again.', 'taxi-peninsula' ) );
		}
		if ( ! empty( $_GET['error'] ) ) {
			tp_gbp_back( __( 'Google sign-in was cancelled.', 'taxi-peninsula' ) );
		}
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		// phpcs:enable
		$r    = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 15,
				'body'    => array(
					'code'          => $code,
					'client_id'     => tp_setting( 'gbp_client_id' ),
					'client_secret' => tp_setting( 'gbp_client_secret' ),
					'redirect_uri'  => tp_gbp_redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);
		$body = is_wp_error( $r ) ? array() : json_decode( wp_remote_retrieve_body( $r ), true );
		if ( empty( $body['refresh_token'] ) ) {
			tp_gbp_back( __( 'Google did not give access. Check the client ID, secret and redirect URI, then try again.', 'taxi-peninsula' ) );
		}
		tp_gbp_save_state(
			array(
				'refresh' => $body['refresh_token'],
				'access'  => $body['access_token'] ?? '',
				'expires' => time() + (int) ( $body['expires_in'] ?? 3000 ),
				'account' => '',
				'location' => '',
				'title'   => '',
			)
		);
		if ( function_exists( 'tp_activity' ) ) {
			tp_activity( 'settings', __( 'Google Business Profile connected', 'taxi-peninsula' ) );
		}
		$locs = tp_gbp_locations();
		if ( ! is_wp_error( $locs ) && 1 === count( $locs ) ) {
			tp_gbp_set_location( (string) array_key_first( $locs ), (string) reset( $locs ) );
		}
		tp_gbp_back( __( 'Connected to Google.', 'taxi-peninsula' ) );
	}
);

/**
 * Locations the signed-in Google account manages: "accounts/1|locations/2" => title.
 */
function tp_gbp_locations() {
	$cached = get_transient( 'tp_gbp_locations' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$accounts = tp_gbp_api( 'GET', 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts' );
	if ( is_wp_error( $accounts ) ) {
		return $accounts;
	}
	$out = array();
	foreach ( (array) ( $accounts['accounts'] ?? array() ) as $acc ) {
		$locs = tp_gbp_api( 'GET', 'https://mybusinessbusinessinformation.googleapis.com/v1/' . $acc['name'] . '/locations?readMask=name,title,storefrontAddress&pageSize=100' );
		if ( is_wp_error( $locs ) ) {
			continue;
		}
		foreach ( (array) ( $locs['locations'] ?? array() ) as $l ) {
			$out[ $acc['name'] . '|' . $l['name'] ] = trim( ( $l['title'] ?? '' ) . ' — ' . ( $l['storefrontAddress']['locality'] ?? '' ), ' —' );
		}
	}
	set_transient( 'tp_gbp_locations', $out, 10 * MINUTE_IN_SECONDS );
	return $out;
}

function tp_gbp_set_location( $value, $title ) {
	$parts = explode( '|', $value );
	if ( 2 !== count( $parts ) ) {
		return;
	}
	$s             = tp_gbp_state();
	$s['account']  = $parts[0];
	$s['location'] = $parts[1];
	$s['title']    = $title;
	tp_gbp_save_state( $s );
}

add_action(
	'admin_post_tp_gbp_location',
	static function () {
		check_admin_referer( 'tp_gbp_location' );
		if ( ! current_user_can( 'tp_manage_offers' ) ) {
			wp_die( '', 403 );
		}
		$value = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '';
		$locs  = tp_gbp_locations();
		if ( is_array( $locs ) && isset( $locs[ $value ] ) ) {
			tp_gbp_set_location( $value, $locs[ $value ] );
		}
		tp_gbp_back( __( 'Location saved.', 'taxi-peninsula' ) );
	}
);

add_action(
	'admin_post_tp_gbp_disconnect',
	static function () {
		check_admin_referer( 'tp_gbp_disconnect' );
		if ( ! current_user_can( 'tp_manage_offers' ) ) {
			wp_die( '', 403 );
		}
		delete_option( 'tp_gbp' );
		delete_transient( 'tp_gbp_locations' );
		tp_gbp_back( __( 'Disconnected.', 'taxi-peninsula' ) );
	}
);

/* -------------------------------------------------------------------------
 * Posting.
 * ---------------------------------------------------------------------- */

function tp_gbp_post_types() {
	return array( 'post', 'tp_notice', 'tp_promo' );
}

add_action(
	'add_meta_boxes',
	static function ( $type ) {
		if ( in_array( $type, tp_gbp_post_types(), true ) && tp_gbp_connected() && current_user_can( 'edit_posts' ) ) {
			add_meta_box( 'tp_gbp_box', __( 'Google profile', 'taxi-peninsula' ), 'tp_render_gbp_box', $type, 'side', 'default' );
		}
	}
);

function tp_render_gbp_box( $post ) {
	wp_nonce_field( 'tp_gbp_save', 'tp_gbp_nonce' );
	$status = (string) get_post_meta( $post->ID, '_tp_gbp_status', true );
	$text   = (string) get_post_meta( $post->ID, '_tp_gbp_text', true );
	$button = (string) get_post_meta( $post->ID, '_tp_gbp_button', true ) ?: ( 'tp_promo' === $post->post_type ? 'BOOK' : 'LEARN_MORE' );
	?>
	<p><label><input type="checkbox" name="tp_gbp_send" value="1"> <strong><?php echo esc_html( $status ? __( 'Post to Google again', 'taxi-peninsula' ) : __( 'Post to Google', 'taxi-peninsula' ) ); ?></strong></label></p>
	<p class="description"><?php echo esc_html( 'tp_promo' === $post->post_type ? __( 'Posted as an Offer with this code and its dates.', 'taxi-peninsula' ) : __( 'Posted as an Update when you save or publish.', 'taxi-peninsula' ) ); ?></p>
	<p><label for="tp_gbp_text"><?php esc_html_e( 'Text for Google (optional)', 'taxi-peninsula' ); ?></label>
		<textarea id="tp_gbp_text" name="tp_gbp_text" rows="3" class="widefat" maxlength="1500"><?php echo esc_textarea( $text ); ?></textarea>
		<span class="description"><?php esc_html_e( 'Leave empty to use the excerpt.', 'taxi-peninsula' ); ?></span></p>
	<p><label for="tp_gbp_button"><?php esc_html_e( 'Button', 'taxi-peninsula' ); ?></label>
		<select id="tp_gbp_button" name="tp_gbp_button" class="widefat">
			<?php foreach ( array( 'BOOK' => __( 'Book', 'taxi-peninsula' ), 'LEARN_MORE' => __( 'Learn more', 'taxi-peninsula' ), 'CALL' => __( 'Call now', 'taxi-peninsula' ), 'NONE' => __( 'No button', 'taxi-peninsula' ) ) as $k => $l ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $button, $k ); ?>><?php echo esc_html( $l ); ?></option>
			<?php endforeach; ?>
		</select></p>
	<?php if ( $status ) : ?>
		<p><strong><?php esc_html_e( 'Last result:', 'taxi-peninsula' ); ?></strong> <?php echo esc_html( $status ); ?> <span class="description">(<?php echo esc_html( get_post_meta( $post->ID, '_tp_gbp_at', true ) ); ?>)</span></p>
	<?php endif; ?>
	<?php
}

add_action(
	'save_post',
	static function ( $post_id, $post ) {
		if ( ! in_array( $post->post_type, tp_gbp_post_types(), true ) || ! isset( $_POST['tp_gbp_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tp_gbp_nonce'] ), 'tp_gbp_save' ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_tp_gbp_text', isset( $_POST['tp_gbp_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tp_gbp_text'] ) ) : '' );
		$button = isset( $_POST['tp_gbp_button'] ) ? sanitize_key( wp_unslash( $_POST['tp_gbp_button'] ) ) : 'LEARN_MORE';
		update_post_meta( $post_id, '_tp_gbp_button', in_array( strtoupper( $button ), array( 'BOOK', 'LEARN_MORE', 'CALL', 'NONE' ), true ) ? strtoupper( $button ) : 'LEARN_MORE' );
		if ( ! empty( $_POST['tp_gbp_send'] ) && 'publish' === $post->post_status ) {
			// After the promo/announcement's own fields are saved.
			add_action(
				'shutdown',
				static function () use ( $post_id ) {
					tp_gbp_publish( $post_id );
				},
				1
			);
		}
	},
	50,
	2
);

/**
 * Build the Google localPost body for a WordPress item.
 */
function tp_gbp_body( $post_id ) {
	$post = get_post( $post_id );
	$text = (string) get_post_meta( $post_id, '_tp_gbp_text', true );
	if ( '' === $text ) {
		$text = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 60 );
	}
	if ( 'tp_promo' === $post->post_type && function_exists( 'tp_promo_label' ) && '' === trim( $text ) ) {
		/* translators: 1: offer 2: code */
		$text = sprintf( __( '%1$s your wheelchair taxi with code %2$s.', 'taxi-peninsula' ), tp_promo_label( $post->post_title ), $post->post_title );
	}
	$text   = trim( $text ) ?: get_the_title( $post );
	$button = (string) get_post_meta( $post_id, '_tp_gbp_button', true ) ?: 'LEARN_MORE';
	$link   = 'tp_promo' === $post->post_type
		? add_query_arg( 'promo', $post->post_title, strtok( tp_booking_page_url(), '#' ) )
		: ( in_array( $post->post_type, array( 'post' ), true ) ? get_permalink( $post ) : tp_booking_page_url() );
	$link   = add_query_arg( array( 'utm_source' => 'google', 'utm_medium' => 'gbp', 'utm_campaign' => sanitize_title( get_the_title( $post ) ) ), $link );
	$body   = array(
		'languageCode' => 'en-AU',
		'summary'      => mb_substr( $text, 0, 1500 ),
		'topicType'    => 'STANDARD',
	);
	if ( 'CALL' === $button ) {
		$body['callToAction'] = array( 'actionType' => 'CALL' );
	} elseif ( 'NONE' !== $button ) {
		$body['callToAction'] = array( 'actionType' => $button, 'url' => $link );
	}
	$img = get_the_post_thumbnail_url( $post, 'large' );
	if ( $img ) {
		$body['media'] = array( array( 'mediaFormat' => 'PHOTO', 'sourceUrl' => $img ) );
	}
	if ( 'tp_promo' === $post->post_type && function_exists( 'tp_promo_meta' ) ) {
		$m     = tp_promo_meta( $post_id );
		$start = $m['start'] ?: wp_date( 'Y-m-d' );
		$end   = $m['end'] ?: wp_date( 'Y-m-d', strtotime( '+30 days' ) );
		$ymd   = static function ( $d ) {
			list( $y, $mo, $da ) = array_map( 'intval', explode( '-', $d ) );
			return array( 'year' => $y, 'month' => $mo, 'day' => $da );
		};
		$body['topicType'] = 'OFFER';
		$body['event']     = array(
			'title'    => mb_substr( tp_promo_label( $post->post_title ), 0, 58 ),
			'schedule' => array( 'startDate' => $ymd( $start ), 'endDate' => $ymd( $end ) ),
		);
		$body['offer']     = array(
			'couponCode'      => $post->post_title,
			'redeemOnlineUrl' => $link,
			'termsConditions' => $m['first'] ? __( 'First trip only. One code per booking.', 'taxi-peninsula' ) : __( 'One code per booking.', 'taxi-peninsula' ),
		);
		unset( $body['callToAction'] );
	}
	return apply_filters( 'tp_gbp_body', $body, $post );
}

/**
 * Publish (or re-publish) an item to Google. Returns true or WP_Error.
 */
function tp_gbp_publish( $post_id ) {
	$s = tp_gbp_state();
	if ( ! tp_gbp_connected() ) {
		return new WP_Error( 'tp_gbp_off', __( 'Google profile is not connected.', 'taxi-peninsula' ) );
	}
	$res = tp_gbp_api( 'POST', 'https://mybusiness.googleapis.com/v4/' . $s['account'] . '/' . $s['location'] . '/localPosts', tp_gbp_body( $post_id ) );
	update_post_meta( $post_id, '_tp_gbp_at', current_time( 'mysql' ) );
	if ( is_wp_error( $res ) ) {
		update_post_meta( $post_id, '_tp_gbp_status', $res->get_error_message() );
		return $res;
	}
	update_post_meta( $post_id, '_tp_gbp_name', (string) ( $res['name'] ?? '' ) );
	update_post_meta( $post_id, '_tp_gbp_status', ( $res['state'] ?? '' ) === 'LIVE' ? __( 'Live on Google', 'taxi-peninsula' ) : __( 'Sent — Google is reviewing it', 'taxi-peninsula' ) );
	if ( function_exists( 'tp_activity' ) ) {
		/* translators: %s: title */
		tp_activity( 'settings', sprintf( __( 'Posted to Google profile: %s', 'taxi-peninsula' ), get_the_title( $post_id ) ) );
	}
	return true;
}
