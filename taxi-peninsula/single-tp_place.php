<?php
/**
 * Place page, e.g. /wheelchair-taxi-to/frankston-hospital/ or
 * /wheelchair-taxi-from/frankston-station/. See inc/places.php.
 *
 * @package TaxiPeninsula
 */

get_header();

while ( have_posts() ) :
	the_post();
	$tp_id   = get_the_ID();
	$tp_m    = tp_place_meta( $tp_id );
	$tp_name = get_the_title();
	$tp_from = 'from' === $tp_m['kind'];
	$tp_addr = $tp_m['address'] ?: $tp_name;
	get_template_part(
		'template-parts/page-header',
		null,
		array(
			'title' => esc_html( tp_place_title( $tp_id ) ),
			'lead'  => has_excerpt() ? esc_html( get_the_excerpt() ) : esc_html( $tp_m['address'] ),
		)
	);
	$tp_times = tp_place_travel_times( $tp_id );
	$tp_faq   = tp_place_faq( $tp_id );
	$tp_tips  = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $tp_m['tips'] ) ) );
	?>
	<div class="container layout-sidebar section place-page">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'layout-sidebar__main' ); ?>>
			<?php if ( tp_setting( 'quick_answers' ) ) : ?>
				<div class="quick-answer" role="note"><p class="quick-answer__label"><?php esc_html_e( 'In short', 'taxi-peninsula' ); ?></p><p class="quick-answer__text"><?php echo esc_html( tp_place_quick_answer( $tp_id ) ); ?></p></div>
			<?php endif; ?>

			<?php if ( tp_show_featured_image() ) : ?>
				<figure class="place-page__photo"><?php the_post_thumbnail( 'large', array( 'loading' => 'eager' ) ); ?>
					<?php if ( get_the_post_thumbnail_caption() ) : ?><figcaption><?php the_post_thumbnail_caption(); ?></figcaption><?php endif; ?>
				</figure>
			<?php endif; ?>

			<div class="local-service__book card">
				<h2>
					<?php
					echo esc_html(
						$tp_from
							/* translators: %s: place */
							? sprintf( __( 'Book a pick-up from %s', 'taxi-peninsula' ), $tp_name )
							/* translators: %s: place */
							: sprintf( __( 'Book a trip to %s', 'taxi-peninsula' ), $tp_name )
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
							? sprintf( __( 'Where do we pick you up at %s?', 'taxi-peninsula' ), $tp_name )
							/* translators: %s: place */
							: sprintf( __( 'Where do we drop you off at %s?', 'taxi-peninsula' ), $tp_name )
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
					<?php if ( $tp_m['parking'] ) : ?>
						<div><dt><?php tp_the_icon( 'clock' ); ?> <?php esc_html_e( 'Parking & waiting', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( $tp_m['parking'] ); ?></dd></div>
					<?php endif; ?>
					<?php if ( $tp_m['address'] ) : ?>
						<div><dt><?php tp_the_icon( 'book' ); ?> <?php esc_html_e( 'Address', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( $tp_m['address'] ); ?></dd></div>
					<?php endif; ?>
				</dl>

				<?php if ( $tp_tips ) : ?>
					<h2><?php /* translators: %s: place */ echo esc_html( sprintf( __( 'Tips from our drivers for %s', 'taxi-peninsula' ), $tp_name ) ); ?></h2>
					<ul>
						<?php foreach ( $tp_tips as $tp_tip ) : ?>
							<li><?php echo esc_html( $tp_tip ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php the_content(); ?>

				<?php if ( $tp_times ) : ?>
					<div class="area-times">
						<h2>
							<?php
							echo esc_html(
								$tp_from
									/* translators: %s: place */
									? sprintf( __( 'How long is the trip home from %s?', 'taxi-peninsula' ), $tp_name )
									/* translators: %s: place */
									: sprintf( __( 'How long does it take to get to %s?', 'taxi-peninsula' ), $tp_name )
							);
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

				<?php echo tp_review_widget( array_filter( array( $tp_name, $tp_m['area'] ) ), $tp_m['area'] ?: $tp_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>

				<h2><?php /* translators: %s: place */ echo esc_html( sprintf( __( 'Questions about wheelchair taxis and %s', 'taxi-peninsula' ), $tp_name ) ); ?></h2>
				<div class="faq">
					<?php foreach ( $tp_faq as $qa ) : ?>
						<?php tp_faq_schema_items( array( 'q' => $qa[0], 'a' => $qa[1] ) ); ?>
						<details class="faq__item"><summary><?php echo esc_html( $qa[0] ); ?></summary><div class="faq__answer prose"><?php echo wp_kses_post( wpautop( esc_html( $qa[1] ) ) ); ?></div></details>
					<?php endforeach; ?>
				</div>

				<?php if ( $tp_m['official'] ) : ?>
					<p><?php /* translators: %s: place */ ?><a href="<?php echo esc_url( $tp_m['official'] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( sprintf( __( 'Visiting hours, maps and contacts for %s (official website)', 'taxi-peninsula' ), $tp_name ) ); ?></a></p>
				<?php endif; ?>
			</div>
			<?php echo tp_updated_line( $tp_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<p class="cta-inline">
				<a class="btn btn--accent btn--lg" href="<?php echo esc_url( add_query_arg( $tp_from ? 'pickup' : 'dropoff', rawurlencode( $tp_addr ), tp_booking_page_url() ) ); ?>"><?php esc_html_e( 'Book online', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
				<a class="btn btn--ghost btn--lg" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?> <?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
			</p>
		</article>
		<?php
		$tp_others = get_posts( array( 'post_type' => 'tp_place', 'numberposts' => 12, 'post__not_in' => array( $tp_id ), 'orderby' => 'title', 'order' => 'ASC' ) );
		?>
		<aside class="layout-sidebar__aside" aria-label="<?php esc_attr_e( 'Other places', 'taxi-peninsula' ); ?>">
			<?php if ( $tp_m['area'] && get_page_by_path( sanitize_title( $tp_m['area'] ), OBJECT, 'tp_area' ) ) : ?>
				<section class="widget">
					<h2 class="widget-title"><?php esc_html_e( 'Suburb', 'taxi-peninsula' ); ?></h2>
					<ul class="link-list"><li><a href="<?php echo esc_url( get_permalink( get_page_by_path( sanitize_title( $tp_m['area'] ), OBJECT, 'tp_area' ) ) ); ?>"><?php /* translators: %s: suburb */ echo esc_html( sprintf( __( 'Wheelchair taxi %s', 'taxi-peninsula' ), $tp_m['area'] ) ); ?></a></li></ul>
				</section>
			<?php endif; ?>
			<?php if ( $tp_others ) : ?>
				<section class="widget">
					<h2 class="widget-title"><?php esc_html_e( 'Other places we go', 'taxi-peninsula' ); ?></h2>
					<ul class="link-list">
						<?php foreach ( $tp_others as $o ) : ?>
							<li><a href="<?php echo esc_url( get_permalink( $o ) ); ?>"><?php echo esc_html( tp_place_title( $o->ID ) ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>
		</aside>
	</div>
	<?php
endwhile;

get_template_part( 'template-parts/cta' );
get_footer();
