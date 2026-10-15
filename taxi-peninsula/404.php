<?php
/**
 * Not found.
 *
 * @package TaxiPeninsula
 */

get_header();
get_template_part( 'template-parts/page-header', null, array( 'title' => __( 'Page not found', 'taxi-peninsula' ) ) );
$tp_links = array_filter(
	array(
		__( 'Book a taxi', 'taxi-peninsula' )       => tp_booking_page_url(),
		__( 'Manage my booking', 'taxi-peninsula' ) => tp_page_url_by_template( 'lookup' ),
		__( 'Our services', 'taxi-peninsula' )      => tp_page_url_by_template( 'services' ),
		__( 'FAQ', 'taxi-peninsula' )               => tp_page_url_by_template( 'faq' ),
		__( 'Contact us', 'taxi-peninsula' )        => tp_page_url_by_template( 'contact' ),
	)
);
?>
<div class="container section">
	<div class="lost">
		<div class="lost__art" aria-hidden="true"><?php echo tp_illustration( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></div>
		<div class="lost__body">
			<h2 class="lost__title"><?php esc_html_e( 'Looks like we took a wrong turn', 'taxi-peninsula' ); ?></h2>
			<p><?php esc_html_e( 'That page has moved or no longer exists. You can still book, check a booking or call us — we are happy to help.', 'taxi-peninsula' ); ?></p>
			<p class="lost__actions">
				<a class="btn btn--accent btn--lg" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book a taxi', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
				<a class="btn btn--ghost btn--lg" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?> <?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
			</p>
			<?php get_search_form(); ?>
			<?php if ( $tp_links ) : ?>
				<h3 class="lost__subtitle"><?php esc_html_e( 'Popular pages', 'taxi-peninsula' ); ?></h3>
				<ul class="lost__links">
					<?php foreach ( $tp_links as $tp_label => $tp_url ) : ?>
						<li><a class="link-arrow" href="<?php echo esc_url( $tp_url ); ?>"><?php echo esc_html( $tp_label ); ?> <?php tp_the_icon( 'arrow' ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php
get_footer();
