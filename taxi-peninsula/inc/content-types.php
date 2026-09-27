<?php
/**
 * Content types managed in wp-admin:
 * - Fleet (tp_fleet)             vehicles shown on the Fleet page and assignable to bookings
 * - Services (tp_service)        /services/airport-transfers/
 * - Areas (tp_area)              /wheelchair-taxi/frankston/
 * - FAQs (tp_faq + topics)       shown by [tp_faq]
 * - Testimonials (tp_testimonial) shown on the home page
 * - Enquiries (tp_message)       saved from the contact form
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

const TP_REWRITE_VERSION = 2;

add_action( 'init', 'tp_register_content_types' );
function tp_register_content_types() {
	$labels = static function ( $plural, $singular ) {
		return array(
			'name'               => $plural,
			'singular_name'      => $singular,
			/* translators: %s: item type */
			'add_new_item'       => sprintf( __( 'Add %s', 'taxi-peninsula' ), $singular ),
			/* translators: %s: item type */
			'edit_item'          => sprintf( __( 'Edit %s', 'taxi-peninsula' ), $singular ),
			/* translators: %s: item type */
			'new_item'           => sprintf( __( 'New %s', 'taxi-peninsula' ), $singular ),
			/* translators: %s: item type */
			'view_item'          => sprintf( __( 'View %s', 'taxi-peninsula' ), $singular ),
			/* translators: %s: item types */
			'search_items'       => sprintf( __( 'Search %s', 'taxi-peninsula' ), $plural ),
			'not_found'          => __( 'Nothing found.', 'taxi-peninsula' ),
			'not_found_in_trash' => __( 'Nothing in the bin.', 'taxi-peninsula' ),
			'all_items'          => $plural,
		);
	};

	register_post_type(
		'tp_fleet',
		array(
			'labels'             => $labels( __( 'Fleet', 'taxi-peninsula' ), __( 'Vehicle', 'taxi-peninsula' ) ),
			'public'             => false,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-admin-generic',
			'menu_position'      => 21,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
			'capability_type'    => 'page',
		)
	);

	register_post_type(
		'tp_service',
		array(
			'labels'       => $labels( __( 'Services', 'taxi-peninsula' ), __( 'Service', 'taxi-peninsula' ) ),
			'public'       => true,
			'has_archive'  => 'services',
			'rewrite'      => array( 'slug' => 'services', 'with_front' => false ),
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-heart',
			'menu_position' => 22,
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
			'capability_type' => 'page',
		)
	);

	register_post_type(
		'tp_area',
		array(
			'labels'       => $labels( __( 'Areas', 'taxi-peninsula' ), __( 'Area', 'taxi-peninsula' ) ),
			'public'       => true,
			'has_archive'  => 'wheelchair-taxi',
			'rewrite'      => array( 'slug' => 'wheelchair-taxi', 'with_front' => false ),
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-location',
			'menu_position' => 23,
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
			'capability_type' => 'page',
		)
	);

	register_post_type(
		'tp_faq',
		array(
			'labels'          => $labels( __( 'FAQs', 'taxi-peninsula' ), __( 'FAQ', 'taxi-peninsula' ) ),
			'public'          => false,
			'show_ui'         => true,
			'show_in_rest'    => true,
			'menu_icon'       => 'dashicons-editor-help',
			'menu_position'   => 24,
			'supports'        => array( 'title', 'editor', 'page-attributes' ),
			'capability_type' => 'page',
		)
	);
	register_taxonomy(
		'tp_faq_topic',
		'tp_faq',
		array(
			'labels'            => array(
				'name'          => __( 'Topics', 'taxi-peninsula' ),
				'singular_name' => __( 'Topic', 'taxi-peninsula' ),
			),
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'hierarchical'      => true,
		)
	);

	register_post_type(
		'tp_testimonial',
		array(
			'labels'          => $labels( __( 'Testimonials', 'taxi-peninsula' ), __( 'Testimonial', 'taxi-peninsula' ) ),
			'public'          => false,
			'show_ui'         => true,
			'show_in_rest'    => true,
			'menu_icon'       => 'dashicons-format-quote',
			'menu_position'   => 25,
			'supports'        => array( 'title', 'editor', 'page-attributes' ),
			'capability_type' => 'page',
		)
	);

	register_post_type(
		'tp_message',
		array(
			'labels'          => array_merge(
				$labels( __( 'Enquiries', 'taxi-peninsula' ), __( 'Enquiry', 'taxi-peninsula' ) ),
				array( 'menu_name' => __( 'Enquiries', 'taxi-peninsula' ) )
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'edit.php?post_type=tp_booking',
			'supports'        => false,
			'capability_type' => array( 'tp_booking', 'tp_bookings' ),
			'map_meta_cap'    => true,
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
		)
	);

	if ( (int) get_option( 'tp_rewrite_version' ) !== TP_REWRITE_VERSION ) {
		add_action(
			'wp_loaded',
			static function () {
				flush_rewrite_rules( false );
				update_option( 'tp_rewrite_version', TP_REWRITE_VERSION );
			}
		);
	}
}

add_action( 'after_switch_theme', static function () {
	delete_option( 'tp_rewrite_version' );
} );

/* -------------------------------------------------------------------------
 * Custom fields for content types.
 * ---------------------------------------------------------------------- */

/**
 * Meta field definitions: post_type => [key => [label, type, choices|help]].
 */
function tp_content_fields() {
	$icons = array();
	foreach ( array( 'wheelchair', 'medical', 'plane', 'heart', 'users', 'calendar', 'clock', 'shield', 'star', 'pin' ) as $icon ) {
		$icons[ $icon ] = ucfirst( $icon );
	}
	return array(
		'tp_fleet'       => array(
			'access'      => array( __( 'Access', 'taxi-peninsula' ), 'select', array( 'ramp' => __( 'Rear ramp', 'taxi-peninsula' ), 'side_ramp' => __( 'Side ramp', 'taxi-peninsula' ), 'hoist' => __( 'Hydraulic hoist / lift', 'taxi-peninsula' ), 'none' => __( 'No wheelchair access', 'taxi-peninsula' ) ) ),
			'wheelchairs' => array( __( 'Wheelchair positions', 'taxi-peninsula' ), 'number', '' ),
			'seats'       => array( __( 'Seated passengers', 'taxi-peninsula' ), 'number', '' ),
			'features'    => array( __( 'Features (one per line)', 'taxi-peninsula' ), 'textarea', __( 'e.g. Four-point restraints, Lap-sash belt, Air-conditioned', 'taxi-peninsula' ) ),
			'rego'        => array( __( 'Registration / fleet no. (staff only)', 'taxi-peninsula' ), 'text', __( 'Not shown on the website.', 'taxi-peninsula' ) ),
		),
		'tp_service'     => array(
			'icon' => array( __( 'Icon', 'taxi-peninsula' ), 'select', $icons ),
		),
		'tp_testimonial' => array(
			'rating' => array( __( 'Stars (1–5)', 'taxi-peninsula' ), 'number', '' ),
			'detail' => array( __( 'Detail shown under the name', 'taxi-peninsula' ), 'text', __( 'e.g. Rosebud · regular dialysis trips', 'taxi-peninsula' ) ),
		),
	);
}

add_action( 'add_meta_boxes', 'tp_content_meta_boxes' );
function tp_content_meta_boxes() {
	foreach ( array_keys( tp_content_fields() ) as $type ) {
		add_meta_box( 'tp_details', __( 'Details', 'taxi-peninsula' ), 'tp_render_content_box', $type, 'normal', 'high' );
	}
}

function tp_render_content_box( WP_Post $post ) {
	wp_nonce_field( 'tp_content_meta', '_tp_content_nonce' );
	echo '<div class="tp-admin-grid">';
	foreach ( tp_content_fields()[ $post->post_type ] as $key => $f ) {
		$id    = 'tp_meta_' . $key;
		$value = get_post_meta( $post->ID, '_tp_' . $key, true );
		$wide  = 'textarea' === $f[1] ? ' tp-admin-grid__wide' : '';
		echo '<p class="tp-admin-field' . esc_attr( $wide ) . '"><label for="' . esc_attr( $id ) . '">' . esc_html( $f[0] ) . '</label>';
		if ( 'select' === $f[1] ) {
			printf( '<select id="%s" name="tp_meta[%s]">', esc_attr( $id ), esc_attr( $key ) );
			foreach ( $f[2] as $k => $label ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $value, $k, false ), esc_html( $label ) );
			}
			echo '</select>';
		} elseif ( 'textarea' === $f[1] ) {
			printf( '<textarea id="%s" name="tp_meta[%s]" rows="4">%s</textarea>', esc_attr( $id ), esc_attr( $key ), esc_textarea( $value ) );
		} else {
			printf(
				'<input type="%1$s" id="%2$s" name="tp_meta[%3$s]" value="%4$s"%5$s>',
				'number' === $f[1] ? 'number' : 'text',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( $value ),
				'number' === $f[1] ? ' min="0" max="20"' : ''
			);
		}
		if ( 'select' !== $f[1] && ! empty( $f[2] ) ) {
			echo '<span class="description">' . esc_html( $f[2] ) . '</span>';
		}
		echo '</p>';
	}
	echo '</div>';
}

add_action( 'save_post', 'tp_save_content_meta', 10, 2 );
function tp_save_content_meta( $post_id, WP_Post $post ) {
	$fields = tp_content_fields();
	if ( ! isset( $fields[ $post->post_type ] ) || ! isset( $_POST['_tp_content_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_content_nonce'] ) ), 'tp_content_meta' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$input = isset( $_POST['tp_meta'] ) && is_array( $_POST['tp_meta'] ) ? wp_unslash( $_POST['tp_meta'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.
	foreach ( $fields[ $post->post_type ] as $key => $f ) {
		$raw = $input[ $key ] ?? '';
		switch ( $f[1] ) {
			case 'select':
				$val = isset( $f[2][ $raw ] ) ? $raw : '';
				break;
			case 'number':
				$val = '' === $raw ? '' : absint( $raw );
				break;
			case 'textarea':
				$val = sanitize_textarea_field( $raw );
				break;
			default:
				$val = sanitize_text_field( $raw );
		}
		update_post_meta( $post_id, '_tp_' . $key, $val );
	}
}

/* -------------------------------------------------------------------------
 * Helpers used by templates and bookings.
 * ---------------------------------------------------------------------- */

/**
 * Short label for a fleet vehicle, e.g. "Toyota HiAce WAT".
 */
function tp_fleet_label( $fleet_id ) {
	$post = get_post( $fleet_id );
	return ( $post && 'tp_fleet' === $post->post_type ) ? $post->post_title : '';
}

/**
 * Published fleet vehicles, in menu order.
 *
 * @return WP_Post[]
 */
function tp_fleet_vehicles() {
	return get_posts(
		array(
			'post_type'   => 'tp_fleet',
			'numberposts' => 50,
			'orderby'     => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		)
	);
}

/**
 * Published services for the home page and footer, falling back to built-in defaults.
 *
 * @return array[] [ title, text, icon, url ]
 */
function tp_services_list() {
	$posts = get_posts(
		array(
			'post_type'   => 'tp_service',
			'numberposts' => 12,
			'orderby'     => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		)
	);
	if ( $posts ) {
		return array_map(
			static function ( $p ) {
				return array(
					'title' => get_the_title( $p ),
					'text'  => has_excerpt( $p ) ? get_the_excerpt( $p ) : wp_trim_words( wp_strip_all_tags( $p->post_content ), 22 ),
					'icon'  => get_post_meta( $p->ID, '_tp_icon', true ) ?: 'wheelchair',
					'url'   => get_permalink( $p ),
				);
			},
			$posts
		);
	}

	$out = array();
	foreach ( tp_default_services() as $s ) {
		$out[] = array(
			'title' => $s['title'],
			'text'  => $s['excerpt'],
			'icon'  => $s['icon'],
			'url'   => '',
		);
	}
	return $out;
}

/**
 * Built-in service descriptions (also used by "Create starter content").
 */
function tp_default_services() {
	return array(
		array(
			'title'   => __( 'Wheelchair accessible taxis', 'taxi-peninsula' ),
			'icon'    => 'wheelchair',
			'excerpt' => __( 'Travel seated in your manual or power wheelchair, or transfer to a seat — your choice.', 'taxi-peninsula' ),
			'content' => __( "Our wheelchair accessible taxis (WATs) let you travel seated in your own manual or power wheelchair, or transfer to a vehicle seat if you prefer.\n\nEvery trip is door-to-door. Your driver will help you on and off the ramp or hoist, secure your chair with restraints, and make sure you are comfortable before setting off.\n\nTell us about your chair when you book — its size, weight and whether it is manual or powered — so we can send the right vehicle.", 'taxi-peninsula' ),
		),
		array(
			'title'   => __( 'Medical & hospital trips', 'taxi-peninsula' ),
			'icon'    => 'medical',
			'excerpt' => __( 'Appointments, dialysis, rehab and discharge pick-ups at hospitals across Melbourne and the Peninsula.', 'taxi-peninsula' ),
			'content' => __( "We take passengers to GP and specialist appointments, dialysis, rehabilitation, day surgery and hospital discharges.\n\nGive us your appointment time and we will plan the pick-up so you arrive relaxed. For regular treatment such as dialysis, you can set up repeat bookings in one go.\n\nIf you are being discharged and don't know the exact time yet, call us and we will arrange a pick-up when you are ready.", 'taxi-peninsula' ),
		),
		array(
			'title'   => __( 'Airport transfers', 'taxi-peninsula' ),
			'icon'    => 'plane',
			'excerpt' => __( 'Melbourne (Tullamarine) and Avalon airports, with room for luggage and mobility equipment.', 'taxi-peninsula' ),
			'content' => __( "Accessible transfers to and from Melbourne (Tullamarine) and Avalon airports.\n\nAdd your flight number in the booking notes. For arrivals, we will check your flight time and meet you at the agreed pick-up point.\n\nLet us know how much luggage and equipment you are travelling with so we can make sure it all fits.", 'taxi-peninsula' ),
		),
		array(
			'title'   => __( 'NDIS & aged care', 'taxi-peninsula' ),
			'icon'    => 'heart',
			'excerpt' => __( 'Regular transport for day programs, therapy and social outings. Account and invoice options available.', 'taxi-peninsula' ),
			'content' => __( "Reliable transport for NDIS participants and aged-care residents: day programs, therapy, work, shopping and social outings.\n\nPlan managers, support coordinators and providers can book on a participant's behalf and choose to be invoiced.", 'taxi-peninsula' ),
		),
		array(
			'title'   => __( 'Group & Maxi travel', 'taxi-peninsula' ),
			'icon'    => 'users',
			'excerpt' => __( 'Maxi vehicles for two wheelchairs or larger groups with carers.', 'taxi-peninsula' ),
			'content' => __( "Travelling with family, carers or more than one wheelchair user? Our Maxi vehicles carry larger groups, including two wheelchairs where available.\n\nChoose \"Maxi WAT\" when booking and tell us how many people and wheelchairs are travelling.", 'taxi-peninsula' ),
		),
		array(
			'title'   => __( 'Events & day trips', 'taxi-peninsula' ),
			'icon'    => 'calendar',
			'excerpt' => __( 'Weddings, footy, concerts and winery tours — booked ahead and on time.', 'taxi-peninsula' ),
			'content' => __( "From weddings and family celebrations to the footy, concerts and Peninsula winery tours — book ahead and we will get you there and home again.\n\nFor return trips, tick \"I need a return trip\" and tell us roughly when you'd like to be collected.", 'taxi-peninsula' ),
		),
	);
}

/**
 * Area pages keyed by lower-case title, for linking the "Areas we cover" chips.
 *
 * @return array title => url
 */
function tp_area_links() {
	static $links = null;
	if ( null !== $links ) {
		return $links;
	}
	$links = array();
	foreach ( get_posts( array( 'post_type' => 'tp_area', 'numberposts' => 200 ) ) as $p ) {
		$links[ strtolower( $p->post_title ) ] = get_permalink( $p );
	}
	return $links;
}

/* -------------------------------------------------------------------------
 * Enquiries admin list.
 * ---------------------------------------------------------------------- */

add_filter( 'manage_tp_message_posts_columns', static function () {
	return array(
		'cb'         => '<input type="checkbox">',
		'title'      => __( 'From', 'taxi-peninsula' ),
		'tp_contact' => __( 'Contact', 'taxi-peninsula' ),
		'tp_message' => __( 'Message', 'taxi-peninsula' ),
		'date'       => __( 'Received', 'taxi-peninsula' ),
	);
} );

add_action( 'manage_tp_message_posts_custom_column', static function ( $col, $post_id ) {
	if ( 'tp_contact' === $col ) {
		$email = get_post_meta( $post_id, '_tp_email', true );
		$phone = get_post_meta( $post_id, '_tp_phone', true );
		printf( '<a href="%s">%s</a>', esc_url( 'mailto:' . $email ), esc_html( $email ) );
		if ( $phone ) {
			printf( '<br><a href="%s">%s</a>', esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) ), esc_html( $phone ) );
		}
	} elseif ( 'tp_message' === $col ) {
		$topic = get_post_meta( $post_id, '_tp_topic', true );
		if ( $topic ) {
			echo '<strong>' . esc_html( $topic ) . '</strong><br>';
		}
		echo esc_html( wp_trim_words( get_post_field( 'post_content', $post_id ), 30 ) );
		if ( ! get_post_meta( $post_id, '_tp_read', true ) ) {
			echo ' <span class="tp-badge tp-badge--pending">' . esc_html__( 'New', 'taxi-peninsula' ) . '</span>';
		}
	}
}, 10, 2 );

add_filter( 'post_row_actions', static function ( $actions, $post ) {
	if ( 'tp_message' === $post->post_type ) {
		unset( $actions['inline hide-if-no-js'] );
		if ( isset( $actions['edit'] ) ) {
			$actions['edit'] = sprintf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $post->ID ) ), esc_html__( 'Read', 'taxi-peninsula' ) );
		}
	}
	return $actions;
}, 10, 2 );

add_action( 'add_meta_boxes_tp_message', static function () {
	remove_meta_box( 'submitdiv', 'tp_message', 'side' );
	add_meta_box( 'tp_message_body', __( 'Enquiry', 'taxi-peninsula' ), 'tp_render_message_box', 'tp_message', 'normal', 'high' );
} );

function tp_render_message_box( WP_Post $post ) {
	update_post_meta( $post->ID, '_tp_read', 1 );
	$email = get_post_meta( $post->ID, '_tp_email', true );
	$phone = get_post_meta( $post->ID, '_tp_phone', true );
	$topic = get_post_meta( $post->ID, '_tp_topic', true );
	echo '<table class="form-table" role="presentation"><tbody>';
	printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Name', 'taxi-peninsula' ), esc_html( $post->post_title ) );
	printf( '<tr><th>%s</th><td><a href="%s">%s</a></td></tr>', esc_html__( 'Email', 'taxi-peninsula' ), esc_url( 'mailto:' . $email ), esc_html( $email ) );
	if ( $phone ) {
		printf( '<tr><th>%s</th><td><a href="%s">%s</a></td></tr>', esc_html__( 'Phone', 'taxi-peninsula' ), esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) ), esc_html( $phone ) );
	}
	if ( $topic ) {
		printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Topic', 'taxi-peninsula' ), esc_html( $topic ) );
	}
	printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Received', 'taxi-peninsula' ), esc_html( get_the_date( 'j M Y g:i a', $post ) ) );
	printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Message', 'taxi-peninsula' ), wp_kses_post( wpautop( esc_html( $post->post_content ) ) ) );
	echo '</tbody></table>';
	printf(
		'<p><a class="button button-primary" href="%1$s">%2$s</a> <a class="button" href="%3$s">%4$s</a> <a class="submitdelete" style="margin-left:1em" href="%5$s">%6$s</a></p>',
		esc_url( 'mailto:' . $email . '?subject=' . rawurlencode( 'Re: ' . ( $topic ?: get_bloginfo( 'name' ) ) ) ),
		esc_html__( 'Reply by email', 'taxi-peninsula' ),
		esc_url( admin_url( 'edit.php?post_type=tp_message' ) ),
		esc_html__( 'Back to enquiries', 'taxi-peninsula' ),
		esc_url( get_delete_post_link( $post->ID ) ),
		esc_html__( 'Move to bin', 'taxi-peninsula' )
	);
}
