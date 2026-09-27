<?php
/**
 * Booking lookup form (reference + phone).
 *
 * @package TaxiPeninsula
 *
 * @var array $args { errors: array, old: array }
 */

$errors = isset( $args['errors'] ) ? (array) $args['errors'] : array();
$old    = isset( $args['old'] ) ? (array) $args['old'] : array();
$return = ( is_singular() && ! is_front_page() ) ? get_permalink() : home_url( '/' );
?>
<div class="lookup card">
	<h2 class="lookup__title"><?php esc_html_e( 'Find your booking', 'taxi-peninsula' ); ?></h2>
	<p><?php esc_html_e( 'Enter the reference from your confirmation (it starts with TP-) and the mobile number you booked with.', 'taxi-peninsula' ); ?></p>

	<?php if ( ! empty( $errors['form'] ) ) : ?>
		<div class="notice notice--error" role="alert" tabindex="-1" data-focus><p><?php echo esc_html( $errors['form'] ); ?></p></div>
	<?php endif; ?>

	<form class="booking-form lookup__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="tp_lookup">
		<input type="hidden" name="_tp_return" value="<?php echo esc_url( $return ); ?>">
		<?php wp_nonce_field( 'tp_lookup', '_tp_nonce', false ); ?>
		<div class="booking-form__group">
			<div class="field">
				<label for="lk-ref"><?php esc_html_e( 'Booking reference', 'taxi-peninsula' ); ?></label>
				<input id="lk-ref" name="ref" type="text" required autocapitalize="characters" autocomplete="off" placeholder="TP-XXXXXX" value="<?php echo esc_attr( $old['ref'] ?? '' ); ?>">
			</div>
			<div class="field">
				<label for="lk-phone"><?php esc_html_e( 'Mobile number', 'taxi-peninsula' ); ?></label>
				<input id="lk-phone" name="phone" type="tel" required autocomplete="tel" inputmode="tel" placeholder="04xx xxx xxx" value="<?php echo esc_attr( $old['phone'] ?? '' ); ?>">
			</div>
		</div>
		<?php tp_spam_fields(); ?>
		<div class="booking-form__submit">
			<button type="submit" class="btn btn--primary btn--lg"><?php esc_html_e( 'Show my booking', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></button>
		</div>
	</form>
	<p class="lookup__help">
		<?php
		printf(
			/* translators: %s: phone link */
			esc_html__( 'Lost your reference? Call %s.', 'taxi-peninsula' ),
			'<a href="' . esc_url( tp_phone_href() ) . '">' . esc_html( tp_opt( 'phone_display' ) ) . '</a>'
		);
		?>
	</p>
</div>
