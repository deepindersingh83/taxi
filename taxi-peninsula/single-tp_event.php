<?php
/**
 * Event page, e.g. /events/afl-grand-final/.
 *
 * @package TaxiPeninsula
 */

get_header();

while ( have_posts() ) :
	the_post();
	$tp_id     = get_the_ID();
	$tp_m      = tp_event_meta( $tp_id );
	$tp_start  = tp_event_ts( $tp_id );
	$tp_end    = tp_event_ts( $tp_id, 'end' );
	$tp_past   = tp_event_is_past( $tp_id );
	$tp_points = tp_event_points( $tp_id );
	if ( ! $tp_points && $tp_m['address'] ) {
		$tp_points = array( array( $tp_m['venue'] ?: get_the_title(), $tp_m['address'], '' ) );
	}
	?>
	<section class="event-hero<?php echo has_post_thumbnail() ? ' event-hero--image' : ''; ?>">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'full', array( 'class' => 'event-hero__img', 'alt' => '' ) ); ?>
		<?php endif; ?>
		<div class="container event-hero__inner">
			<p class="eyebrow"><?php esc_html_e( 'Wheelchair taxi to the event', 'taxi-peninsula' ); ?></p>
			<h1><?php the_title(); ?></h1>
			<?php if ( $tp_start ) : ?>
				<p class="event-hero__when"><?php tp_the_icon( 'calendar' ); ?> <time datetime="<?php echo esc_attr( wp_date( 'c', $tp_start ) ); ?>"><?php echo esc_html( wp_date( 'l j F Y, g:i a', $tp_start ) . ( $tp_end ? ' – ' . wp_date( 'g:i a', $tp_end ) : '' ) ); ?></time></p>
			<?php endif; ?>
			<?php if ( $tp_m['venue'] ) : ?>
				<p class="event-hero__where"><?php tp_the_icon( 'pin' ); ?> <?php echo esc_html( $tp_m['venue'] . ( $tp_m['address'] ? ' — ' . $tp_m['address'] : '' ) ); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<div class="container section event-body">
		<?php if ( $tp_past ) : ?>
			<div class="notice notice--info" role="status">
				<p><strong><?php esc_html_e( 'This event has finished.', 'taxi-peninsula' ); ?></strong> <a href="<?php echo esc_url( get_post_type_archive_link( 'tp_event' ) ); ?>"><?php esc_html_e( 'See upcoming events', 'taxi-peninsula' ); ?></a></p>
			</div>
		<?php endif; ?>

		<?php if ( '' !== trim( get_the_content() ) ) : ?>
			<div class="prose entry-content"><?php the_content(); ?></div>
		<?php endif; ?>

		<?php if ( ! $tp_past && $tp_points ) : ?>
			<h2><?php esc_html_e( 'Accessible drop-off and pick-up points', 'taxi-peninsula' ); ?></h2>
			<?php if ( $tp_m['arrive'] && $tp_start ) : ?>
				<p class="event-body__tip">
					<?php
					/* translators: 1: minutes 2: time */
					echo esc_html( sprintf( __( 'Plan to arrive about %1$d minutes early — by %2$s. Choose a point and we will fill in the booking for you.', 'taxi-peninsula' ), $tp_m['arrive'], wp_date( 'g:i a', $tp_start - $tp_m['arrive'] * MINUTE_IN_SECONDS ) ) );
					?>
				</p>
			<?php endif; ?>
			<ul class="event-points">
				<?php foreach ( $tp_points as $tp_pt ) : ?>
					<li class="event-point card">
						<h3 class="event-point__name"><?php tp_the_icon( 'pin' ); ?> <?php echo esc_html( $tp_pt[0] ); ?></h3>
						<?php if ( $tp_pt[1] ) : ?>
							<p class="event-point__addr"><?php echo esc_html( $tp_pt[1] ); ?></p>
						<?php endif; ?>
						<?php if ( $tp_pt[2] ) : ?>
							<p class="event-point__note"><?php echo esc_html( $tp_pt[2] ); ?></p>
						<?php endif; ?>
						<p class="event-point__actions">
							<a class="btn btn--accent" href="<?php echo esc_url( tp_event_book_url( $tp_id, 'to', $tp_pt ) ); ?>"><?php esc_html_e( 'Book a trip here', 'taxi-peninsula' ); ?><span class="screen-reader-text"> — <?php echo esc_html( $tp_pt[0] ); ?></span></a>
							<a class="btn btn--ghost" href="<?php echo esc_url( tp_event_book_url( $tp_id, 'home', $tp_pt ) ); ?>"><?php esc_html_e( 'Book a ride home from here', 'taxi-peninsula' ); ?><span class="screen-reader-text"> — <?php echo esc_html( $tp_pt[0] ); ?></span></a>
						</p>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( ! $tp_past && $tp_m['return'] ) : ?>
			<div class="event-return card">
				<h2><?php esc_html_e( 'Going home after', 'taxi-peninsula' ); ?></h2>
				<?php echo wp_kses_post( wpautop( esc_html( $tp_m['return'] ) ) ); ?>
			</div>
		<?php endif; ?>

		<?php if ( ! $tp_past && $tp_m['promo'] && function_exists( 'tp_promo_label' ) && tp_promo_label( $tp_m['promo'] ) ) : ?>
			<p class="event-promo">
				<?php
				/* translators: 1: offer 2: code */
				echo esc_html( sprintf( __( 'Event offer: %1$s with code %2$s — already added when you book from this page.', 'taxi-peninsula' ), tp_promo_label( $tp_m['promo'] ), $tp_m['promo'] ) );
				?>
			</p>
		<?php endif; ?>

		<p class="cta-inline">
			<a class="btn btn--ghost btn--lg" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?> <?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
		</p>

		<?php $tp_more = array_filter( tp_upcoming_events( 4 ), static fn( $e ) => $e->ID !== $tp_id ); ?>
		<?php if ( $tp_more ) : ?>
			<h2><?php esc_html_e( 'More events', 'taxi-peninsula' ); ?></h2>
			<div class="event-grid"><?php echo implode( '', array_map( 'tp_event_card', array_slice( $tp_more, 0, 3 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></div>
		<?php endif; ?>
	</div>
	<?php
endwhile;

get_footer();
