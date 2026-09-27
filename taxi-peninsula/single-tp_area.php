<?php
/**
 * Single area page, e.g. /wheelchair-taxi/frankston/.
 *
 * @package TaxiPeninsula
 */

get_header();

while ( have_posts() ) :
	the_post();
	$area = get_the_title();
	get_template_part(
		'template-parts/page-header',
		null,
		array(
			/* translators: %s: suburb */
			'title' => esc_html( sprintf( __( 'Wheelchair taxi %s', 'taxi-peninsula' ), $area ) ),
			'lead'  => has_excerpt() ? esc_html( get_the_excerpt() ) : '',
		)
	);
	$book = add_query_arg( 'pickup', rawurlencode( $area ), tp_booking_page_url() );
	?>
	<div class="container layout-sidebar section">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'layout-sidebar__main' ); ?>>
			<div class="prose entry-content">
				<?php if ( '' !== trim( get_the_content() ) ) : ?>
					<?php the_content(); ?>
				<?php else : ?>
					<p>
						<?php
						/* translators: 1: suburb, 2: site name */
						echo esc_html( sprintf( __( 'Need an accessible ride in %1$s? %2$s provides wheelchair accessible taxis with ramps or hoists, wheelchair restraints and drivers trained to help you in and out safely.', 'taxi-peninsula' ), $area, get_bloginfo( 'name' ) ) );
						?>
					</p>
					<p>
						<?php
						/* translators: %s: suburb */
						echo esc_html( sprintf( __( 'We pick up from homes, hospitals, aged-care residences and day programs in %s and take you anywhere across Melbourne and the Mornington Peninsula — including Melbourne and Avalon airports.', 'taxi-peninsula' ), $area ) );
						?>
					</p>
				<?php endif; ?>

				<?php
				$nearby    = array_filter( array_map( 'trim', explode( "\n", (string) get_post_meta( get_the_ID(), '_tp_nearby', true ) ) ) );
				$local     = trim( (string) get_post_meta( get_the_ID(), '_tp_local', true ) );
				$local_faq = tp_parse_local_faq( get_post_meta( get_the_ID(), '_tp_local_faq', true ) );
				?>
				<?php if ( $nearby ) : ?>
					<h2>
						<?php
						/* translators: %s: suburb */
						echo esc_html( sprintf( __( 'Places we often travel to around %s', 'taxi-peninsula' ), $area ) );
						?>
					</h2>
					<ul class="tick-list">
						<?php foreach ( $nearby as $place ) : ?>
							<li><?php tp_the_icon( 'pin' ); ?><?php echo esc_html( $place ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ( $local ) : ?>
					<h2>
						<?php
						/* translators: %s: suburb */
						echo esc_html( sprintf( __( 'Getting around %s', 'taxi-peninsula' ), $area ) );
						?>
					</h2>
					<?php echo wp_kses_post( wpautop( esc_html( $local ) ) ); ?>
				<?php endif; ?>

				<h2>
					<?php
					/* translators: %s: suburb */
					echo esc_html( sprintf( __( 'Popular trips from %s', 'taxi-peninsula' ), $area ) );
					?>
				</h2>
				<ul class="service-links">
					<?php foreach ( tp_services_list() as $s ) : ?>
						<li>
							<?php tp_the_icon( $s['icon'] ); ?>
							<?php if ( $s['url'] ) : ?>
								<a href="<?php echo esc_url( $s['url'] ); ?>"><?php echo esc_html( $s['title'] ); ?></a>
							<?php else : ?>
								<span><?php echo esc_html( $s['title'] ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php $guides = tp_area_guides( get_the_ID() ); ?>
				<?php if ( $guides ) : ?>
					<h2>
						<?php
						/* translators: %s: suburb */
						echo esc_html( sprintf( __( 'Guides for %s', 'taxi-peninsula' ), $area ) );
						?>
					</h2>
					<ul class="guide-list">
						<?php foreach ( $guides as $g ) : ?>
							<li><?php tp_the_icon( 'book' ); ?><a href="<?php echo esc_url( get_permalink( $g ) ); ?>"><?php echo esc_html( get_the_title( $g ) ); ?></a> <span class="post-meta"><?php echo esc_html( tp_reading_time( $g ) ); ?></span></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ( $local_faq ) : ?>
					<h2>
						<?php
						/* translators: %s: suburb */
						echo esc_html( sprintf( __( 'Questions about travelling in %s', 'taxi-peninsula' ), $area ) );
						?>
					</h2>
					<div class="faq">
						<?php foreach ( $local_faq as $qa ) : ?>
							<?php tp_faq_schema_items( array( 'q' => $qa[0], 'a' => $qa[1] ) ); ?>
							<details class="faq__item"><summary><?php echo esc_html( $qa[0] ); ?></summary><div class="faq__answer prose"><?php echo wp_kses_post( wpautop( esc_html( $qa[1] ) ) ); ?></div></details>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
			<p class="cta-inline">
				<a class="btn btn--accent btn--lg" href="<?php echo esc_url( $book ); ?>">
					<?php
					/* translators: %s: suburb */
					echo esc_html( sprintf( __( 'Book a pick-up in %s', 'taxi-peninsula' ), $area ) );
					?>
					<?php tp_the_icon( 'arrow' ); ?>
				</a>
				<a class="btn btn--ghost btn--lg" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?> <?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
			</p>
		</article>
		<aside class="layout-sidebar__aside" aria-label="<?php esc_attr_e( 'Nearby areas', 'taxi-peninsula' ); ?>">
			<section class="widget">
				<h2 class="widget-title"><?php esc_html_e( 'Other areas we cover', 'taxi-peninsula' ); ?></h2>
				<ul class="chips chips--sm">
					<?php foreach ( get_posts( array( 'post_type' => 'tp_area', 'numberposts' => 40, 'exclude' => array( get_the_ID() ), 'orderby' => 'menu_order', 'order' => 'ASC' ) ) as $a ) : ?>
						<li><a class="chip" href="<?php echo esc_url( get_permalink( $a ) ); ?>"><?php echo esc_html( get_the_title( $a ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</section>
		</aside>
	</div>
	<?php
endwhile;

get_template_part( 'template-parts/testimonials', null, array( 'limit' => 3 ) );
get_template_part( 'template-parts/cta' );
get_footer();
