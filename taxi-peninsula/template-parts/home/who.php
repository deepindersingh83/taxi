<?php
/**
 * Home section: "Who we help" audience panels.
 *
 * @package TaxiPeninsula
 */

$panels = tp_who_panels();
if ( ! $panels ) {
	return;
}
?>
<section class="section" aria-labelledby="who-title">
	<div class="container">
		<h2 id="who-title" class="section__title"><?php echo esc_html( tp_home_text( 'who' ) ); ?></h2>
		<p class="section__lead"><?php echo esc_html( tp_home_text( 'who', 'text' ) ); ?></p>
		<ul class="who">
			<?php foreach ( $panels as $p ) : ?>
				<li class="who__panel">
					<span class="who__icon"><?php tp_the_icon( $p['icon'] ); ?></span>
					<h3 class="who__title"><?php echo esc_html( $p['title'] ); ?></h3>
					<p><?php echo esc_html( $p['text'] ); ?></p>
					<a class="who__link link-arrow" href="<?php echo esc_url( $p['url'] ); ?>"><?php echo esc_html( $p['link'] ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
