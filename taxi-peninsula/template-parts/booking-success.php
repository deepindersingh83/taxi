<?php
/**
 * Shown after a booking request is received.
 *
 * @package TaxiPeninsula
 *
 * @var array $args { reference: string }
 */

$ref = isset( $args['reference'] ) ? $args['reference'] : '';
?>
<div class="booking-success" role="status" tabindex="-1" data-focus>
	<span class="booking-success__icon"><?php tp_the_icon( 'check' ); ?></span>
	<h2><?php esc_html_e( 'Booking request received', 'taxi-peninsula' ); ?></h2>
	<?php if ( $ref ) : ?>
		<p class="booking-success__ref"><?php esc_html_e( 'Your reference', 'taxi-peninsula' ); ?> <strong><?php echo esc_html( $ref ); ?></strong></p>
	<?php endif; ?>
	<p><?php esc_html_e( 'We will confirm your booking by phone, SMS or email shortly. A copy of your request has been sent to your inbox.', 'taxi-peninsula' ); ?></p>
	<p>
		<?php
		printf(
			/* translators: %s: phone link */
			esc_html__( 'Need to change something? Call %s and quote your reference.', 'taxi-peninsula' ),
			'<a href="' . esc_url( tp_phone_href() ) . '">' . esc_html( tp_opt( 'phone_display' ) ) . '</a>'
		);
		?>
	</p>
	<a class="btn btn--primary" href="<?php echo esc_url( remove_query_arg( array( 'booking', 'ref' ) ) ); ?>#book"><?php esc_html_e( 'Make another booking', 'taxi-peninsula' ); ?></a>
</div>
