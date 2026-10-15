<?php
/**
 * Partner landing pages and QR codes for hospitals, aged-care homes and
 * NDIS providers.
 *
 * Bookings → Partner pages. Each partner gets its own page at
 * /book-from/{name}/ with its logo, a short welcome, and a booking card
 * with its pick-up address already filled in. The printable poster
 * (with a QR code) goes on the reception desk: scanning it opens the page.
 *
 * Visits, QR scans and bookings from each page are counted (shown in the
 * list and under Bookings → Leads & calls). Optional: a promo code applied
 * automatically, and account/invoice details filled in for the partner.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_venue_types() {
	return array(
		'hospital'  => __( 'Hospital or clinic', 'taxi-peninsula' ),
		'aged_care' => __( 'Aged-care home', 'taxi-peninsula' ),
		'ndis'      => __( 'NDIS provider', 'taxi-peninsula' ),
		'day'       => __( 'Day program or community centre', 'taxi-peninsula' ),
		'other'     => __( 'Other', 'taxi-peninsula' ),
	);
}

add_action(
	'init',
	static function () {
		register_post_type(
			'tp_venue',
			array(
				'labels'          => array(
					'name'          => __( 'Partner pages', 'taxi-peninsula' ),
					'singular_name' => __( 'Partner page', 'taxi-peninsula' ),
					'add_new_item'  => __( 'Add partner page', 'taxi-peninsula' ),
					'edit_item'     => __( 'Edit partner page', 'taxi-peninsula' ),
					'view_item'     => __( 'View partner page', 'taxi-peninsula' ),
				),
				'public'              => true,
				'exclude_from_search' => true,
				'show_in_nav_menus'   => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=tp_booking',
				'show_in_rest'        => true,
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'rewrite'             => array( 'slug' => 'book-from', 'with_front' => false ),
				'capability_type'     => 'tp_booking',
				'map_meta_cap'        => true,
			)
		);
	}
);

// Partner pages are for people already at the venue: keep them out of search results.
add_filter(
	'wp_robots',
	static function ( $robots ) {
		if ( is_singular( 'tp_venue' ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
);
add_filter(
	'wp_sitemaps_post_types',
	static function ( $types ) {
		unset( $types['tp_venue'] );
		return $types;
	}
);

function tp_venue_meta( $id ) {
	$m = array();
	foreach ( array( 'type', 'pickup', 'pickup_note', 'contact', 'contact_email', 'phone', 'promo', 'invoice_name', 'invoice_email' ) as $k ) {
		$m[ $k ] = (string) get_post_meta( $id, '_tp_v_' . $k, true );
	}
	$m['type'] = $m['type'] ?: 'hospital';
	return $m;
}

/**
 * Link for the QR code (counted as a scan).
 */
function tp_venue_qr_url( $id ) {
	return add_query_arg( 'qr', 1, get_permalink( $id ) );
}

/**
 * Venue from ?venue=ID on the booking page, else 0.
 */
function tp_venue_from_request() {
	$id = isset( $_REQUEST['venue'] ) ? absint( $_REQUEST['venue'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return ( $id && 'tp_venue' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) ? $id : 0;
}

/* -------------------------------------------------------------------------
 * Front end.
 * ---------------------------------------------------------------------- */

// Count visits and scans; partner becomes the visit source.
add_action(
	'template_redirect',
	static function () {
		if ( ! is_singular( 'tp_venue' ) || ! function_exists( 'tp_track' ) ) {
			return;
		}
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only matched.
		if ( preg_match( '/bot|crawl|spider|preview|curl|wget/i', $ua ) || is_user_logged_in() && current_user_can( 'edit_tp_bookings' ) ) {
			return;
		}
		$id   = get_queried_object_id();
		$slug = get_post_field( 'post_name', $id );
		$val  = $slug . '|partner|' . ( ! empty( $_GET['qr'] ) ? 'qr' : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! headers_sent() ) {
			setcookie( 'tp_src', $val, array( 'expires' => time() + 30 * DAY_IN_SECONDS, 'path' => COOKIEPATH ?: '/', 'domain' => COOKIE_DOMAIN ?: '', 'secure' => is_ssl(), 'httponly' => false, 'samesite' => 'Lax' ) );
		}
		$_COOKIE['tp_src'] = $val;
		tp_track( ! empty( $_GET['qr'] ) ? 'qr' : 'view', $id ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
);

add_filter(
	'template_include',
	static function ( $template ) {
		if ( is_singular( 'tp_venue' ) ) {
			$found = locate_template( 'single-tp_venue.php' );
			return $found ?: $template;
		}
		return $template;
	}
);

// Booking form: pick-up, invoice details and promo from the partner page.
add_filter(
	'tp_booking_form_link_prefill',
	static function ( $old ) {
		$id = tp_venue_from_request();
		if ( ! $id ) {
			return $old;
		}
		$m = tp_venue_meta( $id );
		if ( empty( $old['pickup'] ) && $m['pickup'] ) {
			$old['pickup'] = $m['pickup'];
		}
		if ( $m['promo'] && empty( $old['promo'] ) ) {
			$old['promo'] = $m['promo'];
		}
		if ( $m['invoice_name'] ) {
			$old['invoice_name']  = $old['invoice_name'] ?? $m['invoice_name'];
			$old['invoice_email'] = $old['invoice_email'] ?? $m['invoice_email'];
		}
		if ( $m['pickup_note'] && empty( $old['notes'] ) ) {
			$old['notes'] = $m['pickup_note'];
		}
		$old['venue'] = $id;
		return $old;
	}
);

add_action(
	'tp_booking_form_hidden',
	static function ( $old ) {
		if ( ! empty( $old['venue'] ) && 'tp_venue' === get_post_type( (int) $old['venue'] ) ) {
			printf( '<input type="hidden" name="venue" value="%d">', (int) $old['venue'] );
		}
	}
);

add_action(
	'tp_booking_created',
	static function ( $post_id, $ids ) {
		$id = tp_venue_from_request();
		if ( ! $id && function_exists( 'tp_visit_source' ) ) {
			list( $source, $medium ) = tp_visit_source();
			if ( 'partner' === $medium && $source ) {
				$p  = get_page_by_path( $source, OBJECT, 'tp_venue' );
				$id = $p ? $p->ID : 0;
			}
		}
		if ( ! $id ) {
			return;
		}
		foreach ( (array) $ids as $bid ) {
			update_post_meta( $bid, '_tp_venue_id', $id );
		}
		$m = tp_venue_meta( $id );
		if ( $m['contact_email'] && is_email( $m['contact_email'] ) && get_post_meta( $id, '_tp_v_notify', true ) ) {
			$b = tp_get_booking( $post_id );
			wp_mail(
				$m['contact_email'],
				/* translators: 1: reference 2: partner */
				sprintf( __( 'Taxi booked from %2$s (%1$s)', 'taxi-peninsula' ), $b['reference'], get_the_title( $id ) ),
				/* translators: 1: name 2: pick-up time */
				sprintf( __( 'A wheelchair taxi has been booked for %1$s, pick-up %2$s. Reference: %3$s.', 'taxi-peninsula' ), $b['name'], tp_format_pickup( $b ), $b['reference'] ) . "\n\n" . get_bloginfo( 'name' ),
				tp_mail_headers( tp_opt( 'email' ) )
			);
		}
	},
	35,
	2
);

/* -------------------------------------------------------------------------
 * Admin.
 * ---------------------------------------------------------------------- */

add_action(
	'add_meta_boxes_tp_venue',
	static function () {
		add_meta_box( 'tp_venue_box', __( 'Partner details', 'taxi-peninsula' ), 'tp_render_venue_box', 'tp_venue', 'normal', 'high' );
		add_meta_box( 'tp_venue_qr', __( 'QR code & poster', 'taxi-peninsula' ), 'tp_render_venue_qr_box', 'tp_venue', 'side', 'default' );
	}
);

function tp_render_venue_box( $post ) {
	$m = tp_venue_meta( $post->ID );
	wp_nonce_field( 'tp_venue_save', 'tp_venue_nonce' );
	$row = static function ( $key, $label, $type = 'text', $help = '' ) use ( $m ) {
		printf( '<tr><th><label for="tp_v_%1$s">%2$s</label></th><td>', esc_attr( $key ), esc_html( $label ) );
		if ( 'textarea' === $type ) {
			printf( '<textarea class="large-text" rows="2" id="tp_v_%1$s" name="tp_v[%1$s]">%2$s</textarea>', esc_attr( $key ), esc_textarea( $m[ $key ] ) );
		} else {
			printf( '<input type="%3$s" class="regular-text" id="tp_v_%1$s" name="tp_v[%1$s]" value="%2$s">', esc_attr( $key ), esc_attr( $m[ $key ] ), esc_attr( $type ) );
		}
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	};
	?>
	<p class="description"><?php esc_html_e( 'The title is the partner’s name. Use the main editor for a short welcome, the featured image for their logo, and the excerpt for one line under the name.', 'taxi-peninsula' ); ?></p>
	<table class="form-table" role="presentation">
		<tr><th><label for="tp_v_type"><?php esc_html_e( 'Type', 'taxi-peninsula' ); ?></label></th><td>
			<select id="tp_v_type" name="tp_v[type]">
				<?php foreach ( tp_venue_types() as $k => $l ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $m['type'], $k ); ?>><?php echo esc_html( $l ); ?></option>
				<?php endforeach; ?>
			</select></td></tr>
		<?php
		$row( 'pickup', __( 'Pick-up address', 'taxi-peninsula' ), 'text', __( 'Filled in on the booking form, e.g. "Frankston Hospital, 2 Hastings Rd, Frankston VIC 3199".', 'taxi-peninsula' ) );
		$row( 'pickup_note', __( 'Pick-up point for drivers', 'taxi-peninsula' ), 'textarea', __( 'Added to the booking notes, e.g. "Main entrance, Hastings Rd. Drop-off zone in front of Emergency."', 'taxi-peninsula' ) );
		$row( 'contact', __( 'Contact person', 'taxi-peninsula' ) );
		$row( 'contact_email', __( 'Contact email', 'taxi-peninsula' ), 'email' );
		$row( 'phone', __( 'Reception phone', 'taxi-peninsula' ), 'tel' );
		$row( 'promo', __( 'Promo code applied automatically', 'taxi-peninsula' ), 'text', __( 'Optional — a code from Bookings → Promo codes.', 'taxi-peninsula' ) );
		$row( 'invoice_name', __( 'Invoice to (if the partner pays)', 'taxi-peninsula' ) );
		$row( 'invoice_email', __( 'Invoice email', 'taxi-peninsula' ), 'email' );
		?>
		<tr><th><?php esc_html_e( 'Notify the contact', 'taxi-peninsula' ); ?></th><td><label><input type="checkbox" name="tp_v[notify]" value="1" <?php checked( (bool) get_post_meta( $post->ID, '_tp_v_notify', true ) ); ?>> <?php esc_html_e( 'Email the contact when a trip is booked from this page', 'taxi-peninsula' ); ?></label></td></tr>
	</table>
	<?php
	$stats = tp_venue_stats( $post->ID, 90 );
	/* translators: 1: visits 2: scans 3: bookings */
	echo '<p><strong>' . esc_html( sprintf( __( 'Last 90 days: %1$d visits, %2$d QR scans, %3$d bookings.', 'taxi-peninsula' ), $stats['view'], $stats['qr'], $stats['booking'] ) ) . '</strong></p>';
}

function tp_render_venue_qr_box( $post ) {
	if ( 'publish' !== $post->post_status ) {
		echo '<p>' . esc_html__( 'Publish the page to get its QR code.', 'taxi-peninsula' ) . '</p>';
		return;
	}
	$url = tp_venue_qr_url( $post->ID );
	wp_enqueue_script( 'tp-qrcode', TP_URI . '/assets/js/vendor/qrcode.js', array(), '1.4.4', true );
	?>
	<div class="tp-qr" data-qr="<?php echo esc_attr( $url ); ?>" role="img" aria-label="<?php esc_attr_e( 'QR code for this partner page', 'taxi-peninsula' ); ?>"></div>
	<p><code class="tp-qr__url"><?php echo esc_html( $url ); ?></code></p>
	<p><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tp_venue_poster&venue=' . $post->ID ), 'tp_venue_poster_' . $post->ID ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Print poster', 'taxi-peninsula' ); ?></a></p>
	<?php
	tp_qr_render_script();
}

/**
 * Inline script that turns [data-qr] boxes into SVG QR codes.
 */
function tp_qr_render_script() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<script>
	window.addEventListener('load', function () {
		if (typeof qrcode !== 'function') { return; }
		document.querySelectorAll('[data-qr]').forEach(function (box) {
			var qr = qrcode(0, 'M');
			qr.addData(box.getAttribute('data-qr'));
			qr.make();
			box.innerHTML = qr.createSvgTag({ cellSize: 6, margin: 2, scalable: true });
		});
	});
	</script>
	<?php
}

add_action(
	'save_post_tp_venue',
	static function ( $post_id ) {
		if ( ! isset( $_POST['tp_venue_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tp_venue_nonce'] ), 'tp_venue_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in = isset( $_POST['tp_v'] ) && is_array( $_POST['tp_v'] ) ? wp_unslash( $_POST['tp_v'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.
		update_post_meta( $post_id, '_tp_v_type', isset( tp_venue_types()[ $in['type'] ?? '' ] ) ? $in['type'] : 'other' );
		foreach ( array( 'pickup', 'contact', 'phone', 'invoice_name' ) as $k ) {
			update_post_meta( $post_id, '_tp_v_' . $k, sanitize_text_field( $in[ $k ] ?? '' ) );
		}
		update_post_meta( $post_id, '_tp_v_pickup_note', sanitize_textarea_field( $in['pickup_note'] ?? '' ) );
		update_post_meta( $post_id, '_tp_v_promo', strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $in['promo'] ?? '' ) ) ) );
		foreach ( array( 'contact_email', 'invoice_email' ) as $k ) {
			update_post_meta( $post_id, '_tp_v_' . $k, sanitize_email( $in[ $k ] ?? '' ) );
		}
		update_post_meta( $post_id, '_tp_v_notify', empty( $in['notify'] ) ? '' : '1' );
	}
);

/**
 * Visits, scans and bookings over the last N days.
 */
function tp_venue_stats( $id, $days = 90 ) {
	global $wpdb;
	$out = array( 'view' => 0, 'qr' => 0, 'booking' => 0 );
	if ( function_exists( 'tp_track_table' ) && (int) get_option( 'tp_track_db' ) ) {
		$table = tp_track_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT type, COUNT(*) n FROM {$table} WHERE ref_id = %d AND type IN ('view','qr') AND created >= %s GROUP BY type", $id, wp_date( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ) as $r ) {
			$out[ $r->type ] = (int) $r->n;
		}
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$out['booking'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_tp_venue_id' AND m.meta_value = %d AND p.post_type = 'tp_booking' AND p.post_status = 'publish' AND p.post_date >= %s", $id, wp_date( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) );
	return $out;
}

add_filter(
	'manage_tp_venue_posts_columns',
	static function () {
		return array(
			'cb'       => '<input type="checkbox">',
			'title'    => __( 'Partner', 'taxi-peninsula' ),
			'tp_type'  => __( 'Type', 'taxi-peninsula' ),
			'tp_stats' => __( 'Last 90 days', 'taxi-peninsula' ),
			'tp_links' => __( 'Poster', 'taxi-peninsula' ),
		);
	}
);
add_action(
	'manage_tp_venue_posts_custom_column',
	static function ( $col, $id ) {
		if ( 'tp_type' === $col ) {
			echo esc_html( tp_venue_types()[ tp_venue_meta( $id )['type'] ] ?? '' );
		} elseif ( 'tp_stats' === $col ) {
			$s = tp_venue_stats( $id );
			/* translators: 1: visits 2: scans 3: bookings */
			echo esc_html( sprintf( __( '%1$d visits · %2$d scans · %3$d bookings', 'taxi-peninsula' ), $s['view'], $s['qr'], $s['booking'] ) );
		} elseif ( 'tp_links' === $col && 'publish' === get_post_status( $id ) ) {
			printf( '<a href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tp_venue_poster&venue=' . $id ), 'tp_venue_poster_' . $id ) ), esc_html__( 'Print poster', 'taxi-peninsula' ) );
		}
	},
	10,
	2
);

// Partner rows on the Leads & calls report.
add_action(
	'tp_leads_report_after',
	static function () {
		$venues = get_posts( array( 'post_type' => 'tp_venue', 'post_status' => 'publish', 'numberposts' => 100 ) );
		if ( ! $venues ) {
			return;
		}
		echo '<h2>' . esc_html__( 'Partner pages (last 90 days)', 'taxi-peninsula' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Partner', 'taxi-peninsula' ) . '</th><th class="num">' . esc_html__( 'Visits', 'taxi-peninsula' ) . '</th><th class="num">' . esc_html__( 'QR scans', 'taxi-peninsula' ) . '</th><th class="num">' . esc_html__( 'Bookings', 'taxi-peninsula' ) . '</th></tr></thead><tbody>';
		foreach ( $venues as $v ) {
			$s = tp_venue_stats( $v->ID );
			printf( '<tr><td><a href="%1$s">%2$s</a></td><td class="num">%3$d</td><td class="num">%4$d</td><td class="num">%5$d</td></tr>', esc_url( get_edit_post_link( $v->ID ) ), esc_html( get_the_title( $v ) ), (int) $s['view'], (int) $s['qr'], (int) $s['booking'] );
		}
		echo '</tbody></table>';
	}
);

/* -------------------------------------------------------------------------
 * Printable A4 poster.
 * ---------------------------------------------------------------------- */

add_action( 'admin_post_tp_venue_poster', 'tp_venue_poster' );
function tp_venue_poster() {
	$id = isset( $_GET['venue'] ) ? absint( $_GET['venue'] ) : 0;
	check_admin_referer( 'tp_venue_poster_' . $id );
	if ( ! current_user_can( 'edit_post', $id ) || 'tp_venue' !== get_post_type( $id ) ) {
		wp_die( '', 403 );
	}
	$c    = function_exists( 'tp_design_colors' ) ? tp_design_colors() : array( 'primary' => '#0b3d91', 'accent' => '#ffc629', 'on_accent' => '#16213a' );
	$site = get_bloginfo( 'name' );
	$logo = (int) get_theme_mod( 'custom_logo' );
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( get_the_title( $id ) . ' — ' . __( 'Poster', 'taxi-peninsula' ) ); ?></title>
	<style>
		@page { size: A4; margin: 0; }
		* { box-sizing: border-box; }
		body { margin: 0; font: 18px/1.4 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #16213a; background: #e5e9f0; }
		.poster { width: 210mm; min-height: 297mm; margin: 12px auto; background: #fff; display: flex; flex-direction: column; text-align: center; }
		.poster__top { background: <?php echo esc_attr( $c['primary'] ); ?>; color: #fff; padding: 18mm 14mm 14mm; }
		.poster__top h1 { font-size: 34pt; line-height: 1.1; margin: 0 0 6mm; }
		.poster__top p { font-size: 16pt; margin: 0; }
		.poster__qr { margin: 12mm auto 6mm; width: 110mm; height: 110mm; }
		.poster__qr svg { width: 100%; height: 100%; }
		.poster__how { font-size: 16pt; margin: 0 14mm; }
		.poster__phone { display: inline-block; margin: 8mm auto; padding: 4mm 10mm; border-radius: 6mm; background: <?php echo esc_attr( $c['accent'] ); ?>; color: <?php echo esc_attr( $c['on_accent'] ); ?>; font-size: 26pt; font-weight: 800; }
		.poster__foot { margin-top: auto; padding: 8mm 14mm; border-top: 2mm solid <?php echo esc_attr( $c['accent'] ); ?>; display: flex; align-items: center; justify-content: space-between; gap: 8mm; font-size: 13pt; }
		.poster__foot img { max-height: 18mm; width: auto; }
		.poster__url { font-size: 11pt; color: #4a5670; word-break: break-all; }
		.actions { text-align: center; margin: 12px; }
		.actions button { font: inherit; padding: 10px 20px; border-radius: 8px; border: 0; background: <?php echo esc_attr( $c['primary'] ); ?>; color: #fff; cursor: pointer; }
		@media print { body { background: #fff; } .poster { margin: 0; } .actions { display: none; } .poster__top, .poster__phone { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
	</style>
</head>
<body>
	<p class="actions"><button type="button" onclick="window.print()"><?php esc_html_e( 'Print', 'taxi-peninsula' ); ?></button></p>
	<main class="poster">
		<div class="poster__top">
			<h1><?php esc_html_e( 'Need a wheelchair taxi?', 'taxi-peninsula' ); ?></h1>
			<p>
				<?php
				/* translators: %s: partner */
				echo esc_html( sprintf( __( 'Book your ride from %s in two minutes.', 'taxi-peninsula' ), get_the_title( $id ) ) );
				?>
			</p>
		</div>
		<div class="poster__qr" data-qr="<?php echo esc_attr( tp_venue_qr_url( $id ) ); ?>" role="img" aria-label="<?php esc_attr_e( 'QR code', 'taxi-peninsula' ); ?>"></div>
		<p class="poster__how"><strong><?php esc_html_e( 'Point your phone camera at the code.', 'taxi-peninsula' ); ?></strong> <?php esc_html_e( 'The pick-up address is already filled in.', 'taxi-peninsula' ); ?></p>
		<p><?php esc_html_e( 'Or call us:', 'taxi-peninsula' ); ?></p>
		<p><span class="poster__phone"><?php echo esc_html( wp_strip_all_tags( tp_opt( 'phone_display' ) ) ); ?></span></p>
		<div class="poster__foot">
			<?php if ( $logo ) : ?>
				<?php echo wp_get_attachment_image( $logo, 'medium', false, array( 'alt' => $site ) ); ?>
			<?php else : ?>
				<strong><?php echo esc_html( $site ); ?></strong>
			<?php endif; ?>
			<span class="poster__url"><?php echo esc_html( get_permalink( $id ) ); ?></span>
			<?php if ( has_post_thumbnail( $id ) ) : ?>
				<?php echo get_the_post_thumbnail( $id, 'medium' ); ?>
			<?php endif; ?>
		</div>
	</main>
	<script src="<?php echo esc_url( TP_URI . '/assets/js/vendor/qrcode.js?ver=1.4.4' ); ?>"></script><?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- standalone print page. ?>
	<?php tp_qr_render_script(); ?>
</body>
</html>
	<?php
	exit;
}
