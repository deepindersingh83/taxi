<?php
/**
 * Home page.
 *
 * @package TaxiPeninsula
 */

get_header();

$hero_image = (int) tp_opt( 'hero_image' );
?>

<section class="hero">
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
			<ul class="hero__ticks">
				<li><?php tp_the_icon( 'check' ); ?><?php esc_html_e( 'Ramp & hoist vehicles', 'taxi-peninsula' ); ?></li>
				<li><?php tp_the_icon( 'check' ); ?><?php esc_html_e( 'Wheelchair restraints fitted', 'taxi-peninsula' ); ?></li>
				<li><?php tp_the_icon( 'check' ); ?><?php esc_html_e( 'Door-to-door assistance', 'taxi-peninsula' ); ?></li>
			</ul>
		</div>

		<div class="hero__media">
			<?php if ( $hero_image ) : ?>
				<?php echo wp_get_attachment_image( $hero_image, 'large', false, array( 'class' => 'hero__img', 'loading' => 'eager' ) ); ?>
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
	</div>
</section>

<?php get_template_part( 'template-parts/stats' ); ?>

<?php get_template_part( 'template-parts/features' ); ?>

<section id="book" class="section section--book" aria-labelledby="book-title">
	<div class="container book-layout">
		<div class="book-layout__intro">
			<h2 id="book-title" class="section__title"><?php esc_html_e( 'Book your accessible taxi', 'taxi-peninsula' ); ?></h2>
			<p><?php esc_html_e( 'Tell us where you are going and what you need. We will confirm your booking and let you know when your driver is on the way.', 'taxi-peninsula' ); ?></p>
			<?php get_template_part( 'template-parts/steps' ); ?>
			<div class="contact-card">
				<p><?php esc_html_e( 'Prefer to talk to someone?', 'taxi-peninsula' ); ?></p>
				<a class="contact-card__phone" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?><?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
				<a href="<?php echo esc_attr( tp_email_href() ); ?>"><?php echo esc_html( antispambot( tp_opt( 'email' ) ) ); ?></a>
			</div>
		</div>
		<div class="book-layout__form card">
			<?php tp_render_booking_form(); ?>
		</div>
	</div>
</section>

<section id="services" class="section" aria-labelledby="services-title">
	<div class="container">
		<h2 id="services-title" class="section__title"><?php esc_html_e( 'Our services', 'taxi-peninsula' ); ?></h2>
		<p class="section__lead"><?php esc_html_e( 'Reliable wheelchair accessible transport for everyday trips and important appointments.', 'taxi-peninsula' ); ?></p>
		<div class="services">
			<?php foreach ( tp_services_list() as $s ) : ?>
				<?php get_template_part( 'template-parts/service-card', null, $s ); ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php get_template_part( 'template-parts/video' ); ?>

<?php get_template_part( 'template-parts/destinations' ); ?>

<?php $areas = tp_service_areas(); ?>
<?php if ( $areas ) : ?>
<section id="areas" class="section section--areas" aria-labelledby="areas-title">
	<div class="container">
		<h2 id="areas-title" class="section__title"><?php esc_html_e( 'Areas we cover', 'taxi-peninsula' ); ?></h2>
		<p class="section__lead"><?php esc_html_e( 'Based on the Mornington Peninsula and servicing greater Melbourne. Not listed? Call us — we travel further on request.', 'taxi-peninsula' ); ?></p>
		<?php echo do_shortcode( '[tp_suburb_checker]' ); ?>
		<ul class="chips">
			<?php $area_links = tp_area_links(); ?>
			<?php foreach ( $areas as $area ) : ?>
				<?php $link = $area_links[ strtolower( $area ) ] ?? ''; ?>
				<li>
					<?php if ( $link ) : ?>
						<a class="chip" href="<?php echo esc_url( $link ); ?>"><?php tp_the_icon( 'pin' ); ?><?php echo esc_html( $area ); ?></a>
					<?php else : ?>
						<span class="chip"><?php tp_the_icon( 'pin' ); ?><?php echo esc_html( $area ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
<?php endif; ?>

<?php get_template_part( 'template-parts/testimonials', null, array( 'limit' => 3 ) ); ?>

<?php
if ( 'page' === get_option( 'show_on_front' ) ) :
	while ( have_posts() ) :
		the_post();
		if ( '' !== trim( get_the_content() ) ) :
			?>
			<section class="section">
				<div class="container prose">
					<?php the_content(); ?>
				</div>
			</section>
			<?php
		endif;
	endwhile;
endif;

$latest = new WP_Query(
	array(
		'post_type'           => 'post',
		'posts_per_page'      => 3,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
if ( $latest->have_posts() ) :
	$blog_url = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : '';
	?>
	<section class="section section--posts" aria-labelledby="news-title">
		<div class="container">
			<div class="section__head">
				<h2 id="news-title" class="section__title"><?php esc_html_e( 'News & travel tips', 'taxi-peninsula' ); ?></h2>
				<?php if ( $blog_url ) : ?>
					<a class="link-arrow" href="<?php echo esc_url( $blog_url ); ?>"><?php esc_html_e( 'All articles', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
				<?php endif; ?>
			</div>
			<div class="post-grid">
				<?php
				while ( $latest->have_posts() ) :
					$latest->the_post();
					get_template_part( 'template-parts/content', 'card' );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php get_template_part( 'template-parts/partners' ); ?>

<?php get_template_part( 'template-parts/cta' ); ?>

<?php
get_footer();
