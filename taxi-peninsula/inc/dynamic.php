<?php
/**
 * Content that updates itself: live trust numbers, scheduled announcements,
 * time-of-day hero messages, "Message us" links and auto-unpublishing
 * seasonal content.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Live trust numbers.
 * ---------------------------------------------------------------------- */

/**
 * Round down to a "nice" figure: 4,873 → 4,800; 263 → 250.
 */
function tp_nice_floor( $n ) {
	if ( $n < 100 ) {
		return (int) $n;
	}
	$step = $n < 1000 ? 50 : ( $n < 10000 ? 100 : 1000 );
	return (int) ( floor( $n / $step ) * $step );
}

/**
 * Trust statistics (cached for 6 hours). Items are omitted until they're meaningful.
 *
 * @return array[] Each: [value, label].
 */
function tp_trust_stats() {
	$cached = get_transient( 'tp_trust_stats' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	global $wpdb;
	$completed = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = '_tp_status' AND pm.meta_value = 'completed'
		   AND p.post_type = 'tp_booking' AND p.post_status = 'publish'"
	);
	$trips  = $completed + absint( tp_opt( 'trips_offset' ) );
	$stats  = array();
	$min    = max( 1, absint( tp_opt( 'stats_min' ) ) );
	if ( $trips >= $min ) {
		$stats[] = array( number_format_i18n( tp_nice_floor( $trips ) ) . '+', __( 'trips completed', 'taxi-peninsula' ) );
	}
	$areas = count( tp_service_areas() );
	if ( $areas >= 5 ) {
		$stats[] = array( number_format_i18n( $areas ), __( 'suburbs & destinations served', 'taxi-peninsula' ) );
	}
	$fleet = count( tp_fleet_vehicles() );
	if ( $fleet >= 2 ) {
		$stats[] = array( number_format_i18n( $fleet ), __( 'accessible vehicles', 'taxi-peninsula' ) );
	}
	$since = absint( tp_opt( 'since_year' ) );
	if ( $since >= 1950 && $since <= (int) wp_date( 'Y' ) ) {
		$stats[] = array( (string) $since, __( 'serving passengers since', 'taxi-peninsula' ), true );
	}
	set_transient( 'tp_trust_stats', $stats, 6 * HOUR_IN_SECONDS );
	return $stats;
}

add_action( 'tp_booking_status_changed', static function () {
	delete_transient( 'tp_trust_stats' );
} );
add_action( 'customize_save_after', static function () {
	delete_transient( 'tp_trust_stats' );
} );

add_shortcode( 'tp_stats', static function () {
	ob_start();
	get_template_part( 'template-parts/stats' );
	return ob_get_clean();
} );

/* -------------------------------------------------------------------------
 * Announcement bar (scheduled notices).
 * ---------------------------------------------------------------------- */

add_action( 'init', static function () {
	register_post_type(
		'tp_notice',
		array(
			'labels'          => array(
				'name'          => __( 'Announcements', 'taxi-peninsula' ),
				'singular_name' => __( 'Announcement', 'taxi-peninsula' ),
				'add_new_item'  => __( 'Add announcement', 'taxi-peninsula' ),
				'edit_item'     => __( 'Edit announcement', 'taxi-peninsula' ),
				'all_items'     => __( 'Announcements', 'taxi-peninsula' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_position'   => 4,
			'menu_icon'       => 'dashicons-megaphone',
			'supports'        => array( 'title' ),
			'capability_type' => 'page',
		)
	);
} );

add_filter( 'enter_title_here', static function ( $text, $post ) {
	return 'tp_notice' === $post->post_type ? __( 'Message, e.g. "Very busy today — please book ahead or call us."', 'taxi-peninsula' ) : $text;
}, 10, 2 );

function tp_notice_styles() {
	return array(
		'info'    => __( 'Information (blue)', 'taxi-peninsula' ),
		'warning' => __( 'Heads-up (yellow)', 'taxi-peninsula' ),
		'urgent'  => __( 'Urgent (red)', 'taxi-peninsula' ),
	);
}

add_action( 'add_meta_boxes_tp_notice', static function () {
	add_meta_box( 'tp_notice_box', __( 'Schedule & link', 'taxi-peninsula' ), 'tp_render_notice_box', 'tp_notice', 'normal', 'high' );
} );

function tp_render_notice_box( WP_Post $post ) {
	wp_nonce_field( 'tp_notice', '_tp_notice_nonce' );
	$get   = static function ( $k ) use ( $post ) {
		return (string) get_post_meta( $post->ID, '_tp_' . $k, true );
	};
	$local = static function ( $ts ) {
		return $ts ? wp_date( 'Y-m-d\TH:i', (int) $ts ) : '';
	};
	?>
	<div class="tp-admin-grid">
		<p class="tp-admin-field"><label for="tp_n_start"><?php esc_html_e( 'Show from', 'taxi-peninsula' ); ?></label><input type="datetime-local" id="tp_n_start" name="tp_n[start]" value="<?php echo esc_attr( $local( $get( 'notice_start' ) ) ); ?>"><span class="description"><?php esc_html_e( 'Leave empty to show straight away.', 'taxi-peninsula' ); ?></span></p>
		<p class="tp-admin-field"><label for="tp_n_end"><?php esc_html_e( 'Show until', 'taxi-peninsula' ); ?></label><input type="datetime-local" id="tp_n_end" name="tp_n[end]" value="<?php echo esc_attr( $local( $get( 'notice_end' ) ) ); ?>"><span class="description"><?php esc_html_e( 'Leave empty to show until you unpublish it.', 'taxi-peninsula' ); ?></span></p>
		<p class="tp-admin-field"><label for="tp_n_style"><?php esc_html_e( 'Style', 'taxi-peninsula' ); ?></label>
			<select id="tp_n_style" name="tp_n[style]">
				<?php foreach ( tp_notice_styles() as $k => $label ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $get( 'notice_style' ) ?: 'info', $k ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="tp-admin-field"><label for="tp_n_url"><?php esc_html_e( 'Link (optional)', 'taxi-peninsula' ); ?></label><input type="url" id="tp_n_url" name="tp_n[url]" value="<?php echo esc_attr( $get( 'notice_url' ) ); ?>" placeholder="https://"></p>
		<p class="tp-admin-field"><label for="tp_n_link"><?php esc_html_e( 'Link text', 'taxi-peninsula' ); ?></label><input type="text" id="tp_n_link" name="tp_n[link]" value="<?php echo esc_attr( $get( 'notice_link' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. See holiday hours', 'taxi-peninsula' ); ?>"></p>
		<p class="tp-admin-field"><label><input type="checkbox" name="tp_n[sticky]" value="1" <?php checked( $get( 'notice_sticky' ), '1' ); ?>> <?php esc_html_e( 'Visitors cannot dismiss it (use sparingly)', 'taxi-peninsula' ); ?></label></p>
	</div>
	<?php
}

add_action( 'save_post_tp_notice', static function ( $post_id ) {
	if ( ! isset( $_POST['_tp_notice_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_notice_nonce'] ) ), 'tp_notice' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$in = isset( $_POST['tp_n'] ) && is_array( $_POST['tp_n'] ) ? wp_unslash( $_POST['tp_n'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.
	foreach ( array( 'start', 'end' ) as $k ) {
		$raw = sanitize_text_field( $in[ $k ] ?? '' );
		$dt  = $raw ? DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $raw, wp_timezone() ) : false;
		update_post_meta( $post_id, '_tp_notice_' . $k, $dt ? $dt->getTimestamp() : '' );
	}
	$style = sanitize_key( $in['style'] ?? 'info' );
	update_post_meta( $post_id, '_tp_notice_style', isset( tp_notice_styles()[ $style ] ) ? $style : 'info' );
	update_post_meta( $post_id, '_tp_notice_url', esc_url_raw( $in['url'] ?? '' ) );
	update_post_meta( $post_id, '_tp_notice_link', sanitize_text_field( $in['link'] ?? '' ) );
	update_post_meta( $post_id, '_tp_notice_sticky', empty( $in['sticky'] ) ? '' : '1' );
	delete_transient( 'tp_notices' );
} );

add_filter( 'manage_tp_notice_posts_columns', static function ( $cols ) {
	return array(
		'cb'        => $cols['cb'],
		'title'     => __( 'Message', 'taxi-peninsula' ),
		'tp_when'   => __( 'Showing', 'taxi-peninsula' ),
		'tp_style'  => __( 'Style', 'taxi-peninsula' ),
		'date'      => $cols['date'],
	);
} );

add_action( 'manage_tp_notice_posts_custom_column', static function ( $col, $id ) {
	if ( 'tp_style' === $col ) {
		echo esc_html( tp_notice_styles()[ get_post_meta( $id, '_tp_notice_style', true ) ?: 'info' ] ?? '' );
	} elseif ( 'tp_when' === $col ) {
		$start = (int) get_post_meta( $id, '_tp_notice_start', true );
		$end   = (int) get_post_meta( $id, '_tp_notice_end', true );
		$now   = time();
		$live  = 'publish' === get_post_status( $id ) && ( ! $start || $start <= $now ) && ( ! $end || $end > $now );
		echo $live ? '<strong style="color:#00a32a">' . esc_html__( 'Live now', 'taxi-peninsula' ) . '</strong><br>' : '';
		echo esc_html( ( $start ? wp_date( 'j M g:ia', $start ) : __( 'Now', 'taxi-peninsula' ) ) . ' → ' . ( $end ? wp_date( 'j M g:ia', $end ) : __( 'until unpublished', 'taxi-peninsula' ) ) );
	}
}, 10, 2 );

/**
 * Published notices that haven't ended yet (start/end are also checked in the
 * browser, so cached pages still switch on and off on time).
 *
 * @return array[]
 */
function tp_active_notices() {
	$notices = get_transient( 'tp_notices' );
	if ( ! is_array( $notices ) ) {
		$notices = array();
		foreach ( get_posts( array( 'post_type' => 'tp_notice', 'numberposts' => 10, 'orderby' => 'date', 'order' => 'DESC' ) ) as $n ) {
			$notices[] = array(
				'id'     => $n->ID,
				'text'   => $n->post_title,
				'start'  => (int) get_post_meta( $n->ID, '_tp_notice_start', true ),
				'end'    => (int) get_post_meta( $n->ID, '_tp_notice_end', true ),
				'style'  => get_post_meta( $n->ID, '_tp_notice_style', true ) ?: 'info',
				'url'    => get_post_meta( $n->ID, '_tp_notice_url', true ),
				'link'   => get_post_meta( $n->ID, '_tp_notice_link', true ),
				'sticky' => (bool) get_post_meta( $n->ID, '_tp_notice_sticky', true ),
				'ver'    => substr( md5( $n->post_title . $n->post_modified ), 0, 8 ),
			);
		}
		set_transient( 'tp_notices', $notices, HOUR_IN_SECONDS );
	}
	$now = time();
	return array_values(
		array_filter(
			$notices,
			static function ( $n ) use ( $now ) {
				return ! $n['end'] || $n['end'] > $now;
			}
		)
	);
}

add_action( 'transition_post_status', static function ( $new, $old, $post ) {
	if ( 'tp_notice' === $post->post_type ) {
		delete_transient( 'tp_notices' );
	}
}, 10, 3 );

function tp_render_notices() {
	foreach ( array_slice( tp_active_notices(), 0, 2 ) as $n ) {
		$hidden = $n['start'] && $n['start'] > time();
		printf(
			'<div class="announce announce--%1$s" role="region" aria-label="%2$s" data-notice="%3$s" data-start="%4$d" data-end="%5$d"%6$s>',
			esc_attr( $n['style'] ),
			esc_attr__( 'Announcement', 'taxi-peninsula' ),
			esc_attr( $n['id'] . '-' . $n['ver'] ),
			(int) $n['start'],
			(int) $n['end'],
			$hidden ? ' hidden' : ''
		);
		echo '<div class="container announce__inner"><p class="announce__text">';
		tp_the_icon( 'urgent' === $n['style'] ? 'shield' : 'megaphone' );
		echo '<span>' . esc_html( $n['text'] );
		if ( $n['url'] ) {
			printf( ' <a href="%s">%s</a>', esc_url( $n['url'] ), esc_html( $n['link'] ?: __( 'More details', 'taxi-peninsula' ) ) );
		}
		echo '</span></p>';
		if ( ! $n['sticky'] ) {
			printf( '<button type="button" class="announce__close" data-notice-close aria-label="%s">%s</button>', esc_attr__( 'Dismiss announcement', 'taxi-peninsula' ), tp_icon( 'close' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon.
		}
		echo '</div></div>';
	}
}

/* -------------------------------------------------------------------------
 * Time-of-day hero message.
 * ---------------------------------------------------------------------- */

/**
 * Parsed hero moments: [[from 'HH:MM', to 'HH:MM', message], …].
 */
function tp_hero_moments() {
	$out = array();
	foreach ( preg_split( '/\R/', (string) tp_opt( 'hero_moments' ) ) as $line ) {
		if ( preg_match( '/^\s*(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})\s*\|\s*(.+)$/', $line, $m ) ) {
			$out[] = array( str_pad( $m[1], 5, '0', STR_PAD_LEFT ), str_pad( $m[2], 5, '0', STR_PAD_LEFT ), trim( $m[3] ) );
		}
	}
	return $out;
}

/**
 * Message for a given HH:MM (site time). Windows may wrap past midnight.
 */
function tp_hero_moment_for( $hhmm ) {
	foreach ( tp_hero_moments() as $m ) {
		$in = $m[0] <= $m[1] ? ( $hhmm >= $m[0] && $hhmm <= $m[1] ) : ( $hhmm >= $m[0] || $hhmm <= $m[1] );
		if ( $in ) {
			return $m[2];
		}
	}
	return '';
}

/* -------------------------------------------------------------------------
 * "Message us" (SMS / WhatsApp).
 * ---------------------------------------------------------------------- */

function tp_sms_href() {
	$num = preg_replace( '/[^0-9+]/', '', (string) tp_opt( 'sms_number' ) );
	if ( ! $num ) {
		return '';
	}
	return 'sms:' . $num . '?&body=' . rawurlencode( __( 'Hi, I would like to book a wheelchair taxi.', 'taxi-peninsula' ) );
}

function tp_whatsapp_href() {
	$num = preg_replace( '/\D/', '', (string) tp_opt( 'whatsapp_number' ) );
	if ( ! $num ) {
		return '';
	}
	if ( 0 === strpos( $num, '0' ) ) {
		$num = '61' . substr( $num, 1 );
	}
	return 'https://wa.me/' . $num . '?text=' . rawurlencode( __( 'Hi, I would like to book a wheelchair taxi.', 'taxi-peninsula' ) );
}

/* -------------------------------------------------------------------------
 * Auto-unpublish (seasonal pages, promotions).
 * Schedule publishing with WordPress's own "Publish: Schedule" option; set
 * the end date here.
 * ---------------------------------------------------------------------- */

add_action( 'add_meta_boxes', static function () {
	foreach ( array( 'page', 'post' ) as $type ) {
		add_meta_box( 'tp_expire', __( 'Unpublish automatically', 'taxi-peninsula' ), 'tp_render_expire_box', $type, 'side', 'low' );
	}
} );

function tp_render_expire_box( WP_Post $post ) {
	wp_nonce_field( 'tp_expire', '_tp_expire_nonce' );
	$ts = (int) get_post_meta( $post->ID, '_tp_expire', true );
	printf(
		'<p><label for="tp_expire_at">%1$s</label><br><input type="datetime-local" id="tp_expire_at" name="tp_expire_at" value="%2$s" style="width:100%%"></p><p class="description">%3$s</p>',
		esc_html__( 'Move to drafts on', 'taxi-peninsula' ),
		esc_attr( $ts ? wp_date( 'Y-m-d\TH:i', $ts ) : '' ),
		esc_html__( 'For seasonal pages and promotions. Use "Publish → Schedule" to choose when it goes live.', 'taxi-peninsula' )
	);
}

add_action( 'save_post', static function ( $post_id, $post ) {
	if ( ! in_array( $post->post_type, array( 'page', 'post' ), true ) || ! isset( $_POST['_tp_expire_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_expire_nonce'] ) ), 'tp_expire' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$raw = isset( $_POST['tp_expire_at'] ) ? sanitize_text_field( wp_unslash( $_POST['tp_expire_at'] ) ) : '';
	$dt  = $raw ? DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $raw, wp_timezone() ) : false;
	if ( $dt ) {
		update_post_meta( $post_id, '_tp_expire', $dt->getTimestamp() );
	} else {
		delete_post_meta( $post_id, '_tp_expire' );
	}
}, 10, 2 );

add_action( 'init', static function () {
	if ( ! wp_next_scheduled( 'tp_expire_content' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'tp_expire_content' );
	}
} );

add_action( 'tp_expire_content', 'tp_expire_content' );
function tp_expire_content() {
	$due = get_posts(
		array(
			'post_type'   => array( 'page', 'post' ),
			'post_status' => 'publish',
			'numberposts' => 50,
			'fields'      => 'ids',
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array( 'key' => '_tp_expire', 'value' => time(), 'compare' => '<=', 'type' => 'NUMERIC' ),
			),
		)
	);
	foreach ( $due as $id ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
		delete_post_meta( $id, '_tp_expire' );
	}
}

/**
 * Also hide expired content immediately, even before the hourly job runs.
 */
add_action( 'template_redirect', static function () {
	if ( is_singular( array( 'page', 'post' ) ) && ! current_user_can( 'edit_posts' ) ) {
		$ts = (int) get_post_meta( get_queried_object_id(), '_tp_expire', true );
		if ( $ts && $ts <= time() ) {
			tp_expire_content();
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}
} );
