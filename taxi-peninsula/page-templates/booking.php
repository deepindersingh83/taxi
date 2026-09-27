<?php
/**
 * Template Name: Book a Taxi
 *
 * Page with the booking form. Any page content is shown above the form.
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
			'lead'  => esc_html__( 'Request a wheelchair accessible taxi anywhere in Melbourne and the Mornington Peninsula.', 'taxi-peninsula' ),
		)
	);
	?>
	<section id="book" class="section section--book">
		<div class="container book-layout">
			<div class="book-layout__intro">
				<?php if ( '' !== trim( get_the_content() ) ) : ?>
					<div class="prose"><?php the_content(); ?></div>
				<?php endif; ?>
				<?php get_template_part( 'template-parts/steps' ); ?>
				<div class="contact-card">
					<p><?php esc_html_e( 'Urgent or same-hour trip?', 'taxi-peninsula' ); ?></p>
					<a class="contact-card__phone" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?><?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
					<a href="<?php echo esc_attr( tp_email_href() ); ?>"><?php echo esc_html( antispambot( tp_opt( 'email' ) ) ); ?></a>
				</div>
			</div>
			<div class="book-layout__form card">
				<?php tp_render_booking_form(); ?>
			</div>
		</div>
	</section>
	<?php
endwhile;

get_footer();
