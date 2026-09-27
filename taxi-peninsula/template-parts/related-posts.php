<?php
/**
 * After a blog post: suburb links and related articles.
 *
 * @package TaxiPeninsula
 */

$areas   = tp_post_areas();
$related = tp_related_posts( null, 3 );
?>
<?php if ( $areas ) : ?>
	<aside class="post-areas" aria-label="<?php esc_attr_e( 'Suburbs in this article', 'taxi-peninsula' ); ?>">
		<p><strong><?php esc_html_e( 'Travelling in this area?', 'taxi-peninsula' ); ?></strong></p>
		<ul class="chips chips--sm">
			<?php foreach ( $areas as $a ) : ?>
				<li><a class="chip" href="<?php echo esc_url( get_permalink( $a ) ); ?>"><?php tp_the_icon( 'pin' ); ?><?php echo esc_html( sprintf( /* translators: %s: suburb */ __( 'Wheelchair taxi %s', 'taxi-peninsula' ), get_the_title( $a ) ) ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</aside>
<?php endif; ?>

<?php if ( $related ) : ?>
	<section class="related" aria-labelledby="related-title">
		<h2 id="related-title" class="related__title"><?php esc_html_e( 'Keep reading', 'taxi-peninsula' ); ?></h2>
		<div class="post-grid">
			<?php
			global $post;
			$tp_current = $post;
			foreach ( $related as $post ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
				setup_postdata( $post );
				get_template_part( 'template-parts/content', 'card' );
			endforeach;
			$post = $tp_current; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			setup_postdata( $post );
			?>
		</div>
	</section>
<?php endif; ?>
