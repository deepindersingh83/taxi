<?php
/**
 * Partner logos (only those with permission ticked).
 *
 * @package TaxiPeninsula
 */

$partners = tp_partners();
if ( ! $partners ) {
	return;
}
?>
<section class="section section--partners" aria-labelledby="partners-title">
	<div class="container">
		<h2 id="partners-title" class="partners__title"><?php esc_html_e( 'Trusted by local organisations', 'taxi-peninsula' ); ?></h2>
		<ul class="partners">
			<?php foreach ( $partners as $p ) : ?>
				<?php $url = get_post_meta( $p->ID, '_tp_url', true ); ?>
				<li class="partner">
					<?php if ( $url ) : ?><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php endif; ?>
					<?php echo get_the_post_thumbnail( $p, 'medium', array( 'alt' => get_the_title( $p ), 'loading' => 'lazy' ) ); ?>
					<?php if ( $url ) : ?></a><?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
