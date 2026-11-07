<?php
/**
 * Home section: quick price and "Text me this quote".
 *
 * @package TaxiPeninsula
 */

if ( ! function_exists( 'tp_text_quote_available' ) || ! tp_text_quote_available() ) {
	return;
}
?>
<section class="section section--alt" aria-labelledby="tq-home-title">
	<div class="container container--narrow">
		<h2 id="tq-home-title" class="section__title"><?php echo esc_html( tp_home_text( 'textquote' ) ); ?></h2>
		<?php if ( tp_home_text( 'textquote', 'text' ) ) : ?>
			<p class="section__lead"><?php echo esc_html( tp_home_text( 'textquote', 'text' ) ); ?></p>
		<?php endif; ?>
		<?php get_template_part( 'template-parts/text-quote', null, array( 'heading' => false ) ); ?>
	</div>
</section>
