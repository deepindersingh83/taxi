<?php
/**
 * Home section: booking form.
 *
 * @package TaxiPeninsula
 */

?>
<section id="book" class="section section--book" aria-labelledby="book-title">
	<div class="container book-layout">
		<div class="book-layout__intro">
			<h2 id="book-title" class="section__title"><?php echo esc_html( tp_home_text( 'book' ) ); ?></h2>
			<p><?php echo esc_html( tp_home_text( 'book', 'text' ) ); ?></p>
			<?php get_template_part( 'template-parts/steps' ); ?>
			<div class="contact-card">
				<p><?php esc_html_e( 'Prefer to talk to someone?', 'taxi-peninsula' ); ?></p>
				<a class="contact-card__phone" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?><?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
				<a href="<?php echo esc_attr( tp_email_href() ); ?>"><?php echo esc_html( antispambot( tp_opt( 'email' ) ) ); ?></a>
			</div>
		</div>
		<div class="book-layout__form card">
			<?php tp_render_booking_form(); ?>
		</div>
	</div>
</section>
