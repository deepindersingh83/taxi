<?php
/**
 * Template Name: Services
 *
 * Intro (page content), every published Service, how booking works, and a call to action.
 * When this page exists, /services/ redirects here so there is one services listing.
 *
 * @package TaxiPeninsula
 */

get_header();

while ( have_posts() ) :
	the_post();
	get_template_part(
		'template-parts/page-header',
		null,
		array(
			'title' => get_the_title(),
			'lead'  => has_excerpt() ? esc_html( get_the_excerpt() ) : esc_html__( 'Reliable wheelchair accessible transport for everyday trips and important appointments.', 'taxi-peninsula' ),
		)
	);
	?>
	<section class="section">
		<div class="container">
			<?php if ( '' !== trim( get_the_content() ) ) : ?>
				<div class="entry-content page-content section__intro"><?php the_content(); ?></div>
			<?php endif; ?>
			<?php echo do_shortcode( '[tp_services]' ); ?>
		</div>
	</section>
	<?php
endwhile;
?>
<section class="section section--features" aria-labelledby="services-how">
	<div class="container">
		<h2 id="services-how" class="section__title"><?php esc_html_e( 'How booking works', 'taxi-peninsula' ); ?></h2>
		<div class="steps--wide"><?php get_template_part( 'template-parts/steps' ); ?></div>
		<p class="cta-inline"><a class="btn btn--accent btn--lg" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book online', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a></p>
	</div>
</section>
<?php
get_template_part( 'template-parts/testimonials', null, array( 'limit' => 3 ) );
get_template_part( 'template-parts/cta' );
get_footer();
