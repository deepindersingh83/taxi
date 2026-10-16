<?php
/**
 * Council-area page (/council/{slug}/) or the list of council areas (/council/).
 * See inc/councils.php.
 *
 * @package TaxiPeninsula
 */

$tp_c = tp_council_current();
get_header();

if ( '_all' === $tp_c[0] ) :
	get_template_part( 'template-parts/page-header', null, array( 'title' => esc_html__( 'Council areas we cover', 'taxi-peninsula' ), 'lead' => esc_html__( 'Wheelchair accessible taxis in every suburb of these council areas.', 'taxi-peninsula' ) ) );
	?>
	<div class="container section">
		<ul class="council-grid">
			<?php foreach ( tp_councils() as $slug => $c ) : ?>
				<li class="card council-card">
					<h2><a href="<?php echo esc_url( tp_council_url( $slug ) ); ?>"><?php echo esc_html( $c[0] ); ?></a></h2>
					<p><?php echo esc_html( implode( ', ', wp_list_pluck( $c[1], 'post_title' ) ) ); ?></p>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
else :
	list( $tp_slug, $tp_name, $tp_areas ) = $tp_c;
	$tp_names = wp_list_pluck( $tp_areas, 'post_title' );
	$tp_main  = $tp_names[0];
	get_template_part(
		'template-parts/page-header',
		null,
		array(
			'title' => esc_html( tp_council_title( $tp_name ) ),
			/* translators: %s: suburbs */
			'lead'  => esc_html( sprintf( __( 'Accessible taxis for %s.', 'taxi-peninsula' ), implode( ', ', array_slice( $tp_names, 0, 5 ) ) . ( count( $tp_names ) > 5 ? __( ' and more', 'taxi-peninsula' ) : '' ) ) ),
		)
	);
	$tp_faq = tp_council_faq( $tp_name, $tp_areas );
	?>
	<div class="container layout-sidebar section council-page">
		<article class="layout-sidebar__main">
			<?php tp_the_quick_answer( $tp_name ); ?>
			<div class="local-service__book card">
				<h2>
					<?php
					/* translators: %s: council */
					echo esc_html( sprintf( __( 'Book a pick-up in %s', 'taxi-peninsula' ), $tp_name ) );
					?>
				</h2>
				<?php get_template_part( 'template-parts/quickbook', null, array( 'context' => 'section' ) ); ?>
			</div>
			<div class="prose entry-content">
				<h2>
					<?php
					/* translators: %s: council */
					echo esc_html( sprintf( __( 'Which suburbs in %s do you cover?', 'taxi-peninsula' ), $tp_name ) );
					?>
				</h2>
				<ul class="chips">
					<?php foreach ( $tp_areas as $a ) : ?>
						<li><a class="chip" href="<?php echo esc_url( get_permalink( $a ) ); ?>"><?php echo esc_html( get_the_title( $a ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
				<?php
				$tp_map   = tp_area_map( $tp_names, $tp_main );
				$tp_times = tp_area_travel_times( $tp_main );
				?>
				<?php if ( $tp_map || $tp_times ) : ?>
					<div class="area-where">
						<?php echo $tp_map; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in tp_area_map(). ?>
						<?php if ( $tp_times ) : ?>
							<div class="area-times">
								<h2>
									<?php
									/* translators: %s: suburb */
									echo esc_html( sprintf( __( 'How long does it take to get places from %s?', 'taxi-peninsula' ), $tp_main ) );
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

				<h2>
					<?php
					/* translators: %s: council */
					echo esc_html( sprintf( __( 'What trips can I book in %s?', 'taxi-peninsula' ), $tp_name ) );
					?>
				</h2>
				<ul class="service-links">
					<?php foreach ( tp_services_list() as $s ) : ?>
						<li><?php tp_the_icon( $s['icon'] ); ?><?php if ( $s['url'] ) : ?><a href="<?php echo esc_url( $s['url'] ); ?>"><?php echo esc_html( $s['title'] ); ?></a><?php else : ?><span><?php echo esc_html( $s['title'] ); ?></span><?php endif; ?></li>
					<?php endforeach; ?>
				</ul>

				<?php echo tp_review_widget( array_merge( array( $tp_name ), $tp_names ), $tp_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>

				<h2>
					<?php
					/* translators: %s: council */
					echo esc_html( sprintf( __( 'Questions about wheelchair taxis in %s', 'taxi-peninsula' ), $tp_name ) );
					?>
				</h2>
				<div class="faq">
					<?php foreach ( $tp_faq as $qa ) : ?>
						<?php tp_faq_schema_items( array( 'q' => $qa[0], 'a' => $qa[1] ) ); ?>
						<details class="faq__item"><summary><?php echo esc_html( $qa[0] ); ?></summary><div class="faq__answer prose"><?php echo wp_kses_post( wpautop( esc_html( $qa[1] ) ) ); ?></div></details>
					<?php endforeach; ?>
				</div>
			</div>
			<p class="cta-inline">
				<a class="btn btn--accent btn--lg" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book online', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
				<a class="btn btn--ghost btn--lg" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?> <?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
			</p>
		</article>
		<aside class="layout-sidebar__aside" aria-label="<?php esc_attr_e( 'Other council areas', 'taxi-peninsula' ); ?>">
			<section class="widget">
				<h2 class="widget-title"><?php esc_html_e( 'Other council areas', 'taxi-peninsula' ); ?></h2>
				<ul class="link-list">
					<?php foreach ( tp_councils() as $slug => $c ) : ?>
						<?php
						if ( $slug === $tp_slug ) {
							continue;
						}
						?>
						<li><a href="<?php echo esc_url( tp_council_url( $slug ) ); ?>"><?php echo esc_html( $c[0] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</section>
		</aside>
	</div>
	<?php
endif;

get_template_part( 'template-parts/cta' );
get_footer();
