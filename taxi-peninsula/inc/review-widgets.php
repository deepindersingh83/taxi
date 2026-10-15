<?php
/**
 * Review widgets that show what passengers said about a place: partner
 * pages show reviews mentioning that hospital or home, local pages show
 * reviews mentioning that suburb. Uses your Testimonials and your Google
 * reviews (Google only provides your 5 most relevant reviews).
 *
 * When nothing mentions the place, the widget shows your best general
 * reviews instead, labelled honestly as "What passengers say".
 *
 * Shortcode: [tp_reviews_about terms="Frankston, hospital" limit="3"]
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * All reviews as one list: { text, rating, author, detail, source, url }.
 */
function tp_all_reviews() {
	static $all = null;
	if ( null !== $all ) {
		return $all;
	}
	$all = array();
	foreach ( get_posts( array( 'post_type' => 'tp_testimonial', 'numberposts' => 100, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'DESC' ) ) ) as $t ) {
		$all[] = array(
			'text'   => wp_strip_all_tags( $t->post_content ),
			'rating' => (int) get_post_meta( $t->ID, '_tp_rating', true ),
			'author' => get_the_title( $t ),
			'detail' => (string) get_post_meta( $t->ID, '_tp_detail', true ),
			'source' => 'site',
			'url'    => '',
		);
	}
	$place = function_exists( 'tp_google_place' ) ? tp_google_place() : null;
	foreach ( (array) ( $place['reviews'] ?? array() ) as $r ) {
		if ( '' === trim( $r['text'] ) ) {
			continue;
		}
		$all[] = array(
			'text'   => $r['text'],
			'rating' => (int) $r['rating'],
			'author' => $r['author'],
			'detail' => $r['when'],
			'source' => 'google',
			'url'    => $r['uri'],
		);
	}
	return $all;
}

/**
 * Reviews that mention any of the words (whole words, any case).
 *
 * @param string[] $terms Place names or keywords.
 * @param int      $limit Max reviews.
 */
function tp_reviews_mentioning( array $terms, $limit = 3 ) {
	$terms = array_filter( array_map( 'trim', $terms ), static fn( $t ) => mb_strlen( $t ) >= 3 );
	if ( ! $terms ) {
		return array();
	}
	$re  = '/\b(' . implode( '|', array_map( static fn( $t ) => preg_quote( $t, '/' ), $terms ) ) . ')\b/iu';
	$out = array();
	foreach ( tp_all_reviews() as $r ) {
		if ( $r['rating'] && $r['rating'] < 3 ) {
			continue;
		}
		if ( preg_match( $re, $r['text'] . ' ' . $r['detail'] ) ) {
			$out[] = $r;
		}
	}
	return array_slice( $out, 0, $limit );
}

/**
 * Render the widget.
 *
 * @param string[] $terms Terms to look for.
 * @param string   $place Place name for the heading.
 * @param int      $limit Max reviews.
 * @return string HTML.
 */
function tp_review_widget( array $terms, $place, $limit = 3 ) {
	$items = tp_reviews_mentioning( $terms, $limit );
	/* translators: %s: place */
	$title = sprintf( __( 'What passengers say about trips from %s', 'taxi-peninsula' ), $place );
	if ( ! $items ) {
		$items = array_slice( array_values( array_filter( tp_all_reviews(), static fn( $r ) => ! $r['rating'] || $r['rating'] >= 4 ) ), 0, $limit );
		$title = __( 'What passengers say', 'taxi-peninsula' );
	}
	if ( ! $items ) {
		return '';
	}
	$id = wp_unique_id( 'rw-' );
	ob_start();
	?>
	<section class="review-widget" aria-labelledby="<?php echo esc_attr( $id ); ?>">
		<h2 id="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></h2>
		<div class="review-widget__list">
			<?php foreach ( $items as $r ) : ?>
				<figure class="testimonial review-widget__item">
					<?php if ( $r['rating'] ) : ?>
						<p class="stars" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: stars */ __( '%d out of 5 stars', 'taxi-peninsula' ), $r['rating'] ) ); ?>"><?php echo str_repeat( tp_icon( 'star', 'is-filled' ), max( 0, min( 5, $r['rating'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					<?php endif; ?>
					<blockquote data-clamp><?php echo wp_kses_post( wpautop( esc_html( wp_trim_words( $r['text'], 70 ) ) ) ); ?></blockquote>
					<figcaption>
						<strong><?php echo esc_html( $r['author'] ); ?></strong>
						<span>
							<?php echo esc_html( $r['detail'] ); ?>
							<?php if ( 'google' === $r['source'] ) : ?>
								· <?php if ( $r['url'] ) : ?><a href="<?php echo esc_url( $r['url'] ); ?>" target="_blank" rel="noopener nofollow"><?php esc_html_e( 'Google review', 'taxi-peninsula' ); ?></a><?php else : ?><?php esc_html_e( 'Google review', 'taxi-peninsula' ); ?><?php endif; ?>
							<?php endif; ?>
						</span>
					</figcaption>
				</figure>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

add_shortcode(
	'tp_reviews_about',
	static function ( $atts ) {
		$atts  = shortcode_atts( array( 'terms' => '', 'limit' => 3, 'place' => '' ), $atts, 'tp_reviews_about' );
		$terms = array_map( 'trim', explode( ',', (string) $atts['terms'] ) );
		return tp_review_widget( $terms, $atts['place'] ?: (string) reset( $terms ), max( 1, min( 9, (int) $atts['limit'] ) ) );
	}
);

/**
 * Words to look for on a partner page: its name, a shorter form, and extra keywords.
 */
function tp_venue_review_terms( $id ) {
	$terms = array( get_the_title( $id ) );
	foreach ( explode( ',', (string) get_post_meta( $id, '_tp_v_review_terms', true ) ) as $t ) {
		$terms[] = $t;
	}
	return array_values( array_unique( array_filter( array_map( 'trim', $terms ) ) ) );
}
