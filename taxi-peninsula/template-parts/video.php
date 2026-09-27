<?php
/**
 * "How we secure your wheelchair" video: captions, transcript, and a
 * click-to-load player so nothing loads from YouTube/Vimeo until asked.
 *
 * @package TaxiPeninsula
 */

if ( ! tp_video_configured() ) {
	return;
}
$remote     = tp_parse_video_url( tp_opt( 'video_url' ) );
$file       = (int) tp_opt( 'video_file' );
$captions   = (int) tp_opt( 'video_captions' );
$poster_id  = (int) tp_opt( 'video_poster' );
$poster     = $poster_id ? wp_get_attachment_image_url( $poster_id, 'large' ) : '';
$title      = tp_opt( 'video_title' );
$transcript = trim( (string) tp_opt( 'video_transcript' ) );
$id         = wp_unique_id( 'video-' );
?>
<section class="section section--video" aria-labelledby="<?php echo esc_attr( $id ); ?>">
	<div class="container video-layout">
		<div class="video-layout__text">
			<h2 id="<?php echo esc_attr( $id ); ?>" class="section__title"><?php echo esc_html( $title ); ?></h2>
			<p><?php esc_html_e( 'See exactly how our drivers load, secure and unload a wheelchair — so you know what to expect before your first trip.', 'taxi-peninsula' ); ?></p>
			<?php if ( $transcript ) : ?>
				<details class="video-transcript">
					<summary><?php esc_html_e( 'Read the transcript', 'taxi-peninsula' ); ?></summary>
					<div class="prose"><?php echo wp_kses_post( wpautop( esc_html( $transcript ) ) ); ?></div>
				</details>
			<?php endif; ?>
		</div>
		<div class="video-frame">
			<?php if ( $file ) : ?>
				<video controls preload="none" playsinline <?php echo $poster ? 'poster="' . esc_url( $poster ) . '"' : ''; ?> aria-describedby="<?php echo esc_attr( $id ); ?>">
					<source src="<?php echo esc_url( wp_get_attachment_url( $file ) ); ?>" type="<?php echo esc_attr( get_post_mime_type( $file ) ?: 'video/mp4' ); ?>">
					<?php if ( $captions ) : ?>
						<track kind="captions" src="<?php echo esc_url( wp_get_attachment_url( $captions ) ); ?>" srclang="en" label="English" default>
					<?php endif; ?>
				</video>
			<?php else : ?>
				<?php
				if ( 'youtube' === $remote[0] ) {
					$embed = 'https://www.youtube-nocookie.com/embed/' . $remote[1] . '?autoplay=1&rel=0&cc_load_policy=1&hl=en';
					$thumb = $poster ?: 'https://i.ytimg.com/vi/' . $remote[1] . '/hqdefault.jpg';
				} else {
					$embed = 'https://player.vimeo.com/video/' . $remote[1] . '?autoplay=1&dnt=1&texttrack=en';
					$thumb = $poster;
				}
				?>
				<button type="button" class="video-facade" data-video-embed="<?php echo esc_url( $embed ); ?>" data-video-title="<?php echo esc_attr( $title ); ?>">
					<?php if ( $thumb ) : ?>
						<img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy">
					<?php endif; ?>
					<span class="video-facade__play"><?php tp_the_icon( 'play' ); ?></span>
					<span class="video-facade__label">
						<?php
						/* translators: %s: video title */
						echo esc_html( sprintf( __( 'Play video: %s (with captions)', 'taxi-peninsula' ), $title ) );
						?>
					</span>
				</button>
				<p class="video-note"><?php echo 'youtube' === $remote[0] ? esc_html__( 'Plays from YouTube (privacy-enhanced mode).', 'taxi-peninsula' ) : esc_html__( 'Plays from Vimeo.', 'taxi-peninsula' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</section>
