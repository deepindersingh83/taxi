<?php
/**
 * Title: Complaints & feedback page
 * Slug: taxi-peninsula/page-complaints
 * Categories: taxi-peninsula
 * Keywords: complaints, feedback, resolution, escalation, policy
 * Description: A clear complaints process with steps, response times ([CONFIRM]) and how to escalate to regulators.
 * Block Types: core/post-content
 * Post Types: page
 *
 * @package TaxiPeninsula
 */

$tp_contact = add_query_arg( 'topic', 'complaint', tp_page_url_by_template( 'contact', home_url( '/' ) ) ) . '#contact';
?>
<!-- wp:paragraph {"className":"tp-todo"} -->
<p class="tp-todo"><?php esc_html_e( '[Draft template — replace each [CONFIRM] note with your real process and timeframes, and check the regulators\' current contact details before publishing.]', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'We want every trip to be safe, comfortable and respectful. If something was not right, please tell us — complaints help us improve, and making one will never affect the service you receive.', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'How to make a complaint', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li><?php esc_html_e( 'Online:', 'taxi-peninsula' ); ?> <a href="<?php echo esc_url( $tp_contact ); ?>"><?php esc_html_e( 'use our contact form and choose "Complaint"', 'taxi-peninsula' ); ?></a></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><?php esc_html_e( 'Phone:', 'taxi-peninsula' ); ?> <?php echo esc_html( tp_opt( 'phone_display' ) ); ?></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><?php esc_html_e( 'Email:', 'taxi-peninsula' ); ?> <?php echo esc_html( tp_opt( 'email' ) ); ?></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><?php esc_html_e( 'A carer, family member or advocate can contact us on your behalf.', 'taxi-peninsula' ); ?></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'If you can, include your booking reference, the date and time of the trip, and what happened.', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'What happens next', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list"><!-- wp:list-item -->
<li><strong><?php esc_html_e( 'We acknowledge your complaint', 'taxi-peninsula' ); ?></strong> — <?php esc_html_e( 'you get an email straight away if you use the form, and a personal reply', 'taxi-peninsula' ); ?> <span class="tp-todo"><?php esc_html_e( '[CONFIRM: e.g. within 1 business day]', 'taxi-peninsula' ); ?></span>.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><strong><?php esc_html_e( 'We look into it', 'taxi-peninsula' ); ?></strong> — <?php esc_html_e( 'we speak with the driver and check the booking records.', 'taxi-peninsula' ); ?></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><strong><?php esc_html_e( 'We tell you the outcome', 'taxi-peninsula' ); ?></strong> — <?php esc_html_e( 'and what we will do to put it right,', 'taxi-peninsula' ); ?> <span class="tp-todo"><?php esc_html_e( '[CONFIRM: e.g. within 10 business days]', 'taxi-peninsula' ); ?></span>.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><strong><?php esc_html_e( 'If you are not satisfied', 'taxi-peninsula' ); ?></strong> — <?php esc_html_e( 'ask for your complaint to be reviewed by the owner/manager, or contact a regulator (below).', 'taxi-peninsula' ); ?></li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Taking it further', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'You can contact these organisations at any time, including if you are unhappy with how we handled your complaint:', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li><strong><?php esc_html_e( 'Commercial Passenger Vehicles Victoria (CPVV)', 'taxi-peninsula' ); ?></strong> — <?php esc_html_e( 'the regulator for taxis and commercial passenger vehicles in Victoria.', 'taxi-peninsula' ); ?> <span class="tp-todo"><?php esc_html_e( '[CONFIRM: add CPVV\'s current website and phone number]', 'taxi-peninsula' ); ?></span></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><strong><?php esc_html_e( 'NDIS Quality and Safeguards Commission', 'taxi-peninsula' ); ?></strong> — <?php esc_html_e( 'for NDIS participants, if your complaint relates to NDIS-funded supports.', 'taxi-peninsula' ); ?> <span class="tp-todo"><?php esc_html_e( '[CONFIRM: whether this applies to your business, and the Commission\'s current contact details]', 'taxi-peninsula' ); ?></span></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Help to make a complaint', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'If you are Deaf or have a hearing or speech impairment, you can contact us through the National Relay Service, by SMS, or by email. Tell us if you would like information in another format or language.', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Your privacy', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'We only use the details you give us to look into and respond to your complaint.', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->
