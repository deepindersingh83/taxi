<?php
/**
 * Seasonal content plan (Marketing → Content plan): a calendar of Victorian
 * events and seasons worth a blog guide ("Wheelchair access at the Australian
 * Open", "Getting to the Royal Melbourne Show"), with the next date for each,
 * a reminder on the dashboard six weeks ahead, and one click to start a draft
 * with question headings ready to fill in.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * Easter Sunday for a year (anonymous Gregorian algorithm).
 */
function tp_easter_sunday( $y ) {
	$a = $y % 19;
	$b = intdiv( $y, 100 );
	$c = $y % 100;
	$d = intdiv( $b, 4 );
	$e = $b % 4;
	$f = intdiv( $b + 8, 25 );
	$g = intdiv( $b - $f + 1, 3 );
	$h = ( 19 * $a + $b - $d - $g + 15 ) % 30;
	$i = intdiv( $c, 4 );
	$k = $c % 4;
	$l = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
	$m = intdiv( $a + 11 * $h + 22 * $l, 451 );
	$month = intdiv( $h + $l - 7 * $m + 114, 31 );
	$day   = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;
	return sprintf( '%04d-%02d-%02d', $y, $month, $day );
}

/**
 * Nth weekday of a month, e.g. 2nd Sunday of May. $n = -1 for the last.
 */
function tp_nth_weekday( $y, $month, $weekday, $n ) {
	$tz = wp_timezone();
	if ( $n > 0 ) {
		$d = new DateTimeImmutable( sprintf( '%04d-%02d-01', $y, $month ), $tz );
		$d = $d->modify( '+' . ( ( $weekday - (int) $d->format( 'w' ) + 7 ) % 7 ) . ' days' )->modify( '+' . ( $n - 1 ) . ' weeks' );
	} else {
		$d = ( new DateTimeImmutable( sprintf( '%04d-%02d-01', $y, $month ), $tz ) )->modify( 'last day of this month' );
		$d = $d->modify( '-' . ( ( (int) $d->format( 'w' ) - $weekday + 7 ) % 7 ) . ' days' );
	}
	return $d->format( 'Y-m-d' );
}

/**
 * Topics: key => [label, date callback (year → Y-m-d), post title, why, questions[]].
 * Dates marked "approx" in the label vary each year — confirm before publishing.
 */
function tp_content_topics() {
	$q = static fn( ...$items ) => $items;
	return apply_filters(
		'tp_content_topics',
		array(
			'ao'        => array( __( 'Australian Open (mid–late January)', 'taxi-peninsula' ), static fn( $y ) => tp_nth_weekday( $y, 1, 1, 2 ), __( 'Wheelchair access at the Australian Open: getting to Melbourne Park', 'taxi-peninsula' ), __( 'Two weeks of crowds at Melbourne Park; visitors search for accessible drop-off points.', 'taxi-peninsula' ), $q( __( 'Where is the accessible drop-off at Melbourne Park?', 'taxi-peninsula' ), __( 'How early should I book a wheelchair taxi for a night session?', 'taxi-peninsula' ), __( 'How do I get home after a late match?', 'taxi-peninsula' ) ) ),
			'moomba'    => array( __( 'Moomba (Labour Day weekend, March)', 'taxi-peninsula' ), static fn( $y ) => tp_nth_weekday( $y, 3, 1, 2 ), __( 'Moomba with a wheelchair: accessible viewing and drop-off', 'taxi-peninsula' ), __( 'Road closures around the Yarra; families plan the parade and fireworks.', 'taxi-peninsula' ), $q( __( 'Which roads are closed during Moomba?', 'taxi-peninsula' ), __( 'Where can a wheelchair taxi drop me near Alexandra Gardens?', 'taxi-peninsula' ), __( 'Is there accessible viewing for the parade?', 'taxi-peninsula' ) ) ),
			'flower'    => array( __( 'Melbourne International Flower & Garden Show (late March, approx)', 'taxi-peninsula' ), static fn( $y ) => tp_nth_weekday( $y, 3, 3, -1 ), __( 'Accessible taxis to the Flower & Garden Show at the Royal Exhibition Building', 'taxi-peninsula' ), __( 'Popular with seniors and aged-care outings.', 'taxi-peninsula' ), $q( __( 'Where is the accessible entrance at the Royal Exhibition Building?', 'taxi-peninsula' ), __( 'Can a group from an aged-care home travel together?', 'taxi-peninsula' ), __( 'How long does the trip take from the Peninsula?', 'taxi-peninsula' ) ) ),
			'easter'    => array( __( 'Easter long weekend', 'taxi-peninsula' ), static fn( $y ) => gmdate( 'Y-m-d', strtotime( tp_easter_sunday( $y ) . ' -2 days' ) ), __( 'Easter opening hours and booking tips', 'taxi-peninsula' ), __( 'Public holiday surcharges and hospital visits; people check whether you are open.', 'taxi-peninsula' ), $q( __( 'Are you open over Easter?', 'taxi-peninsula' ), __( 'Do public holiday rates apply?', 'taxi-peninsula' ), __( 'How far ahead should I book for Easter Sunday lunch with family?', 'taxi-peninsula' ) ) ),
			'anzac'     => array( __( 'ANZAC Day (25 April)', 'taxi-peninsula' ), static fn( $y ) => $y . '-04-25', __( 'ANZAC Day: accessible transport to the Dawn Service and local marches', 'taxi-peninsula' ), __( 'Veterans and families attend dawn services across the Peninsula.', 'taxi-peninsula' ), $q( __( 'Can I book a pick-up before dawn on ANZAC Day?', 'taxi-peninsula' ), __( 'Where are the Dawn Services on the Mornington Peninsula?', 'taxi-peninsula' ), __( 'Is there accessible seating at the Shrine of Remembrance?', 'taxi-peninsula' ) ) ),
			'mothers'   => array( __( 'Mother’s Day (2nd Sunday of May)', 'taxi-peninsula' ), static fn( $y ) => tp_nth_weekday( $y, 5, 0, 2 ), __( 'Taking Mum out for Mother’s Day: accessible cafés and restaurants on the Peninsula', 'taxi-peninsula' ), __( 'Busiest restaurant day; families book transport for parents in aged care.', 'taxi-peninsula' ), $q( __( 'Can you pick Mum up from her aged-care home?', 'taxi-peninsula' ), __( 'Which Peninsula restaurants have step-free access?', 'taxi-peninsula' ), __( 'How far ahead should I book for Mother’s Day?', 'taxi-peninsula' ) ) ),
			'eofy'      => array( __( 'End of financial year & NDIS plan reviews (June)', 'taxi-peninsula' ), static fn( $y ) => $y . '-06-15', __( 'Using your NDIS plan for transport: what to ask at your plan review', 'taxi-peninsula' ), __( 'Participants and coordinators look at transport budgets before 30 June.', 'taxi-peninsula' ), $q( __( 'Does NDIS pay for taxis?', 'taxi-peninsula' ), __( 'What records do plan managers need for transport?', 'taxi-peninsula' ), __( 'How do I set up an account for regular trips?', 'taxi-peninsula' ) ) ),
			'winter'    => array( __( 'Winter school holidays (late June – July, approx)', 'taxi-peninsula' ), static fn( $y ) => tp_nth_weekday( $y, 6, 6, -1 ), __( 'Accessible winter school holiday outings around Melbourne', 'taxi-peninsula' ), __( 'Families with children who use wheelchairs plan days out.', 'taxi-peninsula' ), $q( __( 'Which Melbourne attractions are fully wheelchair accessible?', 'taxi-peninsula' ), __( 'Can siblings and a carer travel in the same taxi?', 'taxi-peninsula' ), __( 'Do you have child restraints?', 'taxi-peninsula' ) ) ),
			'afl'       => array( __( 'AFL Grand Final (late September, approx)', 'taxi-peninsula' ), static fn( $y ) => tp_nth_weekday( $y, 9, 6, -1 ), __( 'Getting to the MCG in a wheelchair for finals and the Grand Final', 'taxi-peninsula' ), __( 'Huge demand, road closures and a public holiday parade on the Friday.', 'taxi-peninsula' ), $q( __( 'Where is the accessible drop-off at the MCG?', 'taxi-peninsula' ), __( 'Which gates have accessible seating?', 'taxi-peninsula' ), __( 'Can you pick me up after the game?', 'taxi-peninsula' ) ) ),
			'show'      => array( __( 'Royal Melbourne Show (late September – early October, approx)', 'taxi-peninsula' ), static fn( $y ) => tp_nth_weekday( $y, 9, 6, -1 ), __( 'Royal Melbourne Show with a wheelchair: drop-off, accessible toilets and quiet areas', 'taxi-peninsula' ), __( 'School holiday family outing; Flemington parking is limited.', 'taxi-peninsula' ), $q( __( 'Where can a taxi drop off at the Melbourne Showgrounds?', 'taxi-peninsula' ), __( 'Is there a quiet or sensory space?', 'taxi-peninsula' ), __( 'How long is the trip from Frankston?', 'taxi-peninsula' ) ) ),
			'seniors'   => array( __( 'Victorian Seniors Festival (October)', 'taxi-peninsula' ), static fn( $y ) => $y . '-10-01', __( 'Free Seniors Festival events and how to get there', 'taxi-peninsula' ), __( 'Free and discounted events across Victoria; seniors look for transport.', 'taxi-peninsula' ), $q( __( 'What is on for the Seniors Festival near me?', 'taxi-peninsula' ), __( 'Can MPTP be used for these trips?', 'taxi-peninsula' ), __( 'Can a group book together?', 'taxi-peninsula' ) ) ),
			'cup'       => array( __( 'Spring Racing & Melbourne Cup (first Tuesday of November)', 'taxi-peninsula' ), static fn( $y ) => tp_nth_weekday( $y, 11, 2, 1 ), __( 'Spring Racing Carnival: accessible transport to Flemington', 'taxi-peninsula' ), __( 'Public holiday in Melbourne; heavy demand for taxis.', 'taxi-peninsula' ), $q( __( 'Where is the accessible taxi drop-off at Flemington?', 'taxi-peninsula' ), __( 'How early should I book for Cup Day?', 'taxi-peninsula' ), __( 'Are you open on the Melbourne Cup public holiday?', 'taxi-peninsula' ) ) ),
			'idpwd'     => array( __( 'International Day of People with Disability (3 December)', 'taxi-peninsula' ), static fn( $y ) => $y . '-12-03', __( 'International Day of People with Disability: local events and stories', 'taxi-peninsula' ), __( 'A chance to share passenger and driver stories and local events.', 'taxi-peninsula' ), $q( __( 'What events are on near the Peninsula?', 'taxi-peninsula' ), __( 'How can businesses be more accessible?', 'taxi-peninsula' ), __( 'What do our passengers want people to know?', 'taxi-peninsula' ) ) ),
			'lights'    => array( __( 'Christmas lights & carols (December)', 'taxi-peninsula' ), static fn( $y ) => $y . '-12-10', __( 'Accessible Christmas lights and carols across Melbourne and the Peninsula', 'taxi-peninsula' ), __( 'Evening outings for families and aged-care residents.', 'taxi-peninsula' ), $q( __( 'Which Christmas light displays can I see from a wheelchair?', 'taxi-peninsula' ), __( 'Can the taxi wait while we look?', 'taxi-peninsula' ), __( 'Where is Carols by Candlelight accessible seating?', 'taxi-peninsula' ) ) ),
			'christmas' => array( __( 'Christmas & New Year’s Eve', 'taxi-peninsula' ), static fn( $y ) => $y . '-12-24', __( 'Christmas and New Year’s Eve: booking early, holiday hours and fireworks', 'taxi-peninsula' ), __( 'Our busiest days — tell customers to book early, and share holiday hours.', 'taxi-peninsula' ), $q( __( 'Are you open on Christmas Day?', 'taxi-peninsula' ), __( 'How early should I book for New Year’s Eve?', 'taxi-peninsula' ), __( 'Where can I watch the fireworks with step-free access?', 'taxi-peninsula' ) ) ),
		)
	);
}

/**
 * Next date for a topic on or after today (Y-m-d).
 */
function tp_content_next_date( array $t ) {
	$today = wp_date( 'Y-m-d' );
	$y     = (int) wp_date( 'Y' );
	$d     = ( $t[1] )( $y );
	return $d >= $today ? $d : ( $t[1] )( $y + 1 );
}

/**
 * Draft/post created for a topic for its next date's year, or 0.
 */
function tp_content_post_for( $key, $year ) {
	$made = (array) get_option( 'tp_content_plan', array() );
	$id   = (int) ( $made[ $key . '-' . $year ] ?? 0 );
	return ( $id && get_post_status( $id ) && 'trash' !== get_post_status( $id ) ) ? $id : 0;
}

/**
 * Topics due within $weeks weeks with no draft yet.
 */
function tp_content_due( $weeks = 6 ) {
	$out = array();
	$now = strtotime( wp_date( 'Y-m-d' ) );
	foreach ( tp_content_topics() as $key => $t ) {
		$date = tp_content_next_date( $t );
		$days = ( strtotime( $date ) - $now ) / DAY_IN_SECONDS;
		if ( $days <= $weeks * 7 && ! tp_content_post_for( $key, substr( $date, 0, 4 ) ) ) {
			$out[ $key ] = array( $t[0], $date, (int) $days );
		}
	}
	return $out;
}

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( tp_admin_parent( 'marketing' ), __( 'Content plan', 'taxi-peninsula' ), __( 'Content plan', 'taxi-peninsula' ), 'edit_posts', 'tp-content-plan', 'tp_render_content_plan' );
	},
	30
);

add_action(
	'admin_post_tp_content_draft',
	static function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'tp_content_draft' );
		$key    = isset( $_POST['topic'] ) ? sanitize_key( wp_unslash( $_POST['topic'] ) ) : '';
		$topics = tp_content_topics();
		if ( ! isset( $topics[ $key ] ) ) {
			wp_die( '', 400 );
		}
		$t    = $topics[ $key ];
		$date = tp_content_next_date( $t );
		$year = substr( $date, 0, 4 );
		$id   = tp_content_post_for( $key, $year );
		if ( ! $id ) {
			$blocks  = "<!-- wp:paragraph -->\n<p>" . esc_html( sprintf( /* translators: 1: why 2: date */ __( '[CONFIRM] Intro: %1$s This year it is around %2$s — check the official dates, times and road closures.', 'taxi-peninsula' ), $t[3], wp_date( 'l j F Y', strtotime( $date ) ) ) ) . "</p>\n<!-- /wp:paragraph -->\n\n";
			foreach ( $t[4] as $question ) {
				$blocks .= "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $question ) . "</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>" . esc_html__( '[CONFIRM] Answer in two or three sentences. Add the exact drop-off point, times and a link to the official page.', 'taxi-peninsula' ) . "</p>\n<!-- /wp:paragraph -->\n\n";
			}
			$blocks .= "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html__( 'How do I book?', 'taxi-peninsula' ) . "</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>" . esc_html( sprintf( /* translators: %s: phone */ __( 'Book online or call %s. Tell us the event and your seat or gate so the driver can drop you as close as possible. Busy days fill up, so book early.', 'taxi-peninsula' ), wp_strip_all_tags( tp_opt( 'phone_display' ) ) ) ) . "</p>\n<!-- /wp:paragraph -->";
			$id      = wp_insert_post(
				array(
					'post_type'    => 'post',
					'post_status'  => 'draft',
					'post_title'   => $t[2] . ' (' . $year . ')',
					'post_content' => $blocks,
					'post_author'  => get_current_user_id(),
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				$made                       = (array) get_option( 'tp_content_plan', array() );
				$made[ $key . '-' . $year ] = $id;
				update_option( 'tp_content_plan', $made, false );
			}
		}
		wp_safe_redirect( $id ? admin_url( 'post.php?action=edit&post=' . (int) $id ) : admin_url( 'admin.php?page=tp-content-plan' ) );
		exit;
	}
);

function tp_render_content_plan() {
	$rows = array();
	foreach ( tp_content_topics() as $key => $t ) {
		$date         = tp_content_next_date( $t );
		$rows[ $key ] = array( $t, $date, tp_content_post_for( $key, substr( $date, 0, 4 ) ) );
	}
	uasort( $rows, static fn( $a, $b ) => strcmp( $a[1], $b[1] ) );
	$now = strtotime( wp_date( 'Y-m-d' ) );
	?>
	<div class="wrap tp-content-plan">
		<h1><?php esc_html_e( 'Seasonal content plan', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'Publish a guide 4–6 weeks before each event, so Google and AI assistants find it when people start planning. Each draft comes with question headings — answer them with real details (the exact drop-off point, gate numbers, road closures) and a photo if you have one.', 'taxi-peninsula' ); ?></p>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Next date', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Topic', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Suggested guide', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Status', 'taxi-peninsula' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $rows as $key => $r ) : ?>
					<?php
					list( $t, $date, $post ) = $r;
					$days                    = (int) ( ( strtotime( $date ) - $now ) / DAY_IN_SECONDS );
					?>
					<tr>
						<td><strong><?php echo esc_html( wp_date( 'D j M Y', strtotime( $date ) ) ); ?></strong><br><small>
							<?php
							/* translators: %d: days */
							echo esc_html( sprintf( _n( 'in %d day', 'in %d days', $days, 'taxi-peninsula' ), $days ) );
							?>
						</small></td>
						<td><?php echo esc_html( $t[0] ); ?><br><small><?php echo esc_html( $t[3] ); ?></small></td>
						<td><?php echo esc_html( $t[2] ); ?></td>
						<td>
							<?php if ( $post ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $post ) ); ?>"><?php echo 'publish' === get_post_status( $post ) ? esc_html__( 'Published', 'taxi-peninsula' ) : esc_html__( 'Draft — edit', 'taxi-peninsula' ); ?></a>
							<?php else : ?>
								<?php if ( $days <= 42 ) : ?><span class="tp-pill tp-pill--due"><?php esc_html_e( 'Due now', 'taxi-peninsula' ); ?></span><?php endif; ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
									<input type="hidden" name="action" value="tp_content_draft"><input type="hidden" name="topic" value="<?php echo esc_attr( $key ); ?>">
									<?php wp_nonce_field( 'tp_content_draft' ); ?>
									<button class="button button-small"><?php esc_html_e( 'Create draft guide', 'taxi-peninsula' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Dates marked "approx" move each year — check the official event website before publishing.', 'taxi-peninsula' ); ?></p>
	</div>
	<?php
}

// Dashboard reminder.
add_action(
	'admin_notices',
	static function () {
		$s = get_current_screen();
		if ( ! $s || 'dashboard' !== $s->id || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$due = tp_content_due( 6 );
		if ( ! $due ) {
			return;
		}
		$names = array_map( static fn( $d ) => $d[0], array_slice( $due, 0, 3 ) );
		printf(
			'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
			/* translators: %s: topics */
			esc_html( sprintf( __( 'Coming up: %s. A short guide published now helps people find you when they plan.', 'taxi-peninsula' ), implode( '; ', $names ) ) ),
			esc_url( admin_url( 'admin.php?page=tp-content-plan' ) ),
			esc_html__( 'Open the content plan', 'taxi-peninsula' )
		);
	}
);
