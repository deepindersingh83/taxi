<?php
/**
 * Post card used in blog listings.
 *
 * @package TaxiPeninsula
 */

?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-card' ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="post-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
			<?php the_post_thumbnail( 'tp-card', array( 'alt' => '' ) ); ?>
		</a>
	<?php endif; ?>
	<div class="post-card__body">
		<p class="post-meta">
			<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
			<?php
			$cats = get_the_category();
			if ( $cats ) :
				?>
				<span aria-hidden="true">·</span> <span><?php echo esc_html( $cats[0]->name ); ?></span>
			<?php endif; ?>
			<span aria-hidden="true">·</span> <span><?php echo esc_html( tp_reading_time() ); ?></span>
		</p>
		<h3 class="post-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<div class="post-card__excerpt"><?php the_excerpt(); ?></div>
		<a class="link-arrow" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1"><?php esc_html_e( 'Read more', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
	</div>
</article>
