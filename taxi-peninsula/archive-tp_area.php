<?php
/**
 * Areas listing: /wheelchair-taxi/.
 *
 * @package TaxiPeninsula
 */

get_header();
get_template_part(
	'template-parts/page-header',
	null,
	array(
		'title' => esc_html__( 'Areas we cover', 'taxi-peninsula' ),
		'lead'  => esc_html__( 'Wheelchair accessible taxis across Melbourne and the Mornington Peninsula. Not listed? Call us — we travel further on request.', 'taxi-peninsula' ),
	)
);
$areas = get_posts( array( 'post_type' => 'tp_area', 'numberposts' => 200, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
?>
<section class="section">
	<div class="container">
		<ul class="area-grid">
			<?php foreach ( $areas as $a ) : ?>
				<li>
					<a class="area-card" href="<?php echo esc_url( get_permalink( $a ) ); ?>">
						<?php tp_the_icon( 'pin' ); ?>
						<span>
							<?php
							/* translators: %s: suburb */
							echo esc_html( sprintf( __( 'Wheelchair taxi %s', 'taxi-peninsula' ), get_the_title( $a ) ) );
							?>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
<?php
get_template_part( 'template-parts/cta' );
get_footer();
