<?php
/**
 * Service card.
 *
 * @package TaxiPeninsula
 *
 * @var array $args { title, text, icon, url, price? }
 */

$price = isset( $args['price'] ) && '' !== (string) $args['price'] ? (float) $args['price'] : 0;
?>
<article class="service<?php echo $args['url'] ? ' service--link' : ''; ?>">
	<?php tp_the_spot( $args['icon'], 'service__icon' ); ?>
	<h3 class="service__title">
		<?php if ( $args['url'] ) : ?>
			<a href="<?php echo esc_url( $args['url'] ); ?>"><?php echo esc_html( $args['title'] ); ?></a>
		<?php else : ?>
			<?php echo esc_html( $args['title'] ); ?>
		<?php endif; ?>
	</h3>
	<p><?php echo esc_html( $args['text'] ); ?></p>
	<?php if ( $price || ! empty( $args['more'] ) ) : ?>
		<p class="service__foot">
			<?php if ( $price ) : ?>
				<?php /* translators: %s: price */ ?>
				<span class="service__price"><?php echo esc_html( sprintf( __( 'From %s', 'taxi-peninsula' ), '$' . number_format_i18n( $price, floor( $price ) == $price ? 0 : 2 ) ) ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $args['more'] ) ) : ?>
				<?php if ( $args['url'] ) : ?>
					<a class="link-arrow service__more" href="<?php echo esc_url( $args['url'] ); ?>" aria-hidden="true" tabindex="-1"><?php esc_html_e( 'Learn more', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
				<?php else : ?>
					<a class="link-arrow service__more" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book this trip', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
				<?php endif; ?>
			<?php endif; ?>
		</p>
	<?php endif; ?>
</article>
