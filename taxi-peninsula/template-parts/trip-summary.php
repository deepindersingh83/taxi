<?php
/**
 * Live trip summary next to (or inside) the booking form. main.js fills it in
 * as the visitor types; without JavaScript it simply isn't shown.
 *
 * @package TaxiPeninsula
 *
 * @var array $args { compact?: bool }
 */

$compact = ! empty( $args['compact'] );
?>
<aside class="trip-summary<?php echo $compact ? ' trip-summary--compact' : ' card'; ?>" data-trip-summary hidden aria-label="<?php esc_attr_e( 'Your trip so far', 'taxi-peninsula' ); ?>">
	<?php if ( ! $compact ) : ?>
		<?php echo tp_illustration( 'wheelchair', 'trip-summary__illo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
	<?php endif; ?>
	<p class="trip-summary__title"><?php esc_html_e( 'Your trip', 'taxi-peninsula' ); ?></p>
	<p class="trip-summary__route">
		<span data-sum="from"><?php esc_html_e( 'Pick-up', 'taxi-peninsula' ); ?></span>
		<span class="trip-summary__arrow" aria-hidden="true">→</span><span class="screen-reader-text"><?php esc_html_e( 'to', 'taxi-peninsula' ); ?></span>
		<span data-sum="to"><?php esc_html_e( 'Drop-off', 'taxi-peninsula' ); ?></span>
	</p>
	<dl class="trip-summary__list">
		<div><dt><?php esc_html_e( 'When', 'taxi-peninsula' ); ?></dt><dd data-sum="when">—</dd></div>
		<div><dt><?php esc_html_e( 'Vehicle', 'taxi-peninsula' ); ?></dt><dd data-sum="vehicle">—</dd></div>
		<div><dt><?php esc_html_e( 'Travelling', 'taxi-peninsula' ); ?></dt><dd data-sum="people">—</dd></div>
		<div data-sum-row="fare" hidden><dt><?php esc_html_e( 'Estimate', 'taxi-peninsula' ); ?></dt><dd data-sum="fare">—</dd></div>
		<div data-sum-row="return" hidden><dt><?php esc_html_e( 'Return', 'taxi-peninsula' ); ?></dt><dd data-sum="return">—</dd></div>
	</dl>
</aside>
