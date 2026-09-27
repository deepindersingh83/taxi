<?php
/**
 * Template Name: Full width
 *
 * Title band, then the page content across the full container width.
 * Ideal for pages built from the "Taxi Peninsula" block patterns.
 *
 * @package TaxiPeninsula
 */

get_header();

while ( have_posts() ) :
	the_post();
	get_template_part( 'template-parts/page-header', null, array( 'title' => get_the_title() ) );
	?>
	<div class="container section">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry-content page-content page-content--wide' ); ?>>
			<?php the_content(); ?>
		</article>
	</div>
	<?php
endwhile;

get_footer();
