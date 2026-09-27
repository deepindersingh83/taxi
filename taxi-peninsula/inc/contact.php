<?php
/**
 * Contact form: [tp_contact_form]. Enquiries are saved under Bookings → Enquiries
 * and emailed to the office.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_contact_topics() {
	return array(
		__( 'General question', 'taxi-peninsula' ),
		__( 'Quote for a trip', 'taxi-peninsula' ),
		__( 'NDIS / aged-care account', 'taxi-peninsula' ),
		__( 'Feedback', 'taxi-peninsula' ),
		__( 'Lost property', 'taxi-peninsula' ),
	);
}

add_shortcode( 'tp_contact_form', 'tp_contact_shortcode' );
function tp_contact_shortcode() {
	$state = array(
		'errors' => array(),
		'old'    => array(),
	);
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
	$sent = ! empty( $_GET['sent'] );
	if ( isset( $_GET['tp_form'] ) ) {
		$stored = get_transient( 'tp_form_' . sanitize_key( wp_unslash( $_GET['tp_form'] ) ) );
		if ( is_array( $stored ) ) {
			$state = $stored;
		}
	}
	// phpcs:enable

	$errors = $state['errors'];
	$old    = $state['old'];
	$val    = static function ( $k ) use ( $old ) {
		return $old[ $k ] ?? '';
	};
	$return = ( is_singular() && ! is_front_page() ) ? get_permalink() : home_url( '/' );

	ob_start();
	?>
	<div class="tp-component" id="contact">
		<div class="contact-layout">
			<div class="card">
				<?php if ( $sent ) : ?>
					<div class="booking-success" role="status" tabindex="-1" data-focus>
						<span class="booking-success__icon"><?php tp_the_icon( 'check' ); ?></span>
						<h2><?php esc_html_e( 'Message sent', 'taxi-peninsula' ); ?></h2>
						<p><?php esc_html_e( 'Thanks for getting in touch. We will reply as soon as we can.', 'taxi-peninsula' ); ?></p>
					</div>
				<?php else : ?>
					<h2 class="contact-layout__title"><?php esc_html_e( 'Send us a message', 'taxi-peninsula' ); ?></h2>
					<?php if ( $errors ) : ?>
						<div class="notice notice--error" role="alert" tabindex="-1" data-focus>
							<p><?php echo esc_html( $errors['form'] ?? __( 'Please check the highlighted fields.', 'taxi-peninsula' ) ); ?></p>
						</div>
					<?php endif; ?>
					<form class="booking-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
						<input type="hidden" name="action" value="tp_contact">
						<input type="hidden" name="_tp_return" value="<?php echo esc_url( $return ); ?>">
						<?php wp_nonce_field( 'tp_contact', '_tp_nonce', false ); ?>
						<div class="booking-form__group">
							<div class="field">
								<label for="ct-name"><?php esc_html_e( 'Your name', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
								<input id="ct-name" name="name" type="text" required autocomplete="name" value="<?php echo esc_attr( $val( 'name' ) ); ?>"<?php echo isset( $errors['name'] ) ? ' aria-invalid="true"' : ''; ?>>
							</div>
							<div class="field">
								<label for="ct-email"><?php esc_html_e( 'Email', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
								<input id="ct-email" name="email" type="email" required autocomplete="email" value="<?php echo esc_attr( $val( 'email' ) ); ?>"<?php echo isset( $errors['email'] ) ? ' aria-invalid="true"' : ''; ?>>
							</div>
							<div class="field">
								<label for="ct-phone"><?php esc_html_e( 'Phone (optional)', 'taxi-peninsula' ); ?></label>
								<input id="ct-phone" name="phone" type="tel" autocomplete="tel" value="<?php echo esc_attr( $val( 'phone' ) ); ?>">
							</div>
							<div class="field">
								<label for="ct-topic"><?php esc_html_e( 'Topic', 'taxi-peninsula' ); ?></label>
								<select id="ct-topic" name="topic">
									<?php foreach ( tp_contact_topics() as $topic ) : ?>
										<option <?php selected( $val( 'topic' ), $topic ); ?>><?php echo esc_html( $topic ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="field field--wide">
								<label for="ct-message"><?php esc_html_e( 'Message', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
								<textarea id="ct-message" name="message" rows="5" required<?php echo isset( $errors['message'] ) ? ' aria-invalid="true"' : ''; ?>><?php echo esc_textarea( $val( 'message' ) ); ?></textarea>
							</div>
						</div>
						<?php tp_spam_fields(); ?>
						<div class="booking-form__submit">
							<button type="submit" class="btn btn--primary btn--lg"><?php esc_html_e( 'Send message', 'taxi-peninsula' ); ?></button>
						</div>
					</form>
				<?php endif; ?>
			</div>
			<aside class="contact-card contact-card--tall">
				<p class="contact-card__label"><?php esc_html_e( 'To book or for urgent trips, call', 'taxi-peninsula' ); ?></p>
				<a class="contact-card__phone" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?><?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
				<ul class="icon-list">
					<li><?php tp_the_icon( 'mail' ); ?><a href="<?php echo esc_attr( tp_email_href() ); ?>"><?php echo esc_html( antispambot( tp_opt( 'email' ) ) ); ?></a></li>
					<li><?php tp_the_icon( 'pin' ); ?><span><?php echo esc_html( tp_opt( 'location' ) ); ?></span></li>
					<li><?php tp_the_icon( 'clock' ); ?><span><?php echo esc_html( tp_opt( 'hours' ) ); ?></span></li>
				</ul>
				<a class="btn btn--accent" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book online', 'taxi-peninsula' ); ?></a>
			</aside>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

add_action( 'admin_post_nopriv_tp_contact', 'tp_handle_contact' );
add_action( 'admin_post_tp_contact', 'tp_handle_contact' );
function tp_handle_contact() {
	$return = isset( $_POST['_tp_return'] ) ? esc_url_raw( wp_unslash( $_POST['_tp_return'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
	$return = remove_query_arg( array( 'tp_form', 'sent' ), wp_validate_redirect( $return, home_url( '/' ) ) );
	$raw    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- verified below; sanitized per field.

	if ( ! isset( $_POST['_tp_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_nonce'] ) ), 'tp_contact' ) ) {
		tp_booking_fail( $return, array( 'form' => __( 'Your session expired. Please try again.', 'taxi-peninsula' ) ), $raw, '#contact' );
	}
	if ( tp_is_honeypot_hit() ) {
		wp_safe_redirect( add_query_arg( 'sent', 1, $return ) . '#contact' );
		exit;
	}
	if ( ! tp_rate_limit( 'contact', 5, HOUR_IN_SECONDS, false ) ) {
		/* translators: %s: phone */
		tp_booking_fail( $return, array( 'form' => sprintf( __( 'Too many messages from your connection. Please call %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) ), $raw, '#contact' );
	}
	if ( ! tp_verify_captcha() ) {
		tp_booking_fail( $return, array( 'form' => tp_captcha_error_message() ), $raw, '#contact' );
	}

	$name    = sanitize_text_field( $raw['name'] ?? '' );
	$email   = sanitize_email( $raw['email'] ?? '' );
	$phone   = sanitize_text_field( $raw['phone'] ?? '' );
	$topic   = sanitize_text_field( $raw['topic'] ?? '' );
	$message = sanitize_textarea_field( $raw['message'] ?? '' );
	if ( ! in_array( $topic, tp_contact_topics(), true ) ) {
		$topic = tp_contact_topics()[0];
	}

	$errors = array();
	if ( '' === $name ) {
		$errors['name'] = 1;
	}
	if ( ! is_email( $email ) ) {
		$errors['email'] = 1;
	}
	if ( '' === trim( $message ) ) {
		$errors['message'] = 1;
	}
	if ( $errors ) {
		$errors['form'] = __( 'Please fill in your name, a valid email and your message.', 'taxi-peninsula' );
		tp_booking_fail( $return, $errors, $raw, '#contact' );
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'tp_message',
			'post_status'  => 'publish',
			'post_title'   => $name,
			'post_content' => $message,
			'post_excerpt' => $email . ' ' . $phone,
		)
	);
	if ( $post_id && ! is_wp_error( $post_id ) ) {
		update_post_meta( $post_id, '_tp_email', $email );
		update_post_meta( $post_id, '_tp_phone', $phone );
		update_post_meta( $post_id, '_tp_topic', $topic );
	}
	tp_rate_limit( 'contact', 5, HOUR_IN_SECONDS, true );

	wp_mail(
		tp_opt( 'notify_email' ) ?: get_option( 'admin_email' ),
		/* translators: 1: topic, 2: name */
		sprintf( __( 'Website enquiry: %1$s — %2$s', 'taxi-peninsula' ), $topic, $name ),
		sprintf( "%s\n%s\n%s\n\n%s\n\n%s", $name, $email, $phone, $message, admin_url( 'edit.php?post_type=tp_message' ) ),
		tp_mail_headers( $email )
	);

	wp_safe_redirect( add_query_arg( 'sent', 1, $return ) . '#contact' );
	exit;
}

/**
 * Unread enquiries count bubble on the Enquiries submenu.
 */
add_action( 'admin_menu', static function () {
	global $submenu;
	$key = 'edit.php?post_type=tp_booking';
	if ( empty( $submenu[ $key ] ) ) {
		return;
	}
	$unread = count(
		get_posts(
			array(
				'post_type'   => 'tp_message',
				'numberposts' => 99,
				'fields'      => 'ids',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array( 'key' => '_tp_read', 'compare' => 'NOT EXISTS' ),
				),
			)
		)
	);
	if ( ! $unread ) {
		return;
	}
	foreach ( $submenu[ $key ] as $i => $item ) {
		if ( 'edit.php?post_type=tp_message' === $item[2] ) {
			$submenu[ $key ][ $i ][0] .= sprintf( ' <span class="awaiting-mod"><span class="pending-count">%d</span></span>', $unread );
		}
	}
}, 99 );
