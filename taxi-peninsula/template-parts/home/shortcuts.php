<?php
/**
 * Home section: audience shortcuts right under the hero.
 *
 * @package TaxiPeninsula
 */

$panels = tp_who_panels();
if ( ! $panels ) {
	return;
}
?>
<nav class="shortcuts" aria-label="<?php esc_attr_e( 'Find what you need', 'taxi-peninsula' ); ?>">
	<div class="container">
		<ul class="shortcuts__list">
			<?php foreach ( $panels as $p ) : ?>
				<li>
					<a class="shortcut" href="<?php echo esc_url( $p['url'] ); ?>">
						<?php tp_the_spot( $p['icon'], 'shortcut__icon' ); ?>
						<span class="shortcut__label"><?php echo esc_html( $p['short'] ); ?></span>
						<?php tp_the_icon( 'arrow', 'shortcut__arrow' ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</nav>
