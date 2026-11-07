<?php
/**
 * Route page, e.g. /wheelchair-taxi-to/frankston-hospital/from-frankston/.
 * See inc/place-routes.php.
 *
 * @package TaxiPeninsula
 */

get_header();

$tp_route = tp_route_current();
$tp_id    = $tp_route[0];
$tp_m     = tp_place_meta( $tp_id );
$tp_name  = get_the_title( $tp_id );
$tp_area  = $tp_route[1];
$tp_from  = 'from' === $tp_m['kind'];
$tp_addr  = $tp_m['address'] ?: $tp_name;
$tp_fare  = tp_route_fare( $tp_route );
$tp_faq   = tp_route_faq( $tp_route );
$tp_more  = tp_routes_for_area( $tp_area, $tp_id, 6 );
$tp_aurl  = tp_route_area_url( $tp_area );

get_template_part(
	'template-parts/page-header',
	null,
	array(
		'title' => esc_html( tp_route_title( $tp_route ) ),
		/* translators: 1: minutes 2: km */
		'lead'  => esc_html( sprintf( __( 'About %1$d minutes, %2$s km. Ramp and hoist vans with trained drivers, door to door.', 'taxi-peninsula' ), $tp_route[2], number_format_i18n( $tp_route[3], 1 ) ) ),
	)
);
?>
<div class="container layout-sidebar section place-page route-page">
	<article class="layout-sidebar__main">
		<ul class="route-facts" aria-label="<?php esc_attr_e( 'Trip at a glance', 'taxi-peninsula' ); ?>">
			<li><span><?php esc_html_e( 'Travel time', 'taxi-peninsula' ); ?></span><strong><?php /* translators: %d: minutes */ echo esc_html( sprintf( __( 'about %d min', 'taxi-peninsula' ), $tp_route[2] ) ); ?></strong></li>
			<li><span><?php esc_html_e( 'Distance', 'taxi-peninsula' ); ?></span><strong><?php echo esc_html( number_format_i18n( $tp_route[3], 1 ) . ' km' ); ?></strong></li>
			<?php if ( $tp_fare ) : ?>
				<li><span><?php esc_html_e( 'Usual fare', 'taxi-peninsula' ); ?></span><strong><?php echo esc_html( $tp_fare ); ?></strong></li>
			<?php endif; ?>
		</ul>
		<p class="route-facts__note"><?php esc_html_e( 'A guide based on the usual driving route without traffic. The fare depends on the exact address, time and waiting; MPTP members pay half up to the subsidy limit.', 'taxi-peninsula' ); ?></p>

		<div class="local-service__book card">
			<h2>
				<?php
				echo esc_html(
					$tp_from
						/* translators: 1: place 2: suburb */
						? sprintf( __( 'Book a pick-up from %1$s to %2$s', 'taxi-peninsula' ), $tp_name, $tp_area )
						/* translators: 1: suburb 2: place */
						: sprintf( __( 'Book your trip from %1$s to %2$s', 'taxi-peninsula' ), $tp_area, $tp_name )
				);
				?>
			</h2>
			<?php get_template_part( 'template-parts/quickbook', null, $tp_from ? array( 'context' => 'section', 'pickup' => $tp_addr ) : array( 'context' => 'section', 'dropoff' => $tp_addr ) ); ?>
		</div>

		<div class="prose entry-content">
			<h2>
				<?php
				echo esc_html(
					$tp_from
						/* translators: %s: place */
						? sprintf( __( 'Where we pick you up at %s', 'taxi-peninsula' ), $tp_name )
						/* translators: %s: place */
						: sprintf( __( 'Arriving at %s', 'taxi-peninsula' ), $tp_name )
				);
				?>
			</h2>
			<dl class="place-facts">
				<?php if ( $tp_m['spot'] ) : ?>
					<div><dt><?php tp_the_icon( 'pin' ); ?> <?php echo $tp_from ? esc_html__( 'Pick-up point', 'taxi-peninsula' ) : esc_html__( 'Drop-off point', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( $tp_m['spot'] ); ?></dd></div>
				<?php endif; ?>
				<?php if ( $tp_m['entrance'] ) : ?>
					<div><dt><?php tp_the_icon( 'wheelchair' ); ?> <?php esc_html_e( 'Accessible entrance', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( $tp_m['entrance'] ); ?></dd></div>
				<?php endif; ?>
				<?php if ( $tp_m['address'] ) : ?>
					<div><dt><?php tp_the_icon( 'book' ); ?> <?php esc_html_e( 'Address', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( $tp_m['address'] ); ?></dd></div>
				<?php endif; ?>
			</dl>
			<p><a class="link-arrow" href="<?php echo esc_url( get_permalink( $tp_id ) ); ?>"><?php /* translators: %s: place */ echo esc_html( sprintf( __( 'Everything about getting to %s by wheelchair taxi', 'taxi-peninsula' ), $tp_name ) ); ?></a></p>

			<?php if ( ! $tp_from && tp_place_is_medical( $tp_m ) ) : ?>
				<section class="place-home" aria-labelledby="route-home-title">
					<h2 id="route-home-title"><?php /* translators: 1: place 2: suburb */ echo esc_html( sprintf( __( 'Going home from %1$s to %2$s', 'taxi-peninsula' ), $tp_name, $tp_area ) ); ?></h2>
					<p><?php esc_html_e( 'Not sure when you will be discharged? Book a “pick up when ready” trip and tell us when the ward lets you go.', 'taxi-peninsula' ); ?></p>
					<p class="place-home__actions">
						<a class="btn btn--accent" href="<?php echo esc_url( add_query_arg( array( 'pickup' => rawurlencode( $tp_addr ), 'when_ready' => 1 ), tp_booking_page_url() ) . '#book' ); ?>"><?php esc_html_e( 'Book the trip home — pick up when ready', 'taxi-peninsula' ); ?></a>
						<a class="btn btn--ghost" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?> <?php /* translators: %s: phone */ echo esc_html( sprintf( __( 'Ready now? Call %s', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) ); ?></a>
					</p>
				</section>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Questions', 'taxi-peninsula' ); ?></h2>
			<div class="faq">
				<?php foreach ( $tp_faq as $qa ) : ?>
					<?php tp_faq_schema_items( array( 'q' => $qa[0], 'a' => $qa[1] ) ); ?>
					<details class="faq__item"><summary><?php echo esc_html( $qa[0] ); ?></summary><div class="faq__answer prose"><?php echo wp_kses_post( wpautop( esc_html( $qa[1] ) ) ); ?></div></details>
				<?php endforeach; ?>
			</div>

			<?php echo tp_review_widget( array_filter( array( $tp_name, $tp_area ) ), $tp_area ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>

			<?php if ( $tp_more ) : ?>
				<section class="route-links">
					<h2><?php /* translators: %s: suburb */ echo esc_html( sprintf( __( 'Other trips from %s', 'taxi-peninsula' ), $tp_area ) ); ?></h2>
					<ul class="route-links__list">
						<?php foreach ( $tp_more as $r ) : ?>
							<li><a href="<?php echo esc_url( $r[0] ); ?>"><?php echo esc_html( $r[1] ); ?></a> <span><?php /* translators: %d: minutes */ echo esc_html( sprintf( __( 'about %d min', 'taxi-peninsula' ), $r[2] ) ); ?></span></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>
			<?php if ( $tp_aurl ) : ?>
				<p><a class="link-arrow" href="<?php echo esc_url( $tp_aurl ); ?>"><?php /* translators: %s: suburb */ echo esc_html( sprintf( __( 'Wheelchair taxis in %s', 'taxi-peninsula' ), $tp_area ) ); ?></a></p>
			<?php endif; ?>
		</div>
		<p class="cta-inline">
			<a class="btn btn--accent btn--lg" href="<?php echo esc_url( add_query_arg( $tp_from ? 'pickup' : 'dropoff', rawurlencode( $tp_addr ), tp_booking_page_url() ) ); ?>"><?php esc_html_e( 'Book online', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
			<a class="btn btn--ghost btn--lg" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?> <?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
		</p>
	</article>
	<?php $tp_routes = tp_place_routes( $tp_id ); ?>
	<aside class="layout-sidebar__aside" aria-label="<?php esc_attr_e( 'Other routes', 'taxi-peninsula' ); ?>">
		<section class="widget">
			<h2 class="widget-title"><?php /* translators: %s: place */ echo esc_html( sprintf( $tp_from ? __( 'From %s to…', 'taxi-peninsula' ) : __( 'To %s from…', 'taxi-peninsula' ), $tp_name ) ); ?></h2>
			<ul class="link-list">
				<?php foreach ( $tp_routes as $r ) : ?>
					<li><a href="<?php echo esc_url( tp_route_url( $tp_id, $r[0] ) ); ?>"<?php echo $r[0] === $tp_area ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $r[0] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</section>
	</aside>
</div>
<?php
get_template_part( 'template-parts/cta' );
get_footer();
