<?php
/**
 * Home section: fleet showcase (up to three published vehicles).
 *
 * @package TaxiPeninsula
 */

$vehicles = array_slice( tp_fleet_vehicles(), 0, 3 );
if ( ! $vehicles ) {
	return;
}
$access = tp_content_fields()['tp_fleet']['access'][2];
$page   = tp_page_url_by_template( 'fleet' );
?>
<section class="section section--fleet" aria-labelledby="fleet-title">
	<div class="container">
		<div class="section__head">
			<div>
				<h2 id="fleet-title" class="section__title"><?php echo esc_html( tp_home_text( 'fleet' ) ); ?></h2>
				<p class="section__lead"><?php echo esc_html( tp_home_text( 'fleet', 'text' ) ); ?></p>
			</div>
			<?php if ( $page ) : ?>
				<a class="link-arrow" href="<?php echo esc_url( $page ); ?>"><?php esc_html_e( 'See our fleet', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
			<?php endif; ?>
		</div>
		<ul class="fleet-show">
			<?php foreach ( $vehicles as $v ) : ?>
				<?php
				$acc    = get_post_meta( $v->ID, '_tp_access', true );
				$chairs = (int) get_post_meta( $v->ID, '_tp_wheelchairs', true );
				$seats  = (int) get_post_meta( $v->ID, '_tp_seats', true );
				$max    = trim( (string) get_post_meta( $v->ID, '_tp_max_chair', true ) );
				?>
				<li class="fleet-show__card">
					<div class="fleet-show__media">
						<?php if ( has_post_thumbnail( $v ) ) : ?>
							<?php echo get_the_post_thumbnail( $v, 'tp-card', array( 'alt' => tp_thumbnail_alt( $v ), 'loading' => 'lazy' ) ); ?>
						<?php else : ?>
							<?php echo tp_illustration( 'wheelchair', 'fleet-show__illo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
						<?php endif; ?>
					</div>
					<div class="fleet-show__body">
						<h3 class="fleet-show__title"><?php echo esc_html( get_the_title( $v ) ); ?></h3>
						<ul class="fleet-show__specs">
							<?php if ( $chairs ) : ?>
								<?php /* translators: %d: wheelchair positions */ ?>
								<li><?php tp_the_icon( 'wheelchair' ); ?><?php echo esc_html( sprintf( _n( '%d wheelchair', '%d wheelchairs', $chairs, 'taxi-peninsula' ), $chairs ) ); ?></li>
							<?php endif; ?>
							<?php if ( $seats ) : ?>
								<?php /* translators: %d: seats */ ?>
								<li><?php tp_the_icon( 'users' ); ?><?php echo esc_html( sprintf( _n( '%d seat', '%d seats', $seats, 'taxi-peninsula' ), $seats ) ); ?></li>
							<?php endif; ?>
							<?php if ( $acc && 'none' !== $acc ) : ?>
								<li><?php tp_the_icon( 'check' ); ?><?php echo esc_html( $access[ $acc ] ?? $acc ); ?></li>
							<?php endif; ?>
							<?php if ( $max ) : ?>
								<?php /* translators: %s: maximum wheelchair size */ ?>
								<li class="fleet-show__max"><?php tp_the_icon( 'shield' ); ?><?php echo esc_html( sprintf( __( 'Chairs up to %s', 'taxi-peninsula' ), $max ) ); ?></li>
							<?php endif; ?>
						</ul>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
