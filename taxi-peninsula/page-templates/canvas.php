<?php
/**
 * Template Name: Blank canvas (no title band)
 *
 * Header, your content, footer — nothing else. Start with the "Hero banner"
 * pattern to build landing pages entirely in the editor.
 *
 * @package TaxiPeninsula
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry-content canvas' ); ?>>
		<?php if ( false === stripos( get_the_content(), '<h1' ) ) : ?>
			<h1 class="screen-reader-text"><?php the_title(); ?></h1>
		<?php endif; ?>
		<?php the_content(); ?>
	</article>
	<?php
endwhile;

get_footer();
