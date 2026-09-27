<?php
/**
 * Blog that links itself together: reading time, an automatic contents list
 * on long posts, related posts, and suburb ↔ guide links (tag a post with a
 * suburb's name and it appears on that suburb's page).
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * "4 min read".
 */
function tp_reading_time( $post = null ) {
	$post  = get_post( $post );
	$words = str_word_count( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) );
	$mins  = max( 1, (int) ceil( $words / 200 ) );
	/* translators: %d: minutes */
	return sprintf( _n( '%d min read', '%d min read', $mins, 'taxi-peninsula' ), $mins );
}

/**
 * Give every H2 an id and return [content, toc] where toc is [[id, text], …].
 */
function tp_heading_toc( $content ) {
	$toc     = array();
	$used    = array();
	$content = preg_replace_callback(
		'#<h2([^>]*)>(.*?)</h2>#is',
		static function ( $m ) use ( &$toc, &$used ) {
			$text = trim( wp_strip_all_tags( $m[2] ) );
			if ( preg_match( '/\sid=["\']([^"\']+)["\']/', $m[1], $idm ) ) {
				$id    = $idm[1];
				$attrs = $m[1];
			} else {
				$id = 'section-' . ( sanitize_title( $text ) ?: count( $toc ) + 1 );
				while ( isset( $used[ $id ] ) ) {
					$id .= '-2';
				}
				$attrs = $m[1] . ' id="' . esc_attr( $id ) . '"';
			}
			$used[ $id ] = true;
			$toc[]       = array( $id, $text );
			return '<h2' . $attrs . '>' . $m[2] . '</h2>';
		},
		$content
	);
	return array( $content, $toc );
}

/**
 * Contents list at the top of long blog posts (3+ sections).
 */
add_filter( 'the_content', static function ( $content ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	list( $content, $toc ) = tp_heading_toc( $content );
	if ( count( $toc ) < 3 ) {
		return $content;
	}
	$items = '';
	foreach ( $toc as $item ) {
		$items .= sprintf( '<li><a href="#%s">%s</a></li>', esc_attr( $item[0] ), esc_html( $item[1] ) );
	}
	$nav = sprintf(
		'<details class="post-toc" open><summary>%1$s</summary><nav aria-label="%1$s"><ol>%2$s</ol></nav></details>',
		esc_html__( 'In this article', 'taxi-peninsula' ),
		$items
	);
	return $nav . $content;
}, 20 );

/**
 * Posts related by tag, then category, then most recent.
 *
 * @return WP_Post[]
 */
function tp_related_posts( $post = null, $limit = 3 ) {
	$post    = get_post( $post );
	$tags    = wp_get_post_tags( $post->ID, array( 'fields' => 'ids' ) );
	$cats    = wp_get_post_categories( $post->ID );
	$found   = array();
	$exclude = array( $post->ID );

	foreach ( array( array( 'tag__in' => $tags ), array( 'category__in' => $cats ), array() ) as $where ) {
		if ( count( $found ) >= $limit || ( $where && ! reset( $where ) ) ) {
			continue;
		}
		$more = get_posts(
			array_merge(
				array(
					'post_type'           => 'post',
					'numberposts'         => $limit - count( $found ),
					'post__not_in'        => $exclude,
					'ignore_sticky_posts' => true,
				),
				$where
			)
		);
		foreach ( $more as $p ) {
			$found[]   = $p;
			$exclude[] = $p->ID;
		}
	}
	return $found;
}

/**
 * Suburb (area) pages a post is tagged with.
 *
 * @return WP_Post[]
 */
function tp_post_areas( $post = null ) {
	$tags = wp_get_post_tags( get_post( $post )->ID );
	if ( ! $tags ) {
		return array();
	}
	return get_posts(
		array(
			'post_type'   => 'tp_area',
			'numberposts' => 5,
			'post_name__in' => wp_list_pluck( $tags, 'slug' ),
		)
	);
}

/**
 * Blog posts tagged with a suburb (tag slug = the area page's slug, e.g. "frankston").
 *
 * @return WP_Post[]
 */
function tp_area_guides( $area, $limit = 4 ) {
	$area = get_post( $area );
	$tag  = get_term_by( 'slug', $area->post_name, 'post_tag' );
	if ( ! $tag ) {
		$tag = get_term_by( 'name', $area->post_title, 'post_tag' );
	}
	if ( ! $tag ) {
		return array();
	}
	return get_posts(
		array(
			'post_type'   => 'post',
			'numberposts' => $limit,
			'tag_id'      => $tag->term_id,
		)
	);
}
