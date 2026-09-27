<?php
/**
 * Template Name: Drive with us (careers)
 *
 * Why drive with you (page content), followed by the application form.
 * Applications are saved under Bookings → Enquiries.
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
			'lead'  => has_excerpt() ? esc_html( get_the_excerpt() ) : esc_html__( 'Rewarding work helping people get where they need to be.', 'taxi-peninsula' ),
		)
	);
	?>
	<div class="container section careers">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry-content page-content careers__content' ); ?>>
			<?php the_content(); ?>
		</article>
		<?php
		if ( ! has_shortcode( get_the_content(), 'tp_driver_apply' ) ) {
			echo do_shortcode( '[tp_driver_apply]' );
		}
		?>
	</div>
	<?php
endwhile;

get_footer();
