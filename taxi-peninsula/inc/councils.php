<?php
/**
 * Council-area pages: /council/frankston-city/, /council/mornington-peninsula-shire/ …
 *
 * People (and council community-transport teams) search by council name.
 * Each page lists the suburbs you cover in that council area, with a map,
 * travel times, a quick answer, common questions, reviews mentioning the
 * area and a booking card. /council/ lists every council area.
 *
 * Suburbs are matched to councils automatically for Melbourne's south-east
 * and the Mornington Peninsula; any area can be set by hand when editing it
 * (the "Council area" box). Councils with no covered suburbs have no page.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

const TP_COUNCIL_RW = 1;

/**
 * Known council areas: slug => [name, [suburbs…]].
 */
function tp_council_map() {
	return apply_filters(
		'tp_council_map',
		array(
			'frankston-city'            => array( 'Frankston City', array( 'Frankston', 'Frankston South', 'Frankston North', 'Seaford', 'Carrum Downs', 'Langwarrin', 'Langwarrin South', 'Skye', 'Karingal', 'Sandhurst' ) ),
			'mornington-peninsula-shire' => array( 'Mornington Peninsula Shire', array( 'Mornington', 'Mount Martha', 'Mount Eliza', 'Dromana', 'Safety Beach', 'Rosebud', 'Capel Sound', 'Tootgarook', 'Rye', 'Blairgowrie', 'Sorrento', 'Portsea', 'Red Hill', 'Main Ridge', 'Flinders', 'Shoreham', 'Balnarring', 'Merricks', 'Somers', 'Hastings', 'Crib Point', 'Bittern', 'Tyabb', 'Somerville', 'Baxter', 'Pearcedale', 'Moorooduc', 'Arthurs Seat', 'McCrae', 'Boneo', 'Fingal', 'St Andrews Beach' ) ),
			'kingston-city'             => array( 'City of Kingston', array( 'Mordialloc', 'Mentone', 'Cheltenham', 'Parkdale', 'Aspendale', 'Aspendale Gardens', 'Edithvale', 'Chelsea', 'Chelsea Heights', 'Bonbeach', 'Carrum', 'Patterson Lakes', 'Dingley Village', 'Braeside', 'Waterways', 'Moorabbin', 'Clarinda', 'Clayton South', 'Heatherton', 'Highett' ) ),
			'greater-dandenong'         => array( 'City of Greater Dandenong', array( 'Dandenong', 'Dandenong North', 'Dandenong South', 'Noble Park', 'Noble Park North', 'Springvale', 'Springvale South', 'Keysborough', 'Bangholme' ) ),
			'casey-city'                => array( 'City of Casey', array( 'Cranbourne', 'Cranbourne North', 'Cranbourne East', 'Cranbourne West', 'Berwick', 'Narre Warren', 'Narre Warren South', 'Hampton Park', 'Hallam', 'Doveton', 'Endeavour Hills', 'Lynbrook', 'Lyndhurst', 'Clyde', 'Clyde North', 'Botanic Ridge' ) ),
			'bayside-city'              => array( 'Bayside City', array( 'Brighton', 'Brighton East', 'Hampton', 'Hampton East', 'Sandringham', 'Black Rock', 'Beaumaris' ) ),
			'monash-city'               => array( 'City of Monash', array( 'Clayton', 'Glen Waverley', 'Mount Waverley', 'Oakleigh', 'Oakleigh East', 'Mulgrave', 'Wheelers Hill', 'Notting Hill', 'Ashwood', 'Chadstone' ) ),
			'glen-eira-city'            => array( 'City of Glen Eira', array( 'Caulfield', 'Caulfield North', 'Caulfield South', 'Elsternwick', 'Bentleigh', 'Bentleigh East', 'McKinnon', 'Ormond', 'Carnegie', 'Murrumbeena', 'Glen Huntly' ) ),
			'port-phillip-city'         => array( 'City of Port Phillip', array( 'St Kilda', 'St Kilda West', 'Elwood', 'South Melbourne', 'Port Melbourne', 'Albert Park', 'Middle Park', 'Balaclava', 'Ripponlea' ) ),
			'melbourne-city'            => array( 'City of Melbourne', array( 'Melbourne', 'Melbourne CBD', 'Southbank', 'Docklands', 'Carlton', 'Parkville', 'North Melbourne', 'East Melbourne', 'West Melbourne', 'Kensington' ) ),
			'knox-city'                 => array( 'City of Knox', array( 'Bayswater', 'Boronia', 'Ferntree Gully', 'Rowville', 'Scoresby', 'Wantirna', 'Wantirna South', 'Knoxfield' ) ),
			'maroondah-city'            => array( 'City of Maroondah', array( 'Ringwood', 'Ringwood East', 'Ringwood North', 'Croydon', 'Heathmont' ) ),
			'whitehorse-city'           => array( 'City of Whitehorse', array( 'Box Hill', 'Blackburn', 'Burwood', 'Mitcham', 'Nunawading', 'Vermont', 'Forest Hill' ) ),
			'greater-geelong'           => array( 'City of Greater Geelong', array( 'Geelong', 'Geelong West', 'Belmont', 'Lara', 'Ocean Grove', 'Torquay' ) ),
		)
	);
}

/**
 * Council slug for an area post (set by hand, or matched by name).
 */
function tp_area_council( $area ) {
	$set = (string) get_post_meta( $area->ID, '_tp_council', true );
	if ( '' !== $set ) {
		return 'none' === $set ? '' : $set;
	}
	$name = strtolower( trim( get_the_title( $area ) ) );
	foreach ( tp_council_map() as $slug => $c ) {
		foreach ( $c[1] as $sub ) {
			if ( strtolower( $sub ) === $name ) {
				return $slug;
			}
		}
	}
	return '';
}

/**
 * Council areas you cover: slug => [name, WP_Post[] areas].
 */
function tp_councils() {
	static $out = null;
	if ( null !== $out ) {
		return $out;
	}
	$out = array();
	$map = tp_council_map();
	foreach ( get_posts( array( 'post_type' => 'tp_area', 'numberposts' => 300, 'orderby' => 'title', 'order' => 'ASC' ) ) as $a ) {
		$slug = tp_area_council( $a );
		if ( $slug && isset( $map[ $slug ] ) ) {
			$out[ $slug ][0]   = $map[ $slug ][0];
			$out[ $slug ][1][] = $a;
		}
	}
	return $out;
}

function tp_council_url( $slug = '' ) {
	return home_url( user_trailingslashit( 'council' . ( $slug ? '/' . $slug : '' ) ) );
}

add_action(
	'init',
	static function () {
		add_rewrite_rule( '^council/([^/]+)/?$', 'index.php?tp_council=$matches[1]', 'top' );
		add_rewrite_rule( '^council/?$', 'index.php?tp_council=_all', 'top' );
		if ( (int) get_option( 'tp_council_rw' ) !== TP_COUNCIL_RW ) {
			flush_rewrite_rules( false );
			update_option( 'tp_council_rw', TP_COUNCIL_RW, false );
		}
	},
	21
);

add_filter(
	'query_vars',
	static function ( $v ) {
		$v[] = 'tp_council';
		return $v;
	}
);

add_filter(
	'request',
	static function ( $vars ) {
		if ( ! empty( $vars['tp_council'] ) ) {
			$vars['posts_per_page'] = 1;
			$vars['no_found_rows']  = true;
		}
		return $vars;
	}
);

/**
 * Current council: ['_all'] for the list, [slug, name, areas] for one, or null.
 */
function tp_council_current() {
	static $cur = false;
	if ( false !== $cur ) {
		return $cur;
	}
	$cur  = null;
	$slug = sanitize_title( (string) get_query_var( 'tp_council' ) );
	if ( '_all' === get_query_var( 'tp_council' ) ) {
		$cur = array( '_all' );
	} elseif ( $slug && isset( tp_councils()[ $slug ] ) ) {
		$cur = array( $slug, tp_councils()[ $slug ][0], tp_councils()[ $slug ][1] );
	}
	return $cur;
}

add_action(
	'template_redirect',
	static function () {
		if ( ! get_query_var( 'tp_council' ) ) {
			return;
		}
		global $wp_query;
		if ( ! tp_council_current() ) {
			$wp_query->set_404();
			status_header( 404 );
			return;
		}
		$wp_query->is_home    = false;
		$wp_query->is_404     = false;
		$wp_query->is_archive = false;
		status_header( 200 );
	},
	0
);

add_filter(
	'template_include',
	static function ( $t ) {
		return tp_council_current() ? ( locate_template( 'council.php' ) ?: $t ) : $t;
	}
);

function tp_council_title( $name ) {
	/* translators: %s: council name */
	return sprintf( __( 'Wheelchair taxi in %s', 'taxi-peninsula' ), $name );
}

add_filter(
	'document_title_parts',
	static function ( $parts ) {
		$c = tp_council_current();
		if ( $c ) {
			$parts['title'] = '_all' === $c[0] ? __( 'Council areas we cover', 'taxi-peninsula' ) : tp_council_title( $c[1] );
		}
		return $parts;
	},
	20
);

add_filter(
	'tp_meta_description',
	static function ( $text ) {
		$c = tp_council_current();
		if ( ! $c ) {
			return $text;
		}
		if ( '_all' === $c[0] ) {
			return __( 'Wheelchair accessible taxis in every council area we cover across Melbourne and the Mornington Peninsula.', 'taxi-peninsula' );
		}
		/* translators: 1: council 2: suburbs 3: phone */
		return sprintf( __( 'Wheelchair accessible taxis across %1$s: %2$s. Ramp and hoist vans, trained drivers. Book online or call %3$s.', 'taxi-peninsula' ), $c[1], implode( ', ', array_slice( wp_list_pluck( $c[2], 'post_title' ), 0, 6 ) ), wp_strip_all_tags( tp_opt( 'phone_display' ) ) );
	}
);

add_action(
	'wp_head',
	static function () {
		$c = tp_council_current();
		if ( $c && ! ( function_exists( 'tp_seo_plugin_active' ) && tp_seo_plugin_active() ) ) {
			printf( "<link rel=\"canonical\" href=\"%s\">\n", esc_url( tp_council_url( '_all' === $c[0] ? '' : $c[0] ) ) );
		}
	},
	3
);

add_filter(
	'tp_schema_graph',
	static function ( $graph ) {
		$c = tp_council_current();
		if ( $c && '_all' !== $c[0] ) {
			$graph[] = array(
				'@type'      => 'Service',
				'name'       => tp_council_title( $c[1] ),
				'url'        => tp_council_url( $c[0] ),
				'provider'   => array( '@id' => home_url( '/#business' ) ),
				'serviceType' => __( 'Wheelchair accessible taxi', 'taxi-peninsula' ),
				'areaServed' => array( '@type' => 'AdministrativeArea', 'name' => $c[1] . ', Victoria', 'containsPlace' => array_map( static fn( $a ) => array( '@type' => 'Place', 'name' => get_the_title( $a ) ), $c[2] ) ),
			);
		}
		return $graph;
	}
);

/**
 * Questions for a council page.
 */
function tp_council_faq( $name, array $areas ) {
	$list = implode( ', ', wp_list_pluck( $areas, 'post_title' ) );
	return array_merge(
		array(
			/* translators: 1: council 2: suburbs */
			array( sprintf( __( 'Which parts of %s do you cover?', 'taxi-peninsula' ), $name ), sprintf( __( 'We pick up from every address in %1$s, including %2$s.', 'taxi-peninsula' ), $name, $list ) ),
			/* translators: %s: council */
			array( sprintf( __( 'Can community transport or care staff in %s book for clients?', 'taxi-peninsula' ), $name ), __( 'Yes. Coordinators, case managers and aged-care staff can open a coordinator account to book for several clients, see every trip and download monthly trip logs and invoices.', 'taxi-peninsula' ) ),
		),
		tp_access_faq()
	);
}

// Sitemap.
add_action(
	'init',
	static function () {
		if ( ! class_exists( 'WP_Sitemaps_Provider' ) || ! function_exists( 'wp_register_sitemap_provider' ) ) {
			return;
		}
		if ( ! class_exists( 'TP_Council_Sitemap' ) ) {
			/**
			 * Council-area pages.
			 */
			class TP_Council_Sitemap extends WP_Sitemaps_Provider { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
				public function __construct() {
					$this->name        = 'tpcouncil';
					$this->object_type = 'tpcouncil';
				}
				public function get_url_list( $page_num, $object_subtype = '' ) {
					$out = array( array( 'loc' => tp_council_url() ) );
					foreach ( array_keys( tp_councils() ) as $slug ) {
						$out[] = array( 'loc' => tp_council_url( $slug ) );
					}
					return $out;
				}
				public function get_max_num_pages( $object_subtype = '' ) {
					return 1;
				}
			}
		}
		wp_register_sitemap_provider( 'tpcouncil', new TP_Council_Sitemap() );
	},
	11
);

// IndexNow / "send all pages" includes council pages.
add_filter(
	'tp_all_public_urls',
	static function ( $urls ) {
		$urls[] = tp_council_url();
		foreach ( array_keys( tp_councils() ) as $slug ) {
			$urls[] = tp_council_url( $slug );
		}
		return $urls;
	}
);

// Area edit screen: choose the council by hand.
add_action(
	'add_meta_boxes_tp_area',
	static function () {
		add_meta_box( 'tp_area_council', __( 'Council area', 'taxi-peninsula' ), 'tp_render_area_council_box', 'tp_area', 'side' );
	}
);

function tp_render_area_council_box( $post ) {
	wp_nonce_field( 'tp_area_council', 'tp_area_council_nonce' );
	$set  = (string) get_post_meta( $post->ID, '_tp_council', true );
	$auto = '' === $set ? tp_area_council( $post ) : '';
	?>
	<select name="tp_council" class="widefat" aria-label="<?php esc_attr_e( 'Council area', 'taxi-peninsula' ); ?>">
		<option value=""><?php echo esc_html( $auto ? sprintf( /* translators: %s: council */ __( 'Automatic (%s)', 'taxi-peninsula' ), tp_council_map()[ $auto ][0] ) : __( 'Automatic (no match)', 'taxi-peninsula' ) ); ?></option>
		<?php foreach ( tp_council_map() as $slug => $c ) : ?>
			<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $set, $slug ); ?>><?php echo esc_html( $c[0] ); ?></option>
		<?php endforeach; ?>
		<option value="none" <?php selected( $set, 'none' ); ?>><?php esc_html_e( 'Not in a listed council', 'taxi-peninsula' ); ?></option>
	</select>
	<?php
}

add_action(
	'save_post_tp_area',
	static function ( $post_id ) {
		if ( ! isset( $_POST['tp_area_council_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tp_area_council_nonce'] ), 'tp_area_council' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$v = isset( $_POST['tp_council'] ) ? sanitize_key( wp_unslash( $_POST['tp_council'] ) ) : '';
		update_post_meta( $post_id, '_tp_council', ( 'none' === $v || isset( tp_council_map()[ $v ] ) ) ? $v : '' );
	}
);

// Area pages link to their council page.
add_action(
	'tp_area_after_content',
	static function ( $area ) {
		$slug = tp_area_council( $area );
		if ( $slug && isset( tp_councils()[ $slug ] ) ) {
			/* translators: 1: suburb 2: council */
			printf( '<p class="area-council">%1$s <a href="%2$s">%3$s</a></p>', esc_html( sprintf( __( '%1$s is in %2$s.', 'taxi-peninsula' ), get_the_title( $area ), tp_councils()[ $slug ][0] ) ), esc_url( tp_council_url( $slug ) ), esc_html__( 'See all suburbs we cover there', 'taxi-peninsula' ) );
		}
	}
);
