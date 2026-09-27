<?php
/**
 * Live trust numbers. Renders nothing until at least two numbers are meaningful.
 *
 * @package TaxiPeninsula
 */

$stats = tp_trust_stats();
if ( count( $stats ) < 2 ) {
	return;
}
?>
<section class="stats" aria-label="<?php esc_attr_e( 'About our service in numbers', 'taxi-peninsula' ); ?>">
	<div class="container">
		<ul class="stats__list">
			<?php foreach ( $stats as $stat ) : ?>
				<li class="stats__item">
					<?php if ( ! empty( $stat[2] ) ) : ?>
						<span class="stats__label"><?php echo esc_html( $stat[1] ); ?></span>
						<span class="stats__value"><?php echo esc_html( $stat[0] ); ?></span>
					<?php else : ?>
						<span class="stats__value"><?php echo esc_html( $stat[0] ); ?></span>
						<span class="stats__label"><?php echo esc_html( $stat[1] ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
