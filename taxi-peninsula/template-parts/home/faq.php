<?php
/**
 * Home section: short FAQ strip (questions ticked "Show on home page", else the first four).
 *
 * @package TaxiPeninsula
 */

$faq = do_shortcode( '[tp_faq featured="yes" limit="4" search="no"]' );
if ( ! $faq ) {
	return;
}
$all = tp_page_url_by_template( 'faq' );
?>
<section class="section section--faqstrip" aria-labelledby="faqstrip-title">
	<div class="container">
		<h2 id="faqstrip-title" class="section__title"><?php echo esc_html( tp_home_text( 'faq' ) ); ?></h2>
		<?php echo $faq; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in shortcode. ?>
		<?php if ( $all ) : ?>
			<p class="faqstrip__more"><a class="link-arrow" href="<?php echo esc_url( $all ); ?>"><?php esc_html_e( 'See all FAQs', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a></p>
		<?php endif; ?>
	</div>
</section>
