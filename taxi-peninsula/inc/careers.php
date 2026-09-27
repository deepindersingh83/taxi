<?php
/**
 * "Drive with us" recruitment: [tp_driver_apply] form. Applications are saved
 * under Bookings → Enquiries (topic "Driver application") and emailed to the office.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_application_topic() {
	return __( 'Driver application', 'taxi-peninsula' );
}

add_shortcode( 'tp_driver_apply', static function () {
	$state = array( 'errors' => array(), 'old' => array() );
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
	$sent = ! empty( $_GET['applied'] );
	if ( isset( $_GET['tp_form'] ) ) {
		$stored = get_transient( 'tp_form_' . sanitize_key( wp_unslash( $_GET['tp_form'] ) ) );
		if ( is_array( $stored ) ) {
			$state = $stored;
		}
	}
	// phpcs:enable
	$errors = $state['errors'];
	$old    = $state['old'];
	$val    = static function ( $k, $d = '' ) use ( $old ) {
		return $old[ $k ] ?? $d;
	};
	$bad    = static function ( $k ) use ( $errors ) {
		return isset( $errors[ $k ] ) ? ' aria-invalid="true"' : '';
	};
	$return = ( is_singular() && ! is_front_page() ) ? get_permalink() : home_url( '/' );
	$days   = array(
		'mon' => __( 'Mon', 'taxi-peninsula' ), 'tue' => __( 'Tue', 'taxi-peninsula' ), 'wed' => __( 'Wed', 'taxi-peninsula' ),
		'thu' => __( 'Thu', 'taxi-peninsula' ), 'fri' => __( 'Fri', 'taxi-peninsula' ), 'sat' => __( 'Sat', 'taxi-peninsula' ),
		'sun' => __( 'Sun', 'taxi-peninsula' ), 'nights' => __( 'Nights', 'taxi-peninsula' ),
	);
	ob_start();
	?>
	<div class="tp-component card apply" id="apply">
		<?php if ( $sent ) : ?>
			<div class="booking-success" role="status" tabindex="-1" data-focus>
				<span class="booking-success__icon"><?php tp_the_icon( 'check' ); ?></span>
				<h2><?php esc_html_e( 'Application received — thank you!', 'taxi-peninsula' ); ?></h2>
				<p><?php esc_html_e( 'We read every application and will be in touch if there is a suitable opening.', 'taxi-peninsula' ); ?></p>
			</div>
		<?php else : ?>
			<h2 class="contact-layout__title"><?php esc_html_e( 'Apply to drive with us', 'taxi-peninsula' ); ?></h2>
			<?php if ( $errors ) : ?>
				<div class="notice notice--error" role="alert" tabindex="-1" data-focus><p><?php echo esc_html( $errors['form'] ?? __( 'Please check the highlighted fields.', 'taxi-peninsula' ) ); ?></p></div>
			<?php endif; ?>
			<form class="booking-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
				<input type="hidden" name="action" value="tp_driver_apply">
				<input type="hidden" name="_tp_return" value="<?php echo esc_url( $return ); ?>">
				<?php wp_nonce_field( 'tp_driver_apply', '_tp_nonce', false ); ?>
				<div class="booking-form__group">
					<div class="field"><label for="ap-name"><?php esc_html_e( 'Full name', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label><input id="ap-name" name="name" type="text" required autocomplete="name" value="<?php echo esc_attr( $val( 'name' ) ); ?>"<?php echo $bad( 'name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></div>
					<div class="field"><label for="ap-phone"><?php esc_html_e( 'Mobile', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label><input id="ap-phone" name="phone" type="tel" required autocomplete="tel" value="<?php echo esc_attr( $val( 'phone' ) ); ?>"<?php echo $bad( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></div>
					<div class="field"><label for="ap-email"><?php esc_html_e( 'Email', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label><input id="ap-email" name="email" type="email" required autocomplete="email" value="<?php echo esc_attr( $val( 'email' ) ); ?>"<?php echo $bad( 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></div>
					<div class="field"><label for="ap-suburb"><?php esc_html_e( 'Suburb you live in', 'taxi-peninsula' ); ?></label><input id="ap-suburb" name="suburb" type="text" autocomplete="address-level2" value="<?php echo esc_attr( $val( 'suburb' ) ); ?>"></div>
					<div class="field field--wide">
						<label for="ap-accred"><?php esc_html_e( 'Driver accreditation', 'taxi-peninsula' ); ?></label>
						<select id="ap-accred" name="accreditation">
							<option value="yes" <?php selected( $val( 'accreditation' ), 'yes' ); ?>><?php esc_html_e( 'I hold Victorian commercial passenger vehicle driver accreditation', 'taxi-peninsula' ); ?></option>
							<option value="applying" <?php selected( $val( 'accreditation' ), 'applying' ); ?>><?php esc_html_e( 'I have applied / am applying', 'taxi-peninsula' ); ?></option>
							<option value="no" <?php selected( $val( 'accreditation' ), 'no' ); ?>><?php esc_html_e( 'Not yet', 'taxi-peninsula' ); ?></option>
						</select>
					</div>
					<div class="field field--wide">
						<label for="ap-exp"><?php esc_html_e( 'Experience with wheelchair accessible vehicles', 'taxi-peninsula' ); ?></label>
						<select id="ap-exp" name="experience">
							<option value="none" <?php selected( $val( 'experience' ), 'none' ); ?>><?php esc_html_e( 'None yet — happy to be trained', 'taxi-peninsula' ); ?></option>
							<option value="some" <?php selected( $val( 'experience' ), 'some' ); ?>><?php esc_html_e( 'Some (under 1 year)', 'taxi-peninsula' ); ?></option>
							<option value="experienced" <?php selected( $val( 'experience' ), 'experienced' ); ?>><?php esc_html_e( 'Experienced (1 year or more)', 'taxi-peninsula' ); ?></option>
						</select>
					</div>
					<fieldset class="field field--wide weekday-picker">
						<legend class="field__label"><?php esc_html_e( 'When can you drive?', 'taxi-peninsula' ); ?></legend>
						<?php $picked = (array) $val( 'availability', array() ); ?>
						<?php foreach ( $days as $k => $label ) : ?>
							<label class="weekday"><input type="checkbox" name="availability[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, $picked, true ) ); ?>><span><?php echo esc_html( $label ); ?></span></label>
						<?php endforeach; ?>
					</fieldset>
					<div class="field field--wide"><label for="ap-msg"><?php esc_html_e( 'Tell us a little about yourself', 'taxi-peninsula' ); ?></label><textarea id="ap-msg" name="message" rows="4" placeholder="<?php esc_attr_e( 'Driving history, disability or aged-care experience, why you would like to join…', 'taxi-peninsula' ); ?>"><?php echo esc_textarea( $val( 'message' ) ); ?></textarea></div>
					<div class="field field--wide field--check">
						<input id="ap-consent" name="consent" type="checkbox" value="1" required<?php echo $bad( 'consent' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php checked( $val( 'consent' ), '1' ); ?>>
						<label for="ap-consent"><?php esc_html_e( 'I agree to you keeping my application to consider me for driving roles.', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
					</div>
				</div>
				<?php tp_spam_fields(); ?>
				<div class="booking-form__submit"><button type="submit" class="btn btn--accent btn--lg"><?php esc_html_e( 'Send application', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></button></div>
			</form>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
} );

add_action( 'admin_post_nopriv_tp_driver_apply', 'tp_handle_driver_apply' );
add_action( 'admin_post_tp_driver_apply', 'tp_handle_driver_apply' );
function tp_handle_driver_apply() {
	$return = isset( $_POST['_tp_return'] ) ? esc_url_raw( wp_unslash( $_POST['_tp_return'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
	$return = remove_query_arg( array( 'tp_form', 'applied' ), wp_validate_redirect( $return, home_url( '/' ) ) );
	$raw    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- verified below; sanitized per field.

	if ( ! isset( $_POST['_tp_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_nonce'] ) ), 'tp_driver_apply' ) ) {
		tp_booking_fail( $return, array( 'form' => __( 'Your session expired. Please try again.', 'taxi-peninsula' ) ), $raw, '#apply' );
	}
	if ( tp_is_honeypot_hit() ) {
		wp_safe_redirect( add_query_arg( 'applied', 1, $return ) . '#apply' );
		exit;
	}
	if ( ! tp_rate_limit( 'apply', 3, HOUR_IN_SECONDS, false ) ) {
		tp_booking_fail( $return, array( 'form' => __( 'Too many applications from your connection. Please try again later.', 'taxi-peninsula' ) ), $raw, '#apply' );
	}
	if ( ! tp_verify_captcha() ) {
		tp_booking_fail( $return, array( 'form' => tp_captcha_error_message() ), $raw, '#apply' );
	}

	$d = array(
		'name'          => sanitize_text_field( $raw['name'] ?? '' ),
		'email'         => sanitize_email( $raw['email'] ?? '' ),
		'phone'         => sanitize_text_field( $raw['phone'] ?? '' ),
		'suburb'        => sanitize_text_field( $raw['suburb'] ?? '' ),
		'accreditation' => in_array( $raw['accreditation'] ?? '', array( 'yes', 'applying', 'no' ), true ) ? $raw['accreditation'] : 'no',
		'experience'    => in_array( $raw['experience'] ?? '', array( 'none', 'some', 'experienced' ), true ) ? $raw['experience'] : 'none',
		'availability'  => array_values( array_intersect( (array) ( $raw['availability'] ?? array() ), array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun', 'nights' ) ) ),
		'message'       => sanitize_textarea_field( $raw['message'] ?? '' ),
	);
	$errors = array();
	foreach ( array( 'name', 'phone' ) as $req ) {
		if ( '' === $d[ $req ] ) {
			$errors[ $req ] = 1;
		}
	}
	if ( ! is_email( $d['email'] ) ) {
		$errors['email'] = 1;
	}
	if ( empty( $raw['consent'] ) ) {
		$errors['consent'] = 1;
	}
	if ( $errors ) {
		$errors['form'] = __( 'Please fill in your name, mobile, a valid email and tick the consent box.', 'taxi-peninsula' );
		tp_booking_fail( $return, $errors, $raw, '#apply' );
	}

	$labels = array(
		'yes'         => __( 'Holds accreditation', 'taxi-peninsula' ),
		'applying'    => __( 'Applying for accreditation', 'taxi-peninsula' ),
		'no'          => __( 'No accreditation yet', 'taxi-peninsula' ),
		'none'        => __( 'No WAT experience', 'taxi-peninsula' ),
		'some'        => __( 'Some WAT experience', 'taxi-peninsula' ),
		'experienced' => __( 'Experienced WAT driver', 'taxi-peninsula' ),
	);
	$summary = implode(
		"\n",
		array(
			__( 'Suburb:', 'taxi-peninsula' ) . ' ' . $d['suburb'],
			__( 'Accreditation:', 'taxi-peninsula' ) . ' ' . $labels[ $d['accreditation'] ],
			__( 'Experience:', 'taxi-peninsula' ) . ' ' . $labels[ $d['experience'] ],
			__( 'Available:', 'taxi-peninsula' ) . ' ' . strtoupper( implode( ', ', $d['availability'] ) ),
			'',
			$d['message'],
		)
	);

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'tp_message',
			'post_status'  => 'publish',
			'post_title'   => $d['name'],
			'post_content' => $summary,
			'post_excerpt' => $d['email'] . ' ' . $d['phone'],
		)
	);
	if ( $post_id && ! is_wp_error( $post_id ) ) {
		update_post_meta( $post_id, '_tp_email', $d['email'] );
		update_post_meta( $post_id, '_tp_phone', $d['phone'] );
		update_post_meta( $post_id, '_tp_topic', tp_application_topic() );
	}
	tp_rate_limit( 'apply', 3, HOUR_IN_SECONDS, true );

	wp_mail(
		tp_opt( 'notify_email' ) ?: get_option( 'admin_email' ),
		/* translators: %s: name */
		sprintf( __( 'Driver application: %s', 'taxi-peninsula' ), $d['name'] ),
		$d['name'] . "\n" . $d['email'] . "\n" . $d['phone'] . "\n\n" . $summary . "\n\n" . admin_url( 'edit.php?post_type=tp_message' ),
		tp_mail_headers( $d['email'] )
	);

	wp_safe_redirect( add_query_arg( 'applied', 1, $return ) . '#apply' );
	exit;
}
