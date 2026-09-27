<?php
/**
 * Testimonials + Google rating. Renders nothing until real testimonials or a
 * Google rating have been added.
 *
 * @package TaxiPeninsula
 *
 * @var array $args { limit: int, heading: bool }
 */

$limit   = isset( $args['limit'] ) ? (int) $args['limit'] : 3;
$heading = ! isset( $args['heading'] ) || $args['heading'];
$items   = get_posts(
	array(
		'post_type'   => 'tp_testimonial',
		'numberposts' => $limit,
		'orderby'     => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
	)
);
$rating  = trim( (string) tp_setting( 'google_rating' ) );
$count   = (int) tp_setting( 'google_review_count' );
$gurl    = tp_setting( 'google_reviews_url' );

if ( ! $items && '' === $rating ) {
	return;
}
?>
<section class="section section--reviews" aria-labelledby="reviews-title">
	<div class="container">
		<div class="section__head">
			<h2 id="reviews-title" class="section__title"><?php esc_html_e( 'What our passengers say', 'taxi-peninsula' ); ?></h2>
			<?php if ( '' !== $rating ) : ?>
				<?php if ( $gurl ) : ?>
					<a class="google-rating" href="<?php echo esc_url( $gurl ); ?>" target="_blank" rel="noopener">
				<?php else : ?>
					<p class="google-rating">
				<?php endif; ?>
					<span class="google-rating__score"><?php echo esc_html( $rating ); ?></span>
					<span class="stars" aria-hidden="true"><?php echo str_repeat( tp_icon( 'star', 'is-filled' ), max( 0, min( 5, (int) round( (float) $rating ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="google-rating__label">
						<?php
						echo esc_html(
							$count
								/* translators: 1: rating, 2: count */
								? sprintf( _n( 'Rated %1$s out of 5 from %2$d Google review', 'Rated %1$s out of 5 from %2$d Google reviews', $count, 'taxi-peninsula' ), $rating, $count )
								/* translators: %s: rating */
								: sprintf( __( 'Rated %s out of 5 on Google', 'taxi-peninsula' ), $rating )
						);
						?>
					</span>
				<?php echo $gurl ? '</a>' : '</p>'; ?>
			<?php endif; ?>
		</div>

		<?php if ( $items ) : ?>
			<div class="testimonials">
				<?php
				foreach ( $items as $t ) :
					$stars  = max( 0, min( 5, (int) get_post_meta( $t->ID, '_tp_rating', true ) ) );
					$detail = get_post_meta( $t->ID, '_tp_detail', true );
					?>
					<figure class="testimonial">
						<?php if ( $stars ) : ?>
							<p class="stars" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: stars */ __( '%d out of 5 stars', 'taxi-peninsula' ), $stars ) ); ?>">
								<?php echo str_repeat( tp_icon( 'star', 'is-filled' ), $stars ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</p>
						<?php endif; ?>
						<blockquote><?php echo wp_kses_post( wpautop( $t->post_content ) ); ?></blockquote>
						<figcaption>
							<strong><?php echo esc_html( get_the_title( $t ) ); ?></strong>
							<?php if ( $detail ) : ?>
								<span><?php echo esc_html( $detail ); ?></span>
							<?php endif; ?>
						</figcaption>
					</figure>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( $gurl ) : ?>
			<p class="section__more"><a class="link-arrow" href="<?php echo esc_url( $gurl ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Read or leave a review on Google', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a></p>
		<?php endif; ?>
	</div>
</section>
