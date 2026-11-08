<?php
/**
 * Content pack (v2.14): ready-written pages for every suburb and town we
 * cover, hospitals, airports, ferry terminals, stations, shopping centres,
 * hotels, wineries and venues, and the services.
 *
 * Nothing is added until someone with page-editing rights clicks
 * "Add the new pages" (Dashboard notice, or Website content → Place pages).
 * It never overwrites your own writing: existing pages only get the parts
 * that are still empty. Place drafts that still hold [CONFIRM] notes from the
 * older suggestions are filled in and published.
 *
 * Data: inc/content-pack/*.json.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

const TP_PACK_VERSION = 1;

/**
 * Pack items of one kind: suburbs, places or services.
 *
 * @return array[]
 */
function tp_pack_data( $kind ) {
	static $cache = array();
	if ( isset( $cache[ $kind ] ) ) {
		return $cache[ $kind ];
	}
	$out = array();
	foreach ( (array) glob( TP_DIR . '/inc/content-pack/' . $kind . '*.json' ) as $file ) {
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		if ( is_array( $data ) ) {
			$out = array_merge( $out, $data );
		}
	}
	$cache[ $kind ] = $out;
	return $out;
}

// Map positions for the pack's suburbs (travel times, the area map, route pages).
add_filter(
	'tp_map_places',
	static function ( $places ) {
		foreach ( tp_pack_data( 'suburbs' ) as $s ) {
			$k = strtolower( $s['name'] );
			if ( ! isset( $places[ $k ] ) && is_numeric( $s['lat'] ?? null ) && is_numeric( $s['lng'] ?? null ) ) {
				$places[ $k ] = array( (float) $s['lat'], (float) $s['lng'] );
			}
		}
		return $places;
	}
);

/** "q\na\n\nq\na" for the theme's question boxes. */
function tp_pack_faq_text( $faq ) {
	$blocks = array();
	foreach ( (array) $faq as $qa ) {
		if ( ! empty( $qa['q'] ) && ! empty( $qa['a'] ) ) {
			$blocks[] = trim( $qa['q'] ) . "\n" . trim( $qa['a'] );
		}
	}
	return implode( "\n\n", $blocks );
}

/** Block editor markup from [ {h2}, {p}, {ul} ] or plain paragraphs. */
function tp_pack_blocks( $items ) {
	$out = array();
	foreach ( (array) $items as $item ) {
		if ( is_string( $item ) ) {
			$item = array( 'p' => $item );
		}
		if ( isset( $item['h2'] ) ) {
			$out[] = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $item['h2'] ) . "</h2>\n<!-- /wp:heading -->";
		} elseif ( isset( $item['p'] ) ) {
			$out[] = "<!-- wp:paragraph -->\n<p>" . esc_html( $item['p'] ) . "</p>\n<!-- /wp:paragraph -->";
		} elseif ( isset( $item['ul'] ) ) {
			$li    = implode( '', array_map( static fn( $t ) => "<!-- wp:list-item -->\n<li>" . esc_html( $t ) . "</li>\n<!-- /wp:list-item -->", (array) $item['ul'] ) );
			$out[] = "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . $li . "</ul>\n<!-- /wp:list -->";
		}
	}
	return implode( "\n\n", $out );
}

/** Set a meta value only when it is empty. */
function tp_pack_fill_meta( $id, $key, $value, $force = false ) {
	if ( '' === (string) $value ) {
		return;
	}
	if ( $force || '' === trim( (string) get_post_meta( $id, $key, true ) ) ) {
		update_post_meta( $id, $key, $value );
	}
}

/** Pack place details as theme meta (without the _tp_p_ prefix). */
function tp_pack_place_meta( array $p ) {
	return array(
		'type'     => $p['type'],
		'address'  => $p['address'] ?? '',
		'area'     => $p['area'] ?? '',
		'lat'      => isset( $p['lat'] ) ? (string) $p['lat'] : '',
		'lng'      => isset( $p['lng'] ) ? (string) $p['lng'] : '',
		'entrance' => $p['entrance'] ?? '',
		'spot'     => $p['spot'] ?? '',
		'parking'  => $p['parking'] ?? '',
		'access'   => $p['access'] ?? '',
		'tips'     => implode( "\n", (array) ( $p['tips'] ?? array() ) ),
		'pickup'   => $p['pickup'] ?? '',
		'official' => $p['official'] ?? '',
		'faq'      => tp_pack_faq_text( $p['faq'] ?? array() ),
	);
}

/** Does a place post still hold [CONFIRM] notes from the old suggestions? */
function tp_pack_place_unconfirmed( $id ) {
	foreach ( array( 'entrance', 'spot', 'tips', 'address' ) as $k ) {
		if ( false !== strpos( (string) get_post_meta( $id, '_tp_p_' . $k, true ), '[CONFIRM' ) ) {
			return true;
		}
	}
	return false;
}

function tp_pack_find( $type, $name, $slug = '' ) {
	$slug = $slug ?: sanitize_title( $name );
	$hit  = get_posts( array( 'post_type' => $type, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'name' => $slug, 'numberposts' => 1 ) );
	if ( ! $hit ) {
		$hit = get_posts( array( 'post_type' => $type, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'title' => $name, 'numberposts' => 1 ) );
	}
	return $hit ? $hit[0] : null;
}

/**
 * What the pack would add: [ suburbs, places, services ] counts.
 */
function tp_pack_pending() {
	$n = get_transient( 'tp_pack_pending' );
	if ( is_array( $n ) ) {
		return $n;
	}
	$n = array( 0, 0, 0 );
	foreach ( tp_pack_data( 'suburbs' ) as $s ) {
		$n[0] += tp_pack_find( 'tp_area', $s['name'] ) ? 0 : 1;
	}
	foreach ( tp_pack_data( 'places' ) as $p ) {
		$post  = tp_pack_find( 'tp_place', $p['name'] );
		$n[1] += ! $post || ( 'publish' !== $post->post_status && tp_pack_place_unconfirmed( $post->ID ) ) ? 1 : 0;
	}
	foreach ( tp_pack_data( 'services' ) as $s ) {
		$n[2] += tp_pack_find( 'tp_service', $s['title'], $s['slug'] ) ? 0 : 1;
	}
	set_transient( 'tp_pack_pending', $n, 10 * MINUTE_IN_SECONDS );
	return $n;
}

/**
 * Add the pack.
 *
 * @return array [ added, filled ]
 */
function tp_pack_import() {
	$added  = 0;
	$filled = 0;
	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- one-off import.
	}
	wp_defer_term_counting( true );

	// Suburbs.
	$order = (int) count( get_posts( array( 'post_type' => 'tp_area', 'post_status' => 'any', 'numberposts' => 500, 'fields' => 'ids' ) ) );
	$lines = (string) get_theme_mod( 'tp_service_areas', tp_defaults()['service_areas'] ?? '' );
	$known = array();
	foreach ( tp_service_area_aliases() as $name => $aliases ) {
		$known = array_merge( $known, $aliases );
	}
	foreach ( tp_pack_data( 'suburbs' ) as $s ) {
		$post    = tp_pack_find( 'tp_area', $s['name'] );
		$content = implode( "\n\n", array_map( 'trim', (array) $s['intro'] ) );
		if ( ! $post ) {
			$id = wp_insert_post(
				array(
					'post_type'    => 'tp_area',
					'post_status'  => 'publish',
					'post_title'   => $s['name'],
					'post_name'    => sanitize_title( $s['name'] ),
					'post_content' => $content,
					'menu_order'   => ++$order,
				)
			);
			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}
			update_post_meta( $id, '_tp_no_local', 1 );
			update_post_meta( $id, '_tp_pack', TP_PACK_VERSION );
			++$added;
		} else {
			$id = $post->ID;
			if ( '' === trim( $post->post_content ) ) {
				wp_update_post( array( 'ID' => $id, 'post_content' => $content ) );
			}
			++$filled;
		}
		tp_pack_fill_meta( $id, '_tp_local', $s['local'] ?? '' );
		tp_pack_fill_meta( $id, '_tp_nearby', implode( "\n", (array) ( $s['nearby'] ?? array() ) ) );
		tp_pack_fill_meta( $id, '_tp_local_faq', tp_pack_faq_text( $s['faq'] ?? array() ) );
		tp_pack_fill_meta( $id, '_tp_meta_desc', $s['description'] ?? '' );
		if ( ! in_array( strtolower( $s['name'] ), $known, true ) ) {
			$aliases = array_values( array_unique( array_filter( array_map( 'trim', (array) ( $s['aliases'] ?? array() ) ) ) ) );
			$lines  .= "\n" . implode( ' | ', array_merge( array( $s['name'] ), $aliases ) );
			$known[] = strtolower( $s['name'] );
		}
	}
	set_theme_mod( 'tp_service_areas', trim( $lines ) );

	// Places.
	foreach ( tp_pack_data( 'places' ) as $p ) {
		if ( ! isset( tp_place_types()[ $p['type'] ?? '' ] ) ) {
			continue;
		}
		$post  = tp_pack_find( 'tp_place', $p['name'] );
		$meta  = tp_pack_place_meta( $p );
		$force = false;
		if ( ! $post ) {
			$id = wp_insert_post(
				array(
					'post_type'    => 'tp_place',
					'post_status'  => 'publish',
					'post_title'   => $p['name'],
					'post_name'    => sanitize_title( $p['name'] ),
					'post_content' => tp_pack_blocks( $p['about'] ?? array() ),
				)
			);
			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}
			update_post_meta( $id, '_tp_pack', TP_PACK_VERSION );
			++$added;
		} else {
			$id = $post->ID;
			if ( 'publish' !== $post->post_status ) {
				if ( ! tp_pack_place_unconfirmed( $id ) ) {
					continue; // Someone is working on this draft.
				}
				$force = true;
				wp_update_post(
					array(
						'ID'           => $id,
						'post_status'  => 'publish',
						'post_content' => '' === trim( $post->post_content ) ? tp_pack_blocks( $p['about'] ?? array() ) : $post->post_content,
					)
				);
				++$added;
			} else {
				if ( '' === trim( $post->post_content ) ) {
					wp_update_post( array( 'ID' => $id, 'post_content' => tp_pack_blocks( $p['about'] ?? array() ) ) );
				}
				++$filled;
			}
		}
		foreach ( $meta as $k => $v ) {
			tp_pack_fill_meta( $id, '_tp_p_' . $k, $v, $force && 'type' !== $k );
		}
		tp_pack_fill_meta( $id, '_tp_meta_desc', $p['description'] ?? '' );
	}

	// Services.
	$defaults = array();
	foreach ( tp_default_services() as $d ) {
		$defaults[] = preg_replace( '/\s+/', ' ', trim( $d['content'] ) );
	}
	$order = (int) count( get_posts( array( 'post_type' => 'tp_service', 'post_status' => 'any', 'numberposts' => 100, 'fields' => 'ids' ) ) );
	foreach ( tp_pack_data( 'services' ) as $s ) {
		$post    = tp_pack_find( 'tp_service', $s['title'], $s['slug'] );
		$content = tp_pack_blocks( $s['content'] ?? array() );
		if ( ! $post ) {
			$id = wp_insert_post(
				array(
					'post_type'    => 'tp_service',
					'post_status'  => 'draft' === ( $s['status'] ?? '' ) ? 'draft' : 'publish',
					'post_title'   => $s['title'],
					'post_name'    => $s['slug'],
					'post_content' => $content,
					'post_excerpt' => $s['excerpt'] ?? '',
					'menu_order'   => ++$order,
				)
			);
			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}
			update_post_meta( $id, '_tp_no_local', 1 );
			update_post_meta( $id, '_tp_pack', TP_PACK_VERSION );
			++$added;
		} else {
			$id  = $post->ID;
			$now = preg_replace( '/\s+/', ' ', trim( wp_strip_all_tags( $post->post_content ) ) );
			if ( '' === $now || in_array( $now, $defaults, true ) ) {
				wp_update_post( array( 'ID' => $id, 'post_content' => $content ) );
			}
			if ( ! has_excerpt( $post ) && ! empty( $s['excerpt'] ) ) {
				wp_update_post( array( 'ID' => $id, 'post_excerpt' => $s['excerpt'] ) );
			}
			++$filled;
		}
		tp_pack_fill_meta( $id, '_tp_icon', $s['icon'] ?? '' );
		tp_pack_fill_meta( $id, '_tp_expect', implode( "\n", (array) ( $s['expect'] ?? array() ) ) );
		tp_pack_fill_meta( $id, '_tp_faq', tp_pack_faq_text( $s['faq'] ?? array() ) );
		tp_pack_fill_meta( $id, '_tp_meta_desc', $s['description'] ?? '' );
	}

	wp_defer_term_counting( false );
	update_option( 'tp_pack_done', TP_PACK_VERSION, false );
	delete_transient( 'tp_pack_pending' );
	delete_transient( 'tp_old_links_count' );
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_tp\_routes\_area\_%' OR option_name LIKE '\_transient\_timeout\_tp\_routes\_area\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return array( $added, $filled );
}

add_action(
	'admin_post_tp_pack_import',
	static function () {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'tp_pack_import' );
		list( $added, $filled ) = tp_pack_import();
		/* translators: 1: pages added 2: pages filled in */
		set_transient( 'tp_admin_notice_' . get_current_user_id(), array( 'success', sprintf( __( 'Done: %1$d new pages published, %2$d existing pages filled in where they were empty. Next: in Google Search Console, submit your sitemap again.', 'taxi-peninsula' ), $added, $filled ) ), 120 );
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}
);

add_action(
	'admin_notices',
	static function () {
		$screen = get_current_screen();
		if ( (int) get_option( 'tp_pack_done' ) >= TP_PACK_VERSION || ! current_user_can( 'edit_pages' ) || ! $screen || ! in_array( $screen->id, array( 'dashboard', 'edit-tp_area', 'edit-tp_place', 'edit-tp_service' ), true ) ) {
			return;
		}
		$n = tp_pack_pending();
		if ( ! array_sum( $n ) ) {
			return;
		}
		?>
		<div class="notice notice-info">
			<p><strong><?php esc_html_e( 'New pages are ready to add', 'taxi-peninsula' ); ?></strong></p>
			<p>
				<?php
				/* translators: 1: suburbs 2: places 3: services */
				echo esc_html( sprintf( __( 'Written for your site: %1$d suburb and town pages, %2$d pages for hospitals, airports, ferries, stations, hotels, wineries and venues, and %3$d service pages. Your own pages are never overwritten — existing ones only get the parts that are still empty.', 'taxi-peninsula' ), $n[0], $n[1], $n[2] ) );
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:8px">
				<input type="hidden" name="action" value="tp_pack_import">
				<?php wp_nonce_field( 'tp_pack_import' ); ?>
				<button class="button button-primary"><?php esc_html_e( 'Add the new pages', 'taxi-peninsula' ); ?></button>
			</form>
		</div>
		<?php
	}
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'tp content-pack',
		static function () {
			list( $added, $filled ) = tp_pack_import();
			WP_CLI::success( sprintf( '%d added, %d filled in.', $added, $filled ) );
		}
	);
}
