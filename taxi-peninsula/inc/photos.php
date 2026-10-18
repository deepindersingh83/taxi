<?php
/**
 * Real photos everywhere.
 *
 * - Photo slots: a real photo replaces the illustrated "pick-up" and
 *   "airport" scenes on the home page and service pages.
 * - Photo checklist (Website content → Photo checklist): every service,
 *   suburb, vehicle, driver, partner and place page still without a photo,
 *   with a shot list and consent guidance.
 * - Suburb and place pages show their featured image.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_photo_slots() {
	return array(
		'scene_pickup'  => array( __( 'Pick-up at home', 'taxi-peninsula' ), __( 'Replaces the illustrated pick-up scene on the home page and most service pages. Shot idea: a driver helping a passenger up the ramp outside a house (with written consent).', 'taxi-peninsula' ) ),
		'scene_airport' => array( __( 'Airport trip', 'taxi-peninsula' ), __( 'Replaces the illustrated airport scene on the home page and the airport service page. Shot idea: the van at the departures kerb with luggage being loaded.', 'taxi-peninsula' ) ),
	);
}

function tp_photo_slot( $slot ) {
	$id = (int) ( ( (array) get_option( 'tp_photo_slots', array() ) )[ $slot ] ?? 0 );
	return ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
}

// A real photo replaces the illustrated scene.
add_filter(
	'tp_scene_html',
	static function ( $html, $name ) {
		$id = tp_photo_slot( 'scene_' . $name );
		if ( ! $id ) {
			return $html;
		}
		$alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		return sprintf(
			'<div class="tp-scene tp-scene--photo tp-scene--%1$s">%2$s</div>',
			esc_attr( $name ),
			wp_get_attachment_image( $id, 'large', false, array( 'class' => 'tp-scene__photo', 'alt' => $alt ?: tp_photo_slots()[ 'scene_' . $name ][0], 'sizes' => '(min-width: 960px) 560px, 100vw' ) )
		);
	},
	10,
	2
);

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( tp_admin_parent( 'website' ), __( 'Photo checklist', 'taxi-peninsula' ), __( 'Photo checklist', 'taxi-peninsula' ), 'edit_posts', 'tp-photos', 'tp_render_photo_checklist' );
	},
	30
);

add_action(
	'admin_enqueue_scripts',
	static function () {
		$s = get_current_screen();
		if ( $s && false !== strpos( $s->id, 'tp-photos' ) && current_user_can( 'upload_files' ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'tp-admin', TP_URI . '/assets/js/admin.js', array( 'jquery' ), TP_VERSION, true );
		}
	}
);

add_action(
	'admin_post_tp_photo_slots',
	static function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'tp_photo_slots' );
		$out = array();
		foreach ( array_keys( tp_photo_slots() ) as $slot ) {
			$id           = isset( $_POST[ 'slot_' . $slot ] ) ? absint( $_POST[ 'slot_' . $slot ] ) : 0;
			$out[ $slot ] = ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
		}
		update_option( 'tp_photo_slots', $out );
		set_transient( 'tp_admin_notice_' . get_current_user_id(), array( 'success', __( 'Photos saved.', 'taxi-peninsula' ) ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=tp-photos' ) );
		exit;
	}
);

/**
 * What still needs a photo: [label, items[[title, edit url]], total].
 */
function tp_photo_gaps() {
	$groups = array();
	$types  = array(
		'tp_service' => __( 'Services', 'taxi-peninsula' ),
		'tp_area'    => __( 'Suburbs', 'taxi-peninsula' ),
		'tp_fleet'   => __( 'Vehicles', 'taxi-peninsula' ),
		'tp_partner' => __( 'Partner logos', 'taxi-peninsula' ),
		'tp_place'   => __( 'Place pages (entrances and drop-off points)', 'taxi-peninsula' ),
		'post'       => __( 'Blog posts', 'taxi-peninsula' ),
	);
	foreach ( $types as $type => $label ) {
		if ( ! post_type_exists( $type ) ) {
			continue;
		}
		$all     = get_posts( array( 'post_type' => $type, 'numberposts' => 300, 'fields' => 'ids' ) );
		$missing = array_values( array_filter( $all, static fn( $id ) => ! has_post_thumbnail( $id ) ) );
		$groups[ $type ] = array( $label, array_map( static fn( $id ) => array( get_the_title( $id ), get_edit_post_link( $id, 'raw' ) ), array_slice( $missing, 0, 30 ) ), count( $all ), count( $missing ) );
	}
	$drivers = array_filter( tp_drivers(), static fn( $u ) => get_user_meta( $u->ID, 'tp_public', true ) );
	$missing = array_values( array_filter( $drivers, static fn( $u ) => ! (int) get_user_meta( $u->ID, 'tp_photo', true ) ) );
	$groups['drivers'] = array( __( 'Drivers shown on the website', 'taxi-peninsula' ), array_map( static fn( $u ) => array( $u->display_name, get_edit_user_link( $u->ID ) ), $missing ), count( $drivers ), count( $missing ) );
	return $groups;
}

function tp_render_photo_checklist() {
	$slots = (array) get_option( 'tp_photo_slots', array() );
	$hero  = (int) get_theme_mod( 'tp_hero_image' );
	$gaps  = tp_photo_gaps();
	$done  = array_sum( array_map( static fn( $g ) => $g[2] - $g[3], $gaps ) ) + ( $hero ? 1 : 0 ) + count( array_filter( $slots ) );
	$total = array_sum( array_map( static fn( $g ) => $g[2], $gaps ) ) + 1 + count( tp_photo_slots() );
	?>
	<div class="wrap tp-photos">
		<h1><?php esc_html_e( 'Photo checklist', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'Real photos of your vehicles, drivers and the places you go build more trust than illustrations or stock images — and Google shows them in image search and on your Business Profile.', 'taxi-peninsula' ); ?></p>
		<p><strong>
			<?php
			/* translators: 1: done 2: total */
			echo esc_html( sprintf( __( '%1$d of %2$d photo spots filled.', 'taxi-peninsula' ), $done, $total ) );
			?>
		</strong></p>
		<progress max="<?php echo esc_attr( $total ); ?>" value="<?php echo esc_attr( $done ); ?>" style="width:320px"></progress>

		<h2><?php esc_html_e( 'Home page', 'taxi-peninsula' ); ?></h2>
		<table class="widefat striped" style="max-width:900px">
			<tbody>
				<tr><td><strong><?php esc_html_e( 'Hero photo', 'taxi-peninsula' ); ?></strong><br><small><?php esc_html_e( 'The big image at the top of the home page. A real vehicle with a passenger works best.', 'taxi-peninsula' ); ?></small></td>
					<td><?php echo tp_settings_status_icon( $hero ? 'ok' : 'warn' ) . ' ' . ( $hero ? esc_html__( 'Done', 'taxi-peninsula' ) : esc_html__( 'Missing', 'taxi-peninsula' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></td>
					<td><a class="button" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[control]=tp_hero_image' ) ); ?>"><?php esc_html_e( 'Choose in the Customizer', 'taxi-peninsula' ); ?></a></td></tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Replace the illustrations with photos', 'taxi-peninsula' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tp_photo_slots">
			<?php wp_nonce_field( 'tp_photo_slots' ); ?>
			<table class="form-table" role="presentation">
				<?php foreach ( tp_photo_slots() as $slot => $s ) : ?>
					<?php $pid = (int) ( $slots[ $slot ] ?? 0 ); ?>
					<tr><th><?php echo esc_html( $s[0] ); ?></th><td>
						<div class="tp-media" data-tp-media data-multiple="0">
							<input type="hidden" name="slot_<?php echo esc_attr( $slot ); ?>" value="<?php echo esc_attr( $pid ?: '' ); ?>">
							<div class="tp-media__preview"><?php echo $pid && function_exists( 'tp_media_previews' ) ? tp_media_previews( array( $pid ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup. ?></div>
							<button type="button" class="button" data-tp-media-pick><?php esc_html_e( 'Choose photo', 'taxi-peninsula' ); ?></button>
							<button type="button" class="button-link" data-tp-media-clear><?php esc_html_e( 'Remove (show the illustration)', 'taxi-peninsula' ); ?></button>
						</div>
						<p class="description"><?php echo esc_html( $s[1] ); ?></p>
					</td></tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button( __( 'Save photos', 'taxi-peninsula' ) ); ?>
		</form>

		<h2><?php esc_html_e( 'Still without a photo', 'taxi-peninsula' ); ?></h2>
		<?php foreach ( $gaps as $g ) : ?>
			<?php
			if ( ! $g[2] ) {
				continue;
			}
			?>
			<h3><?php echo esc_html( $g[0] ); ?> <small>(<?php /* translators: 1: missing 2: total */ echo esc_html( sprintf( __( '%1$d of %2$d missing', 'taxi-peninsula' ), $g[3], $g[2] ) ); ?>)</small></h3>
			<?php if ( ! $g[1] ) : ?>
				<p><?php echo tp_settings_status_icon( 'ok' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?> <?php esc_html_e( 'All done.', 'taxi-peninsula' ); ?></p>
			<?php else : ?>
				<ul class="ul-disc">
					<?php foreach ( $g[1] as $item ) : ?>
						<li><a href="<?php echo esc_url( $item[1] ); ?>"><?php echo esc_html( $item[0] ?: __( '(no title)', 'taxi-peninsula' ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endforeach; ?>

		<h2><?php esc_html_e( 'Shot list', 'taxi-peninsula' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Each vehicle from the side and the back with the ramp or hoist down, in daylight, with your logo visible.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Inside: the wheelchair space with restraints fitted, and the passenger seats.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Each driver: friendly head-and-shoulders photo, in uniform, next to the van.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'A driver helping a passenger up the ramp (only with written consent).', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Hospital and station entrances you use most — the exact drop-off spot, without people in the shot.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Local landmarks for suburb pages: the foreshore, main street or station (your own photos, not from Google).', 'taxi-peninsula' ); ?></li>
		</ol>
		<p><?php esc_html_e( 'Tips: landscape, at least 1600 pixels wide, no faces of members of the public, and write alt text describing what the photo shows (e.g. "Wheelchair accessible van with rear ramp lowered outside Frankston Hospital").', 'taxi-peninsula' ); ?></p>

		<h2><?php esc_html_e( 'Consent and privacy', 'taxi-peninsula' ); ?></h2>
		<ul class="ul-disc">
			<li><?php esc_html_e( 'Photos of passengers are personal information — and can reveal health information, which the Privacy Act treats as sensitive. Get written consent that says where the photo will be used (website, Google, social media) and for how long.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'For a passenger under 18, or who cannot consent, get consent from a parent, guardian or person responsible.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Drivers and staff should agree in writing too; tick "Show on website" on their profile only once they have.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Remove a photo promptly if someone withdraws consent, and keep the consent forms with your privacy records.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Avoid number plates of other cars and hospital signage that identifies a specific clinic a passenger attends.', 'taxi-peninsula' ); ?></li>
		</ul>
	</div>
	<?php
}
