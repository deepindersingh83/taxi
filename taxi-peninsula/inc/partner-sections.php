<?php
/**
 * Partner sections (two rows of moving logos, numbers and one button):
 *
 * - Travel partners: hospitals, care homes, clubs and businesses that book
 *   with you. Logos from "Partner logos" (set to Travel partners) and from
 *   partner pages with "show our logo" ticked. Home page, [tp_partners].
 * - NDIS & funding partners: schemes you accept and the plan managers and
 *   support coordinators you work with. Logos from "Partner logos" (set to
 *   NDIS & funding); a scheme without an uploaded logo shows as a neat text
 *   tile. Home page, top of the NDIS & aged-care page, [tp_funding_partners].
 *
 * Every logo needs the permission tick. Numbers are counted from your
 * bookings and logos, or typed in under Settings → Marketing & SEO.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_partner_kinds() {
	return array(
		'scheme'       => __( 'Funding scheme', 'taxi-peninsula' ),
		'plan_manager' => __( 'Plan manager', 'taxi-peninsula' ),
		'coordinator'  => __( 'Support coordinator', 'taxi-peninsula' ),
		'accredited'   => __( 'Accreditation', 'taxi-peninsula' ),
		'hospital'     => __( 'Hospital or clinic', 'taxi-peninsula' ),
		'aged_care'    => __( 'Aged care', 'taxi-peninsula' ),
		'ndis'         => __( 'NDIS provider', 'taxi-peninsula' ),
		'business'     => __( 'Business or club', 'taxi-peninsula' ),
		'other'        => __( 'Other', 'taxi-peninsula' ),
	);
}

add_filter(
	'tp_settings_sections',
	static function ( $s ) {
		$s['partners'] = array( __( 'Partner sections', 'taxi-peninsula' ), __( 'The two partner sections on the home page. Numbers left empty are counted automatically. Logos are added under Website content → Partners.', 'taxi-peninsula' ) );
		return $s;
	}
);
add_filter(
	'tp_settings_tabs',
	static function ( $tabs ) {
		if ( isset( $tabs['marketing'] ) && ! in_array( 'partners', $tabs['marketing'][1], true ) ) {
			$tabs['marketing'][1][] = 'partners';
		}
		return $tabs;
	}
);
add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$auto = __( 'Leave empty to count automatically.', 'taxi-peninsula' );
		$s['pp_stat_partners'] = array( 'partners', __( 'Travel partners: number of partner organisations', 'taxi-peninsula' ), 'number', '', $auto );
		$s['pp_stat_trips']    = array( 'partners', __( 'Travel partners: partner trips this year', 'taxi-peninsula' ), 'number', '', __( 'Counted from completed trips booked through partner pages. Leave empty to count automatically.', 'taxi-peninsula' ) );
		$s['pp_stat_schemes']  = array( 'partners', __( 'NDIS & funding: number of funding schemes accepted', 'taxi-peninsula' ), 'number', '', $auto );
		$s['pp_stat_managers'] = array( 'partners', __( 'NDIS & funding: number of plan managers and coordinators', 'taxi-peninsula' ), 'number', '', $auto );
		$s['pp_travel_more']   = array( 'partners', __( 'Travel partners: “How partnering works” link', 'taxi-peninsula' ), 'url', '', __( 'Optional page explaining partner accounts, QR posters and the partner portal. Hidden when empty.', 'taxi-peninsula' ) );
		$s['pp_funding_more']  = array( 'partners', __( 'NDIS & funding: “NDIS transport guide” link', 'taxi-peninsula' ), 'url', '', __( 'Leave empty to use your NDIS & aged-care page.', 'taxi-peninsula' ) );
		return $s;
	}
);

/**
 * Logos for a section, in display order.
 *
 * @param string $group travel|funding.
 * @return array[] [ name, logo (attachment id or 0), url, kind ]
 */
function tp_partner_items( $group ) {
	static $cache = array();
	if ( isset( $cache[ $group ] ) ) {
		return $cache[ $group ];
	}
	$items = array();
	$posts = get_posts(
		array(
			'post_type'   => 'tp_partner',
			'post_status' => 'publish',
			'numberposts' => 60,
			'orderby'     => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			'meta_key'    => '_tp_permission', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	foreach ( $posts as $p ) {
		$g = get_post_meta( $p->ID, '_tp_group', true ) ?: 'travel';
		if ( $g !== $group ) {
			continue;
		}
		$logo = (int) get_post_thumbnail_id( $p );
		if ( ! $logo && 'travel' === $group ) {
			continue; // Travel partners always show a logo.
		}
		$items[] = array(
			'name' => get_the_title( $p ),
			'logo' => $logo,
			'url'  => (string) get_post_meta( $p->ID, '_tp_url', true ),
			'kind' => (string) get_post_meta( $p->ID, '_tp_kind', true ),
		);
	}
	if ( 'travel' === $group ) {
		// Partner pages (hospitals, care homes…) that agreed to show their logo; they link to their booking page.
		foreach ( get_posts( array( 'post_type' => 'tp_venue', 'post_status' => 'publish', 'numberposts' => 60, 'orderby' => 'title', 'order' => 'ASC', 'meta_key' => '_tp_v_show_logo', 'meta_value' => '1' ) ) as $v ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			if ( has_post_thumbnail( $v ) ) {
				$items[] = array( 'name' => get_the_title( $v ), 'logo' => (int) get_post_thumbnail_id( $v ), 'url' => get_permalink( $v ), 'kind' => (string) get_post_meta( $v->ID, '_tp_v_type', true ) );
			}
		}
	}
	$cache[ $group ] = apply_filters( 'tp_partner_items', $items, $group );
	return $cache[ $group ];
}

/**
 * The two numbers for a section: [ [number, label, icon], … ] (zeros left out).
 */
function tp_partner_stats( $group ) {
	$set = static fn( $key ) => '' === trim( (string) tp_setting( $key ) ) ? null : max( 0, (int) tp_setting( $key ) );
	$out = array();
	if ( 'travel' === $group ) {
		$orgs  = $set( 'pp_stat_partners' ) ?? count( tp_partner_items( 'travel' ) );
		$trips = $set( 'pp_stat_trips' );
		if ( null === $trips ) {
			$trips = (int) get_transient( 'tp_pp_trips' );
			if ( ! $trips && false === get_transient( 'tp_pp_trips' ) ) {
				global $wpdb;
				$from = wp_date( 'Y' ) . '-01-01';
				if ( function_exists( 'tp_trips_ready' ) && tp_trips_ready() ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$trips = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . tp_trips_table() . " WHERE venue_id > 0 AND status = 'completed' AND date >= %s", $from ) );
				} else {
					$trips = count( get_posts( array( 'post_type' => 'tp_booking', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_tp_venue_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ), array( 'key' => '_tp_status', 'value' => 'completed' ), array( 'key' => '_tp_date', 'value' => $from, 'compare' => '>=' ) ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
				}
				set_transient( 'tp_pp_trips', $trips, 6 * HOUR_IN_SECONDS );
			}
		}
		$out[] = array( $orgs, _n( 'partner organisation', 'partner organisations', $orgs, 'taxi-peninsula' ), 'building' );
		$out[] = array( $trips, _n( 'partner trip this year', 'partner trips this year', $trips, 'taxi-peninsula' ), 'car' );
	} else {
		$items    = tp_partner_items( 'funding' );
		$schemes  = $set( 'pp_stat_schemes' ) ?? count( array_filter( $items, static fn( $i ) => 'scheme' === $i['kind'] ) );
		$managers = $set( 'pp_stat_managers' ) ?? count( array_filter( $items, static fn( $i ) => in_array( $i['kind'], array( 'plan_manager', 'coordinator' ), true ) ) );
		$out[]    = array( $schemes, _n( 'funding scheme accepted', 'funding schemes accepted', $schemes, 'taxi-peninsula' ), 'shield' );
		$out[]    = array( $managers, _n( 'plan manager & coordinator', 'plan managers & coordinators', $managers, 'taxi-peninsula' ), 'people' );
	}
	return array_values( array_filter( $out, static fn( $s ) => $s[0] > 0 ) );
}

// Fresh trip counts after each completed trip.
add_action(
	'tp_booking_status_changed',
	static function ( $id, $old, $status ) {
		if ( 'completed' === $status ) {
			delete_transient( 'tp_pp_trips' );
		}
	},
	10,
	3
);

/**
 * Heading, intro and buttons for a section.
 */
function tp_partner_section_text( $group ) {
	if ( 'travel' === $group ) {
		$contact = tp_page_url_by_template( 'contact' );
		return array(
			'chip'  => __( 'Travel partners', 'taxi-peninsula' ),
			'title' => tp_home_text( 'partners' ),
			'intro' => tp_home_text( 'partners', 'intro' ),
			'btn'   => array( __( 'Become a partner', 'taxi-peninsula' ), $contact ? add_query_arg( 'topic', sanitize_title( __( 'Become a partner', 'taxi-peninsula' ) ), $contact ) . '#contact' : 'tel:' . preg_replace( '/[^\d+]/', '', tp_opt( 'phone_link' ) ) ),
			'more'  => array( __( 'How partnering works', 'taxi-peninsula' ), (string) tp_setting( 'pp_travel_more' ) ),
		);
	}
	$cb = function_exists( 'tp_callback_url' ) ? tp_callback_url() : '';
	return array(
		'chip'  => __( 'NDIS & funding partners', 'taxi-peninsula' ),
		'title' => tp_home_text( 'funding' ),
		'intro' => tp_home_text( 'funding', 'intro' ),
		'btn'   => array( __( 'Check your funding', 'taxi-peninsula' ), $cb ? add_query_arg( 'reason', 'funding', $cb ) . '#callback' : 'tel:' . preg_replace( '/[^\d+]/', '', tp_opt( 'phone_link' ) ) ),
		'more'  => array( __( 'NDIS transport guide', 'taxi-peninsula' ), (string) tp_setting( 'pp_funding_more' ) ?: tp_page_url_by_template( 'ndis' ) ),
	);
}

/**
 * Print a section.
 *
 * @param string $group travel|funding.
 * @param bool   $inset Inside page content (rounded box) rather than full width.
 */
function tp_partner_section( $group, $inset = false ) {
	if ( ! tp_partner_items( $group ) ) {
		return;
	}
	get_template_part( 'template-parts/partner-section', null, array( 'group' => $group, 'inset' => $inset ) );
}

function tp_partner_section_html( $group, $inset = false ) {
	ob_start();
	tp_partner_section( $group, $inset );
	return ob_get_clean();
}

add_shortcode( 'tp_funding_partners', static fn() => tp_partner_section_html( 'funding', true ) );

// NDIS & aged-care page: funding partners at the top (unless placed in the content already).
add_filter(
	'the_content',
	static function ( $content ) {
		static $done = false;
		$ndis = (int) ( ( (array) get_option( 'tp_pages', array() ) )['ndis'] ?? 0 );
		// Only the page's own content, once (other parts of the page also run the content filter).
		if ( $done || ! $ndis || ! is_page( $ndis ) || get_the_ID() !== $ndis || ! in_the_loop() || ! is_main_query() || doing_filter( 'get_the_excerpt' ) || has_shortcode( $content, 'tp_funding_partners' ) ) {
			return $content;
		}
		$done = true;
		return tp_partner_section_html( 'funding', true ) . $content;
	},
	9
);

/* -------------------------------------------------------------------------
 * Admin: which section a logo belongs to; partner pages opt in.
 * ---------------------------------------------------------------------- */

add_action(
	'tp_partner_box_fields',
	static function ( WP_Post $post ) {
		$group = get_post_meta( $post->ID, '_tp_group', true ) ?: 'travel';
		$kind  = get_post_meta( $post->ID, '_tp_kind', true );
		?>
		<fieldset style="margin:12px 0"><legend><strong><?php esc_html_e( 'Show in', 'taxi-peninsula' ); ?></strong></legend>
			<label style="display:block;margin:4px 0"><input type="radio" name="tp_partner_group" value="travel" <?php checked( $group, 'travel' ); ?>> <?php esc_html_e( 'Travel partners — organisations that book trips with us (hospitals, care homes, clubs, businesses)', 'taxi-peninsula' ); ?></label>
			<label style="display:block;margin:4px 0"><input type="radio" name="tp_partner_group" value="funding" <?php checked( $group, 'funding' ); ?>> <?php esc_html_e( 'NDIS & funding partners — schemes we accept, plan managers, support coordinators', 'taxi-peninsula' ); ?></label>
		</fieldset>
		<p><label for="tp_partner_kind"><strong><?php esc_html_e( 'Type', 'taxi-peninsula' ); ?></strong></label><br>
			<select id="tp_partner_kind" name="tp_partner_kind">
				<option value=""><?php esc_html_e( '— Choose —', 'taxi-peninsula' ); ?></option>
				<?php foreach ( tp_partner_kinds() as $k => $label ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $kind, $k ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select><br>
			<span class="description"><?php esc_html_e( 'Used for the numbers (schemes, plan managers) and, for an NDIS & funding partner without a logo, shown under its name on a text tile.', 'taxi-peninsula' ); ?></span></p>
		<p class="description"><?php esc_html_e( 'Official logos (NDIS, MPTP, TAC, DVA…) belong to government bodies: only upload a version you are allowed to use and follow its rules. Without a logo, NDIS & funding partners show as a neat text tile, which needs no permission from the scheme.', 'taxi-peninsula' ); ?></p>
		<?php
	}
);
add_action(
	'save_post_tp_partner',
	static function ( $post_id ) {
		if ( ! isset( $_POST['_tp_partner_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_partner_nonce'] ) ), 'tp_partner' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$group = sanitize_key( wp_unslash( $_POST['tp_partner_group'] ?? 'travel' ) );
		$kind  = sanitize_key( wp_unslash( $_POST['tp_partner_kind'] ?? '' ) );
		update_post_meta( $post_id, '_tp_group', in_array( $group, array( 'travel', 'funding' ), true ) ? $group : 'travel' );
		update_post_meta( $post_id, '_tp_kind', isset( tp_partner_kinds()[ $kind ] ) ? $kind : '' );
	},
	11
);

// Partner pages: "show our logo in the Travel partners section".
add_action(
	'tp_venue_box_fields',
	static function ( WP_Post $post ) {
		?>
		<p><label><input type="checkbox" name="tp_v_show_logo" value="1" <?php checked( get_post_meta( $post->ID, '_tp_v_show_logo', true ), '1' ); ?>> <strong><?php esc_html_e( 'Show their logo in the Travel partners section on our website', 'taxi-peninsula' ); ?></strong></label><br>
			<span class="description"><?php esc_html_e( 'Only tick this with the partner’s permission. Uses the featured image as the logo and links to their booking page.', 'taxi-peninsula' ); ?></span></p>
		<?php
	}
);
add_action(
	'save_post_tp_venue',
	static function ( $post_id ) {
		if ( ! isset( $_POST['tp_venue_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tp_venue_nonce'] ), 'tp_venue_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_tp_v_show_logo', empty( $_POST['tp_v_show_logo'] ) ? '' : '1' );
	},
	11
);

// "Partners" list: show the section and type.
add_filter(
	'manage_tp_partner_posts_columns',
	static function ( $cols ) {
		return array_slice( $cols, 0, 2, true ) + array( 'tp_group' => __( 'Section', 'taxi-peninsula' ), 'tp_ok' => __( 'Permission', 'taxi-peninsula' ) ) + array_slice( $cols, 2, null, true );
	}
);
add_action(
	'manage_tp_partner_posts_custom_column',
	static function ( $col, $id ) {
		if ( 'tp_group' === $col ) {
			$kind = tp_partner_kinds()[ get_post_meta( $id, '_tp_kind', true ) ] ?? '';
			echo esc_html( ( 'funding' === get_post_meta( $id, '_tp_group', true ) ? __( 'NDIS & funding', 'taxi-peninsula' ) : __( 'Travel partners', 'taxi-peninsula' ) ) . ( $kind ? ' · ' . $kind : '' ) );
		} elseif ( 'tp_ok' === $col ) {
			echo get_post_meta( $id, '_tp_permission', true ) ? '✓' : '<span style="color:#b32d2e">' . esc_html__( 'Not yet — hidden', 'taxi-peninsula' ) . '</span>';
		}
	},
	10,
	2
);
