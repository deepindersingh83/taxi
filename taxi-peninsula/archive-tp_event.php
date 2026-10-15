<?php
/**
 * Upcoming events: /events/.
 *
 * @package TaxiPeninsula
 */

get_header();
get_template_part(
	'template-parts/page-header',
	null,
	array(
		'title' => esc_html__( 'Wheelchair taxis to events', 'taxi-peninsula' ),
		'lead'  => esc_html__( 'Concerts, the footy, festivals and the Christmas lights — book an accessible taxi to the right gate and home again.', 'taxi-peninsula' ),
	)
);
?>
<div class="container section">
	<?php if ( have_posts() ) : ?>
		<div class="event-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				echo tp_event_card( get_post() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
			endwhile;
			?>
		</div>
	<?php else : ?>
		<p class="empty-note">
			<?php
			/* translators: %s: phone */
			echo esc_html( sprintf( __( 'No events listed right now. Going somewhere special? Book online or call %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) );
			?>
		</p>
		<p><a class="btn btn--accent" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book a taxi', 'taxi-peninsula' ); ?></a></p>
	<?php endif; ?>
</div>
<?php
get_footer();
