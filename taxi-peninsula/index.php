<?php
/**
 * Blog index and generic fallback.
 *
 * @package TaxiPeninsula
 */

get_header();

if ( is_home() && ! is_front_page() ) {
	$title = single_post_title( '', false );
} elseif ( is_search() ) {
	/* translators: %s: search query */
	$title = sprintf( __( 'Search results for “%s”', 'taxi-peninsula' ), esc_html( get_search_query() ) );
} elseif ( is_archive() ) {
	$title = get_the_archive_title();
} else {
	$title = __( 'News & travel tips', 'taxi-peninsula' );
}

get_template_part(
	'template-parts/page-header',
	null,
	array(
		'title' => $title,
		'lead'  => is_archive() ? get_the_archive_description() : '',
	)
);
?>
<div class="container layout-sidebar section">
	<div class="layout-sidebar__main">
		<?php if ( have_posts() ) : ?>
			<div class="post-grid post-grid--2">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content', 'card' );
				endwhile;
				?>
			</div>
			<?php
			the_posts_pagination(
				array(
					'mid_size'  => 1,
					'prev_text' => __( 'Previous', 'taxi-peninsula' ),
					'next_text' => __( 'Next', 'taxi-peninsula' ),
				)
			);
			?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content', 'none' ); ?>
		<?php endif; ?>
	</div>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
