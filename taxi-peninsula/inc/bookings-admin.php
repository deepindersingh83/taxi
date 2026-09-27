<?php
/**
 * wp-admin Bookings manager: list columns, filters, status editing,
 * bulk actions, CSV export and a dashboard widget.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_enqueue_scripts', 'tp_admin_assets' );
function tp_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ( $screen && 'tp_booking' === $screen->post_type ) || 'index.php' === $hook ) {
		wp_enqueue_style( 'tp-admin', TP_URI . '/assets/css/admin.css', array(), TP_VERSION );
	}
}

/**
 * Status badge markup.
 */
function tp_status_badge( $status ) {
	$labels = tp_statuses();
	return sprintf(
		'<span class="tp-badge tp-badge--%1$s">%2$s</span>',
		esc_attr( $status ),
		esc_html( $labels[ $status ] ?? $status )
	);
}

/* -------------------------------------------------------------------------
 * Menu bubble with pending count.
 * ---------------------------------------------------------------------- */

function tp_count_by_status() {
	global $wpdb;
	$rows   = $wpdb->get_results(
		"SELECT pm.meta_value AS status, COUNT(*) AS n
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = '_tp_status' AND p.post_type = 'tp_booking' AND p.post_status = 'publish'
		 GROUP BY pm.meta_value"
	);
	$counts = array_fill_keys( array_keys( tp_statuses() ), 0 );
	foreach ( $rows as $row ) {
		$counts[ $row->status ] = (int) $row->n;
	}
	return $counts;
}

add_action( 'admin_menu', 'tp_booking_menu_bubble', 99 );
function tp_booking_menu_bubble() {
	global $menu;
	$pending = tp_count_by_status()['pending'];
	if ( ! $pending || ! is_array( $menu ) ) {
		return;
	}
	foreach ( $menu as $i => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=tp_booking' === $item[2] ) {
			$menu[ $i ][0] .= sprintf( ' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>', $pending );
		}
	}
}

/* -------------------------------------------------------------------------
 * List table columns.
 * ---------------------------------------------------------------------- */

add_filter( 'manage_tp_booking_posts_columns', 'tp_booking_columns' );
function tp_booking_columns( $cols ) {
	return array(
		'cb'          => $cols['cb'],
		'title'       => __( 'Booking', 'taxi-peninsula' ),
		'tp_pickup'   => __( 'Pick-up time', 'taxi-peninsula' ),
		'tp_route'    => __( 'Route', 'taxi-peninsula' ),
		'tp_contact'  => __( 'Contact', 'taxi-peninsula' ),
		'tp_vehicle'  => __( 'Vehicle', 'taxi-peninsula' ),
		'tp_status'   => __( 'Status', 'taxi-peninsula' ),
		'date'        => __( 'Received', 'taxi-peninsula' ),
	);
}

add_action( 'manage_tp_booking_posts_custom_column', 'tp_booking_column_content', 10, 2 );
function tp_booking_column_content( $col, $post_id ) {
	$b = tp_get_booking( $post_id );
	switch ( $col ) {
		case 'tp_pickup':
			echo esc_html( tp_format_pickup( $b ) );
			if ( $b['return_trip'] ) {
				echo '<br><small>' . esc_html__( 'Return:', 'taxi-peninsula' ) . ' ' . esc_html( $b['return_time'] ?: __( 'TBA', 'taxi-peninsula' ) ) . '</small>';
			}
			break;
		case 'tp_route':
			echo '<strong>' . esc_html__( 'From:', 'taxi-peninsula' ) . '</strong> ' . esc_html( $b['pickup'] ) . '<br>';
			echo '<strong>' . esc_html__( 'To:', 'taxi-peninsula' ) . '</strong> ' . esc_html( $b['dropoff'] );
			break;
		case 'tp_contact':
			printf(
				'<a href="%1$s">%2$s</a><br><a href="%3$s">%4$s</a>',
				esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $b['phone'] ) ),
				esc_html( $b['phone'] ),
				esc_url( 'mailto:' . $b['email'] ),
				esc_html( $b['email'] )
			);
			break;
		case 'tp_vehicle':
			$vehicles = tp_vehicle_types();
			echo esc_html( $vehicles[ $b['vehicle'] ] ?? $b['vehicle'] );
			/* translators: 1: passengers, 2: wheelchairs */
			echo '<br><small>' . esc_html( sprintf( __( '%1$d pax · %2$d wheelchair(s)', 'taxi-peninsula' ), $b['passengers'], $b['wheelchairs'] ) ) . '</small>';
			if ( $b['mptp'] ) {
				echo ' <small class="tp-tag">MPTP</small>';
			}
			break;
		case 'tp_status':
			echo tp_status_badge( $b['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
			break;
	}
}

add_filter( 'manage_edit-tp_booking_sortable_columns', static function ( $cols ) {
	$cols['tp_pickup'] = 'tp_pickup';
	$cols['tp_status'] = 'tp_status';
	return $cols;
} );

/* -------------------------------------------------------------------------
 * Filters: status, pick-up date range, upcoming.
 * ---------------------------------------------------------------------- */

add_action( 'restrict_manage_posts', 'tp_booking_filters' );
function tp_booking_filters( $post_type ) {
	if ( 'tp_booking' !== $post_type ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$status = isset( $_GET['tp_status'] ) ? sanitize_key( wp_unslash( $_GET['tp_status'] ) ) : '';
	$when   = isset( $_GET['tp_when'] ) ? sanitize_key( wp_unslash( $_GET['tp_when'] ) ) : '';
	$from   = isset( $_GET['tp_from'] ) ? sanitize_text_field( wp_unslash( $_GET['tp_from'] ) ) : '';
	$to     = isset( $_GET['tp_to'] ) ? sanitize_text_field( wp_unslash( $_GET['tp_to'] ) ) : '';
	// phpcs:enable

	echo '<select name="tp_status"><option value="">' . esc_html__( 'All statuses', 'taxi-peninsula' ) . '</option>';
	foreach ( tp_statuses() as $key => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $status, $key, false ), esc_html( $label ) );
	}
	echo '</select>';

	$whens = array(
		''         => __( 'Any pick-up date', 'taxi-peninsula' ),
		'today'    => __( 'Today', 'taxi-peninsula' ),
		'tomorrow' => __( 'Tomorrow', 'taxi-peninsula' ),
		'upcoming' => __( 'Upcoming', 'taxi-peninsula' ),
		'past'     => __( 'Past', 'taxi-peninsula' ),
	);
	echo '<select name="tp_when">';
	foreach ( $whens as $key => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $when, $key, false ), esc_html( $label ) );
	}
	echo '</select>';

	printf(
		'<label class="screen-reader-text" for="tp_from">%1$s</label><input type="date" id="tp_from" name="tp_from" value="%2$s" title="%1$s">',
		esc_attr__( 'Pick-up from', 'taxi-peninsula' ),
		esc_attr( $from )
	);
	printf(
		'<label class="screen-reader-text" for="tp_to">%1$s</label><input type="date" id="tp_to" name="tp_to" value="%2$s" title="%1$s">',
		esc_attr__( 'Pick-up to', 'taxi-peninsula' ),
		esc_attr( $to )
	);
}

/**
 * Build meta_query args from the current request filters (shared by list + export).
 */
function tp_booking_filter_meta_query( array $req ) {
	$mq     = array();
	$status = isset( $req['tp_status'] ) ? sanitize_key( $req['tp_status'] ) : '';
	if ( $status && isset( tp_statuses()[ $status ] ) ) {
		$mq[] = array(
			'key'   => '_tp_status',
			'value' => $status,
		);
	}

	$today = wp_date( 'Y-m-d' );
	$now   = wp_date( 'Y-m-d H:i' );
	switch ( $req['tp_when'] ?? '' ) {
		case 'today':
			$mq[] = array( 'key' => '_tp_date', 'value' => $today );
			break;
		case 'tomorrow':
			$mq[] = array( 'key' => '_tp_date', 'value' => wp_date( 'Y-m-d', strtotime( '+1 day' ) ) );
			break;
		case 'upcoming':
			$mq[] = array( 'key' => '_tp_pickup_at', 'value' => $now, 'compare' => '>=' );
			break;
		case 'past':
			$mq[] = array( 'key' => '_tp_pickup_at', 'value' => $now, 'compare' => '<' );
			break;
	}

	foreach ( array( 'tp_from' => '>=', 'tp_to' => '<=' ) as $param => $cmp ) {
		$val = isset( $req[ $param ] ) ? sanitize_text_field( $req[ $param ] ) : '';
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $val ) ) {
			$mq[] = array( 'key' => '_tp_date', 'value' => $val, 'compare' => $cmp );
		}
	}

	return $mq;
}

add_action( 'pre_get_posts', 'tp_booking_admin_query' );
function tp_booking_admin_query( WP_Query $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || 'tp_booking' !== $q->get( 'post_type' ) ) {
		return;
	}
	$req = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per key.
	$mq  = tp_booking_filter_meta_query( $req );
	if ( $mq ) {
		$q->set( 'meta_query', array_merge( array( 'relation' => 'AND' ), $mq ) );
	}

	$orderby = $q->get( 'orderby' );
	if ( 'tp_pickup' === $orderby ) {
		$q->set( 'meta_key', '_tp_pickup_at' );
		$q->set( 'orderby', 'meta_value' );
	} elseif ( 'tp_status' === $orderby ) {
		$q->set( 'meta_key', '_tp_status' );
		$q->set( 'orderby', 'meta_value' );
	}
}

/* -------------------------------------------------------------------------
 * Bulk status changes.
 * ---------------------------------------------------------------------- */

add_filter( 'bulk_actions-edit-tp_booking', static function ( $actions ) {
	foreach ( tp_statuses() as $key => $label ) {
		/* translators: %s: status */
		$actions[ 'tp_mark_' . $key ] = sprintf( __( 'Mark as %s', 'taxi-peninsula' ), $label );
	}
	unset( $actions['edit'] );
	return $actions;
} );

add_filter( 'handle_bulk_actions-edit-tp_booking', static function ( $redirect, $action, $ids ) {
	if ( 0 !== strpos( $action, 'tp_mark_' ) ) {
		return $redirect;
	}
	$status = substr( $action, 8 );
	if ( ! isset( tp_statuses()[ $status ] ) ) {
		return $redirect;
	}
	$done = 0;
	foreach ( $ids as $id ) {
		if ( current_user_can( 'edit_post', $id ) ) {
			tp_set_status( (int) $id, $status, false );
			$done++;
		}
	}
	return add_query_arg( 'tp_updated', $done, $redirect );
}, 10, 3 );

add_action( 'admin_notices', static function () {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['tp_updated'] ) ) {
		$n = absint( $_GET['tp_updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		/* translators: %d: number of bookings */
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( sprintf( _n( '%d booking updated.', '%d bookings updated.', $n, 'taxi-peninsula' ), $n ) ) );
	}
} );

/**
 * Change a booking's status and log it.
 */
function tp_set_status( $post_id, $status, $notify ) {
	$old = get_post_meta( $post_id, '_tp_status', true );
	if ( $old === $status ) {
		return;
	}
	update_post_meta( $post_id, '_tp_status', $status );

	$log   = (array) get_post_meta( $post_id, '_tp_log', true );
	$user  = wp_get_current_user();
	$log[] = array(
		'time'   => current_time( 'mysql' ),
		'user'   => $user->exists() ? $user->display_name : '',
		'from'   => $old,
		'to'     => $status,
		'notify' => (bool) $notify,
	);
	update_post_meta( $post_id, '_tp_log', array_filter( $log ) );

	if ( $notify ) {
		tp_send_status_email( $post_id );
	}
}

/* -------------------------------------------------------------------------
 * Edit screen.
 * ---------------------------------------------------------------------- */

add_action( 'add_meta_boxes_tp_booking', 'tp_booking_meta_boxes' );
function tp_booking_meta_boxes() {
	remove_meta_box( 'submitdiv', 'tp_booking', 'side' );
	add_meta_box( 'tp_booking_details', __( 'Trip details', 'taxi-peninsula' ), 'tp_render_details_box', 'tp_booking', 'normal', 'high' );
	add_meta_box( 'tp_booking_status', __( 'Status', 'taxi-peninsula' ), 'tp_render_status_box', 'tp_booking', 'side', 'high' );
	add_meta_box( 'tp_booking_log', __( 'History', 'taxi-peninsula' ), 'tp_render_log_box', 'tp_booking', 'side', 'default' );
}

function tp_render_details_box( WP_Post $post ) {
	$b = tp_get_booking( $post->ID );
	if ( 'auto-draft' === $post->post_status ) {
		$b['passengers']  = 1;
		$b['wheelchairs'] = 1;
		$b['vehicle']     = 'wat';
		$b['date']        = wp_date( 'Y-m-d' );
	}
	wp_nonce_field( 'tp_save_booking', '_tp_admin_nonce' );

	echo '<div class="tp-admin-grid">';
	foreach ( tp_booking_fields() as $key => $field ) {
		list( $label, $type ) = $field;
		$id    = 'tp_' . $key;
		$value = $b[ $key ];
		$wide  = in_array( $key, array( 'pickup', 'dropoff', 'notes' ), true ) ? ' tp-admin-grid__wide' : '';
		echo '<p class="tp-admin-field' . esc_attr( $wide ) . '">';

		if ( 'checkbox' === $type ) {
			printf(
				'<label><input type="checkbox" id="%1$s" name="tp[%2$s]" value="1"%3$s> %4$s</label>',
				esc_attr( $id ),
				esc_attr( $key ),
				checked( (int) $value, 1, false ),
				esc_html( $label )
			);
			echo '</p>';
			continue;
		}

		printf( '<label for="%s">%s</label>', esc_attr( $id ), esc_html( $label ) );

		if ( 'vehicle' === $type || 'mobility' === $type ) {
			$options = 'vehicle' === $type ? tp_vehicle_types() : tp_mobility_aids();
			printf( '<select id="%s" name="tp[%s]">', esc_attr( $id ), esc_attr( $key ) );
			foreach ( $options as $k => $l ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $value, $k, false ), esc_html( $l ) );
			}
			echo '</select>';
		} elseif ( 'textarea' === $type ) {
			printf( '<textarea id="%s" name="tp[%s]" rows="3">%s</textarea>', esc_attr( $id ), esc_attr( $key ), esc_textarea( $value ) );
		} else {
			printf(
				'<input type="%1$s" id="%2$s" name="tp[%3$s]" value="%4$s"%5$s>',
				esc_attr( $type ),
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( $value ),
				'number' === $type ? ' min="0" max="11"' : ''
			);
		}
		echo '</p>';
	}
	echo '</div>';

	echo '<p class="tp-admin-field tp-admin-grid__wide"><label for="tp_admin_notes">' . esc_html__( 'Internal notes (not shown to the customer)', 'taxi-peninsula' ) . '</label>';
	printf( '<textarea id="tp_admin_notes" name="tp_admin_notes" rows="3">%s</textarea></p>', esc_textarea( $b['admin_notes'] ) );
}

function tp_render_status_box( WP_Post $post ) {
	$b = tp_get_booking( $post->ID );
	if ( $b['reference'] ) {
		echo '<p class="tp-ref">' . esc_html( $b['reference'] ) . '</p>';
	}
	echo '<p><label for="tp_status" class="screen-reader-text">' . esc_html__( 'Status', 'taxi-peninsula' ) . '</label><select id="tp_status" name="tp_status" class="widefat">';
	foreach ( tp_statuses() as $key => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $b['status'], $key, false ), esc_html( $label ) );
	}
	echo '</select></p>';
	echo '<p><label><input type="checkbox" name="tp_notify" value="1"' . checked( (bool) tp_opt( 'customer_emails' ), true, false ) . '> ' . esc_html__( 'Email the customer if the status changes', 'taxi-peninsula' ) . '</label></p>';

	echo '<div class="tp-status-actions">';
	submit_button( 'auto-draft' === $post->post_status ? __( 'Create booking', 'taxi-peninsula' ) : __( 'Save booking', 'taxi-peninsula' ), 'primary large', 'publish', false );
	if ( current_user_can( 'delete_post', $post->ID ) && 'auto-draft' !== $post->post_status ) {
		printf( '<a class="submitdelete" href="%s">%s</a>', esc_url( get_delete_post_link( $post->ID ) ), esc_html__( 'Move to bin', 'taxi-peninsula' ) );
	}
	echo '</div>';
}

function tp_render_log_box( WP_Post $post ) {
	$log      = array_reverse( array_filter( (array) get_post_meta( $post->ID, '_tp_log', true ) ) );
	$statuses = tp_statuses();
	echo '<ul class="tp-log">';
	foreach ( $log as $entry ) {
		printf(
			'<li><strong>%1$s</strong> %2$s<br><small>%3$s%4$s</small></li>',
			esc_html( $statuses[ $entry['to'] ] ?? $entry['to'] ),
			$entry['notify'] ? '<span class="dashicons dashicons-email-alt" title="' . esc_attr__( 'Customer emailed', 'taxi-peninsula' ) . '"></span>' : '',
			esc_html( mysql2date( 'j M Y g:i a', $entry['time'] ) ),
			$entry['user'] ? ' · ' . esc_html( $entry['user'] ) : ''
		);
	}
	printf( '<li><strong>%s</strong><br><small>%s</small></li>', esc_html__( 'Received', 'taxi-peninsula' ), esc_html( get_the_date( 'j M Y g:i a', $post ) ) );
	echo '</ul>';
}

add_action( 'save_post_tp_booking', 'tp_save_booking_admin', 10, 2 );
function tp_save_booking_admin( $post_id, WP_Post $post ) {
	if ( ! isset( $_POST['_tp_admin_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_admin_nonce'] ) ), 'tp_save_booking' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$raw = isset( $_POST['tp'] ) && is_array( $_POST['tp'] ) ? wp_unslash( $_POST['tp'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
	list( $data ) = tp_sanitize_booking( $raw, false );
	tp_save_booking_meta( $post_id, $data );

	update_post_meta( $post_id, '_tp_admin_notes', isset( $_POST['tp_admin_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tp_admin_notes'] ) ) : '' );

	$ref = get_post_meta( $post_id, '_tp_reference', true );
	if ( ! $ref ) {
		$ref = tp_new_reference();
		update_post_meta( $post_id, '_tp_reference', $ref );
		update_post_meta( $post_id, '_tp_status', 'pending' );
	}

	$status = isset( $_POST['tp_status'] ) ? sanitize_key( wp_unslash( $_POST['tp_status'] ) ) : 'pending';
	if ( isset( tp_statuses()[ $status ] ) ) {
		tp_set_status( $post_id, $status, ! empty( $_POST['tp_notify'] ) );
	}

	// Keep title and search text in sync without re-triggering this hook.
	remove_action( 'save_post_tp_booking', 'tp_save_booking_admin', 10 );
	wp_update_post(
		array(
			'ID'           => $post_id,
			'post_title'   => $ref . ' — ' . $data['name'],
			'post_excerpt' => implode( ' | ', array( $data['phone'], $data['email'], $data['pickup'], $data['dropoff'] ) ),
			'post_status'  => 'publish',
		)
	);
	add_action( 'save_post_tp_booking', 'tp_save_booking_admin', 10, 2 );
}

/* -------------------------------------------------------------------------
 * CSV export.
 * ---------------------------------------------------------------------- */

add_action( 'manage_posts_extra_tablenav', static function ( $which ) {
	$screen = get_current_screen();
	if ( 'top' !== $which || ! $screen || 'tp_booking' !== $screen->post_type ) {
		return;
	}
	$args = array_intersect_key( wp_unslash( $_GET ), array_flip( array( 'tp_status', 'tp_when', 'tp_from', 'tp_to' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passed through add_query_arg & sanitized on export.
	$url  = wp_nonce_url( add_query_arg( array_map( 'rawurlencode', $args ), admin_url( 'admin-post.php?action=tp_export_bookings' ) ), 'tp_export' );
	printf( '<div class="alignleft actions"><a class="button" href="%s">%s</a></div>', esc_url( $url ), esc_html__( 'Export CSV', 'taxi-peninsula' ) );
} );

add_action( 'admin_post_tp_export_bookings', 'tp_export_bookings' );
function tp_export_bookings() {
	if ( ! current_user_can( 'edit_tp_bookings' ) ) {
		wp_die( esc_html__( 'You are not allowed to export bookings.', 'taxi-peninsula' ), 403 );
	}
	check_admin_referer( 'tp_export' );

	$req = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per key.
	$mq  = tp_booking_filter_meta_query( $req );
	$ids = get_posts(
		array(
			'post_type'   => 'tp_booking',
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_key'    => '_tp_pickup_at',
			'orderby'     => 'meta_value',
			'order'       => 'ASC',
			'meta_query'  => $mq ? array_merge( array( 'relation' => 'AND' ), $mq ) : array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		)
	);

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="bookings-' . wp_date( 'Y-m-d' ) . '.csv"' );

	$out    = fopen( 'php://output', 'w' );
	$fields = tp_booking_fields();
	fputcsv( $out, array_merge( array( 'Reference', 'Status' ), wp_list_pluck( $fields, 0 ), array( 'Internal notes', 'Received' ) ), ",", "\"", "" );

	foreach ( $ids as $id ) {
		$b                 = tp_get_booking( $id );
		$b['vehicle']      = tp_vehicle_types()[ $b['vehicle'] ] ?? $b['vehicle'];
		$b['mobility_aid'] = tp_mobility_aids()[ $b['mobility_aid'] ] ?? $b['mobility_aid'];
		$row               = array( $b['reference'], tp_statuses()[ $b['status'] ] ?? $b['status'] );
		foreach ( array_keys( $fields ) as $key ) {
			$row[] = $b[ $key ];
		}
		$row[] = $b['admin_notes'];
		$row[] = get_the_date( 'Y-m-d H:i', $id );
		// Guard against spreadsheet formula injection.
		$row = array_map(
			static function ( $v ) {
				$v = (string) $v;
				return ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) ? "'" . $v : $v;
			},
			$row
		);
		fputcsv( $out, $row, ",", "\"", "" );
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}

/* -------------------------------------------------------------------------
 * Dashboard widget.
 * ---------------------------------------------------------------------- */

add_action( 'wp_dashboard_setup', static function () {
	if ( current_user_can( 'edit_tp_bookings' ) ) {
		wp_add_dashboard_widget( 'tp_bookings_widget', __( 'Taxi bookings', 'taxi-peninsula' ), 'tp_dashboard_widget' );
	}
} );

function tp_dashboard_widget() {
	$counts = tp_count_by_status();
	$base   = admin_url( 'edit.php?post_type=tp_booking' );

	echo '<ul class="tp-stats">';
	foreach ( tp_statuses() as $key => $label ) {
		printf(
			'<li><a href="%1$s"><span class="tp-stats__n">%2$d</span> %3$s</a></li>',
			esc_url( add_query_arg( 'tp_status', $key, $base ) ),
			(int) $counts[ $key ],
			esc_html( $label )
		);
	}
	echo '</ul>';

	$upcoming = get_posts(
		array(
			'post_type'   => 'tp_booking',
			'numberposts' => 6,
			'meta_key'    => '_tp_pickup_at',
			'orderby'     => 'meta_value',
			'order'       => 'ASC',
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array( 'key' => '_tp_pickup_at', 'value' => wp_date( 'Y-m-d H:i' ), 'compare' => '>=' ),
				array( 'key' => '_tp_status', 'value' => array( 'pending', 'confirmed', 'assigned' ), 'compare' => 'IN' ),
			),
		)
	);

	echo '<h3>' . esc_html__( 'Next pick-ups', 'taxi-peninsula' ) . '</h3>';
	if ( ! $upcoming ) {
		echo '<p>' . esc_html__( 'No upcoming bookings.', 'taxi-peninsula' ) . '</p>';
	} else {
		echo '<ul class="tp-upcoming">';
		foreach ( $upcoming as $p ) {
			$b = tp_get_booking( $p->ID );
			printf(
				'<li><a href="%1$s"><strong>%2$s</strong> — %3$s</a> %4$s<br><small>%5$s → %6$s</small></li>',
				esc_url( get_edit_post_link( $p->ID ) ),
				esc_html( tp_format_pickup( $b ) ),
				esc_html( $b['name'] ),
				tp_status_badge( $b['status'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html( $b['pickup'] ),
				esc_html( $b['dropoff'] )
			);
		}
		echo '</ul>';
	}
	printf(
		'<p><a class="button button-primary" href="%1$s">%2$s</a> <a class="button" href="%3$s">%4$s</a></p>',
		esc_url( add_query_arg( 'tp_when', 'upcoming', $base ) . '&orderby=tp_pickup&order=asc' ),
		esc_html__( 'View upcoming', 'taxi-peninsula' ),
		esc_url( admin_url( 'post-new.php?post_type=tp_booking' ) ),
		esc_html__( 'Add phone booking', 'taxi-peninsula' )
	);
}

/* -------------------------------------------------------------------------
 * Booking-friendly wording and status views.
 * ---------------------------------------------------------------------- */

add_filter( 'post_updated_messages', static function ( $messages ) {
	$saved                     = __( 'Booking saved.', 'taxi-peninsula' );
	$messages['tp_booking'] = array_fill( 1, 10, $saved );
	$messages['tp_booking'][0] = '';
	return $messages;
} );

add_filter( 'disable_months_dropdown', static function ( $disable, $post_type ) {
	return 'tp_booking' === $post_type ? true : $disable;
}, 10, 2 );

add_filter( 'post_date_column_status', static function ( $status, $post ) {
	return 'tp_booking' === $post->post_type ? '' : $status;
}, 10, 2 );

/**
 * Replace the "All / Published" links with one link per booking status.
 */
add_filter( 'views_edit-tp_booking', static function ( $views ) {
	$counts  = tp_count_by_status();
	$base    = admin_url( 'edit.php?post_type=tp_booking' );
	$current = isset( $_GET['tp_status'] ) ? sanitize_key( wp_unslash( $_GET['tp_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$new = array(
		'all' => sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( $base ),
			'' === $current && ! isset( $_GET['post_status'] ) ? ' class="current" aria-current="page"' : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			esc_html__( 'All', 'taxi-peninsula' ),
			array_sum( $counts )
		),
	);
	foreach ( tp_statuses() as $key => $label ) {
		$new[ $key ] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( add_query_arg( 'tp_status', $key, $base ) ),
			$current === $key ? ' class="current" aria-current="page"' : '',
			esc_html( $label ),
			$counts[ $key ]
		);
	}
	if ( isset( $views['trash'] ) ) {
		$new['trash'] = $views['trash'];
	}
	return $new;
} );
