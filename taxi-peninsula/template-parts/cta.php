<?php
/**
 * Call-to-action band.
 *
 * @package TaxiPeninsula
 */

?>
<section class="cta-band" aria-labelledby="cta-title">
	<div class="container cta-band__inner">
		<div>
			<h2 id="cta-title" class="cta-band__title"><?php echo esc_html( $args['title'] ?? __( 'Need a wheelchair taxi today?', 'taxi-peninsula' ) ); ?></h2>
			<p><?php echo esc_html( $args['text'] ?? __( 'Call our friendly team and we will get you moving.', 'taxi-peninsula' ) ); ?></p>
		</div>
		<div class="cta-band__actions">
			<a class="btn btn--accent btn--lg" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?> <?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
			<a class="btn btn--light btn--lg" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book online', 'taxi-peninsula' ); ?></a>
		</div>
	</div>
</section>
