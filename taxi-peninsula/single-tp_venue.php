<?php
/**
 * Partner landing page, e.g. /book-from/frankston-hospital/.
 *
 * @package TaxiPeninsula
 */

get_header();

while ( have_posts() ) :
	the_post();
	$tp_id   = get_the_ID();
	$tp_m    = tp_venue_meta( $tp_id );
	$tp_name = get_the_title();
	?>
	<section class="venue-hero">
		<div class="container venue-hero__inner">
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="venue-hero__logo"><?php the_post_thumbnail( 'medium', array( 'alt' => $tp_name ) ); ?></div>
			<?php endif; ?>
			<div class="venue-hero__text">
				<p class="eyebrow"><?php echo esc_html( tp_venue_types()[ $tp_m['type'] ] ?? '' ); ?></p>
				<h1>
					<?php
					/* translators: %s: partner name */
					echo esc_html( sprintf( __( 'Wheelchair taxi from %s', 'taxi-peninsula' ), $tp_name ) );
					?>
				</h1>
				<p class="venue-hero__lead"><?php echo has_excerpt() ? esc_html( get_the_excerpt() ) : esc_html__( 'Book an accessible taxi home, to an appointment or anywhere you need to go. Your pick-up address is already filled in.', 'taxi-peninsula' ); ?></p>
			</div>
		</div>
	</section>

	<div class="container section venue-body">
		<div class="venue-book card">
			<h2><?php esc_html_e( 'Book your trip', 'taxi-peninsula' ); ?></h2>
			<?php
			get_template_part(
				'template-parts/quickbook',
				null,
				array(
					'context' => 'section',
					'pickup'  => $tp_m['pickup'],
					'hidden'  => array( 'venue' => $tp_id ),
				)
			);
			?>
			<p class="venue-book__or">
				<?php esc_html_e( 'Or call us — we are happy to book for you:', 'taxi-peninsula' ); ?>
				<a class="venue-book__phone" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?> <?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
			</p>
		</div>

		<div class="venue-info prose entry-content">
			<?php if ( '' !== trim( get_the_content() ) ) : ?>
				<?php the_content(); ?>
			<?php endif; ?>
			<?php if ( $tp_m['pickup_note'] ) : ?>
				<h2><?php esc_html_e( 'Where we pick you up', 'taxi-peninsula' ); ?></h2>
				<p><?php tp_the_icon( 'pin' ); ?> <?php echo esc_html( $tp_m['pickup_note'] ); ?></p>
			<?php endif; ?>
			<h2><?php esc_html_e( 'Good to know', 'taxi-peninsula' ); ?></h2>
			<ul class="tick-list">
				<li><?php tp_the_icon( 'check' ); ?><?php esc_html_e( 'Ramps or hoists and wheelchair restraints in every vehicle', 'taxi-peninsula' ); ?></li>
				<li><?php tp_the_icon( 'check' ); ?><?php esc_html_e( 'Carers and family travel with you', 'taxi-peninsula' ); ?></li>
				<li><?php tp_the_icon( 'check' ); ?><?php esc_html_e( 'Pay the driver, use your MPTP card, or ask for an NDIS / aged-care invoice', 'taxi-peninsula' ); ?></li>
				<li><?php tp_the_icon( 'check' ); ?><?php esc_html_e( 'We text you when your driver is on the way', 'taxi-peninsula' ); ?></li>
			</ul>
			<?php if ( $tp_m['phone'] ) : ?>
				<p class="venue-info__staff">
					<?php
					/* translators: %s: partner name */
					echo esc_html( sprintf( __( 'Staff at %s can also book for you at reception.', 'taxi-peninsula' ), $tp_name ) );
					?>
				</p>
			<?php endif; ?>
		</div>
	</div>
	<?php
endwhile;

get_footer();
