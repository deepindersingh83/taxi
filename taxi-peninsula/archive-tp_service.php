<?php
/**
 * Services listing: /services/.
 *
 * @package TaxiPeninsula
 */

get_header();
get_template_part(
	'template-parts/page-header',
	null,
	array(
		'title' => esc_html__( 'Our services', 'taxi-peninsula' ),
		'lead'  => esc_html__( 'Reliable wheelchair accessible transport for everyday trips and important appointments.', 'taxi-peninsula' ),
	)
);
?>
<section class="section">
	<div class="container">
		<div class="services">
			<?php foreach ( tp_services_list() as $s ) : ?>
				<?php get_template_part( 'template-parts/service-card', null, $s ); ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php
get_template_part( 'template-parts/cta' );
get_footer();
