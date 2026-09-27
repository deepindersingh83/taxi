<?php
/**
 * Home section: latest blog posts.
 *
 * @package TaxiPeninsula
 */

$latest = new WP_Query(
	array(
		'post_type'           => 'post',
		'posts_per_page'      => 3,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
if ( ! $latest->have_posts() ) {
	return;
}
$blog_url = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : tp_page_url_by_template( 'blog' );
?>
<section class="section section--posts" aria-labelledby="news-title">
	<div class="container">
		<div class="section__head">
			<h2 id="news-title" class="section__title"><?php echo esc_html( tp_home_text( 'posts' ) ); ?></h2>
			<?php if ( $blog_url ) : ?>
				<a class="link-arrow" href="<?php echo esc_url( $blog_url ); ?>"><?php esc_html_e( 'All articles', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
			<?php endif; ?>
		</div>
		<div class="post-grid">
			<?php
			while ( $latest->have_posts() ) :
				$latest->the_post();
				get_template_part( 'template-parts/content', 'card' );
			endwhile;
			wp_reset_postdata();
			?>
		</div>
	</div>
</section>
