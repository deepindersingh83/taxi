<?php
/**
 * Service card.
 *
 * @package TaxiPeninsula
 *
 * @var array $args { title, text, icon, url }
 */

?>
<article class="service<?php echo $args['url'] ? ' service--link' : ''; ?>">
	<span class="service__icon"><?php tp_the_icon( $args['icon'] ); ?></span>
	<h3 class="service__title">
		<?php if ( $args['url'] ) : ?>
			<a href="<?php echo esc_url( $args['url'] ); ?>"><?php echo esc_html( $args['title'] ); ?></a>
		<?php else : ?>
			<?php echo esc_html( $args['title'] ); ?>
		<?php endif; ?>
	</h3>
	<p><?php echo esc_html( $args['text'] ); ?></p>
</article>
