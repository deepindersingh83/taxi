<?php
/**
 * Home section: how it works in three steps.
 *
 * @package TaxiPeninsula
 */

$reply = trim( (string) tp_opt( 'reply_time' ) );
$steps = array(
	array( 'calendar', __( 'Book online or call', 'taxi-peninsula' ), __( 'Tell us where, when and about your wheelchair. It takes about two minutes.', 'taxi-peninsula' ) ),
	array( 'message', __( 'We confirm', 'taxi-peninsula' ), __( 'By SMS or email, with your driver’s name and vehicle once assigned.', 'taxi-peninsula' ) ),
	array( 'wheelchair', __( 'Your driver arrives', 'taxi-peninsula' ), __( 'We text you when they are on the way, then help you aboard and secure your chair.', 'taxi-peninsula' ) ),
);
?>
<section class="section section--steps" aria-labelledby="steps-title">
	<div class="container">
		<h2 id="steps-title" class="section__title"><?php echo esc_html( tp_home_text( 'steps' ) ); ?></h2>
		<p class="section__lead"><?php echo esc_html( tp_home_text( 'steps', 'text' ) ); ?></p>
		<ol class="howto">
			<?php foreach ( $steps as $i => $st ) : ?>
				<li class="howto__step">
					<span class="howto__num" aria-hidden="true"><?php echo esc_html( $i + 1 ); ?></span>
					<?php tp_the_spot( $st[0], 'howto__icon' ); ?>
					<h3 class="howto__title"><?php echo esc_html( $st[1] ); ?></h3>
					<p><?php echo esc_html( $st[2] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ol>
		<p class="howto__note">
			<?php tp_the_icon( 'clock' ); ?>
			<span>
				<?php
				echo esc_html(
					$reply
						/* translators: %s: reply time, e.g. "within the hour" */
						? sprintf( __( 'Average reply time: %s.', 'taxi-peninsula' ), $reply )
						: __( 'By SMS or email — usually within the hour during opening times.', 'taxi-peninsula' )
				);
				?>
			</span>
		</p>
		<p class="howto__actions">
			<a class="btn btn--accent" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book a taxi online', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
			<a class="btn btn--ghost" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?> <?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
		</p>
	</div>
</section>
