<?php
/**
 * Home section: services.
 *
 * @package TaxiPeninsula
 */

?>
<section id="services" class="section" aria-labelledby="services-title">
	<div class="container">
		<h2 id="services-title" class="section__title"><?php echo esc_html( tp_home_text( 'services' ) ); ?></h2>
		<p class="section__lead"><?php echo esc_html( tp_home_text( 'services', 'text' ) ); ?></p>
		<div class="services">
			<?php foreach ( tp_services_list() as $s ) : ?>
				<?php get_template_part( 'template-parts/service-card', null, $s ); ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
