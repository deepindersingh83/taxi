# Taxi Peninsula — WordPress theme

A WordPress theme for a Melbourne wheelchair taxi service. It includes online bookings, a bookings manager, driver job sheets, SMS and payment integrations, and a blog.

- **Phone:** 0468 323 211 · **Email:** support@taxipeninsula.com.au
- **Theme folder:** [`taxi-peninsula/`](taxi-peninsula)
- **Requires:** WordPress 6.3+ and PHP 7.4+. Tested on WordPress 7.1 and PHP 8.4.

## Features

### For passengers
- **Online booking** covers:
  - pick-up and drop-off, date and time, and an optional return trip
  - vehicle type, mobility aid, passengers and wheelchairs
  - MPTP membership and notes
- **Repeat trips:** tick "regular trip", choose the days (for example Mon/Wed/Fri) and an end date. Each trip is created as its own booking with its own reference.
- **Payment choice:**
  - pay the driver
  - **account / invoice** for NDIS plan managers, aged care or businesses, which collects who to invoice and the NDIS number or purchase order
  - an **online deposit through Stripe**, when switched on
- **Manage My Booking:** the passenger enters their reference and mobile number, or taps the link in their email or SMS. They can then:
  - see the booking's progress, the driver and the vehicle
  - pay an outstanding deposit
  - ask to change or cancel a trip, or all remaining trips in a repeat series
- **Pages:** FAQ (grouped accordion), Our Fleet, NDIS & Aged Care, and Contact (enquiries are saved in the admin).
- **Service pages** such as `/services/airport-transfers/`, and a **page per suburb** such as `/wheelchair-taxi/frankston/`.
- A **testimonials** section and Google rating badge on the home page. It stays hidden until you add real testimonials or a rating.
- **Accessibility and dynamic type:**
  - fluid text sizes, plus A− / A / A+ / A++ text-size buttons and a high-contrast switch
  - the Atkinson Hyperlegible font and large tap targets
  - keyboard and screen-reader support

### For staff (wp-admin → Bookings)
- **All bookings** list:
  - filter by status, pick-up date, driver and repeat series, and search
  - bulk status changes and CSV export
  - badges for customer requests, account bookings and paid deposits
- **Booking screen:** edit any detail, set the status and notify the customer, assign a **driver and vehicle**, and see the history log. You can also mark customer requests as handled and apply a status to the rest of a repeat series.
- **Calendar:** a month view of every trip, colour-coded by status.
- **Run sheet:** a printable daily list of jobs, which you can filter by driver.
- **Reports:** trips per week, busiest times and days, cancellation rate, wheelchair share, account trips, deposits, and trips per driver.
- **Enquiries:** messages from the contact form, with an unread count.
- **Settings:** SMS, Stripe, spam protection, Google reviews and repeat-trip limits.
- **Setup:** one click creates all the pages, FAQs, service and suburb pages, example fleet vehicles (saved as drafts) and menus.
- **Roles:**
  - **Booking Manager** can manage bookings and enquiries only.
  - **Driver** sees only their own jobs on a mobile **Driver Jobs** page, with tap-to-call, map links and a "Completed" button.

### Notifications
| Event | Email | SMS (ClickSend or Twilio) |
|---|---|---|
| New booking | Office + customer | Office + customer |
| Confirmed / driver assigned / cancelled | Customer (optional) | Customer (optional) |
| Job assigned | — | Driver |
| Change or cancel request | Office | Office |
| Deposit paid | Office | — |

### SEO
- Meta description, Open Graph and Twitter tags. These switch off automatically if Yoast, Rank Math, All in One SEO or SEOPress is active.
- schema.org structured data: `LocalBusiness` with areas served and hours, `Service` on service and suburb pages, and `FAQPage` on FAQ content.
- The Manage My Booking and Driver Jobs pages are set to `noindex`.

### Spam protection
- Honeypot fields and per-IP rate limits on every public form.
- Optional **Cloudflare Turnstile** or **Google reCAPTCHA v2** on the booking, lookup and contact forms.

### Page templates & block patterns (v1.2)
- **Page templates** (Page panel → Template):
  - **About Us:** your story, "what makes us different", the fleet, testimonials and a call to action.
  - **Contact Us:** the form, your contact details, the areas you cover and a map.
  - **Services:** an intro plus every service. When this page exists, `/services/` redirects to it.
  - **Blog:** a post listing with an optional intro.
  - **Book a Taxi**
  - **Policy / legal page:** an automatic table of contents, a "last updated" date and a print button.
  - **Full width**
  - **Blank canvas (no title band):** for landing pages built from patterns.
- **Block patterns** (+ inserter → Patterns → *Taxi Peninsula*):
  - sections: hero banner, feature cards, how it works, call-to-action band, our story, values, services grid, areas, testimonials, FAQ, contact details + map, booking form
  - **page starters** offered when you create a new page: About Us, Terms & Cancellation Policy, Accessibility Statement
  - **blog outlines** offered when you create a new post: local destination guide, travel tips
- Placeholder text you must replace is shown in **dashed yellow boxes** and marked **[CONFIRM]**.

### Address suggestions & fare estimates (v1.2)
- As passengers type an address, suggestions appear (Google Places API (New)). The dropdown works with keyboard and screen readers, and is biased to Melbourne and the Peninsula.
- An optional **fare estimate** appears once both addresses are filled in (Google Routes API). It uses your rates: flagfall, per km, per minute, booking fee, airport fee, minimum fare, and a night or weekend surcharge. It is shown as a range and labelled "estimate only".
  - The estimate, distance and drive time are saved with the booking and shown to staff, drivers and the customer.
  - It stays **off until you enter your real rates** under Bookings → Settings.
- Google is only ever called from the server, so **the API key is never exposed in the browser**. Results are cached to keep Google costs down.

### Terms, accessibility & cookies (v1.2)
- If a Terms & Cancellation Policy page exists, customers must tick **"I accept the terms and cancellation policy"** before booking. The time they accepted is stored on the booking.
- Setup creates **draft** Terms and Accessibility Statement pages. They stay drafts until you replace the [CONFIRM] notes and publish.
- **Google Analytics 4 with a consent banner:**
  - Nothing loads from Google until the visitor clicks **Accept**, and **Reject** is just as prominent.
  - Visitors can change their choice later via "Cookie settings" in the footer.
  - Staff visits are not tracked.

### Local SEO (v1.2)
- **Breadcrumbs**, with matching `BreadcrumbList` structured data, on pages, services, suburb pages and blog posts.
- **Suburb pages** get "Local details" fields: places you often travel to, local travel notes, and local questions (these also become FAQ structured data). Fill them in with real local knowledge.
- **Google Business Profile:** add your profile link (included in the structured data). After a completed trip, customers get a one-time **"Leave a review"** email and SMS.
- **Images:** featured images fall back to a descriptive alt text, and editors are warned when alt text is missing. Uploaded file names are tidied, e.g. `IMG_1234 (1).JPG` becomes `img-1234-1.jpg`.
- The WordPress sitemap excludes Manage My Booking, Driver Jobs and user archives. Submit `https://YOUR-SITE/wp-sitemap.xml` in Google Search Console.

### Security hardening (v1.2)
Each item can be switched under **Bookings → Settings → Security hardening**:
- security headers (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`), plus optional HSTS
- theme and plugin **file editors disabled**
- **XML-RPC blocked**
- **usernames hidden**: `?author=` scans, author archives, the public users API, the users sitemap and "unknown username" login messages
- WordPress version number hidden
- **Cloudflare-aware rate limits:** the visitor's real IP is used only when the request really comes from Cloudflare's network

A **Security check** panel under Bookings → Setup flags HTTPS, error display, secrets stored in the database, an `admin` username, outdated WordPress or PHP, and recommends two-factor login and backups.

### Dynamic content (v1.3)
**Content that updates itself**
- **Live trust numbers** under the home page hero: trips completed (counted from your bookings, plus any trips from before the website), suburbs served, vehicles, and the year you started. Figures round down (4,873 shows as "4,800+"), and the trip count stays hidden until it reaches a threshold you set.
- **Live Google reviews**, refreshed daily through the Places API:
  - shown exactly as Google returns them, including lower ratings, with the author's name, a link to the review and Google attribution
  - the star rating and review count update automatically
  - connect your listing under Bookings → Settings → *Find your Google Place ID*
- **Announcement bar** (wp-admin → **Announcements**):
  - three styles (information, heads-up, urgent), an optional link, and start/end dates
  - visitors can dismiss it
  - it still switches on and off at the right time on cached pages
- **Popular destinations**, built from real bookings in the last 12 months:
  - a place only appears if it looks public (hospital, airport, centre…) **and** at least 3 different passengers went there, so a home address can never show
  - staff can hide, rename or link a guide under Bookings → **Destinations**
  - each has a **Book** button with the destination filled in
- **Blog that links itself:**
  - reading time on every post
  - an automatic contents list on posts with 3 or more sections
  - "Keep reading" related posts
  - **suburb guides:** tag a post with a suburb (e.g. `frankston`) and it appears on that suburb's page, and the post links back

**Interactive tools**
- **"Do you cover my suburb?" checker** on the home, Contact and Areas pages:
  - instant answer that tolerates typos
  - add alternative names and postcodes to each service-area line: `Frankston | Frankston South | 3199`
- **Instant FAQ search** that filters questions as you type, with a live result count for screen readers.

**People & trust**
- **Meet the drivers** (About page and `[tp_drivers]`):
  - each driver's first name, photo and bio appear only when "Show on the website" is ticked on their profile, which requires their consent
  - once a driver is assigned, the passenger sees "Your driver" with their photo on Manage My Booking
- **Fleet photo gallery:** a "Photo gallery" box on each vehicle opens a full-screen viewer. It supports the keyboard (arrows, Esc), announces "Photo 2 of 5", and reads out alt text and captions.
- **Wheelchair safety video** (Customize → Taxi Peninsula → Wheelchair safety video):
  - use a YouTube/Vimeo link, or upload an MP4 with a `.vtt` captions file
  - nothing loads from YouTube until the visitor presses play (privacy-enhanced mode)
  - includes an optional transcript
- **Partner logos** (wp-admin → **Partners**): a logo only appears once "We have written permission to show this logo" is ticked.

**Growth**
- **Drive With Us** page template and application form. Applications are saved under Bookings → Enquiries ("Driver application") and emailed to you. Setup creates it as a draft with [CONFIRM] notes for pay and conditions.
- **Seasonal pages:**
  - use the *Seasonal / event landing page* pattern
  - schedule go-live with Publish → Schedule, and set **"Unpublish automatically"** in the side panel, which moves the page to drafts on that date
- **"Text us" / WhatsApp buttons** next to "Call now" on phones. Set the numbers in Customize → Contact details.

**Look & feel**
- **Dark mode:** follows the device setting, with a Dark mode button beside the text-size and high-contrast controls.
- **Time-of-day hero message**, e.g. "Home from the hospital? Book a pick-up" in the afternoon. It uses Melbourne time and is edited in Customize → *Live numbers & hero messages*.

### Home & contact page upgrades (v1.4)
- **Home page builder** (Customize → Taxi Peninsula → *Home page layout*): tick to show or hide each of the 14 sections, and reorder them with ▲ ▼. It works with the keyboard and screen readers. The hero always stays on top. Edit every section's heading and intro under *Home page headings & text*; leave a field empty to keep the default wording.
- **Quick-book bar in the hero:** From, To and Date, with Google address suggestions if they're switched on. "Continue" opens the booking form with those details filled in and focuses the next empty field. It can be switched off in *Home page layout*.
- **Seasonal hero photos** (Appearance → **Hero photos**): add a photo with From and Until dates, optionally repeating every year (e.g. every December). It replaces the normal hero image during those dates.
- **"Who we help" panels:** four audiences (passengers, family & carers, NDIS coordinators, hospitals & aged care). Each has an editable heading, text and link under Customize → *Who we help panels*. Clear a heading to hide that panel.
- **Top-questions FAQ strip:** tick "Show on the home page" on any FAQ. If fewer than four are ticked, the first FAQs fill the gap. It links to "See all FAQs".
- **Live "Open now" status:** in Customize → Contact details, untick "Open 24 hours, 7 days" and enter hours like `Mon-Fri 06:00-22:00` / `Sat 07:00-20:00` / `Sun closed`.
  - The top bar, contact card and footer then show e.g. "Open now · phones answered until 10pm" or "Closed now · we open tomorrow at 7am".
  - It's calculated in Melbourne time, so it stays correct on cached pages.
  - The contact page shows the weekly table, and the hours are added to your Google business data.
- **"What can we help with?" chooser** at the top of the Contact page. It sends each enquiry to the right place:
  - booking a trip → the booking form
  - changing a booking → Manage My Booking
  - NDIS accounts → the NDIS page
  - feedback / complaints → the complaints page
  - lost property → the contact form with "Lost property" pre-selected
  - driver jobs → Drive With Us
- **Complaints process:** Setup creates a draft *Complaints & Feedback* page. It covers how to complain, the steps and timeframes, and escalation to Commercial Passenger Vehicles Victoria and the NDIS Quality and Safeguards Commission. Response times and the regulators' contact details are marked [CONFIRM].
- **Enquiry auto-reply:** every contact form enquiry now gets an acknowledgement email. Complaints also get a link to the complaints process. Set a reply time (e.g. "within one business day") in Customize → Contact details, or leave it empty to not promise one.

### Design options (v1.5)
Everything is under **Appearance → Customize** and previews before you publish.

**Logo & branding** (*Site Identity*)
- Upload your logo, then set its **height on computers and on phones** with sliders.
- **Extra logos:** a **dark mode** version (usually white), a **high contrast** version (plain black) and a **footer** logo. The right one swaps in automatically.
- **Show in the header:** the logo only, or the logo with the site name and tagline. On small phones the name is hidden visually but still read by screen readers.
- **Editable tagline** under the name (default "Wheelchair Accessible Taxis"). Leave it empty to hide it.

**Colours** (*Design → Colours*)
- **Five ready-made schemes:** Navy & Yellow (original), Teal & Amber, Forest Green & Lime, Charcoal & Orange, Purple & Gold. Picking one fills in the colour pickers.
- **Pickers** for the main colour, accent, links, page background and footer. Shades such as hover colours, tints, borders, button text and the dark-mode link colour are worked out automatically.
- **Contrast warnings:** a warning appears under a colour, with its contrast ratio, if the combination fails WCAG AA (e.g. white text on a light main colour).
- **Editor palette:** the block editor's colour palette follows your choices, and pattern blocks use them too.

**Fonts & shape** (*Design → Fonts & shape*)
- **Fonts:** Atkinson Hyperlegible (default), Lexend, Inter or Source Sans 3. All are stored in the theme (`assets/fonts`, SIL Open Font License), so nothing is loaded from Google. The chosen font is preloaded.
- **Sizes and weights:** base text size (90–125%) and heading weight. The visitor's text-size buttons still work on top of the base size.
- **Shapes:** button shape (pill, rounded, square), card corner size and shadow strength (none, soft, strong).

**Header** (*Design → Header*)
- **Layouts on computers:** logo left with menu right, everything centred, or a logo row above the menu.
- **Sticky header:** on or off.
- **Header buttons:** show or hide the phone and "Book" buttons and change their wording.
- **Top bar:** full (contact details + display options), contact details only, or none. When the display options (text size, high contrast, dark mode) leave the top bar they move to the footer; they are never removed.
- **Automatic Menu button:** if the full menu doesn't fit on a computer screen (a long menu, a big logo, larger text), the header switches to the Menu button instead of overflowing.

**Home page hero** (*Design → Home page hero*)
- **Background:** gradient, solid colour, or the hero/seasonal photo with an adjustable darkening overlay (keep it at 55% or more).
- **Beside the text:** the photo, the badge card, or nothing.
- **Text position and height:** left or centred text; short, medium or full-screen height.

**Footer** (*Design → Footer*)
- **Columns:** automatic, 2, 3 or 4.
- **Footer menu:** show or hide it.
- **Social links:** Facebook, Instagram, LinkedIn and YouTube icons. They're also added to your Google business data (`sameAs`).
- **Copyright line:** your own text, where `{year}` and `{site}` are filled in for you (e.g. "© {year} {site} · ABN …").

**Pages & blog** (*Design → Pages & blog*)
- **Page title banner:** the main colour, an image (a page's featured image, or a default banner image), or plain.
- **Blog:** grid or list layout, the sidebar on or off (for the blog and single posts), and posts per page.
- **Back to top button:** show or hide it.

## Install

1. Zip the `taxi-peninsula` folder and upload it under **Appearance → Themes → Add New → Upload**. Then activate it.
2. **Settings → General:** set the timezone to **Melbourne**.
3. **Settings → Permalinks:** choose "Post name".
4. **Bookings → Setup:** click **Create starter content**.
5. **Settings → Reading:** choose a static front page (for example a page called *Home*), and set a *Blog* page as the posts page.
6. **Appearance → Customize → Taxi Peninsula:** check the contact details, hours, hero text and service areas. Upload your logo under *Site Identity* and choose colours and fonts under *Design*.
7. **Bookings → Settings:** connect SMS, Stripe and spam protection as needed (details below).
8. **Users → Add New:** add drivers with the **Driver** role and their mobile number, and office staff as **Booking Manager**.
9. Review the starter content: FAQs, services, suburb pages and the example fleet vehicles (add photos, then publish them). Add real **Testimonials**.
10. Install an SMTP plugin, for example WP Mail SMTP, so emails are delivered reliably.

### SMS (ClickSend or Twilio)
Create an account with either provider and paste its credentials into **Bookings → Settings**. Set a sender ID (for example `TaxiPen`) or your SMS number. Use **Send a test SMS** to check it works. Messages are billed by the provider.

### Stripe deposits
1. Turn on **Offer online deposit**, set the amount, and paste your **secret key**.
2. In Stripe, go to **Developers → Webhooks** and add an endpoint at `https://YOUR-SITE/wp-json/taxi-peninsula/v1/stripe` for the event `checkout.session.completed`.
3. Paste that endpoint's **signing secret** into the settings.

Payments happen on Stripe's hosted checkout, so card details never touch your site.

### Google Maps (address suggestions & fare estimates)
1. In Google Cloud, create a project with billing, and enable **Places API (New)** and **Routes API**.
2. Create an API key and **restrict it** to those two APIs and to your server's IP address. The key is only used server-side.
3. Paste the key in Bookings → Settings (or use `TP_GOOGLE_MAPS_KEY` in wp-config.php).
4. Enter your fare rates, then tick **Show a fare estimate**.

### Google Analytics
Paste your GA4 measurement ID (`G-…`) in Bookings → Settings → Analytics & cookies. The consent banner appears automatically.

## Hosting hardening
A theme can only do part of this; the rest belongs in your server settings.

**1. wp-config.php** (above the "stop editing" line):
```php
// Keep API secrets out of the database.
define( 'TP_STRIPE_SECRET_KEY', 'sk_live_...' );
define( 'TP_STRIPE_WEBHOOK_SECRET', 'whsec_...' );
define( 'TP_CLICKSEND_API_KEY', '...' );      // or TP_TWILIO_AUTH_TOKEN
define( 'TP_CAPTCHA_SECRET_KEY', '...' );
define( 'TP_GOOGLE_MAPS_KEY', '...' );

define( 'DISALLOW_FILE_EDIT', true );          // no code editing from wp-admin
define( 'FORCE_SSL_ADMIN', true );
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
```

**2. Security headers at the server.** These also cover images and files that WordPress doesn't serve. For Apache (`.htaccess`):
```apache
<IfModule mod_headers.c>
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-Frame-Options "SAMEORIGIN"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
</IfModule>
<Files wp-config.php>
  Require all denied
</Files>
<Files xmlrpc.php>
  Require all denied
</Files>
```
For nginx:
```nginx
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
location = /xmlrpc.php { deny all; }
location ~* /wp-content/uploads/.*\.php$ { deny all; }
```

**3. Everything else:**
- Use HTTPS everywhere.
- Enable **two-factor login** (the "Two Factor" plugin) for every administrator and Booking Manager.
- Keep daily **off-site backups**.
- Keep WordPress, PHP (8.1+) and plugins updated.
- File permissions: 644 for files, 755 for folders, 600 for `wp-config.php`.
- Don't use an account called `admin`.

## Shortcodes
`[tp_booking_form]` `[tp_booking_lookup]` `[tp_contact_form]` `[tp_faq]` `[tp_faq topic="booking"]` `[tp_fleet]` `[tp_fleet limit="2"]` `[tp_ndis]` `[tp_testimonials]` `[tp_driver_jobs]` `[tp_services]` `[tp_areas]` `[tp_contact_details]` `[tp_map]` `[tp_map q="Frankston VIC"]` `[tp_stats]` `[tp_suburb_checker]` `[tp_destinations]` `[tp_drivers]` `[tp_video]` `[tp_partners]` `[tp_driver_apply]` `[tp_contact_chooser]` `[tp_faq featured="yes" limit="4" search="no"]`

## Before going live
- **Complaints page:** replace the [CONFIRM] response times and add the regulators' current contact details, then publish it. The chooser and the auto-reply link to it once it's published.
- **Opening hours:** if you're not truly 24/7, untick "Open 24 hours" and enter real hours. The live status, contact page and Google data all use them.
- **Google reviews:** Google requires its logo next to reviews shown without a map. Upload the official logo from Google's brand resources and paste its URL in Bookings → Settings → Google reviews.
- **Driver profiles and partner logos:** only tick "show" once you have the person's or organisation's permission.
- **Terms and Accessibility pages:** replace every **[CONFIRM]** note with your real policy, have the terms checked, then publish. Customers only have to accept the terms once that page is published.
- **Fare estimates:** switch them on only when the rates match your real fares.
- **Check the wording against your real service.** This covers the starter FAQs and service text, and home page claims such as "24 hours", "accredited drivers" and "ramp & hoist vehicles".
- **Add local detail to suburb pages** (nearby hospitals, landmarks). Pages that only use the default text are thin content for Google.
- **Exclude the booking, Manage My Booking and Contact pages from page caching.** Their forms use security tokens that go stale when cached.
