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
	$types  = array( 'tp_booking', 'tp_message', 'tp_fleet', 'tp_service', 'tp_testimonial', 'tp_area', 'tp_faq' );
	if ( ( $screen && in_array( $screen->post_type, $types, true ) ) || 'index.php' === $hook || false !== strpos( $hook, 'tp-' ) ) {
		wp_enqueue_style( 'tp-admin', TP_URI . '/assets/css/admin.css', array(), TP_VERSION );
	}
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
		'tp_driver'   => __( 'Driver', 'taxi-peninsula' ),
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
		case 'tp_driver':
			$driver = $b['driver_id'] ? get_userdata( $b['driver_id'] ) : null;
			echo $driver ? esc_html( $driver->display_name ) : '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Unassigned', 'taxi-peninsula' ) . '</span>';
			if ( $b['fleet_id'] ) {
				echo '<br><small>' . esc_html( tp_fleet_label( $b['fleet_id'] ) ) . '</small>';
			}
			break;
		case 'tp_status':
			echo tp_status_badge( $b['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
			if ( ! empty( $b['request'] ) ) {
				echo '<br><span class="tp-badge tp-badge--request">' . esc_html__( 'Customer request', 'taxi-peninsula' ) . '</span>';
			}
			if ( 'account' === $b['payment'] ) {
				echo '<br><small class="tp-tag tp-tag--muted">' . esc_html__( 'Account', 'taxi-peninsula' ) . '</small>';
			} elseif ( $b['paid'] > 0 ) {
				/* translators: %s: amount */
				echo '<br><small class="tp-tag tp-tag--paid">' . esc_html( sprintf( __( '%s paid', 'taxi-peninsula' ), tp_money( $b['paid'] ) ) ) . '</small>';
			}
			if ( $b['series'] ) {
				printf( '<br><a href="%s"><small>%s</small></a>', esc_url( admin_url( 'edit.php?post_type=tp_booking&tp_series=' . rawurlencode( $b['series'] ) . '&orderby=tp_pickup&order=asc' ) ), esc_html__( 'Repeat series', 'taxi-peninsula' ) );
			}
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

	$driver = isset( $_GET['tp_driver'] ) ? absint( $_GET['tp_driver'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	echo '<select name="tp_driver"><option value="0">' . esc_html__( 'All drivers', 'taxi-peninsula' ) . '</option>';
	printf( '<option value="-1"%s>%s</option>', selected( isset( $_GET['tp_driver'] ) && '-1' === $_GET['tp_driver'], true, false ), esc_html__( 'Unassigned', 'taxi-peninsula' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	foreach ( tp_drivers() as $d ) {
		printf( '<option value="%d"%s>%s</option>', (int) $d->ID, selected( $driver, $d->ID, false ), esc_html( $d->display_name ) );
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

	if ( isset( $req['tp_driver'] ) && '-1' === (string) $req['tp_driver'] ) {
		$mq[] = array(
			'relation' => 'OR',
			array( 'key' => '_tp_driver_id', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_tp_driver_id', 'value' => '0' ),
		);
	} elseif ( ! empty( $req['tp_driver'] ) && absint( $req['tp_driver'] ) ) {
		$mq[] = array( 'key' => '_tp_driver_id', 'value' => absint( $req['tp_driver'] ) );
	}

	if ( ! empty( $req['tp_series'] ) ) {
		$mq[] = array( 'key' => '_tp_series', 'value' => sanitize_text_field( $req['tp_series'] ) );
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

/* -------------------------------------------------------------------------
 * Edit screen.
 * ---------------------------------------------------------------------- */

add_action( 'add_meta_boxes_tp_booking', 'tp_booking_meta_boxes' );
function tp_booking_meta_boxes() {
	remove_meta_box( 'submitdiv', 'tp_booking', 'side' );
	add_meta_box( 'tp_booking_details', __( 'Trip details', 'taxi-peninsula' ), 'tp_render_details_box', 'tp_booking', 'normal', 'high' );
	add_meta_box( 'tp_booking_status', __( 'Status', 'taxi-peninsula' ), 'tp_render_status_box', 'tp_booking', 'side', 'high' );
	add_meta_box( 'tp_booking_assign', __( 'Driver & vehicle', 'taxi-peninsula' ), 'tp_render_assign_box', 'tp_booking', 'side', 'high' );
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
		if ( in_array( $key, array( 'invoice_name', 'invoice_email', 'ndis_number' ), true ) && 'account' !== $b['payment'] ) {
			$wide .= ' tp-admin-field--account';
		}
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

		if ( 'vehicle' === $type || 'mobility' === $type || 'payment' === $type ) {
			$options = 'vehicle' === $type ? tp_vehicle_types() : ( 'mobility' === $type ? tp_mobility_aids() : tp_all_payment_methods() );
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
	echo '<p><label><input type="checkbox" name="tp_notify" value="1"' . checked( (bool) tp_opt( 'customer_emails' ) || tp_sms_enabled(), true, false ) . '> ' . esc_html__( 'Notify the customer (email / SMS) if the status or driver changes', 'taxi-peninsula' ) . '</label></p>';

	if ( $b['series'] ) {
		$count = count( tp_series_ids( $b['series'] ) );
		echo '<p><label><input type="checkbox" name="tp_apply_series" value="1"> ';
		/* translators: %d: number of trips */
		echo esc_html( sprintf( __( 'Also apply this status to later trips in this repeat booking (%d trips in total)', 'taxi-peninsula' ), $count ) );
		echo '</label></p>';
	}

	if ( ! empty( $b['request'] ) && is_array( $b['request'] ) ) {
		$types = tp_request_types();
		echo '<div class="tp-request">';
		echo '<p><strong>' . esc_html( $types[ $b['request']['type'] ] ?? __( 'Customer request', 'taxi-peninsula' ) ) . '</strong><br><small>' . esc_html( mysql2date( 'j M Y g:i a', $b['request']['time'] ) ) . '</small></p>';
		if ( ! empty( $b['request']['message'] ) ) {
			echo '<p>' . esc_html( $b['request']['message'] ) . '</p>';
		}
		echo '<label><input type="checkbox" name="tp_request_done" value="1"> ' . esc_html__( 'Mark request as handled', 'taxi-peninsula' ) . '</label>';
		echo '</div>';
	}

	if ( $b['reference'] ) {
		printf( '<p><a href="%s" target="_blank" rel="noopener">%s</a></p>', esc_url( tp_booking_manage_url( $post->ID ) ), esc_html__( 'View customer booking page ↗', 'taxi-peninsula' ) );
	}
	echo '<div class="tp-status-actions">';
	submit_button( 'auto-draft' === $post->post_status ? __( 'Create booking', 'taxi-peninsula' ) : __( 'Save booking', 'taxi-peninsula' ), 'primary large', 'publish', false );
	if ( current_user_can( 'delete_post', $post->ID ) && 'auto-draft' !== $post->post_status ) {
		printf( '<a class="submitdelete" href="%s">%s</a>', esc_url( get_delete_post_link( $post->ID ) ), esc_html__( 'Move to bin', 'taxi-peninsula' ) );
	}
	echo '</div>';
}

function tp_render_assign_box( WP_Post $post ) {
	$b       = tp_get_booking( $post->ID );
	$drivers = tp_drivers();
	echo '<p><label for="tp_driver_id"><strong>' . esc_html__( 'Driver', 'taxi-peninsula' ) . '</strong></label><select id="tp_driver_id" name="tp_driver_id" class="widefat"><option value="0">' . esc_html__( '— Unassigned —', 'taxi-peninsula' ) . '</option>';
	foreach ( $drivers as $d ) {
		printf( '<option value="%d"%s>%s</option>', (int) $d->ID, selected( $b['driver_id'], $d->ID, false ), esc_html( $d->display_name ) );
	}
	echo '</select></p>';
	if ( ! $drivers ) {
		printf( '<p class="description">%s <a href="%s">%s</a></p>', esc_html__( 'No drivers yet.', 'taxi-peninsula' ), esc_url( admin_url( 'user-new.php' ) ), esc_html__( 'Add a user with the Driver role.', 'taxi-peninsula' ) );
	}

	echo '<p><label for="tp_fleet_id"><strong>' . esc_html__( 'Vehicle', 'taxi-peninsula' ) . '</strong></label><select id="tp_fleet_id" name="tp_fleet_id" class="widefat"><option value="0">' . esc_html__( '— Not set —', 'taxi-peninsula' ) . '</option>';
	foreach ( tp_fleet_vehicles() as $v ) {
		$rego = get_post_meta( $v->ID, '_tp_rego', true );
		printf( '<option value="%d"%s>%s</option>', (int) $v->ID, selected( $b['fleet_id'], $v->ID, false ), esc_html( $v->post_title . ( $rego ? ' (' . $rego . ')' : '' ) ) );
	}
	echo '</select></p>';

	if ( $b['distance_km'] > 0 ) {
		echo '<p><strong>' . esc_html__( 'Trip:', 'taxi-peninsula' ) . '</strong> ';
		/* translators: 1: km, 2: minutes */
		echo esc_html( sprintf( __( '%1$s km, about %2$d min', 'taxi-peninsula' ), number_format_i18n( $b['distance_km'], 1 ), $b['duration'] ) );
		if ( $b['estimate'] ) {
			echo '<br><strong>' . esc_html__( 'Estimate given:', 'taxi-peninsula' ) . '</strong> ' . esc_html( $b['estimate'] );
		}
		echo '</p>';
	}

	echo '<p class="description">' . esc_html__( 'Assigning a driver sets the status to "Driver assigned" and texts the driver when SMS is set up.', 'taxi-peninsula' ) . '</p>';

	if ( 'online' === $b['payment'] ) {
		echo '<p><strong>' . esc_html__( 'Online deposit:', 'taxi-peninsula' ) . '</strong> ';
		echo $b['paid'] > 0
			? '<span class="tp-badge tp-badge--completed">' . esc_html( tp_money( $b['paid'] ) . ' ' . __( 'paid', 'taxi-peninsula' ) ) . '</span>'
			: '<span class="tp-badge tp-badge--pending">' . esc_html__( 'Not paid yet', 'taxi-peninsula' ) . '</span>';
		echo '</p>';
	}
}

function tp_render_log_box( WP_Post $post ) {
	$log      = array_reverse( array_filter( (array) get_post_meta( $post->ID, '_tp_log', true ) ) );
	$statuses = tp_statuses();
	echo '<ul class="tp-log">';
	foreach ( $log as $entry ) {
		$entry = array_merge( array( 'to' => '', 'note' => '', 'notify' => false, 'user' => '', 'time' => '' ), (array) $entry );
		printf(
			'<li%5$s><strong>%1$s</strong> %2$s<br><small>%3$s%4$s</small></li>',
			esc_html( $entry['note'] ? $entry['note'] : ( $statuses[ $entry['to'] ] ?? $entry['to'] ) ),
			$entry['notify'] ? '<span class="dashicons dashicons-email-alt" title="' . esc_attr__( 'Customer emailed', 'taxi-peninsula' ) . '"></span>' : '',
			esc_html( mysql2date( 'j M Y g:i a', $entry['time'] ) ),
			$entry['user'] ? ' · ' . esc_html( $entry['user'] ) : '',
			$entry['note'] ? ' class="tp-log__note"' : ''
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

	$notify = ! empty( $_POST['tp_notify'] );
	$status = isset( $_POST['tp_status'] ) ? sanitize_key( wp_unslash( $_POST['tp_status'] ) ) : 'pending';
	$before = get_post_meta( $post_id, '_tp_status', true );
	if ( isset( tp_statuses()[ $status ] ) ) {
		tp_set_status( $post_id, $status, $notify );
	}

	// A driver change may move the status to "Driver assigned" unless staff picked a status themselves.
	$driver_id = isset( $_POST['tp_driver_id'] ) ? absint( $_POST['tp_driver_id'] ) : 0;
	$fleet_id  = isset( $_POST['tp_fleet_id'] ) ? absint( $_POST['tp_fleet_id'] ) : 0;
	if ( $driver_id && ! in_array( 'tp_driver', (array) ( get_userdata( $driver_id )->roles ?? array() ), true ) ) {
		$driver_id = 0;
	}
	if ( $fleet_id && 'tp_fleet' !== get_post_type( $fleet_id ) ) {
		$fleet_id = 0;
	}
	if ( $status !== $before && ! in_array( $status, array( 'pending', 'confirmed' ), true ) ) {
		// Staff chose a later status explicitly; record the assignment without changing it again.
		update_post_meta( $post_id, '_tp_driver_id', $driver_id );
		update_post_meta( $post_id, '_tp_fleet_id', $fleet_id );
	} else {
		tp_assign( $post_id, $driver_id, $fleet_id, $notify );
	}

	if ( ! empty( $_POST['tp_request_done'] ) ) {
		delete_post_meta( $post_id, '_tp_request' );
		tp_add_log( $post_id, array( 'note' => __( 'Customer request handled', 'taxi-peninsula' ) ) );
	}

	$series = get_post_meta( $post_id, '_tp_series', true );
	if ( $series && ! empty( $_POST['tp_apply_series'] ) && isset( tp_statuses()[ $status ] ) ) {
		$from = get_post_meta( $post_id, '_tp_pickup_at', true );
		foreach ( tp_series_ids( $series ) as $sid ) {
			if ( (int) $sid !== (int) $post_id && get_post_meta( $sid, '_tp_pickup_at', true ) > $from && current_user_can( 'edit_post', $sid ) ) {
				tp_set_status( $sid, $status, false );
			}
		}
	}

	// Keep title and search text in sync without re-triggering this hook.
	remove_action( 'save_post_tp_booking', 'tp_save_booking_admin', 10 );
	wp_update_post(
		array_merge(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			),
			tp_booking_post_fields( $ref, $data )
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
	$args = array_intersect_key( wp_unslash( $_GET ), array_flip( array( 'tp_status', 'tp_when', 'tp_from', 'tp_to', 'tp_driver', 'tp_series' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passed through add_query_arg & sanitized on export.
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
	fputcsv( $out, array_merge( array( 'Reference', 'Status' ), wp_list_pluck( $fields, 0 ), array( 'Driver', 'Assigned vehicle', 'Paid online', 'Repeat series', 'Internal notes', 'Received' ) ), ",", "\"", "" );

	foreach ( $ids as $id ) {
		$b                 = tp_get_booking( $id );
		$b['vehicle']      = tp_vehicle_types()[ $b['vehicle'] ] ?? $b['vehicle'];
		$b['mobility_aid'] = tp_mobility_aids()[ $b['mobility_aid'] ] ?? $b['mobility_aid'];
		$b['payment']      = tp_all_payment_methods()[ $b['payment'] ] ?? $b['payment'];
		$row               = array( $b['reference'], tp_statuses()[ $b['status'] ] ?? $b['status'] );
		foreach ( array_keys( $fields ) as $key ) {
			$row[] = $b[ $key ];
		}
		$driver = $b['driver_id'] ? get_userdata( $b['driver_id'] ) : null;
		$row[]  = $driver ? $driver->display_name : '';
		$row[]  = $b['fleet_id'] ? tp_fleet_label( $b['fleet_id'] ) : '';
		$row[]  = $b['paid'] > 0 ? number_format( $b['paid'], 2, '.', '' ) : '';
		$row[]  = $b['series'];
		$row[]  = $b['admin_notes'];
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
