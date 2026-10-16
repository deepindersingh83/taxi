<?php
/**
 * Glossary: short, answer-ready pages for terms passengers search for
 * (MPTP, WAT, NDIS transport funding…) at /glossary/ and /glossary/{term}/.
 *
 * Each term has a one or two sentence definition (the excerpt) that Google and
 * AI assistants can quote, a longer explanation (the editor) and links to
 * related terms. Marked up as DefinedTerm in a DefinedTermSet.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		register_post_type(
			'tp_term',
			array(
				'labels'       => array(
					'name'          => __( 'Glossary', 'taxi-peninsula' ),
					'singular_name' => __( 'Glossary term', 'taxi-peninsula' ),
					'add_new'       => __( 'Add term', 'taxi-peninsula' ),
					'add_new_item'  => __( 'Add glossary term', 'taxi-peninsula' ),
					'edit_item'     => __( 'Edit glossary term', 'taxi-peninsula' ),
					'view_item'     => __( 'View term', 'taxi-peninsula' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-book-alt',
				'supports'     => array( 'title', 'editor', 'excerpt', 'revisions' ),
				'has_archive'  => 'glossary',
				'rewrite'      => array( 'slug' => 'glossary', 'with_front' => false ),
			)
		);
		if ( (int) get_option( 'tp_glossary_rw' ) !== 1 ) {
			flush_rewrite_rules( false );
			update_option( 'tp_glossary_rw', 1, false );
		}
	},
	21
);

add_action(
	'pre_get_posts',
	static function ( $q ) {
		if ( ! is_admin() && $q->is_main_query() && $q->is_post_type_archive( 'tp_term' ) ) {
			$q->set( 'posts_per_page', 200 );
			$q->set( 'orderby', 'title' );
			$q->set( 'order', 'ASC' );
		}
	}
);

add_filter(
	'enter_title_here',
	static function ( $text, $post ) {
		return 'tp_term' === $post->post_type ? __( 'Term, e.g. "MPTP (Multi Purpose Taxi Program)"', 'taxi-peninsula' ) : $text;
	},
	10,
	2
);

add_action(
	'edit_form_after_title',
	static function ( $post ) {
		if ( 'tp_term' === $post->post_type ) {
			echo '<p class="description">' . esc_html__( 'Put a one or two sentence definition in the Excerpt box (it is shown first and quoted by search engines), and the longer explanation in the editor. Remove any [CONFIRM] notes before publishing.', 'taxi-peninsula' ) . '</p>';
		}
	}
);

add_filter(
	'tp_breadcrumb_extra',
	static function ( $extra ) {
		if ( is_singular( 'tp_term' ) ) {
			$extra[] = array( __( 'Glossary', 'taxi-peninsula' ), get_post_type_archive_link( 'tp_term' ) );
			$extra[] = array( single_post_title( '', false ), get_permalink() );
		}
		return $extra;
	}
);

add_filter(
	'document_title_parts',
	static function ( $parts ) {
		if ( is_singular( 'tp_term' ) ) {
			/* translators: %s: term */
			$parts['title'] = sprintf( __( 'What is %s?', 'taxi-peninsula' ), single_post_title( '', false ) );
		} elseif ( is_post_type_archive( 'tp_term' ) ) {
			$parts['title'] = __( 'Accessible transport glossary', 'taxi-peninsula' );
		}
		return $parts;
	},
	20
);

add_filter(
	'tp_meta_description',
	static function ( $text ) {
		if ( is_post_type_archive( 'tp_term' ) ) {
			return __( 'Plain-English answers to the words used in accessible transport in Victoria: MPTP, WAT, NDIS transport funding, wheelchair restraints, ramps and hoists, and more.', 'taxi-peninsula' );
		}
		return $text;
	}
);

/**
 * The short definition for a term.
 */
function tp_term_definition( $id ) {
	$post = get_post( $id );
	return $post && has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ), 40 );
}

add_filter(
	'tp_schema_graph',
	static function ( $graph ) {
		$set = array( '@type' => 'DefinedTermSet', '@id' => get_post_type_archive_link( 'tp_term' ) . '#set', 'name' => __( 'Accessible transport glossary', 'taxi-peninsula' ), 'url' => get_post_type_archive_link( 'tp_term' ) );
		if ( is_singular( 'tp_term' ) ) {
			$id      = get_queried_object_id();
			$graph[] = array(
				'@type'            => 'DefinedTerm',
				'name'             => get_the_title( $id ),
				'description'      => tp_term_definition( $id ),
				'url'              => get_permalink( $id ),
				'inDefinedTermSet' => $set,
			);
		} elseif ( is_post_type_archive( 'tp_term' ) ) {
			$set['hasDefinedTerm'] = array_map(
				static fn( $p ) => array( '@type' => 'DefinedTerm', 'name' => get_the_title( $p ), 'description' => tp_term_definition( $p->ID ), 'url' => get_permalink( $p ) ),
				get_posts( array( 'post_type' => 'tp_term', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC' ) )
			);
			$graph[]               = $set;
		}
		return $graph;
	}
);

/**
 * Link the first mention of each glossary term in blog posts and pages.
 */
add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! is_singular( array( 'post', 'page', 'tp_service' ) ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$terms = get_transient( 'tp_glossary_links' );
		if ( ! is_array( $terms ) ) {
			$terms = array();
			foreach ( get_posts( array( 'post_type' => 'tp_term', 'numberposts' => 200 ) ) as $p ) {
				// "MPTP (Multi Purpose Taxi Program)" matches "MPTP".
				$short = trim( preg_replace( '/\s*\(.*\)$/', '', $p->post_title ) );
				if ( mb_strlen( $short ) >= 3 ) {
					$terms[ $short ] = get_permalink( $p );
				}
			}
			set_transient( 'tp_glossary_links', $terms, DAY_IN_SECONDS );
		}
		$done = 0;
		foreach ( $terms as $word => $url ) {
			if ( $done >= 5 || false === stripos( $content, $word ) ) {
				continue;
			}
			// Only in plain paragraph text, never inside tags, links or headings.
			$pattern = '/(<p(?:\s[^>]*)?>(?:(?!<\/p>).)*?)(?<![\w\/-])(' . preg_quote( $word, '/' ) . ')(?![\w-])/isu';
			$linked  = false;
			$new     = preg_replace_callback(
				$pattern,
				static function ( $m ) use ( $url, &$linked ) {
					$before = strtolower( $m[1] );
					if ( $linked ) {
						return $m[0];
					}
					// Skip inside a link, a button, or a tag's attributes.
					if ( substr_count( $before, '<a ' ) > substr_count( $before, '</a>' )
						|| substr_count( $before, '<button' ) > substr_count( $before, '</button>' )
						|| strrpos( $before, '<' ) > strrpos( $before, '>' ) ) {
						return $m[0];
					}
					$linked = true;
					return $m[1] . '<a class="glossary-link" href="' . esc_url( $url ) . '">' . $m[2] . '</a>';
				},
				$content
			);
			if ( null !== $new && $new !== $content ) {
				$content = $new;
				++$done;
			}
		}
		return $content;
	},
	20
);
add_action(
	'save_post_tp_term',
	static function () {
		delete_transient( 'tp_glossary_links' );
	}
);

/* -------------------------------------------------------------------------
 * Starter terms (drafts).
 * ---------------------------------------------------------------------- */

function tp_glossary_starters() {
	$c = ' ' . __( '[CONFIRM current details with the Department of Transport and Planning before publishing.]', 'taxi-peninsula' );
	return array(
		array( 'WAT (Wheelchair Accessible Taxi)', __( 'A taxi built or converted to carry a passenger seated in their wheelchair, with a ramp or hoist, wheelchair restraints and a seatbelt for the passenger.', 'taxi-peninsula' ) ),
		array( 'Maxi taxi', __( 'A larger taxi that seats up to 11 passengers. Many maxi taxis are also wheelchair accessible, but not all — ask for a wheelchair accessible vehicle when you book.', 'taxi-peninsula' ) ),
		array( 'MPTP (Multi Purpose Taxi Program)', __( 'A Victorian Government program that pays part of the taxi fare for eligible people with a severe and permanent disability. Members show their MPTP card to the driver and pay the rest of the fare.', 'taxi-peninsula' ) . $c ),
		array( 'Lifting fee', __( 'A payment the Victorian Government makes to drivers of wheelchair accessible taxis for each trip with a passenger who travels in their wheelchair. It is not charged to the passenger.', 'taxi-peninsula' ) . $c ),
		array( 'NDIS transport funding', __( 'Money in an NDIS plan that helps a participant travel to work, study, therapy and community activities when they cannot use public transport without substantial difficulty. It can usually pay for taxi trips.', 'taxi-peninsula' ) . ' ' . __( '[CONFIRM against current NDIS guidelines.]', 'taxi-peninsula' ) ),
		array( 'Plan manager', __( 'An organisation that pays providers from an NDIS participant’s plan. If you are plan managed, we send the invoice to your plan manager.', 'taxi-peninsula' ) ),
		array( 'Support coordinator', __( 'A person funded by the NDIS to help a participant use their plan, find providers and organise supports such as regular transport.', 'taxi-peninsula' ) ),
		array( 'Four-point restraint', __( 'The four straps that secure a wheelchair to the floor of the vehicle — two at the front and two at the back — so the chair cannot move during the trip.', 'taxi-peninsula' ) ),
		array( 'Ramp vs hoist', __( 'A ramp is a sloping platform you roll up into the vehicle, usually at the back or side. A hoist is a powered platform that lifts you and your chair level with the floor. Hoists suit heavier power chairs.', 'taxi-peninsula' ) ),
		array( 'Booking service provider (BSP)', __( 'A business accredited in Victoria to take taxi and hire-car bookings. Booking service providers must meet safety, record-keeping and complaints rules set by Commercial Passenger Vehicles Victoria.', 'taxi-peninsula' ) . $c ),
		array( 'CPVV (Commercial Passenger Vehicles Victoria)', __( 'The Victorian regulator for taxis, hire cars and booking services. You can contact them if a complaint to the operator is not resolved.', 'taxi-peninsula' ) ),
		array( 'Companion Card', __( 'A card for people who need a companion for support at venues and events. The companion usually gets a free ticket; it does not change taxi fares, but carers are always welcome to travel with you.', 'taxi-peninsula' ) ),
		array( 'Assistance animal', __( 'A dog or other animal trained to help a person with a disability. Assistance animals can travel in our vehicles at no extra cost.', 'taxi-peninsula' ) ),
		array( 'Door-to-door service', __( 'The driver helps you from your front door to the vehicle and from the vehicle to the door at the other end, rather than only kerb to kerb.', 'taxi-peninsula' ) ),
	);
}

add_action(
	'admin_post_tp_glossary_starters',
	static function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'tp_glossary_starters' );
		$n = 0;
		foreach ( tp_glossary_starters() as $t ) {
			if ( get_posts( array( 'post_type' => 'tp_term', 'post_status' => 'any', 'title' => $t[0], 'numberposts' => 1, 'fields' => 'ids' ) ) ) {
				continue;
			}
			$n += wp_insert_post( array( 'post_type' => 'tp_term', 'post_status' => 'draft', 'post_title' => $t[0], 'post_excerpt' => $t[1] ) ) ? 1 : 0;
		}
		/* translators: %d: number */
		set_transient( 'tp_admin_notice_' . get_current_user_id(), array( 'success', sprintf( _n( '%d draft term added.', '%d draft terms added.', $n, 'taxi-peninsula' ), $n ) ), 60 );
		wp_safe_redirect( admin_url( 'edit.php?post_type=tp_term&post_status=draft' ) );
		exit;
	}
);

add_action(
	'admin_notices',
	static function () {
		$s = get_current_screen();
		if ( ! $s || 'edit-tp_term' !== $s->id ) {
			return;
		}
		?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'Short definitions people search for — "what is MPTP", "does NDIS pay for taxis" — are often quoted directly by Google and AI assistants. Each term links to the others and is linked automatically the first time it appears in your blog posts.', 'taxi-peninsula' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:8px">
				<input type="hidden" name="action" value="tp_glossary_starters">
				<?php wp_nonce_field( 'tp_glossary_starters' ); ?>
				<button class="button"><?php esc_html_e( 'Add 14 starter terms (drafts)', 'taxi-peninsula' ); ?></button>
			</form>
		</div>
		<?php
	}
);

add_action(
	'tp_footer_area_links',
	static function () {
		if ( wp_count_posts( 'tp_term' )->publish ) {
			printf( '<li><a href="%1$s">%2$s</a></li>', esc_url( get_post_type_archive_link( 'tp_term' ) ), esc_html__( 'Glossary', 'taxi-peninsula' ) );
		}
	},
	20
);
