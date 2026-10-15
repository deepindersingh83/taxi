<?php
/**
 * Home section: meet the drivers (only drivers who agreed to be shown).
 *
 * @package TaxiPeninsula
 */

$grid = do_shortcode( '[tp_drivers]' );
if ( ! $grid ) {
	return;
}
?>
<section class="section section--drivers" aria-labelledby="drivers-title">
	<div class="container">
		<h2 id="drivers-title" class="section__title"><?php echo esc_html( tp_home_text( 'drivers' ) ); ?></h2>
		<p class="section__lead"><?php echo esc_html( tp_home_text( 'drivers', 'text' ) ); ?></p>
		<?php echo $grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the shortcode. ?>
	</div>
</section>
