<?php
/**
 * Template Name: About Us
 *
 * Your story (the page content), followed by why passengers choose you,
 * the fleet, testimonials and a call to action.
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
			'lead'  => has_excerpt() ? esc_html( get_the_excerpt() ) : '',
		)
	);
	?>
	<div class="container section">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry-content page-content' ); ?>>
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="featured-image"><?php the_post_thumbnail( 'large', array( 'alt' => tp_thumbnail_alt( get_post() ) ) ); ?></figure>
			<?php endif; ?>
			<?php the_content(); ?>
		</article>
	</div>
	<?php
endwhile;

get_template_part( 'template-parts/features', null, array( 'title' => __( 'What makes us different', 'taxi-peninsula' ) ) );

$tp_drivers = do_shortcode( '[tp_drivers]' );
if ( $tp_drivers ) :
	?>
	<section class="section" aria-labelledby="about-drivers">
		<div class="container">
			<h2 id="about-drivers" class="section__title"><?php esc_html_e( 'Meet the drivers', 'taxi-peninsula' ); ?></h2>
			<?php echo $tp_drivers; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in shortcode. ?>
		</div>
	</section>
	<?php
endif;

get_template_part( 'template-parts/video' );

if ( tp_fleet_vehicles() ) :
	?>
	<section class="section" aria-labelledby="about-fleet">
		<div class="container">
			<div class="section__head">
				<h2 id="about-fleet" class="section__title"><?php esc_html_e( 'Our vehicles', 'taxi-peninsula' ); ?></h2>
				<?php $fleet_url = tp_page_url_by_template( 'fleet' ); ?>
				<?php if ( $fleet_url ) : ?>
					<a class="link-arrow" href="<?php echo esc_url( $fleet_url ); ?>"><?php esc_html_e( 'See the full fleet', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
				<?php endif; ?>
			</div>
			<?php echo do_shortcode( '[tp_fleet limit="2"]' ); ?>
		</div>
	</section>
	<?php
endif;

get_template_part( 'template-parts/testimonials', null, array( 'limit' => 3 ) );
get_template_part( 'template-parts/partners' );
get_template_part( 'template-parts/cta' );
get_footer();
