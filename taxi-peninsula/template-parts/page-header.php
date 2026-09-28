<?php
/**
 * Page title band.
 *
 * @package TaxiPeninsula
 *
 * @var array $args { title: string, lead?: string, meta?: string }
 */

$tp_style = tp_design( 'banner' );
$tp_img   = tp_banner_image_id();
if ( 'image' === $tp_style && ! $tp_img ) {
	$tp_style = 'color';
}
?>
<header class="page-header page-header--<?php echo esc_attr( $tp_style ); ?><?php echo tp_anim_road_in( 'banner' ) ? ' page-header--has-road' : ''; ?>">
	<?php if ( $tp_img ) : ?>
		<?php echo wp_get_attachment_image( $tp_img, 'full', false, array( 'class' => 'page-header__bg', 'alt' => '', 'sizes' => '100vw', 'loading' => 'eager' ) ); ?>
	<?php endif; ?>
	<div class="container">
		<?php tp_breadcrumbs(); ?>
		<h1 class="page-header__title"><?php echo wp_kses_post( $args['title'] ); ?></h1>
		<?php if ( ! empty( $args['lead'] ) ) : ?>
			<div class="page-header__lead"><?php echo wp_kses_post( $args['lead'] ); ?></div>
		<?php endif; ?>
		<?php if ( ! empty( $args['meta'] ) ) : ?>
			<p class="post-meta"><?php echo wp_kses_post( $args['meta'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php if ( tp_anim_road_in( 'banner' ) ) : ?>
		<?php tp_the_road_strip( 'banner' ); ?>
	<?php endif; ?>
</header>
