<?php
/**
 * Generated local page: a service in an area, e.g.
 * /wheelchair-taxi/mornington/airport-transfers/ (see inc/local-pages.php).
 *
 * @package TaxiPeninsula
 */

list( $tp_area, $tp_service ) = tp_local_current();
$tp_a     = get_the_title( $tp_area );
$tp_title = tp_local_title( $tp_area, $tp_service );
$tp_faq   = tp_local_faq( $tp_area, $tp_service );
$tp_times = tp_area_travel_times( $tp_a );

get_header();
get_template_part(
	'template-parts/page-header',
	null,
	array(
		'title' => esc_html( $tp_title ),
		'lead'  => has_excerpt( $tp_service ) ? esc_html( get_the_excerpt( $tp_service ) ) : '',
	)
);
?>
<div class="container layout-sidebar section local-service">
	<article class="layout-sidebar__main">
		<div class="local-service__book card">
			<h2>
				<?php
				/* translators: %s: suburb */
				echo esc_html( sprintf( __( 'Book a pick-up in %s', 'taxi-peninsula' ), $tp_a ) );
				?>
			</h2>
			<?php
			get_template_part(
				'template-parts/quickbook',
				null,
				array(
					'context' => 'section',
					'pickup'  => $tp_a,
					'dropoff' => tp_local_suggested_dropoff( $tp_service ),
				)
			);
			?>
		</div>

		<div class="prose entry-content">
			<p>
				<?php
				/* translators: 1: service (lower case) 2: suburb 3: site name */
				echo esc_html( sprintf( __( 'Looking for %1$s from %2$s? %3$s picks up from any address in %2$s in a wheelchair accessible taxi with a ramp or hoist, four-point wheelchair restraints and a driver trained to help you in and out safely.', 'taxi-peninsula' ), strtolower( get_the_title( $tp_service ) ), $tp_a, get_bloginfo( 'name' ) ) );
				?>
			</p>
			<?php $tp_local_text = trim( (string) get_post_meta( $tp_area->ID, '_tp_local', true ) ); ?>
			<?php if ( $tp_local_text ) : ?>
				<?php echo wp_kses_post( wpautop( esc_html( $tp_local_text ) ) ); ?>
			<?php elseif ( has_excerpt( $tp_area ) ) : ?>
				<p><?php echo esc_html( get_the_excerpt( $tp_area ) ); ?></p>
			<?php endif; ?>

			<?php $tp_map = tp_area_map( tp_service_areas(), $tp_a ); ?>
			<?php if ( $tp_map || $tp_times ) : ?>
				<div class="area-where">
					<?php echo $tp_map; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in tp_area_map(). ?>
					<?php if ( $tp_times ) : ?>
						<div class="area-times">
							<h2>
								<?php
								/* translators: %s: suburb */
								echo esc_html( sprintf( __( 'Travel times from %s', 'taxi-peninsula' ), $tp_a ) );
								?>
							</h2>
							<ul class="area-times__list">
								<?php foreach ( $tp_times as $t ) : ?>
									<?php /* translators: %d: minutes */ ?>
									<li><span><?php echo esc_html( $t[0] ); ?></span><strong><?php echo esc_html( sprintf( __( 'about %d min', 'taxi-peninsula' ), $t[1] ) ); ?></strong></li>
								<?php endforeach; ?>
							</ul>
							<p class="area-times__note"><?php esc_html_e( 'A rough guide without traffic. We allow extra time for loading your wheelchair.', 'taxi-peninsula' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php get_template_part( 'template-parts/service', 'details', array( 'id' => $tp_service->ID ) ); ?>

			<?php if ( $tp_faq ) : ?>
				<h2>
					<?php
					/* translators: %s: page title */
					echo esc_html( sprintf( __( 'Questions about %s', 'taxi-peninsula' ), strtolower( $tp_title ) ) );
					?>
				</h2>
				<div class="faq">
					<?php foreach ( $tp_faq as $qa ) : ?>
						<?php tp_faq_schema_items( array( 'q' => $qa[0], 'a' => $qa[1] ) ); ?>
						<details class="faq__item"><summary><?php echo esc_html( $qa[0] ); ?></summary><div class="faq__answer prose"><?php echo wp_kses_post( wpautop( esc_html( $qa[1] ) ) ); ?></div></details>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<p class="cta-inline">
			<a class="btn btn--accent btn--lg" href="<?php echo esc_url( add_query_arg( 'pickup', rawurlencode( $tp_a ), tp_booking_page_url() ) ); ?>"><?php esc_html_e( 'Book online', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
			<a class="btn btn--ghost btn--lg" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?> <?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
		</p>
	</article>

	<aside class="layout-sidebar__aside" aria-label="<?php esc_attr_e( 'Related pages', 'taxi-peninsula' ); ?>">
		<section class="widget">
			<h2 class="widget-title">
				<?php
				/* translators: %s: suburb */
				echo esc_html( sprintf( __( 'More from %s', 'taxi-peninsula' ), $tp_a ) );
				?>
			</h2>
			<ul class="service-links">
				<li><?php tp_the_icon( 'pin' ); ?><a href="<?php echo esc_url( get_permalink( $tp_area ) ); ?>"><?php /* translators: %s: suburb */ echo esc_html( sprintf( __( 'Wheelchair taxi %s', 'taxi-peninsula' ), $tp_a ) ); ?></a></li>
				<?php foreach ( get_posts( array( 'post_type' => 'tp_service', 'numberposts' => 12, 'exclude' => array( $tp_service->ID ), 'orderby' => 'menu_order', 'order' => 'ASC' ) ) as $tp_s ) : ?>
					<li><?php tp_the_icon( get_post_meta( $tp_s->ID, '_tp_icon', true ) ?: 'wheelchair' ); ?><a href="<?php echo esc_url( tp_local_url( $tp_area, $tp_s ) ); ?>"><?php echo esc_html( tp_local_title( $tp_area, $tp_s ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</section>
		<section class="widget">
			<h2 class="widget-title">
				<?php
				/* translators: %s: service */
				echo esc_html( sprintf( __( '%s from other areas', 'taxi-peninsula' ), get_the_title( $tp_service ) ) );
				?>
			</h2>
			<ul class="chips chips--sm">
				<?php foreach ( get_posts( array( 'post_type' => 'tp_area', 'numberposts' => 40, 'exclude' => array( $tp_area->ID ), 'orderby' => 'menu_order', 'order' => 'ASC' ) ) as $tp_other ) : ?>
					<li><a class="chip" href="<?php echo esc_url( tp_local_url( $tp_other, $tp_service ) ); ?>"><?php echo esc_html( get_the_title( $tp_other ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</section>
	</aside>
</div>
<script type="application/ld+json"><?php echo wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'Service', 'name' => $tp_title, 'serviceType' => get_the_title( $tp_service ), 'url' => tp_local_url( $tp_area, $tp_service ), 'areaServed' => array( '@type' => 'Place', 'name' => $tp_a . ', VIC' ), 'provider' => array( '@type' => 'LocalBusiness', 'name' => get_bloginfo( 'name' ), 'telephone' => tp_opt( 'phone_link' ) ) ) ); ?></script>
<?php
get_template_part( 'template-parts/testimonials', null, array( 'limit' => 3 ) );
get_template_part( 'template-parts/cta' );
get_footer();
