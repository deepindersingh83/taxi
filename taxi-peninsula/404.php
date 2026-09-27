<?php
/**
 * Not found.
 *
 * @package TaxiPeninsula
 */

get_header();
get_template_part( 'template-parts/page-header', null, array( 'title' => __( 'Page not found', 'taxi-peninsula' ) ) );
?>
<div class="container section prose">
	<p><?php esc_html_e( 'Sorry, we could not find that page. Try a search, or head back home.', 'taxi-peninsula' ); ?></p>
	<?php get_search_form(); ?>
	<p>
		<a class="btn btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to home', 'taxi-peninsula' ); ?></a>
		<a class="btn btn--accent" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book a taxi', 'taxi-peninsula' ); ?></a>
	</p>
</div>
<?php
get_footer();
