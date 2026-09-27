<?php
/**
 * Home section: content written in the page editor (static front page only).
 *
 * @package TaxiPeninsula
 */

if ( 'page' !== get_option( 'show_on_front' ) ) {
	return;
}
$front = get_post( (int) get_option( 'page_on_front' ) );
if ( ! $front || '' === trim( $front->post_content ) ) {
	return;
}
?>
<section class="section">
	<div class="container entry-content page-content">
		<?php echo apply_filters( 'the_content', $front->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core content filter. ?>
	</div>
</section>
