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
list( $rating, $count, $gurl ) = tp_google_rating_summary();
$place   = tp_google_place();
$greview = $place ? array_values( array_filter( $place['reviews'], static function ( $r ) { return '' !== trim( $r['text'] ); } ) ) : array();

if ( ! $items && ! $greview && '' === $rating ) {
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

		<?php if ( $greview ) : ?>
			<div class="testimonials testimonials--google">
				<?php foreach ( array_slice( $greview, 0, $limit ) as $r ) : ?>
					<figure class="testimonial">
						<p class="stars" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: stars */ __( '%d out of 5 stars', 'taxi-peninsula' ), $r['rating'] ) ); ?>">
							<?php echo str_repeat( tp_icon( 'star', 'is-filled' ), max( 0, min( 5, $r['rating'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</p>
						<blockquote class="testimonial__text" data-clamp><?php echo wp_kses_post( wpautop( esc_html( $r['text'] ) ) ); ?></blockquote>
						<figcaption>
							<strong>
								<?php if ( $r['author_u'] ) : ?>
									<a href="<?php echo esc_url( $r['author_u'] ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $r['author'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $r['author'] ); ?>
								<?php endif; ?>
							</strong>
							<span>
								<?php echo esc_html( $r['when'] ); ?>
								<?php if ( $r['uri'] ) : ?>
									· <a href="<?php echo esc_url( $r['uri'] ); ?>" target="_blank" rel="noopener nofollow"><?php esc_html_e( 'View on Google', 'taxi-peninsula' ); ?></a>
								<?php endif; ?>
							</span>
						</figcaption>
					</figure>
				<?php endforeach; ?>
			</div>
			<p class="google-attribution">
				<?php if ( tp_setting( 'google_logo_url' ) ) : ?>
					<img src="<?php echo esc_url( tp_setting( 'google_logo_url' ) ); ?>" alt="Google" width="66" height="22" loading="lazy">
				<?php else : ?>
					<span class="google-attribution__text">Google</span>
				<?php endif; ?>
				<span><?php esc_html_e( 'Reviews from Google, shown as written by reviewers.', 'taxi-peninsula' ); ?></span>
			</p>
		<?php endif; ?>

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
