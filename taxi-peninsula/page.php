<?php
/**
 * Default page.
 *
 * @package TaxiPeninsula
 */

get_header();

while ( have_posts() ) :
	the_post();
	get_template_part( 'template-parts/page-header', null, array( 'title' => get_the_title() ) );
	?>
	<div class="container section">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry-content page-content' ); ?>>
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="featured-image"><?php the_post_thumbnail( 'large' ); ?></figure>
			<?php endif; ?>
			<?php the_content(); ?>
			<?php wp_link_pages(); ?>
		</article>
		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	</div>
	<?php
endwhile;

get_footer();
