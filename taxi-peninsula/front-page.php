<?php
/**
 * Home page.
 *
 * @package TaxiPeninsula
 */

get_header();

$hero_image = tp_current_hero_image();
$tp_bg      = tp_design( 'hero_bg' );
$tp_align   = 'center' === tp_design( 'hero_align' ) ? 'center' : 'left';
if ( 'photo' === $tp_bg && ! $hero_image ) {
	$tp_bg = 'gradient';
}
// What sits beside the text: a photo, the badge card, or nothing.
$tp_side = tp_design( 'hero_side' );
if ( 'photo' === $tp_bg || 'center' === $tp_align || 'none' === $tp_side ) {
	$tp_side = '';
} elseif ( 'card' === $tp_side || ! $hero_image ) {
	$tp_side = 'card';
} else {
	$tp_side = 'photo';
}
$tp_hero_classes = array(
	'hero',
	'hero--' . $tp_bg,
	'hero--' . $tp_align,
	'hero--h-' . sanitize_html_class( tp_design( 'hero_height' ) ),
	$tp_side ? 'hero--with-side' : 'hero--text-only',
	tp_anim_road_in( 'hero' ) ? 'hero--has-road' : '',
);
?>

<section class="<?php echo esc_attr( trim( implode( ' ', $tp_hero_classes ) ) ); ?>">
	<?php if ( 'photo' === $tp_bg ) : ?>
		<?php echo wp_get_attachment_image( $hero_image, 'full', false, array( 'class' => 'hero__bg', 'alt' => '', 'loading' => 'eager', 'fetchpriority' => 'high', 'sizes' => '100vw' ) ); ?>
	<?php endif; ?>
	<div class="container hero__inner">
		<div class="hero__copy">
			<p class="eyebrow"><?php tp_the_icon( 'wheelchair' ); ?><?php echo esc_html( tp_opt( 'hero_eyebrow' ) ); ?></p>
			<h1 class="hero__title"><?php echo esc_html( tp_opt( 'hero_title' ) ); ?></h1>
			<p class="hero__lead"><?php echo esc_html( tp_opt( 'hero_text' ) ); ?></p>
			<?php
			$tp_moment = tp_hero_moment_for( wp_date( 'H:i' ) );
			if ( tp_hero_moments() ) :
				?>
				<p class="hero__moment" data-hero-moment data-moments="<?php echo esc_attr( wp_json_encode( tp_hero_moments() ) ); ?>" data-tz="<?php echo esc_attr( wp_timezone_string() ); ?>" <?php echo $tp_moment ? '' : 'hidden'; ?>><?php tp_the_icon( 'clock' ); ?><span><?php echo esc_html( $tp_moment ); ?></span></p>
			<?php endif; ?>
			<div class="hero__actions">
				<a class="btn btn--accent btn--lg" href="#book"><?php esc_html_e( 'Book online', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
				<a class="btn btn--light btn--lg" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?> <?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
			</div>
			<?php if ( get_theme_mod( 'tp_home_quickbook', true ) ) : ?>
				<?php get_template_part( 'template-parts/quickbook' ); ?>
			<?php endif; ?>
			<ul class="hero__ticks">
				<li><?php tp_the_icon( 'check' ); ?><?php esc_html_e( 'Ramp & hoist vehicles', 'taxi-peninsula' ); ?></li>
				<li><?php tp_the_icon( 'check' ); ?><?php esc_html_e( 'Wheelchair restraints fitted', 'taxi-peninsula' ); ?></li>
				<li><?php tp_the_icon( 'check' ); ?><?php esc_html_e( 'Door-to-door assistance', 'taxi-peninsula' ); ?></li>
			</ul>
		</div>

		<?php if ( $tp_side ) : ?>
		<div class="hero__media">
			<?php if ( 'photo' === $tp_side ) : ?>
				<?php echo wp_get_attachment_image( $hero_image, 'large', false, array( 'class' => 'hero__img', 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?>
			<?php else : ?>
				<div class="hero__card" aria-hidden="true">
					<span class="hero__badge"><?php tp_the_icon( 'wheelchair' ); ?></span>
					<p class="hero__card-title"><?php esc_html_e( 'Accessible by design', 'taxi-peninsula' ); ?></p>
					<ul>
						<li><?php tp_the_icon( 'clock' ); ?><?php esc_html_e( 'On-time pick-ups', 'taxi-peninsula' ); ?></li>
						<li><?php tp_the_icon( 'shield' ); ?><?php esc_html_e( 'Accredited drivers', 'taxi-peninsula' ); ?></li>
						<li><?php tp_the_icon( 'heart' ); ?><?php esc_html_e( 'Patient, friendly help', 'taxi-peninsula' ); ?></li>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
	<?php if ( tp_anim_road_in( 'hero' ) ) : ?>
		<?php tp_the_road_strip( 'hero' ); ?>
	<?php endif; ?>
</section>

<?php
foreach ( tp_home_layout() as $tp_section => $tp_visible ) {
	if ( $tp_visible ) {
		get_template_part( 'template-parts/home/' . $tp_section );
	}
}

get_footer();
