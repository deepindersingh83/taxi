<?php
/**
 * Popular destinations (from real, anonymised bookings). Hidden until there are at least 3.
 *
 * @package TaxiPeninsula
 */

$destinations = tp_popular_destinations( 6 );
if ( count( $destinations ) < 3 ) {
	return;
}
?>
<section class="section section--destinations" aria-labelledby="dest-title">
	<div class="container">
		<h2 id="dest-title" class="section__title"><?php echo esc_html( $args['title'] ?? __( 'Where our passengers go', 'taxi-peninsula' ) ); ?></h2>
		<p class="section__lead"><?php echo esc_html( $args['text'] ?? __( 'Our most-booked destinations this year. Tap one to book a trip there.', 'taxi-peninsula' ) ); ?></p>
		<ul class="destinations">
			<?php foreach ( $destinations as $d ) : ?>
				<li class="destination">
					<span class="destination__icon"><?php tp_the_icon( preg_match( '/airport|terminal/i', $d['label'] ) ? 'plane' : ( preg_match( '/hospital|medical|clinic|dialysis|health|rehab/i', $d['label'] ) ? 'medical' : 'pin' ) ); ?></span>
					<span class="destination__name"><?php echo esc_html( $d['label'] ); ?></span>
					<span class="destination__links">
						<a class="btn btn--accent" href="<?php echo esc_url( $d['book_url'] ); ?>">
							<?php esc_html_e( 'Book', 'taxi-peninsula' ); ?><span class="screen-reader-text"> <?php echo esc_html( sprintf( /* translators: %s: place */ __( 'a trip to %s', 'taxi-peninsula' ), $d['label'] ) ); ?></span>
						</a>
						<?php if ( $d['guide_url'] ) : ?>
							<a class="link-arrow" href="<?php echo esc_url( $d['guide_url'] ); ?>">
								<?php esc_html_e( 'Guide', 'taxi-peninsula' ); ?><span class="screen-reader-text"> <?php echo esc_html( sprintf( /* translators: %s: place */ __( 'to travelling to %s', 'taxi-peninsula' ), $d['label'] ) ); ?></span>
							</a>
						<?php endif; ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
