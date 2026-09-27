<?php
/**
 * Page title band.
 *
 * @package TaxiPeninsula
 *
 * @var array $args { title: string, lead?: string, meta?: string }
 */

?>
<header class="page-header">
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
</header>
