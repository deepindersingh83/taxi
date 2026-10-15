<?php
/**
 * Activity log: who changed which booking, and when.
 *
 * Every status change, driver assignment, edited field, cancellation,
 * rating, privacy request and staff sign-in is written to one table
 * ({prefix}tp_activity). Staff see it under Bookings → Activity log, filtered
 * by booking, person, action or date, and can export it as CSV.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

const TP_ACTIVITY_DB = 1;

function tp_activity_table() {
	global $wpdb;
	return $wpdb->prefix . 'tp_activity';
}

add_action(
	'init',
	static function () {
		if ( (int) get_option( 'tp_activity_db' ) === TP_ACTIVITY_DB ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = tp_activity_table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				created datetime NOT NULL,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				actor varchar(120) NOT NULL DEFAULT '',
				action varchar(40) NOT NULL DEFAULT '',
				booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
				reference varchar(20) NOT NULL DEFAULT '',
				summary text NOT NULL,
				ip varchar(45) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				KEY created (created),
				KEY booking_id (booking_id),
				KEY user_id (user_id),
				KEY action (action)
			) {$charset};"
		);
		update_option( 'tp_activity_db', TP_ACTIVITY_DB, false );
	},
	5
);

/**
 * Action types shown in the log filter.
 */
function tp_activity_actions() {
	return apply_filters(
		'tp_activity_actions',
		array(
		'created'  => __( 'Booking created', 'taxi-peninsula' ),
		'status'   => __( 'Status changed', 'taxi-peninsula' ),
		'edited'   => __( 'Details changed', 'taxi-peninsula' ),
		'note'     => __( 'Driver, request or rating', 'taxi-peninsula' ),
		'deleted'  => __( 'Booking deleted', 'taxi-peninsula' ),
		'privacy'  => __( 'Privacy', 'taxi-peninsula' ),
		'login'    => __( 'Staff sign-in', 'taxi-peninsula' ),
		'settings' => __( 'Settings', 'taxi-peninsula' ),
		'enquiry'  => __( 'Quotes, lost property & sign-ups', 'taxi-peninsula' ),
		)
	);
}

/**
 * Name an actor for changes made without a signed-in user (e.g. a customer
 * using their booking link, or a payment webhook). Pass null to reset.
 *
 * @param string|null $label Label.
 * @return string|null Current label.
 */
function tp_activity_as( $label = false ) {
	static $as = null;
	if ( false !== $label ) {
		$as = $label;
	}
	return $as;
}

/**
 * Write one activity entry.
 *
 * @param string $action     One of tp_activity_actions().
 * @param string $summary    Plain-text description.
 * @param int    $booking_id Booking ID, if any.
 * @param int    $user_id    Override the acting user (e.g. on sign-in).
 */
function tp_activity( $action, $summary, $booking_id = 0, $user_id = null ) {
	global $wpdb;
	if ( (int) get_option( 'tp_activity_db' ) !== TP_ACTIVITY_DB ) {
		return;
	}
	$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
	$user    = $user_id ? get_userdata( $user_id ) : false;
	if ( $user ) {
		$actor = $user->display_name;
	} elseif ( tp_activity_as() ) {
		$actor = tp_activity_as();
	} else {
		$actor = tp_in_site_lang(
			static function () {
				return wp_doing_cron() ? __( 'System (scheduled task)', 'taxi-peninsula' ) : __( 'Customer or visitor (not signed in)', 'taxi-peninsula' );
			}
		);
	}

	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		tp_activity_table(),
		array(
			'created'    => current_time( 'mysql' ),
			'user_id'    => $user ? $user_id : 0,
			'actor'      => mb_substr( $actor, 0, 120 ),
			'action'     => $action,
			'booking_id' => (int) $booking_id,
			'reference'  => $booking_id ? (string) get_post_meta( $booking_id, '_tp_reference', true ) : '',
			'summary'    => $summary,
			'ip'         => (string) tp_client_ip(),
		),
		array( '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
	);
}

/* -------------------------------------------------------------------------
 * What gets logged.
 * ---------------------------------------------------------------------- */

// Status changes and notes already go into each booking's own history.
add_action(
	'tp_booking_log_added',
	static function ( $post_id, array $entry ) {
		tp_in_site_lang(
			static function () use ( $post_id, $entry ) {
				if ( ! empty( $entry['to'] ) ) {
					$statuses = tp_statuses();
					$summary  = sprintf(
						/* translators: 1: old status, 2: new status */
						__( 'Status: %1$s → %2$s', 'taxi-peninsula' ),
						$statuses[ $entry['from'] ] ?? ( $entry['from'] ?: '—' ),
						$statuses[ $entry['to'] ] ?? $entry['to']
					);
					if ( ! empty( $entry['notify'] ) ) {
						$summary .= ' ' . __( '(customer notified)', 'taxi-peninsula' );
					}
					tp_activity( 'status', $summary, $post_id );
				} elseif ( ! empty( $entry['note'] ) ) {
					tp_activity( 'note', $entry['note'], $post_id );
				}
			}
		);
	},
	10,
	2
);

/**
 * Bookings created in this request: field changes on them are not logged
 * one by one.
 */
function tp_activity_created( $post_id = 0 ) {
	static $ids = array();
	if ( $post_id ) {
		$ids[ $post_id ] = true;
	}
	return $ids;
}

add_action(
	'wp_insert_post',
	static function ( $post_id, $post, $update ) {
		if ( 'tp_booking' !== $post->post_type || $update || 'auto-draft' === $post->post_status ) {
			return;
		}
		tp_activity_created( $post_id );
		tp_activity_pending( $post_id, '__created', '', '' );
	},
	10,
	3
);

// Staff "Add new" starts as an auto-draft; its first real save counts as creation.
add_action(
	'transition_post_status',
	static function ( $new, $old, $post ) {
		if ( 'tp_booking' === $post->post_type && 'auto-draft' === $old && 'auto-draft' !== $new ) {
			tp_activity_created( $post->ID );
			tp_activity_pending( $post->ID, '__created', '', '' );
		}
	},
	10,
	3
);

/**
 * Meta keys watched for changes, with labels. Sensitive ones are logged
 * as "changed" without the values.
 */
function tp_activity_fields() {
	$fields = array();
	foreach ( tp_booking_fields() as $key => $field ) {
		$fields[ '_tp_' . $key ] = $field[0];
	}
	$fields['_tp_fare']           = __( 'Fare', 'taxi-peninsula' );
	$fields['_tp_paid']           = __( 'Paid online', 'taxi-peninsula' );
	$fields['_tp_invoice_status'] = __( 'Invoice status', 'taxi-peninsula' );
	$fields['_tp_admin_notes']    = __( 'Office notes', 'taxi-peninsula' );
	$fields['_tp_fleet_id']       = __( 'Vehicle', 'taxi-peninsula' );
	$fields['_tp_request']        = __( 'Change request', 'taxi-peninsula' );
	return $fields;
}

function tp_activity_sensitive() {
	return array( '_tp_notes', '_tp_admin_notes', '_tp_ndis_number', '_tp_request' );
}

/**
 * Collect changes during the request; written as one entry per booking at shutdown.
 */
function tp_activity_pending( $post_id = null, $key = null, $old = null, $new = null ) {
	static $changes = array();
	if ( null === $post_id ) {
		$out     = $changes;
		$changes = array();
		return $out;
	}
	if ( ! isset( $changes[ $post_id ][ $key ] ) ) {
		$changes[ $post_id ][ $key ] = array( $old, $new );
	} else {
		$changes[ $post_id ][ $key ][1] = $new;
	}
	return $changes;
}

/**
 * Suppress field-change logging (used while personal data is erased).
 */
function tp_activity_quiet( $set = null ) {
	static $quiet = false;
	if ( null !== $set ) {
		$quiet = (bool) $set;
	}
	return $quiet;
}

function tp_activity_watch( $object_id, $meta_key, $new, $old ) {
	if ( tp_activity_quiet() || ! isset( tp_activity_fields()[ $meta_key ] ) || 'tp_booking' !== get_post_type( $object_id ) ) {
		return;
	}
	if ( isset( tp_activity_created()[ $object_id ] ) || (string) maybe_serialize( $old ) === (string) maybe_serialize( $new ) ) {
		return;
	}
	if ( ( '' === $old || null === $old ) && ( '' === $new || null === $new || 0 === $new || '0' === $new ) ) {
		return;
	}
	tp_activity_pending( $object_id, $meta_key, $old, $new );
}

add_action(
	'update_post_meta',
	static function ( $meta_id, $object_id, $meta_key, $meta_value ) {
		tp_activity_watch( $object_id, $meta_key, $meta_value, get_post_meta( $object_id, $meta_key, true ) );
	},
	10,
	4
);
add_action(
	'added_post_meta',
	static function ( $meta_id, $object_id, $meta_key, $meta_value ) {
		tp_activity_watch( $object_id, $meta_key, $meta_value, '' );
	},
	10,
	4
);
add_action(
	'deleted_post_meta',
	static function ( $meta_ids, $object_id, $meta_key ) {
		if ( '_tp_request' === $meta_key ) {
			tp_activity_watch( $object_id, $meta_key, '', 'x' );
		}
	},
	10,
	3
);

/**
 * Readable value for a watched field.
 */
function tp_activity_value( $key, $value ) {
	if ( '' === $value || null === $value ) {
		return '—';
	}
	switch ( $key ) {
		case '_tp_return_trip':
		case '_tp_mptp':
			return $value ? __( 'Yes', 'taxi-peninsula' ) : __( 'No', 'taxi-peninsula' );
		case '_tp_vehicle':
			return tp_vehicle_types()[ $value ] ?? $value;
		case '_tp_mobility_aid':
			return tp_mobility_aids()[ $value ] ?? $value;
		case '_tp_payment':
			return tp_all_payment_methods()[ $value ] ?? $value;
		case '_tp_fare':
		case '_tp_paid':
			return tp_money( $value );
		case '_tp_fleet_id':
			return $value ? get_the_title( (int) $value ) : '—';
		case '_tp_invoice_status':
			return function_exists( 'tp_invoice_statuses' ) ? ( tp_invoice_statuses()[ $value ] ?? $value ) : $value;
	}
	return is_scalar( $value ) ? (string) $value : '…';
}

add_action(
	'shutdown',
	static function () {
		if ( ! function_exists( 'tp_in_site_lang' ) ) {
			return;
		}
		tp_in_site_lang(
			static function () {
				foreach ( tp_activity_pending() as $post_id => $changes ) {
					if ( isset( $changes['__created'] ) ) {
						$b = tp_get_booking( $post_id );
						tp_activity(
							'created',
							/* translators: 1: pick-up date/time, 2: from, 3: to */
							sprintf( __( 'Booking created for %1$s: %2$s → %3$s', 'taxi-peninsula' ), tp_format_pickup( $b ), $b['pickup'], $b['dropoff'] ),
							$post_id
						);
						continue;
					}
					$labels = tp_activity_fields();
					$parts  = array();
					foreach ( $changes as $key => $pair ) {
						if ( '_tp_request' === $key ) {
							$parts[] = '' === $pair[1] ? __( 'Change request closed', 'taxi-peninsula' ) : __( 'Change request sent', 'taxi-peninsula' );
						} elseif ( in_array( $key, tp_activity_sensitive(), true ) ) {
							/* translators: %s: field label */
							$parts[] = sprintf( __( '%s changed', 'taxi-peninsula' ), $labels[ $key ] );
						} elseif ( tp_activity_value( $key, $pair[0] ) !== tp_activity_value( $key, $pair[1] ) ) {
							$parts[] = sprintf( '%1$s: %2$s → %3$s', $labels[ $key ], tp_activity_value( $key, $pair[0] ), tp_activity_value( $key, $pair[1] ) );
						}
					}
					if ( $parts ) {
						tp_activity( 'edited', implode( '; ', $parts ), $post_id );
					}
				}
			}
		);
	},
	1
);

add_action(
	'wp_trash_post',
	static function ( $post_id ) {
		if ( 'tp_booking' === get_post_type( $post_id ) ) {
			tp_activity( 'deleted', __( 'Booking moved to the bin', 'taxi-peninsula' ), $post_id );
		}
	}
);
add_action(
	'untrashed_post',
	static function ( $post_id ) {
		if ( 'tp_booking' === get_post_type( $post_id ) ) {
			tp_activity( 'deleted', __( 'Booking restored from the bin', 'taxi-peninsula' ), $post_id );
		}
	}
);
add_action(
	'before_delete_post',
	static function ( $post_id ) {
		if ( 'tp_booking' === get_post_type( $post_id ) ) {
			tp_activity( 'deleted', __( 'Booking permanently deleted', 'taxi-peninsula' ), $post_id );
		}
	}
);

/**
 * Staff and drivers (anyone beyond a customer account).
 */
function tp_is_staff( $user ) {
	foreach ( array( 'manage_options', 'edit_tp_bookings', 'tp_driver_jobs', 'tp_view_finance' ) as $cap ) {
		if ( user_can( $user, $cap ) ) {
			return true;
		}
	}
	return false;
}

add_action(
	'wp_login',
	static function ( $login, $user ) {
		if ( tp_is_staff( $user ) ) {
			tp_activity( 'login', __( 'Signed in', 'taxi-peninsula' ), 0, $user->ID );
		}
	},
	10,
	2
);
add_action(
	'wp_login_failed',
	static function ( $username ) {
		$user = get_user_by( 'login', $username ) ?: get_user_by( 'email', $username );
		if ( $user && tp_is_staff( $user ) ) {
			tp_activity( 'login', __( 'Failed sign-in attempt (wrong password)', 'taxi-peninsula' ), 0, $user->ID );
		}
	}
);
add_action(
	'update_option_tp_settings',
	static function () {
		tp_activity( 'settings', __( 'Booking settings saved', 'taxi-peninsula' ) );
	}
);

/* -------------------------------------------------------------------------
 * Bookings → Activity log.
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page(
			'edit.php?post_type=tp_booking',
			__( 'Activity log', 'taxi-peninsula' ),
			__( 'Activity log', 'taxi-peninsula' ),
			'tp_view_activity',
			'tp-activity',
			'tp_render_activity_page'
		);
	},
	40
);

/**
 * Filters from the request.
 */
function tp_activity_filters( array $req ) {
	$f = array(
		's'      => sanitize_text_field( wp_unslash( $req['s'] ?? '' ) ),
		'action' => sanitize_key( $req['act'] ?? '' ),
		'user'   => absint( $req['user'] ?? 0 ),
		'from'   => sanitize_text_field( wp_unslash( $req['from'] ?? '' ) ),
		'to'     => sanitize_text_field( wp_unslash( $req['to'] ?? '' ) ),
		'b'      => absint( $req['b'] ?? 0 ),
	);
	foreach ( array( 'from', 'to' ) as $k ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $f[ $k ] ) ) {
			$f[ $k ] = '';
		}
	}
	if ( ! isset( tp_activity_actions()[ $f['action'] ] ) ) {
		$f['action'] = '';
	}
	return $f;
}

/**
 * Query the log. Returns [rows, total].
 */
function tp_activity_query( array $f, $limit = 50, $offset = 0 ) {
	global $wpdb;
	$table = tp_activity_table();
	$where = array( '1=1' );
	$args  = array();
	if ( $f['s'] ) {
		$like    = '%' . $wpdb->esc_like( $f['s'] ) . '%';
		$where[] = '(reference LIKE %s OR actor LIKE %s OR summary LIKE %s)';
		array_push( $args, $like, $like, $like );
	}
	if ( $f['action'] ) {
		$where[] = 'action = %s';
		$args[]  = $f['action'];
	}
	if ( $f['user'] ) {
		$where[] = 'user_id = %d';
		$args[]  = $f['user'];
	}
	if ( $f['b'] ) {
		$where[] = 'booking_id = %d';
		$args[]  = $f['b'];
	}
	if ( $f['from'] ) {
		$where[] = 'created >= %s';
		$args[]  = $f['from'] . ' 00:00:00';
	}
	if ( $f['to'] ) {
		$where[] = 'created <= %s';
		$args[]  = $f['to'] . ' 23:59:59';
	}
	$sql_where = implode( ' AND ', $where );
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$sql_where}";
	$total     = (int) $wpdb->get_var( $args ? $wpdb->prepare( $count_sql, $args ) : $count_sql );
	$rows_sql  = "SELECT * FROM {$table} WHERE {$sql_where} ORDER BY id DESC LIMIT %d OFFSET %d";
	$rows      = $wpdb->get_results( $wpdb->prepare( $rows_sql, array_merge( $args, array( (int) $limit, (int) $offset ) ) ) );
	// phpcs:enable
	return array( $rows, $total );
}

function tp_render_activity_page() {
	if ( ! current_user_can( 'tp_view_activity' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters.
	$f       = tp_activity_filters( $_GET );
	$paged   = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$per     = 50;
	list( $rows, $total ) = tp_activity_query( $f, $per, ( $paged - 1 ) * $per );
	$base    = admin_url( 'edit.php?post_type=tp_booking&page=tp-activity' );
	$staff   = get_users( array( 'capability__in' => array( 'manage_options', 'edit_tp_bookings', 'tp_driver_jobs', 'tp_view_finance' ), 'orderby' => 'display_name', 'fields' => array( 'ID', 'display_name' ) ) );
	$actions = tp_activity_actions();
	?>
	<div class="wrap tp-activity">
		<h1><?php esc_html_e( 'Activity log', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'Who changed which booking, and when. Includes status changes, driver assignments, edited details, cancellations, ratings, privacy requests and staff sign-ins.', 'taxi-peninsula' ); ?>
			<?php
			$months = (int) tp_setting( 'retention_activity_months' );
			if ( $months ) {
				/* translators: %d: months */
				echo esc_html( sprintf( _n( 'Entries are kept for %d month.', 'Entries are kept for %d months.', $months, 'taxi-peninsula' ), $months ) );
			}
			?>
		</p>

		<?php if ( $f['b'] ) : ?>
			<p class="tp-activity__booking">
				<?php
				/* translators: %s: booking reference */
				echo esc_html( sprintf( __( 'Showing booking %s only.', 'taxi-peninsula' ), get_post_meta( $f['b'], '_tp_reference', true ) ?: '#' . $f['b'] ) );
				?>
				<a href="<?php echo esc_url( get_edit_post_link( $f['b'] ) ?: '#' ); ?>"><?php esc_html_e( 'Open booking', 'taxi-peninsula' ); ?></a> ·
				<a href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Show everything', 'taxi-peninsula' ); ?></a>
			</p>
		<?php endif; ?>

		<form method="get" class="tp-activity__filters">
			<input type="hidden" name="post_type" value="tp_booking">
			<input type="hidden" name="page" value="tp-activity">
			<?php if ( $f['b'] ) : ?>
				<input type="hidden" name="b" value="<?php echo esc_attr( $f['b'] ); ?>">
			<?php endif; ?>
			<label><span><?php esc_html_e( 'Search', 'taxi-peninsula' ); ?></span>
				<input type="search" name="s" value="<?php echo esc_attr( $f['s'] ); ?>" placeholder="<?php esc_attr_e( 'Reference, name or text', 'taxi-peninsula' ); ?>"></label>
			<label><span><?php esc_html_e( 'What', 'taxi-peninsula' ); ?></span>
				<select name="act">
					<option value=""><?php esc_html_e( 'Everything', 'taxi-peninsula' ); ?></option>
					<?php foreach ( $actions as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f['action'], $k ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select></label>
			<label><span><?php esc_html_e( 'Who', 'taxi-peninsula' ); ?></span>
				<select name="user">
					<option value="0"><?php esc_html_e( 'Anyone', 'taxi-peninsula' ); ?></option>
					<?php foreach ( $staff as $u ) : ?>
						<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $f['user'], (int) $u->ID ); ?>><?php echo esc_html( $u->display_name ); ?></option>
					<?php endforeach; ?>
				</select></label>
			<label><span><?php esc_html_e( 'From', 'taxi-peninsula' ); ?></span><input type="date" name="from" value="<?php echo esc_attr( $f['from'] ); ?>"></label>
			<label><span><?php esc_html_e( 'To', 'taxi-peninsula' ); ?></span><input type="date" name="to" value="<?php echo esc_attr( $f['to'] ); ?>"></label>
			<?php submit_button( __( 'Filter', 'taxi-peninsula' ), 'secondary', '', false ); ?>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'tp_activity_csv' ), array_filter( array( 's' => $f['s'], 'act' => $f['action'], 'user' => $f['user'], 'from' => $f['from'], 'to' => $f['to'], 'b' => $f['b'] ) ) ), admin_url( 'admin-post.php' ) ), 'tp_activity_csv' ) ); ?>"><?php esc_html_e( 'Export CSV', 'taxi-peninsula' ); ?></a>
		</form>

		<p class="tp-activity__count">
			<?php
			/* translators: %s: number of entries */
			echo esc_html( sprintf( _n( '%s entry', '%s entries', $total, 'taxi-peninsula' ), number_format_i18n( $total ) ) );
			?>
		</p>

		<?php if ( ! $rows ) : ?>
			<div class="tp-admin-empty">
				<?php echo tp_illustration( 'empty-list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				<p><?php esc_html_e( 'Nothing logged for these filters yet.', 'taxi-peninsula' ); ?></p>
			</div>
		<?php else : ?>
			<table class="widefat striped tp-activity__table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'When', 'taxi-peninsula' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Who', 'taxi-peninsula' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Booking', 'taxi-peninsula' ); ?></th>
						<th scope="col"><?php esc_html_e( 'What happened', 'taxi-peninsula' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<td class="tp-activity__when"><?php echo esc_html( mysql2date( 'D j M Y, g:i a', $r->created ) ); ?></td>
							<td>
								<?php if ( $r->user_id ) : ?>
									<a href="<?php echo esc_url( add_query_arg( 'user', (int) $r->user_id, $base ) ); ?>"><?php echo esc_html( $r->actor ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $r->actor ); ?>
								<?php endif; ?>
								<?php if ( $r->ip && current_user_can( 'manage_options' ) ) : ?>
									<br><small class="tp-activity__ip"><?php echo esc_html( $r->ip ); ?></small>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $r->booking_id ) : ?>
									<a href="<?php echo esc_url( add_query_arg( 'b', (int) $r->booking_id, $base ) ); ?>"><?php echo esc_html( $r->reference ?: '#' . $r->booking_id ); ?></a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td><span class="tp-activity__tag tp-activity__tag--<?php echo esc_attr( $r->action ); ?>"><?php echo esc_html( $actions[ $r->action ] ?? $r->action ); ?></span> <?php echo esc_html( $r->summary ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			$pages = (int) ceil( $total / $per );
			if ( $pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $paged,
							'total'   => $pages,
						)
					)
				);
				echo '</div></div>';
			}
			?>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'admin_post_tp_activity_csv', 'tp_activity_csv' );
function tp_activity_csv() {
	if ( ! current_user_can( 'tp_view_activity' ) ) {
		wp_die( '', 403 );
	}
	check_admin_referer( 'tp_activity_csv' );
	$f = tp_activity_filters( $_GET );
	list( $rows ) = tp_activity_query( $f, 20000, 0 );
	tp_activity( 'privacy', __( 'Activity log exported as CSV', 'taxi-peninsula' ) );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=activity-' . gmdate( 'Y-m-d' ) . '.csv' );
	$out  = fopen( 'php://output', 'w' );
	$safe = static function ( $v ) {
		$v = (string) $v;
		return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v;
	};
	fputcsv( $out, array( 'When', 'Who', 'User ID', 'IP', 'Action', 'Booking', 'Details' ) );
	$actions = tp_activity_actions();
	foreach ( $rows as $r ) {
		fputcsv( $out, array_map( $safe, array( $r->created, $r->actor, $r->user_id, $r->ip, $actions[ $r->action ] ?? $r->action, $r->reference, $r->summary ) ) );
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}
