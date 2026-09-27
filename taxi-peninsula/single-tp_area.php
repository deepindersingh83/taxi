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
