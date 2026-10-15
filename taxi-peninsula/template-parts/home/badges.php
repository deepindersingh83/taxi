<?php
/**
 * Hero trust badges and Google rating.
 *
 * @package TaxiPeninsula
 */

$badges = array(
	array( 'shield', __( 'Accredited drivers', 'taxi-peninsula' ) ),
	array( 'heart', __( 'NDIS-friendly', 'taxi-peninsula' ) ),
);
if ( tp_opt( 'show_mptp' ) ) {
	$badges[] = array( 'check', __( 'MPTP accepted', 'taxi-peninsula' ) );
}
$badges[] = tp_opt( 'open_247' ) ? array( 'clock', __( '24/7', 'taxi-peninsula' ) ) : array( 'clock', __( '7 days', 'taxi-peninsula' ) );

list( $rating, $count, $gurl ) = tp_google_rating_summary();
?>
<div class="hero__trust">
	<ul class="hero__badges" aria-label="<?php esc_attr_e( 'Why book with us', 'taxi-peninsula' ); ?>">
		<?php foreach ( $badges as $b ) : ?>
			<li><?php tp_the_icon( $b[0] ); ?><?php echo esc_html( $b[1] ); ?></li>
		<?php endforeach; ?>
	</ul>
	<?php if ( '' !== $rating ) : ?>
		<?php
		$label = $count
			/* translators: 1: rating, 2: number of reviews */
			? sprintf( _n( '%1$s from %2$d Google review', '%1$s from %2$d Google reviews', $count, 'taxi-peninsula' ), $rating, $count )
			/* translators: %s: rating */
			: sprintf( __( '%s on Google', 'taxi-peninsula' ), $rating );
		$tag   = $gurl ? 'a' : 'p';
		?>
		<<?php echo esc_attr( $tag ); ?> class="hero__rating"<?php echo $gurl ? ' href="' . esc_url( $gurl ) . '" target="_blank" rel="noopener"' : ''; ?>>
			<span class="stars" aria-hidden="true"><?php echo str_repeat( tp_icon( 'star', 'is-filled' ), max( 0, min( 5, (int) round( (float) $rating ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
			<span><?php echo esc_html( $label ); ?></span>
		</<?php echo esc_attr( $tag ); ?>>
	<?php endif; ?>
</div>
