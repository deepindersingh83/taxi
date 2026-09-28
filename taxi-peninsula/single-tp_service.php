<?php
/**
 * Single service page, e.g. /services/airport-transfers/.
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
	$current = get_the_ID();
	?>
	<div class="container layout-sidebar section">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'layout-sidebar__main' ); ?>>
			<?php if ( tp_show_featured_image() ) : ?>
				<figure class="featured-image"><?php the_post_thumbnail( 'large', array( 'alt' => tp_thumbnail_alt( get_post() ) ) ); ?></figure>
			<?php endif; ?>
			<?php tp_the_scene( tp_service_scene_name() ); ?>
			<div class="prose entry-content"><?php the_content(); ?></div>
			<p class="cta-inline">
				<a class="btn btn--accent btn--lg" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book this trip', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
				<a class="btn btn--ghost btn--lg" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?> <?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
			</p>
		</article>
		<aside class="layout-sidebar__aside" aria-label="<?php esc_attr_e( 'More services', 'taxi-peninsula' ); ?>">
			<section class="widget widget--book">
				<h2 class="widget-title"><?php esc_html_e( 'Ready to book?', 'taxi-peninsula' ); ?></h2>
				<p><?php esc_html_e( 'Book online in two minutes or call our team.', 'taxi-peninsula' ); ?></p>
				<a class="btn btn--accent btn--block" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book online', 'taxi-peninsula' ); ?></a>
				<a class="btn btn--ghost btn--block" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?> <?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
			</section>
			<section class="widget">
				<h2 class="widget-title"><?php esc_html_e( 'Other services', 'taxi-peninsula' ); ?></h2>
				<ul class="link-list">
					<?php foreach ( get_posts( array( 'post_type' => 'tp_service', 'numberposts' => 12, 'exclude' => array( $current ), 'orderby' => 'menu_order', 'order' => 'ASC' ) ) as $s ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $s ) ); ?>"><?php echo esc_html( get_the_title( $s ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</section>
		</aside>
	</div>
	<?php
endwhile;

get_template_part( 'template-parts/testimonials', null, array( 'limit' => 3 ) );
get_template_part( 'template-parts/cta' );
get_footer();
