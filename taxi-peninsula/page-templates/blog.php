<?php
/**
 * Template Name: Blog
 *
 * Lists your latest posts with an optional intro (the page content).
 * Tip: if you set this page as the "Posts page" under Settings → Reading,
 * WordPress uses its own blog layout instead — both look the same.
 *
 * @package TaxiPeninsula
 */

get_header();

while ( have_posts() ) :
	the_post();
	get_template_part( 'template-parts/page-header', null, array( 'title' => get_the_title() ) );
	$tp_intro = trim( get_the_content() );
endwhile;

$tp_paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$tp_blog = new WP_Query(
	array(
		'post_type'      => 'post',
		'paged'          => $tp_paged,
		'posts_per_page' => (int) get_option( 'posts_per_page', 10 ),
	)
);
?>
<div class="container layout-sidebar section">
	<div class="layout-sidebar__main">
		<?php if ( ! empty( $tp_intro ) ) : ?>
			<div class="entry-content page-content section__intro"><?php echo apply_filters( 'the_content', $tp_intro ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core content filter. ?></div>
		<?php endif; ?>
		<?php if ( $tp_blog->have_posts() ) : ?>
			<div class="post-grid post-grid--2">
				<?php
				while ( $tp_blog->have_posts() ) :
					$tp_blog->the_post();
					get_template_part( 'template-parts/content', 'card' );
				endwhile;
				?>
			</div>
			<nav class="navigation pagination" aria-label="<?php esc_attr_e( 'Posts', 'taxi-peninsula' ); ?>">
				<div class="nav-links">
					<?php
					echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup.
						array(
							'total'     => $tp_blog->max_num_pages,
							'current'   => $tp_paged,
							'mid_size'  => 1,
							'prev_text' => __( 'Previous', 'taxi-peninsula' ),
							'next_text' => __( 'Next', 'taxi-peninsula' ),
						)
					);
					?>
				</div>
			</nav>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content', 'none' ); ?>
		<?php endif; ?>
	</div>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
