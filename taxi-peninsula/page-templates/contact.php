<?php
/**
 * Template Name: Contact Us
 *
 * Page content (optional intro), the contact form with your details, a map
 * and the areas you cover.
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
			'lead'  => has_excerpt() ? esc_html( get_the_excerpt() ) : esc_html__( 'Questions, quotes or account enquiries — we are happy to help.', 'taxi-peninsula' ),
		)
	);
	?>
	<div class="container section">
		<?php if ( '' !== trim( get_the_content() ) ) : ?>
			<div class="entry-content page-content"><?php the_content(); ?></div>
		<?php endif; ?>
		<?php
		// Don't show the form twice if the editor already placed it in the content.
		if ( ! has_shortcode( get_the_content(), 'tp_contact_form' ) ) {
			echo tp_contact_shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the shortcode.
		}
		?>
	</div>
	<?php
endwhile;
?>
<section class="section section--areas" aria-labelledby="contact-map">
	<div class="container contact-map">
		<div>
			<h2 id="contact-map" class="section__title"><?php esc_html_e( 'Where we operate', 'taxi-peninsula' ); ?></h2>
			<?php echo do_shortcode( '[tp_areas]' ); ?>
		</div>
		<?php echo do_shortcode( '[tp_map]' ); ?>
	</div>
</section>
<?php
get_footer();
