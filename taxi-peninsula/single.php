<?php
/**
 * Single blog post.
 *
 * @package TaxiPeninsula
 */

get_header();

while ( have_posts() ) :
	the_post();

	$meta = sprintf(
		'<time datetime="%1$s">%2$s</time> · %3$s',
		esc_attr( get_the_date( 'c' ) ),
		esc_html( get_the_date() ),
		esc_html( get_the_author() )
	);
	$cats = get_the_category_list( ', ' );
	if ( $cats ) {
		$meta .= ' · ' . $cats;
	}

	get_template_part(
		'template-parts/page-header',
		null,
		array(
			'title' => get_the_title(),
			'meta'  => $meta,
		)
	);
	?>
	<div class="container layout-sidebar section">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'layout-sidebar__main' ); ?>>
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="featured-image"><?php the_post_thumbnail( 'large', array( 'alt' => tp_thumbnail_alt( get_post() ) ) ); ?></figure>
			<?php endif; ?>
			<div class="prose entry-content">
				<?php
				the_content();
				wp_link_pages(
					array(
						'before' => '<nav class="page-links">' . esc_html__( 'Pages:', 'taxi-peninsula' ),
						'after'  => '</nav>',
					)
				);
				?>
			</div>
			<?php
			$tags = get_the_tag_list( '<ul class="chips chips--tags"><li class="chip">', '</li><li class="chip">', '</li></ul>' );
			if ( $tags && ! is_wp_error( $tags ) ) {
				echo wp_kses_post( $tags );
			}

			the_post_navigation(
				array(
					'prev_text' => '<span class="nav-label">' . esc_html__( 'Previous', 'taxi-peninsula' ) . '</span> %title',
					'next_text' => '<span class="nav-label">' . esc_html__( 'Next', 'taxi-peninsula' ) . '</span> %title',
				)
			);

			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
			?>
		</article>
		<?php get_sidebar(); ?>
	</div>
	<?php
endwhile;

get_template_part( 'template-parts/cta' );
get_footer();
