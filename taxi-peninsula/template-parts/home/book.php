<?php
/**
 * Home section: quick booking card (or the full form, if chosen in the Customizer).
 *
 * @package TaxiPeninsula
 */

$full = get_theme_mod( 'tp_home_book_full', false );
?>
<section id="book" class="section section--book<?php echo $full ? '' : ' section--book-quick'; ?>" aria-labelledby="book-title">
	<div class="container book-layout<?php echo $full ? '' : ' book-layout--quick'; ?>">
		<div class="book-layout__intro">
			<h2 id="book-title" class="section__title"><?php echo esc_html( tp_home_text( 'book' ) ); ?></h2>
			<p><?php echo esc_html( tp_home_text( 'book', 'text' ) ); ?></p>
			<?php if ( $full ) : ?>
				<?php get_template_part( 'template-parts/steps' ); ?>
			<?php endif; ?>
			<div class="contact-card">
				<p><?php esc_html_e( 'Prefer to talk to someone?', 'taxi-peninsula' ); ?></p>
				<a class="contact-card__phone" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?><?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
				<a href="<?php echo esc_attr( tp_email_href() ); ?>"><?php echo esc_html( antispambot( tp_opt( 'email' ) ) ); ?></a>
			</div>
		</div>
		<div class="book-layout__form card">
			<?php if ( $full ) : ?>
				<?php tp_render_booking_form(); ?>
			<?php else : ?>
				<?php get_template_part( 'template-parts/quickbook', null, array( 'context' => 'section' ) ); ?>
				<p class="quickbook__hint"><?php esc_html_e( 'Next you add the passenger, wheelchair details and how you will pay — about a minute more.', 'taxi-peninsula' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</section>
