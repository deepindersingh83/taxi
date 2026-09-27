<?php
/**
 * Home section: areas we cover + suburb checker.
 *
 * @package TaxiPeninsula
 */

$areas = tp_service_areas();
if ( ! $areas ) {
	return;
}
$area_links = tp_area_links();
?>
<section id="areas" class="section section--areas" aria-labelledby="areas-title">
	<div class="container">
		<h2 id="areas-title" class="section__title"><?php echo esc_html( tp_home_text( 'areas' ) ); ?></h2>
		<p class="section__lead"><?php echo esc_html( tp_home_text( 'areas', 'text' ) ); ?></p>
		<?php echo do_shortcode( '[tp_suburb_checker]' ); ?>
		<ul class="chips">
			<?php foreach ( $areas as $area ) : ?>
				<?php $link = $area_links[ strtolower( $area ) ] ?? ''; ?>
				<li>
					<?php if ( $link ) : ?>
						<a class="chip" href="<?php echo esc_url( $link ); ?>"><?php tp_the_icon( 'pin' ); ?><?php echo esc_html( $area ); ?></a>
					<?php else : ?>
						<span class="chip"><?php tp_the_icon( 'pin' ); ?><?php echo esc_html( $area ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
