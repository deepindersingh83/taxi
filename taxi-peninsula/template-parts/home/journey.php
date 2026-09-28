<?php
/**
 * Home section: how a trip works — animated pickup and airport scenes.
 *
 * @package TaxiPeninsula
 */

?>
<section class="section section--journey" aria-labelledby="journey-title">
	<div class="container">
		<h2 id="journey-title" class="section__title"><?php echo esc_html( tp_home_text( 'journey' ) ); ?></h2>
		<p class="section__lead"><?php echo esc_html( tp_home_text( 'journey', 'text' ) ); ?></p>
		<div class="journey">
			<div class="journey__item">
				<?php tp_the_scene( 'pickup' ); ?>
				<h3 class="journey__title"><?php esc_html_e( 'Pickups from your door', 'taxi-peninsula' ); ?></h3>
				<p><?php esc_html_e( 'Our maxi taxis back up close, lower the ramp and help you aboard. You stay in your own chair, secured with four-point restraints.', 'taxi-peninsula' ); ?></p>
				<a class="link-arrow" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book a pickup', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
			</div>
			<div class="journey__item">
				<?php tp_the_scene( 'airport' ); ?>
				<h3 class="journey__title"><?php esc_html_e( 'Airport drop-offs & pickups', 'taxi-peninsula' ); ?></h3>
				<p><?php esc_html_e( 'Straight to Departures, and waiting at Arrivals when you land — with room for luggage and mobility equipment.', 'taxi-peninsula' ); ?></p>
				<a class="link-arrow" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book an airport transfer', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
			</div>
		</div>
	</div>
</section>
