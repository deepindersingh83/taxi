<?php
/**
 * "Why passengers choose us" feature grid.
 *
 * @package TaxiPeninsula
 *
 * @var array $args { title?: string }
 */

$title = isset( $args['title'] ) ? $args['title'] : __( 'Why passengers choose us', 'taxi-peninsula' );
$id    = wp_unique_id( 'features-title-' );
?>
<section class="section section--features" aria-labelledby="<?php echo esc_attr( $id ); ?>">
	<div class="container">
		<h2 id="<?php echo esc_attr( $id ); ?>" class="section__title"><?php echo esc_html( $title ); ?></h2>
		<div class="features">
			<?php
			$features = array(
				array( 'wheelchair', __( 'Purpose-built vehicles', 'taxi-peninsula' ), __( 'Rear-entry ramps or hoists and four-point restraints, so you can stay in your own chair.', 'taxi-peninsula' ) ),
				array( 'shield', __( 'Trained, accredited drivers', 'taxi-peninsula' ), __( 'Our drivers are experienced in loading, securing and assisting passengers with a disability.', 'taxi-peninsula' ) ),
				array( 'clock', __( 'Punctual, 24/7', 'taxi-peninsula' ), __( 'Book ahead for appointments and flights, or call us for a same-day pick-up.', 'taxi-peninsula' ) ),
				array( 'heart', __( 'Carers welcome', 'taxi-peninsula' ), __( 'Family members and support workers can travel with you at no extra hassle.', 'taxi-peninsula' ) ),
			);
			foreach ( $features as $f ) :
				?>
				<div class="feature">
					<span class="feature__icon"><?php tp_the_icon( $f[0] ); ?></span>
					<h3 class="feature__title"><?php echo esc_html( $f[1] ); ?></h3>
					<p><?php echo esc_html( $f[2] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
