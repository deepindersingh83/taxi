<?php
/**
 * Incident & complaint register (Bookings → Incidents) and the data breach
 * response plan (Taxi settings → Data breach plan).
 *
 * Register: safety incidents, injuries, near misses, vehicle problems,
 * complaints and privacy breaches, each with the trip, driver and vehicle,
 * what happened, what was done, the outcome, which regulators it was
 * reported to and when, and a follow-up date. Complaints sent through the
 * contact form are added automatically. CSV export for audits.
 *
 * Breach plan: the four steps of the Notifiable Data Breaches scheme
 * (contain, assess, notify, review) as a checklist with your response
 * team, notification wording and a printable copy. Every privacy breach
 * goes into the register so there is a record either way.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_incident_types() {
	return array(
		'safety'    => __( 'Safety incident', 'taxi-peninsula' ),
		'injury'    => __( 'Injury', 'taxi-peninsula' ),
		'near_miss' => __( 'Near miss', 'taxi-peninsula' ),
		'vehicle'   => __( 'Vehicle or equipment fault', 'taxi-peninsula' ),
		'complaint' => __( 'Complaint', 'taxi-peninsula' ),
		'conduct'   => __( 'Driver or staff conduct', 'taxi-peninsula' ),
		'privacy'   => __( 'Privacy / data breach', 'taxi-peninsula' ),
		'other'     => __( 'Other', 'taxi-peninsula' ),
	);
}

function tp_incident_statuses() {
	return array(
		'open'          => __( 'Open', 'taxi-peninsula' ),
		'investigating' => __( 'Investigating', 'taxi-peninsula' ),
		'closed'        => __( 'Closed', 'taxi-peninsula' ),
	);
}

function tp_incident_bodies() {
	return array(
		'cpvv'   => __( 'Commercial Passenger Vehicles Victoria', 'taxi-peninsula' ),
		'ndis'   => __( 'NDIS Quality and Safeguards Commission', 'taxi-peninsula' ),
		'oaic'   => __( 'Office of the Australian Information Commissioner (OAIC)', 'taxi-peninsula' ),
		'work'   => __( 'WorkSafe Victoria', 'taxi-peninsula' ),
		'police' => __( 'Police', 'taxi-peninsula' ),
		'insurer' => __( 'Insurer', 'taxi-peninsula' ),
	);
}

add_action(
	'init',
	static function () {
		register_post_type(
			'tp_incident',
			array(
				'labels'          => array(
					'name'          => __( 'Incidents & complaints', 'taxi-peninsula' ),
					'singular_name' => __( 'Incident', 'taxi-peninsula' ),
					'menu_name'     => __( 'Incidents', 'taxi-peninsula' ),
					'all_items'     => __( 'Incidents', 'taxi-peninsula' ),
					'add_new_item'  => __( 'Record an incident or complaint', 'taxi-peninsula' ),
					'edit_item'     => __( 'Incident', 'taxi-peninsula' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => tp_admin_parent( 'bookings' ),
				'supports'        => array( 'title' ),
				'capability_type' => array( 'tp_booking', 'tp_bookings' ),
				'map_meta_cap'    => true,
			)
		);
	}
);

function tp_incident_meta( $id ) {
	$m = array();
	foreach ( array( 'type', 'status', 'when', 'reference', 'booking_id', 'driver_id', 'fleet_id', 'people', 'what', 'action', 'outcome', 'followup', 'source' ) as $k ) {
		$m[ $k ] = (string) get_post_meta( $id, '_tp_i_' . $k, true );
	}
	$m['reported'] = (array) get_post_meta( $id, '_tp_i_reported', true );
	$m['type']     = $m['type'] ?: 'other';
	$m['status']   = $m['status'] ?: 'open';
	return $m;
}

/**
 * Add an incident from code (complaints, breaches). Returns post ID.
 */
function tp_add_incident( $type, $title, array $meta = array() ) {
	$id = wp_insert_post( array( 'post_type' => 'tp_incident', 'post_status' => 'publish', 'post_title' => mb_substr( $title, 0, 120 ) ) );
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	$meta = array_merge( array( 'type' => $type, 'status' => 'open', 'when' => current_time( 'Y-m-d\TH:i' ) ), $meta );
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, '_tp_i_' . $k, $v );
	}
	return $id;
}

// Complaints from the contact form go straight into the register.
add_action(
	'save_post_tp_message',
	static function ( $post_id, $post, $update ) {
		if ( $update || wp_is_post_revision( $post_id ) ) {
			return;
		}
		add_action(
			'shutdown',
			static function () use ( $post_id, $post ) {
				$topic = (string) get_post_meta( $post_id, '_tp_topic', true );
				if ( __( 'Complaint', 'taxi-peninsula' ) !== $topic && 'Complaint' !== $topic ) {
					return;
				}
				/* translators: %s: name */
				tp_add_incident( 'complaint', sprintf( __( 'Complaint from %s', 'taxi-peninsula' ), $post->post_title ), array( 'what' => wp_strip_all_tags( $post->post_content ), 'people' => $post->post_title . ' ' . get_post_meta( $post_id, '_tp_email', true ) . ' ' . get_post_meta( $post_id, '_tp_phone', true ), 'source' => 'message:' . $post_id ) );
			},
			1
		);
	},
	10,
	3
);

add_action(
	'add_meta_boxes_tp_incident',
	static function () {
		add_meta_box( 'tp_incident_box', __( 'Details', 'taxi-peninsula' ), 'tp_render_incident_box', 'tp_incident', 'normal', 'high' );
	}
);

function tp_render_incident_box( $post ) {
	$m = tp_incident_meta( $post->ID );
	wp_nonce_field( 'tp_incident_save', 'tp_incident_nonce' );
	if ( 'auto-draft' === $post->post_status && isset( $_GET['type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$m['type'] = sanitize_key( wp_unslash( $_GET['type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	$sel = static function ( $name, $choices, $value ) {
		printf( '<select id="tp_i_%1$s" name="tp_i[%1$s]">', esc_attr( $name ) );
		foreach ( $choices as $k => $l ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $k ), selected( $value, (string) $k, false ), esc_html( $l ) );
		}
		echo '</select>';
	};
	$drivers = array( '' => '—' );
	foreach ( tp_drivers() as $d ) {
		$drivers[ $d->ID ] = $d->display_name;
	}
	$fleet = array( '' => '—' );
	foreach ( get_posts( array( 'post_type' => 'tp_fleet', 'numberposts' => 50, 'post_status' => array( 'publish', 'draft' ) ) ) as $f ) {
		$fleet[ $f->ID ] = get_the_title( $f );
	}
	$text = static function ( $name, $value, $rows = 3 ) {
		printf( '<textarea class="large-text" rows="%3$d" id="tp_i_%1$s" name="tp_i[%1$s]">%2$s</textarea>', esc_attr( $name ), esc_textarea( $value ), (int) $rows );
	};
	?>
	<p class="description"><?php esc_html_e( 'Keep facts, not opinions. Do not delete records — close them with an outcome instead.', 'taxi-peninsula' ); ?></p>
	<table class="form-table" role="presentation">
		<tr><th><label for="tp_i_type"><?php esc_html_e( 'Type', 'taxi-peninsula' ); ?></label></th><td><?php $sel( 'type', tp_incident_types(), $m['type'] ); ?></td></tr>
		<tr><th><label for="tp_i_status"><?php esc_html_e( 'Status', 'taxi-peninsula' ); ?></label></th><td><?php $sel( 'status', tp_incident_statuses(), $m['status'] ); ?></td></tr>
		<tr><th><label for="tp_i_when"><?php esc_html_e( 'When it happened', 'taxi-peninsula' ); ?></label></th><td><input type="datetime-local" id="tp_i_when" name="tp_i[when]" value="<?php echo esc_attr( $m['when'] ); ?>"></td></tr>
		<tr><th><label for="tp_i_reference"><?php esc_html_e( 'Booking reference', 'taxi-peninsula' ); ?></label></th><td><input type="text" id="tp_i_reference" name="tp_i[reference]" value="<?php echo esc_attr( $m['reference'] ); ?>" placeholder="TP-XXXXXX">
			<?php if ( $m['booking_id'] && get_post( (int) $m['booking_id'] ) ) : ?>
				<a href="<?php echo esc_url( get_edit_post_link( (int) $m['booking_id'] ) ); ?>"><?php esc_html_e( 'Open booking', 'taxi-peninsula' ); ?></a>
			<?php endif; ?></td></tr>
		<tr><th><label for="tp_i_driver_id"><?php esc_html_e( 'Driver', 'taxi-peninsula' ); ?></label></th><td><?php $sel( 'driver_id', $drivers, $m['driver_id'] ); ?></td></tr>
		<tr><th><label for="tp_i_fleet_id"><?php esc_html_e( 'Vehicle', 'taxi-peninsula' ); ?></label></th><td><?php $sel( 'fleet_id', $fleet, $m['fleet_id'] ); ?></td></tr>
		<tr><th><label for="tp_i_people"><?php esc_html_e( 'People involved and contact details', 'taxi-peninsula' ); ?></label></th><td><?php $text( 'people', $m['people'], 2 ); ?></td></tr>
		<tr><th><label for="tp_i_what"><?php esc_html_e( 'What happened', 'taxi-peninsula' ); ?></label></th><td><?php $text( 'what', $m['what'], 4 ); ?></td></tr>
		<tr><th><label for="tp_i_action"><?php esc_html_e( 'Immediate action taken', 'taxi-peninsula' ); ?></label></th><td><?php $text( 'action', $m['action'] ); ?></td></tr>
		<tr><th><label for="tp_i_outcome"><?php esc_html_e( 'Outcome and changes made', 'taxi-peninsula' ); ?></label></th><td><?php $text( 'outcome', $m['outcome'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Reported to', 'taxi-peninsula' ); ?></th><td>
			<?php foreach ( tp_incident_bodies() as $k => $l ) : ?>
				<p><label><input type="checkbox" name="tp_i[reported][<?php echo esc_attr( $k ); ?>][on]" value="1" <?php checked( ! empty( $m['reported'][ $k ]['on'] ) ); ?>> <?php echo esc_html( $l ); ?></label>
				<label class="screen-reader-text" for="tp_i_rep_<?php echo esc_attr( $k ); ?>"><?php esc_html_e( 'Date reported', 'taxi-peninsula' ); ?></label>
				<input type="date" id="tp_i_rep_<?php echo esc_attr( $k ); ?>" name="tp_i[reported][<?php echo esc_attr( $k ); ?>][date]" value="<?php echo esc_attr( $m['reported'][ $k ]['date'] ?? '' ); ?>"></p>
			<?php endforeach; ?>
			<p class="description"><?php esc_html_e( 'Check your obligations with each body — for example, registered NDIS providers must notify reportable incidents to the NDIS Commission within set timeframes, and eligible data breaches must be notified to the OAIC.', 'taxi-peninsula' ); ?></p></td></tr>
		<tr><th><label for="tp_i_followup"><?php esc_html_e( 'Follow up by', 'taxi-peninsula' ); ?></label></th><td><input type="date" id="tp_i_followup" name="tp_i[followup]" value="<?php echo esc_attr( $m['followup'] ); ?>"></td></tr>
	</table>
	<?php if ( 'privacy' === $m['type'] ) : ?>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=tp-breach-plan' ) ); ?>"><?php esc_html_e( 'Open the data breach response plan', 'taxi-peninsula' ); ?></a></p>
	<?php endif; ?>
	<?php if ( 0 === strpos( $m['source'], 'message:' ) ) : ?>
		<p><a href="<?php echo esc_url( get_edit_post_link( (int) substr( $m['source'], 8 ) ) ); ?>"><?php esc_html_e( 'Open the original message', 'taxi-peninsula' ); ?></a></p>
	<?php endif; ?>
	<?php
}

add_action(
	'save_post_tp_incident',
	static function ( $post_id ) {
		if ( ! isset( $_POST['tp_incident_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tp_incident_nonce'] ), 'tp_incident_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in  = isset( $_POST['tp_i'] ) && is_array( $_POST['tp_i'] ) ? wp_unslash( $_POST['tp_i'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.
		$old = tp_incident_meta( $post_id );
		update_post_meta( $post_id, '_tp_i_type', isset( tp_incident_types()[ $in['type'] ?? '' ] ) ? $in['type'] : 'other' );
		$status = isset( tp_incident_statuses()[ $in['status'] ?? '' ] ) ? $in['status'] : 'open';
		update_post_meta( $post_id, '_tp_i_status', $status );
		update_post_meta( $post_id, '_tp_i_when', preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string) ( $in['when'] ?? '' ) ) ? $in['when'] : '' );
		$ref = strtoupper( sanitize_text_field( $in['reference'] ?? '' ) );
		update_post_meta( $post_id, '_tp_i_reference', $ref );
		$bid = tp_find_booking( $ref );
		update_post_meta( $post_id, '_tp_i_booking_id', $bid ?: '' );
		foreach ( array( 'driver_id', 'fleet_id' ) as $k ) {
			$v = absint( $in[ $k ] ?? 0 );
			if ( ! $v && $bid ) {
				$v = (int) get_post_meta( $bid, 'driver_id' === $k ? '_tp_driver_id' : '_tp_fleet_id', true );
			}
			update_post_meta( $post_id, '_tp_i_' . $k, $v ?: '' );
		}
		foreach ( array( 'people', 'what', 'action', 'outcome' ) as $k ) {
			update_post_meta( $post_id, '_tp_i_' . $k, sanitize_textarea_field( $in[ $k ] ?? '' ) );
		}
		update_post_meta( $post_id, '_tp_i_followup', preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $in['followup'] ?? '' ) ) ? $in['followup'] : '' );
		$rep = array();
		foreach ( array_keys( tp_incident_bodies() ) as $k ) {
			$d = (string) ( $in['reported'][ $k ]['date'] ?? '' );
			if ( ! empty( $in['reported'][ $k ]['on'] ) || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
				$rep[ $k ] = array( 'on' => 1, 'date' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : '' );
			}
		}
		update_post_meta( $post_id, '_tp_i_reported', $rep );
		if ( $bid && $old['booking_id'] !== (string) $bid ) {
			/* translators: %s: type */
			tp_add_log( $bid, array( 'note' => sprintf( __( 'Incident recorded: %s', 'taxi-peninsula' ), tp_incident_types()[ get_post_meta( $post_id, '_tp_i_type', true ) ] ?? '' ) ) );
		}
		if ( function_exists( 'tp_activity' ) && $old['status'] !== $status ) {
			/* translators: 1: title 2: status */
			tp_activity( 'note', sprintf( __( 'Incident "%1$s": %2$s', 'taxi-peninsula' ), get_the_title( $post_id ), tp_incident_statuses()[ $status ] ) );
		}
	}
);

add_filter(
	'manage_tp_incident_posts_columns',
	static function () {
		return array(
			'cb'          => '<input type="checkbox">',
			'title'       => __( 'Incident', 'taxi-peninsula' ),
			'tp_type'     => __( 'Type', 'taxi-peninsula' ),
			'tp_when'     => __( 'When', 'taxi-peninsula' ),
			'tp_ref'      => __( 'Trip', 'taxi-peninsula' ),
			'tp_reported' => __( 'Reported to', 'taxi-peninsula' ),
			'tp_followup' => __( 'Follow up', 'taxi-peninsula' ),
			'tp_status'   => __( 'Status', 'taxi-peninsula' ),
		);
	}
);
add_action(
	'manage_tp_incident_posts_custom_column',
	static function ( $col, $id ) {
		$m = tp_incident_meta( $id );
		switch ( $col ) {
			case 'tp_type':
				echo esc_html( tp_incident_types()[ $m['type'] ] ?? $m['type'] );
				break;
			case 'tp_when':
				echo esc_html( $m['when'] ? wp_date( 'j M Y, g:i a', strtotime( $m['when'] ) ) : '' );
				break;
			case 'tp_ref':
				echo esc_html( $m['reference'] ?: '—' );
				break;
			case 'tp_reported':
				echo esc_html( implode( ', ', array_map( static fn( $k ) => strtoupper( $k ), array_keys( array_filter( $m['reported'] ) ) ) ) ?: '—' );
				break;
			case 'tp_followup':
				$late = $m['followup'] && 'closed' !== $m['status'] && $m['followup'] < wp_date( 'Y-m-d' );
				echo $m['followup'] ? '<span' . ( $late ? ' style="color:#b32d2e;font-weight:600"' : '' ) . '>' . esc_html( wp_date( 'j M Y', strtotime( $m['followup'] ) ) . ( $late ? ' — ' . __( 'overdue', 'taxi-peninsula' ) : '' ) ) . '</span>' : '—';
				break;
			case 'tp_status':
				echo esc_html( tp_incident_statuses()[ $m['status'] ] ?? $m['status'] );
				break;
		}
	},
	10,
	2
);

// Filters by type and status, and a CSV export.
add_action(
	'restrict_manage_posts',
	static function ( $type ) {
		if ( 'tp_incident' !== $type ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$t = isset( $_GET['tp_i_type'] ) ? sanitize_key( wp_unslash( $_GET['tp_i_type'] ) ) : '';
		$s = isset( $_GET['tp_i_status'] ) ? sanitize_key( wp_unslash( $_GET['tp_i_status'] ) ) : '';
		// phpcs:enable
		echo '<label class="screen-reader-text" for="tp_i_type_f">' . esc_html__( 'Type', 'taxi-peninsula' ) . '</label><select id="tp_i_type_f" name="tp_i_type"><option value="">' . esc_html__( 'All types', 'taxi-peninsula' ) . '</option>';
		foreach ( tp_incident_types() as $k => $l ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $k ), selected( $t, $k, false ), esc_html( $l ) );
		}
		echo '</select><label class="screen-reader-text" for="tp_i_status_f">' . esc_html__( 'Status', 'taxi-peninsula' ) . '</label><select id="tp_i_status_f" name="tp_i_status"><option value="">' . esc_html__( 'Any status', 'taxi-peninsula' ) . '</option>';
		foreach ( tp_incident_statuses() as $k => $l ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $k ), selected( $s, $k, false ), esc_html( $l ) );
		}
		echo '</select>';
		printf( ' <a class="button" href="%1$s">%2$s</a>', esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tp_incidents_csv' ), 'tp_incidents_csv' ) ), esc_html__( 'Download CSV', 'taxi-peninsula' ) );
	}
);

add_action(
	'pre_get_posts',
	static function ( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || 'tp_incident' !== $q->get( 'post_type' ) ) {
			return;
		}
		$mq = array();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		foreach ( array( 'tp_i_type' => '_tp_i_type', 'tp_i_status' => '_tp_i_status' ) as $arg => $key ) {
			if ( ! empty( $_GET[ $arg ] ) ) {
				$mq[] = array( 'key' => $key, 'value' => sanitize_key( wp_unslash( $_GET[ $arg ] ) ) );
			}
		}
		// phpcs:enable
		if ( $mq ) {
			$q->set( 'meta_query', $mq );
		}
	}
);

add_action(
	'admin_post_tp_incidents_csv',
	static function () {
		check_admin_referer( 'tp_incidents_csv' );
		if ( ! current_user_can( 'edit_tp_bookings' ) ) {
			wp_die( '', 403 );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="incident-register-' . wp_date( 'Y-m-d' ) . '.csv"' );
		$out  = fopen( 'php://output', 'w' );
		$safe = static fn( $v ) => ( '' !== (string) $v && in_array( ( (string) $v )[0], array( '=', '+', '-', '@' ), true ) ) ? "'" . $v : (string) $v;
		fputcsv( $out, array( 'Recorded', 'When', 'Type', 'Status', 'Title', 'Booking', 'Driver', 'Vehicle', 'People', 'What happened', 'Action', 'Outcome', 'Reported to', 'Follow up' ), ',', '"', '' );
		foreach ( get_posts( array( 'post_type' => 'tp_incident', 'numberposts' => -1, 'post_status' => 'any' ) ) as $p ) {
			$m   = tp_incident_meta( $p->ID );
			$rep = array();
			foreach ( $m['reported'] as $k => $r ) {
				$rep[] = strtoupper( $k ) . ( ! empty( $r['date'] ) ? ' ' . $r['date'] : '' );
			}
			fputcsv( $out, array_map( $safe, array( $p->post_date, $m['when'], tp_incident_types()[ $m['type'] ] ?? $m['type'], tp_incident_statuses()[ $m['status'] ] ?? $m['status'], $p->post_title, $m['reference'], $m['driver_id'] ? get_the_author_meta( 'display_name', (int) $m['driver_id'] ) : '', $m['fleet_id'] ? get_the_title( (int) $m['fleet_id'] ) : '', $m['people'], $m['what'], $m['action'], $m['outcome'], implode( '; ', $rep ), $m['followup'] ) ), ',', '"', '' );
		}
		exit;
	}
);

// Open incidents count on the menu.
add_action(
	'admin_menu',
	static function () {
		global $submenu;
		$key = tp_admin_parent( 'bookings' );
		if ( empty( $submenu[ $key ] ) ) {
			return;
		}
		$open = count( get_posts( array( 'post_type' => 'tp_incident', 'numberposts' => 99, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_tp_i_status', 'value' => 'closed', 'compare' => '!=' ) ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( ! $open ) {
			return;
		}
		foreach ( $submenu[ $key ] as $i => $item ) {
			if ( 'edit.php?post_type=tp_incident' === $item[2] ) {
				$submenu[ $key ][ $i ][0] .= sprintf( ' <span class="awaiting-mod"><span class="pending-count">%d</span></span>', $open );
			}
		}
	},
	99
);

/* =========================================================================
 * Data breach response plan.
 * ====================================================================== */

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['breach_team'] = array( 'privacy', __( 'Data breach response team', 'taxi-peninsula' ), 'textarea', '', __( 'One per line: name, role, phone. Shown in the data breach plan (Taxi settings → Data breach plan).', 'taxi-peninsula' ) );
		return $s;
	}
);

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( tp_admin_parent( 'settings' ), __( 'Data breach response plan', 'taxi-peninsula' ), __( 'Data breach plan', 'taxi-peninsula' ), 'manage_options', 'tp-breach-plan', 'tp_render_breach_plan' );
	},
	30
);

function tp_breach_steps() {
	return array(
		array(
			__( '1. Contain', 'taxi-peninsula' ),
			array(
				__( 'Stop the breach: change passwords, revoke access, recall a wrongly sent email, retrieve lost devices or papers.', 'taxi-peninsula' ),
				__( 'If an account was used: sign it out everywhere (Users → edit → "Log out everywhere else") and reset its password.', 'taxi-peninsula' ),
				__( 'Keep evidence: do not delete logs. Download the activity log (Reports → Activity log) for the period.', 'taxi-peninsula' ),
				__( 'Record it now in the incident register (type: Privacy / data breach) with the time you found out.', 'taxi-peninsula' ),
			),
		),
		array(
			__( '2. Assess (within 30 days)', 'taxi-peninsula' ),
			array(
				__( 'What information was involved (names, phone numbers, addresses, health or disability details, NDIS numbers, payment details)?', 'taxi-peninsula' ),
				__( 'Whose information, and how many people?', 'taxi-peninsula' ),
				__( 'Is serious harm likely — for example identity theft, physical safety, or embarrassment from disability or health details being exposed?', 'taxi-peninsula' ),
				__( 'Can action now prevent the harm (for example the email was recalled unread)? If so, it may not need to be notified.', 'taxi-peninsula' ),
				__( 'If you suspect an eligible data breach, the assessment must be done quickly — the OAIC expects it within 30 days.', 'taxi-peninsula' ),
			),
		),
		array(
			__( '3. Notify', 'taxi-peninsula' ),
			array(
				__( 'If it is an eligible data breach (likely serious harm that could not be prevented), notify the OAIC and the people affected as soon as practicable.', 'taxi-peninsula' ),
				__( 'The Notifiable Data Breaches scheme covers businesses under the Privacy Act — generally those with turnover over $3 million, and also smaller businesses that provide a health service. Check with the OAIC whether it applies to you; notifying voluntarily is still good practice.', 'taxi-peninsula' ),
				__( 'If NDIS participants are affected and you are a registered NDIS provider, check whether it is also a reportable incident for the NDIS Commission.', 'taxi-peninsula' ),
				__( 'Tell people what happened, what information was involved, what you are doing, and what they should do (for example watch for scam calls).', 'taxi-peninsula' ),
			),
		),
		array(
			__( '4. Review', 'taxi-peninsula' ),
			array(
				__( 'Why did it happen? What will stop it happening again (training, settings, a process change)?', 'taxi-peninsula' ),
				__( 'Update this plan and close the incident with the outcome.', 'taxi-peninsula' ),
			),
		),
	);
}

function tp_breach_notice_template() {
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	/* translators: 1: business 2: phone 3: email */
	return sprintf( __( "Subject: Important information about your personal information\n\nDear [name],\n\nOn [date] we found that [what happened]. The information involved was [list, e.g. your name, mobile number and pick-up address]. Your payment card details were [not] involved.\n\nAs soon as we found out we [what you did to contain it].\n\nWe recommend that you [steps, e.g. be careful of calls or messages claiming to be from us].\n\nWe are sorry this happened. If you have any questions, please contact %1\$s on %2\$s or %3\$s. You can also contact the Office of the Australian Information Commissioner at oaic.gov.au.\n\n%1\$s", 'taxi-peninsula' ), $site, wp_strip_all_tags( tp_opt( 'phone_display' ) ), tp_opt( 'email' ) );
}

function tp_render_breach_plan() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$team = array_filter( array_map( 'trim', preg_split( '/\R/', (string) tp_setting( 'breach_team' ) ) ) );
	$open = get_posts( array( 'post_type' => 'tp_incident', 'numberposts' => 20, 'meta_query' => array( array( 'key' => '_tp_i_type', 'value' => 'privacy' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	?>
	<div class="wrap tp-breach">
		<h1><?php esc_html_e( 'Data breach response plan', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'What to do if personal information is lost, stolen, or seen by someone who should not see it — for example an email sent to the wrong person, a stolen phone with the driver screen open, or a hacked account.', 'taxi-peninsula' ); ?></p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=tp_incident&type=privacy' ) ); ?>"><?php esc_html_e( 'Record a data breach now', 'taxi-peninsula' ); ?></a>
			<button type="button" class="button" onclick="window.print()"><?php esc_html_e( 'Print this plan', 'taxi-peninsula' ); ?></button>
		</p>
		<h2><?php esc_html_e( 'Response team', 'taxi-peninsula' ); ?></h2>
		<?php if ( $team ) : ?>
			<ul class="ul-disc"><?php foreach ( $team as $t ) : ?><li><?php echo esc_html( $t ); ?></li><?php endforeach; ?></ul>
		<?php else : ?>
			<p class="description"><?php printf( /* translators: %s: link */ esc_html__( 'Add the people to call in %s.', 'taxi-peninsula' ), '<a href="' . esc_url( admin_url( 'admin.php?page=tp-settings' ) ) . '">' . esc_html__( 'Taxi settings → Settings → Privacy', 'taxi-peninsula' ) . '</a>' ); ?></p>
		<?php endif; ?>
		<?php foreach ( tp_breach_steps() as $step ) : ?>
			<h2><?php echo esc_html( $step[0] ); ?></h2>
			<ul class="tp-breach__list">
				<?php foreach ( $step[1] as $i => $line ) : ?>
					<li><label><input type="checkbox"> <?php echo esc_html( $line ); ?></label></li>
				<?php endforeach; ?>
			</ul>
		<?php endforeach; ?>
		<h2><?php esc_html_e( 'Notification wording (edit before sending)', 'taxi-peninsula' ); ?></h2>
		<textarea class="large-text code" rows="14" readonly onclick="this.select()"><?php echo esc_textarea( tp_breach_notice_template() ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Report to the OAIC with its online Notifiable Data Breach form at oaic.gov.au. This plan is general information, not legal advice.', 'taxi-peninsula' ); ?></p>
		<h2><?php esc_html_e( 'Recorded privacy breaches', 'taxi-peninsula' ); ?></h2>
		<?php if ( $open ) : ?>
			<ul class="ul-disc">
				<?php foreach ( $open as $p ) : ?>
					<?php $m = tp_incident_meta( $p->ID ); ?>
					<li><a href="<?php echo esc_url( get_edit_post_link( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a> — <?php echo esc_html( tp_incident_statuses()[ $m['status'] ] . ( $m['when'] ? ', ' . wp_date( 'j M Y', strtotime( $m['when'] ) ) : '' ) ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e( 'None recorded.', 'taxi-peninsula' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}
