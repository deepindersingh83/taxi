<?php
/**
 * Home page builder: show, hide and reorder sections, and edit their
 * headings and intro text, from Appearance → Customize → Home page layout.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * Home page sections in their default order: key => [label, default title, default intro].
 * An empty default title means the section has no editable heading.
 */
function tp_home_sections() {
	return array(
		'stats'        => array( __( 'Live trust numbers', 'taxi-peninsula' ), '', '' ),
		'features'     => array( __( 'Why passengers choose us', 'taxi-peninsula' ), __( 'Why passengers choose us', 'taxi-peninsula' ), '' ),
		'journey'      => array( __( 'How a trip works (animated scenes)', 'taxi-peninsula' ), __( 'From your door to the departure gate', 'taxi-peninsula' ), __( 'We back right up to your door, lower the ramp and help you aboard — and we are waiting at Arrivals when you land.', 'taxi-peninsula' ) ),
		'who'          => array( __( 'Who we help', 'taxi-peninsula' ), __( 'Who we help', 'taxi-peninsula' ), __( 'Whether you are travelling yourself or arranging transport for someone else, we make it simple.', 'taxi-peninsula' ) ),
		'book'         => array( __( 'Booking form', 'taxi-peninsula' ), __( 'Book your accessible taxi', 'taxi-peninsula' ), __( 'Tell us where you are going and what you need. We will confirm your booking and let you know when your driver is on the way.', 'taxi-peninsula' ) ),
		'services'     => array( __( 'Services', 'taxi-peninsula' ), __( 'Our services', 'taxi-peninsula' ), __( 'Reliable wheelchair accessible transport for everyday trips and important appointments.', 'taxi-peninsula' ) ),
		'video'        => array( __( 'Wheelchair safety video', 'taxi-peninsula' ), '', '' ),
		'destinations' => array( __( 'Popular destinations', 'taxi-peninsula' ), __( 'Where our passengers go', 'taxi-peninsula' ), __( 'Our most-booked destinations this year. Tap one to book a trip there.', 'taxi-peninsula' ) ),
		'areas'        => array( __( 'Areas we cover + suburb checker', 'taxi-peninsula' ), __( 'Areas we cover', 'taxi-peninsula' ), __( 'Based on the Mornington Peninsula and servicing greater Melbourne. Not listed? Call us — we travel further on request.', 'taxi-peninsula' ) ),
		'faq'          => array( __( 'Top questions (FAQ strip)', 'taxi-peninsula' ), __( 'Questions we are often asked', 'taxi-peninsula' ), '' ),
		'reviews'      => array( __( 'Reviews & testimonials', 'taxi-peninsula' ), __( 'What our passengers say', 'taxi-peninsula' ), '' ),
		'content'      => array( __( 'Home page content (from the editor)', 'taxi-peninsula' ), '', '' ),
		'posts'        => array( __( 'Latest blog posts', 'taxi-peninsula' ), __( 'News & travel tips', 'taxi-peninsula' ), '' ),
		'partners'     => array( __( 'Partner logos', 'taxi-peninsula' ), __( 'Trusted by local organisations', 'taxi-peninsula' ), '' ),
		'cta'          => array( __( 'Call-to-action band', 'taxi-peninsula' ), __( 'Need a wheelchair taxi today?', 'taxi-peninsula' ), __( 'Call our friendly team and we will get you moving.', 'taxi-peninsula' ) ),
	);
}

/**
 * Current layout as [key => visible bool], in display order. New sections added
 * in theme updates are shown in their default position so they are never silently lost.
 */
function tp_home_layout() {
	$known  = tp_home_sections();
	$saved  = (string) get_theme_mod( 'tp_home_layout', '' );
	$layout = array();
	foreach ( array_filter( array_map( 'trim', explode( ',', $saved ) ) ) as $item ) {
		$hidden = 0 === strpos( $item, '-' );
		$key    = ltrim( $item, '-' );
		if ( isset( $known[ $key ] ) && ! isset( $layout[ $key ] ) ) {
			$layout[ $key ] = ! $hidden;
		}
	}
	// Sections added in a theme update go right after the section they follow by default.
	$prev = null;
	foreach ( array_keys( $known ) as $key ) {
		if ( ! isset( $layout[ $key ] ) ) {
			$pos    = null === $prev ? 0 : array_search( $prev, array_keys( $layout ), true ) + 1;
			$layout = array_slice( $layout, 0, $pos, true ) + array( $key => true ) + array_slice( $layout, $pos, null, true );
		}
		$prev = $key;
	}
	return $layout;
}

function tp_sanitize_home_layout( $value ) {
	$known = tp_home_sections();
	$out   = array();
	foreach ( array_filter( array_map( 'trim', explode( ',', (string) $value ) ) ) as $item ) {
		$key = ltrim( $item, '-' );
		if ( isset( $known[ $key ] ) ) {
			$out[] = ( 0 === strpos( $item, '-' ) ? '-' : '' ) . $key;
		}
	}
	return implode( ',', array_unique( $out ) );
}

/**
 * Editable heading / intro for a home section.
 *
 * @param string $key   Section key.
 * @param string $field 'title' or 'text'.
 */
function tp_home_text( $key, $field = 'title' ) {
	$sections = tp_home_sections();
	$default  = $sections[ $key ][ 'title' === $field ? 1 : 2 ] ?? '';
	$value    = get_theme_mod( 'tp_home_' . $key . '_' . $field, $default );
	return '' === trim( (string) $value ) ? $default : $value;
}

/* -------------------------------------------------------------------------
 * "Who we help" panels.
 * ---------------------------------------------------------------------- */

function tp_who_defaults() {
	return array(
		1 => array( 'wheelchair', __( 'Passengers', 'taxi-peninsula' ), __( 'Book your own trip online or by phone. We help you from your door to theirs.', 'taxi-peninsula' ), 'book', __( 'Book a trip', 'taxi-peninsula' ) ),
		2 => array( 'heart', __( 'Family & carers', 'taxi-peninsula' ), __( 'Booking for a parent, partner or client? Carers can travel too, and you can manage the booking online.', 'taxi-peninsula' ), 'faq', __( 'Tips for carers', 'taxi-peninsula' ) ),
		3 => array( 'users', __( 'NDIS coordinators & plan managers', 'taxi-peninsula' ), __( 'Set up regular trips for participants and have them invoiced to the right place.', 'taxi-peninsula' ), 'ndis', __( 'NDIS & accounts', 'taxi-peninsula' ) ),
		4 => array( 'medical', __( 'Hospitals & aged care', 'taxi-peninsula' ), __( 'Discharges, appointments and outings for your patients and residents. Ask us about an account.', 'taxi-peninsula' ), 'contact', __( 'Talk to us', 'taxi-peninsula' ) ),
	);
}

/**
 * Panels with Customizer overrides applied.
 *
 * @return array[] Each: icon, title, text, url, link.
 */
function tp_who_panels() {
	$out = array();
	foreach ( tp_who_defaults() as $n => $d ) {
		$title = get_theme_mod( "tp_who_{$n}_title", $d[1] );
		if ( '' === trim( (string) $title ) ) {
			continue; // Clearing the heading hides the panel.
		}
		$url = get_theme_mod( "tp_who_{$n}_url", '' );
		if ( ! $url ) {
			$url = 'book' === $d[3] ? tp_booking_page_url() : tp_page_url_by_template( $d[3], tp_booking_page_url() );
		}
		$out[] = array(
			'icon'  => $d[0],
			'title' => $title,
			'text'  => get_theme_mod( "tp_who_{$n}_text", $d[2] ),
			'url'   => $url,
			'link'  => get_theme_mod( "tp_who_{$n}_link", $d[4] ),
		);
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Customizer.
 * ---------------------------------------------------------------------- */

add_action( 'customize_register', 'tp_home_customize', 20 );
function tp_home_customize( WP_Customize_Manager $wp_customize ) {
	if ( ! class_exists( 'TP_Sortable_Sections_Control' ) ) {
		/**
		 * Accessible show/hide + reorder list (checkboxes and Up/Down buttons).
		 */
		class TP_Sortable_Sections_Control extends WP_Customize_Control {
			public $type = 'tp_sortable';

			public function render_content() {
				$labels = wp_list_pluck( tp_home_sections(), 0 );
				?>
				<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
				<?php if ( $this->description ) : ?>
					<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
				<?php endif; ?>
				<input type="hidden" class="tp-sortable-value" <?php $this->link(); ?> value="<?php echo esc_attr( $this->value() ); ?>">
				<ol class="tp-sortable">
					<?php foreach ( tp_home_layout() as $key => $visible ) : ?>
						<li class="tp-sortable__item" data-key="<?php echo esc_attr( $key ); ?>">
							<label><input type="checkbox" <?php checked( $visible ); ?>> <?php echo esc_html( $labels[ $key ] ); ?></label>
							<span class="tp-sortable__btns">
								<button type="button" class="button-link tp-up" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: section */ __( 'Move %s up', 'taxi-peninsula' ), $labels[ $key ] ) ); ?>">▲</button>
								<button type="button" class="button-link tp-down" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: section */ __( 'Move %s down', 'taxi-peninsula' ), $labels[ $key ] ) ); ?>">▼</button>
							</span>
						</li>
					<?php endforeach; ?>
				</ol>
				<?php
			}
		}
	}

	$wp_customize->add_section(
		'tp_home_layout',
		array(
			'title'       => __( 'Home page layout', 'taxi-peninsula' ),
			'panel'       => 'tp_panel',
			'priority'    => 5,
			'description' => __( 'Tick to show a section, untick to hide it, and use ▲ ▼ to reorder. The hero banner always stays at the top.', 'taxi-peninsula' ),
		)
	);
	$wp_customize->add_setting( 'tp_home_layout', array( 'default' => '', 'sanitize_callback' => 'tp_sanitize_home_layout' ) );
	$wp_customize->add_control( new TP_Sortable_Sections_Control( $wp_customize, 'tp_home_layout', array( 'section' => 'tp_home_layout', 'label' => __( 'Sections', 'taxi-peninsula' ) ) ) );

	$wp_customize->add_setting( 'tp_home_quickbook', array( 'default' => true, 'sanitize_callback' => 'tp_sanitize_checkbox' ) );
	$wp_customize->add_control( 'tp_home_quickbook', array( 'section' => 'tp_home_layout', 'type' => 'checkbox', 'label' => __( 'Show the quick-book bar in the hero (From, To, Date)', 'taxi-peninsula' ) ) );

	$wp_customize->add_section(
		'tp_home_text',
		array(
			'title'       => __( 'Home page headings & text', 'taxi-peninsula' ),
			'panel'       => 'tp_panel',
			'priority'    => 6,
			'description' => __( 'Leave a field empty to use the default wording.', 'taxi-peninsula' ),
		)
	);
	foreach ( tp_home_sections() as $key => $s ) {
		if ( $s[1] ) {
			$wp_customize->add_setting( "tp_home_{$key}_title", array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
			/* translators: %s: section */
			$wp_customize->add_control( "tp_home_{$key}_title", array( 'section' => 'tp_home_text', 'type' => 'text', 'label' => sprintf( __( '%s — heading', 'taxi-peninsula' ), $s[0] ), 'input_attrs' => array( 'placeholder' => $s[1] ) ) );
		}
		if ( $s[2] ) {
			$wp_customize->add_setting( "tp_home_{$key}_text", array( 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ) );
			/* translators: %s: section */
			$wp_customize->add_control( "tp_home_{$key}_text", array( 'section' => 'tp_home_text', 'type' => 'textarea', 'label' => sprintf( __( '%s — intro', 'taxi-peninsula' ), $s[0] ), 'input_attrs' => array( 'placeholder' => $s[2] ) ) );
		}
	}

	$wp_customize->add_section(
		'tp_who',
		array(
			'title'       => __( 'Who we help panels', 'taxi-peninsula' ),
			'panel'       => 'tp_panel',
			'priority'    => 7,
			'description' => __( 'Four short panels for different audiences. Clear a heading to hide that panel. Leave a link empty to use the matching page automatically.', 'taxi-peninsula' ),
		)
	);
	foreach ( tp_who_defaults() as $n => $d ) {
		foreach ( array(
			'title' => array( 'text', $d[1], 'sanitize_text_field', __( 'heading', 'taxi-peninsula' ) ),
			'text'  => array( 'textarea', $d[2], 'sanitize_textarea_field', __( 'text', 'taxi-peninsula' ) ),
			'link'  => array( 'text', $d[4], 'sanitize_text_field', __( 'link text', 'taxi-peninsula' ) ),
			'url'   => array( 'url', '', 'esc_url_raw', __( 'link (optional)', 'taxi-peninsula' ) ),
		) as $field => $f ) {
			$wp_customize->add_setting( "tp_who_{$n}_{$field}", array( 'default' => $f[1], 'sanitize_callback' => $f[2] ) );
			/* translators: 1: panel number, 2: field */
			$wp_customize->add_control( "tp_who_{$n}_{$field}", array( 'section' => 'tp_who', 'type' => $f[0], 'label' => sprintf( __( 'Panel %1$d — %2$s', 'taxi-peninsula' ), $n, $f[3] ) ) );
		}
	}
}

add_action( 'customize_controls_enqueue_scripts', static function () {
	wp_enqueue_script( 'tp-customizer', TP_URI . '/assets/js/customizer.js', array( 'jquery', 'customize-controls', 'wp-a11y' ), TP_VERSION, true );
	wp_add_inline_style( 'customize-controls', '.tp-sortable{margin:8px 0 0;list-style:none}.tp-sortable__item{display:flex;align-items:center;justify-content:space-between;gap:6px;padding:6px 8px;margin:0 0 4px;background:#fff;border:1px solid #dcdcde;border-radius:4px}.tp-sortable__item label{flex:1}.tp-sortable__btns button{padding:2px 6px;font-size:12px}.tp-sortable__item.is-hidden label{opacity:.6}' );
} );

/* -------------------------------------------------------------------------
 * Seasonal hero photos (Appearance → Hero photos).
 * ---------------------------------------------------------------------- */

add_action( 'init', static function () {
	register_post_type(
		'tp_hero',
		array(
			'labels'          => array(
				'name'               => __( 'Hero photos', 'taxi-peninsula' ),
				'singular_name'      => __( 'Hero photo', 'taxi-peninsula' ),
				'add_new_item'       => __( 'Add seasonal hero photo', 'taxi-peninsula' ),
				'edit_item'          => __( 'Edit hero photo', 'taxi-peninsula' ),
				'featured_image'     => __( 'Hero photo', 'taxi-peninsula' ),
				'set_featured_image' => __( 'Set hero photo', 'taxi-peninsula' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'themes.php',
			'supports'        => array( 'title', 'thumbnail' ),
			'capability_type' => 'page',
		)
	);
} );

add_action( 'add_meta_boxes_tp_hero', static function () {
	add_meta_box( 'tp_hero_box', __( 'When to show it', 'taxi-peninsula' ), static function ( WP_Post $post ) {
		wp_nonce_field( 'tp_hero', '_tp_hero_nonce' );
		$get = static function ( $k ) use ( $post ) {
			return (string) get_post_meta( $post->ID, '_tp_hero_' . $k, true );
		};
		?>
		<p><?php esc_html_e( 'Set the photo with "Hero photo" on the right (give it alt text in the Media Library). It replaces the normal hero image between these dates.', 'taxi-peninsula' ); ?></p>
		<div class="tp-admin-grid">
			<p class="tp-admin-field"><label for="tp_hero_start"><?php esc_html_e( 'From', 'taxi-peninsula' ); ?></label><input type="date" id="tp_hero_start" name="tp_hero[start]" value="<?php echo esc_attr( $get( 'start' ) ); ?>" required></p>
			<p class="tp-admin-field"><label for="tp_hero_end"><?php esc_html_e( 'Until (inclusive)', 'taxi-peninsula' ); ?></label><input type="date" id="tp_hero_end" name="tp_hero[end]" value="<?php echo esc_attr( $get( 'end' ) ); ?>" required></p>
			<p class="tp-admin-field"><label><input type="checkbox" name="tp_hero[yearly]" value="1" <?php checked( $get( 'yearly' ), '1' ); ?>> <?php esc_html_e( 'Repeat every year (e.g. every December)', 'taxi-peninsula' ); ?></label></p>
		</div>
		<?php
	}, 'tp_hero', 'normal', 'high' );
} );

add_action( 'save_post_tp_hero', static function ( $post_id ) {
	if ( ! isset( $_POST['_tp_hero_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_hero_nonce'] ) ), 'tp_hero' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$in = isset( $_POST['tp_hero'] ) && is_array( $_POST['tp_hero'] ) ? wp_unslash( $_POST['tp_hero'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
	foreach ( array( 'start', 'end' ) as $k ) {
		$v = sanitize_text_field( $in[ $k ] ?? '' );
		update_post_meta( $post_id, '_tp_hero_' . $k, preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '' );
	}
	update_post_meta( $post_id, '_tp_hero_yearly', empty( $in['yearly'] ) ? '' : '1' );
	delete_transient( 'tp_hero_items' );
} );

add_action( 'transition_post_status', static function ( $new, $old, $post ) {
	if ( 'tp_hero' === $post->post_type ) {
		delete_transient( 'tp_hero_items' );
	}
}, 10, 3 );

/**
 * Is a seasonal photo active on a date? Yearly items compare month-day only
 * (and may span New Year, e.g. 20 Dec → 5 Jan).
 */
function tp_hero_is_active( $start, $end, $yearly, $today ) {
	if ( ! $start || ! $end ) {
		return false;
	}
	if ( ! $yearly ) {
		return $today >= $start && $today <= $end;
	}
	$t = substr( $today, 5 );
	$s = substr( $start, 5 );
	$e = substr( $end, 5 );
	return $s <= $e ? ( $t >= $s && $t <= $e ) : ( $t >= $s || $t <= $e );
}

/**
 * Attachment ID for the hero: an active seasonal photo, else the Customizer image.
 */
function tp_current_hero_image() {
	$items = get_transient( 'tp_hero_items' );
	if ( ! is_array( $items ) ) {
		$items = array();
		foreach ( get_posts( array( 'post_type' => 'tp_hero', 'numberposts' => 30 ) ) as $p ) {
			if ( has_post_thumbnail( $p ) ) {
				$items[] = array(
					'img'    => (int) get_post_thumbnail_id( $p ),
					'start'  => get_post_meta( $p->ID, '_tp_hero_start', true ),
					'end'    => get_post_meta( $p->ID, '_tp_hero_end', true ),
					'yearly' => (bool) get_post_meta( $p->ID, '_tp_hero_yearly', true ),
				);
			}
		}
		set_transient( 'tp_hero_items', $items, DAY_IN_SECONDS );
	}
	$today = wp_date( 'Y-m-d' );
	foreach ( $items as $it ) {
		if ( tp_hero_is_active( $it['start'], $it['end'], $it['yearly'], $today ) ) {
			return $it['img'];
		}
	}
	return (int) tp_opt( 'hero_image' );
}

add_filter( 'manage_tp_hero_posts_columns', static function ( $cols ) {
	return array(
		'cb'        => $cols['cb'],
		'thumb'     => __( 'Photo', 'taxi-peninsula' ),
		'title'     => __( 'Name', 'taxi-peninsula' ),
		'tp_when'   => __( 'Shown', 'taxi-peninsula' ),
	);
} );
add_action( 'manage_tp_hero_posts_custom_column', static function ( $col, $id ) {
	if ( 'thumb' === $col ) {
		echo get_the_post_thumbnail( $id, array( 80, 50 ) );
	} elseif ( 'tp_when' === $col ) {
		$s = get_post_meta( $id, '_tp_hero_start', true );
		$e = get_post_meta( $id, '_tp_hero_end', true );
		$y = get_post_meta( $id, '_tp_hero_yearly', true );
		if ( $s && $e ) {
			$fmt = $y ? 'j M' : 'j M Y';
			echo esc_html( wp_date( $fmt, strtotime( $s ) ) . ' → ' . wp_date( $fmt, strtotime( $e ) ) . ( $y ? ' ' . __( '(every year)', 'taxi-peninsula' ) : '' ) );
			if ( 'publish' === get_post_status( $id ) && tp_hero_is_active( $s, $e, (bool) $y, wp_date( 'Y-m-d' ) ) ) {
				echo '<br><strong style="color:#00a32a">' . esc_html__( 'Showing now', 'taxi-peninsula' ) . '</strong>';
			}
		}
	}
}, 10, 2 );
