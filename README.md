# Taxi Peninsula — WordPress theme

A WordPress theme for a Melbourne wheelchair taxi service. It includes online bookings, a bookings manager, driver job sheets, SMS and payment integrations, and a blog.

- **Phone:** 0468 323 211 · **Email:** support@taxipeninsula.com.au
- **Theme folder:** [`taxi-peninsula/`](taxi-peninsula)
- **Requires:** WordPress 6.3+ and PHP 7.4+. Tested on WordPress 7.1 and PHP 8.4.

## Features

### New in v2.7
**More pages that rank**
- **Place pages:**
  - "Going to" pages for hospitals, clinics, airports and venues at `/wheelchair-taxi-to/{place}/`, for searches like "wheelchair taxi to Frankston Hospital"
  - "Picking up from" pages for railway stations and shopping centres at `/wheelchair-taxi-from/{place}/`
  - Each page has the accessible entrance, the exact drop-off or pick-up spot, parking, driver tips, a booking card with the address filled in, travel times from nearby suburbs, questions and answers, reviews and business data for Google.
  - Manage them under Website content → Place pages. One click adds suggested hospitals, stations and shopping centres as drafts, or drafts from Popular destinations.
  - A page with [CONFIRM] notes stays a draft until you remove them.
  - `[tp_places]` lists them on the "Places We Go" starter page. Suburb pages and the footer link to them.
- **Glossary** at `/glossary/`: short answers to terms such as MPTP, WAT, maxi taxi, NDIS transport funding, plan manager, four-point restraint, ramp vs hoist and CPVV.
  - One click adds 14 starter terms as drafts.
  - Each term is marked up for Google as a defined term.
  - The first mention of a term in your blog posts and pages links to it automatically.
- **Seasonal content plan** (Marketing → Content plan) lists the next date for each Victorian event and season: Australian Open, Moomba, Easter, ANZAC Day, Mother's Day, the AFL Grand Final, the Royal Melbourne Show, Melbourne Cup, Christmas and more.
  - "Create draft guide" starts a blog post with question headings ready to fill in.
  - The Dashboard reminds you 6 weeks ahead.

**Real photos**
- **Website content → Photo checklist** shows every service, suburb, vehicle, driver, partner, place page and blog post still without a photo. It also has a shot list and consent guidance.
- Real photos can replace the illustrated pick-up and airport scenes.
- Suburb and place pages now show their featured photo. Service pages that have a photo no longer show the illustrated scene as well.

**Bookings and payments**
- **Australian address suggestions from Addressfinder:** under Taxi settings → Settings → Maps, addresses & fare estimates, choose Google, Addressfinder or both. Addressfinder handles unit numbers and new estates better; Google also finds places by name.
- **BPAY, PayID and bank transfer details** on account invoices (receipts with money owing), in quote emails and in `[tp_pay_options]`.
  - Set them in Taxi settings → Settings → Payments & invoicing.
  - Each trip or quote gets its own BPAY reference number with a check digit.
- **Clear cancellation and no-show fees:**
  - shown above the terms on the booking form, on active bookings and in booking and quote emails, and in `[tp_cancellation_policy]`
  - Set the free-cancellation window, fees and free waiting time in Taxi settings → Settings → Recurring trips.
  - The fees shown are saved with each booking.
- **Call-back requests** (`[tp_callback]`, starter page "Request a Call Back"):
  - The form asks for a name, phone number, best time and what it's about.
  - The office gets an email and an SMS, and the customer gets an SMS confirmation.
  - Out of hours, the form says when you'll call.
  - Linked from the account menu, the contact chooser and the booking form.
  - Bookings → Call-backs lists requests with their status (new, no answer, called, done) and a count on the menu.
- **Weather and road alerts:**
  - Bureau of Meteorology warnings for your districts are checked every hour.
  - Road alerts the office types in under Bookings → Alerts disappear when they expire.
  - Both show on the driver screen and the dispatch board. Road alerts can also show as a banner on the website, and so can weather warnings if you turn that on.

**Partner portal**
- Link coordinator accounts to a partner page (hospital, aged-care home, NDIS provider) in its "Portal users" box.
- They see an **Our organisation** tab in My Account with:
  - the month's trips, completed, upcoming and cancelled trips, fares and the account balance
  - every trip booked from the partner page, its QR code or by its portal users, with receipt and invoice links
  - the last 6 months, a CSV download and a "Book from {organisation}" button

**Legal and compliance (Australia)**
- **Incident and complaint register** (Bookings → Incidents):
  - Covers safety incidents, injuries, near misses, vehicle problems, complaints, driver conduct and privacy breaches.
  - Each entry has the booking, driver and vehicle (filled in from the booking reference), what happened, the action taken, the outcome, a follow-up date and who it was reported to (CPVV, NDIS Commission, OAIC, WorkSafe, police, insurer).
  - Complaints sent through the contact form are added automatically.
  - Includes a CSV export and an open-items count on the menu.
- **Data breach response plan** (Taxi settings → Data breach plan):
  - the four steps (contain, assess within 30 days, notify, review) under the Notifiable Data Breaches scheme, with a checklist and a notice template
  - your response team, and a list of recorded breaches
  - a "Record a data breach now" button
  - a print-friendly layout

**Fixes**
- The settings for Google Maps and fare estimates, Google Analytics and the cookie banner, live Google reviews and security hardening were described in this guide but never appeared on the settings page. They are now there. Security hardening is on by default, except HSTS and the Cloudflare option.
- Tables and month pickers in My Account (NDIS trip log, Our organisation) are now styled.
- Activity log CSV export no longer logs a PHP 8.4 deprecation warning.

### v2.6.1 — fixes from a full error sweep
- Driver "Found an item?" no longer lands on "Page not found". It returns to the driver's day view.
- The language links on the "Page not found" page now go to the home page in that language, instead of to more missing pages.
- The "Written by" box shows the author's initials instead of a Gravatar photo, so no visitor data goes to an outside service.

### New in v2.6 — search and AI visibility
- **Council-area pages** at `/council/{council}/`, plus a list of all council areas at `/council/`. Each page has:
  - the suburbs you cover in that council area, a map and travel times
  - a quick answer, questions and answers, reviews that mention the area, and a booking card
  - business data for Google describing the council as the area served
  - Suburbs are matched to councils automatically across Melbourne's south-east, the Mornington Peninsula and Geelong. You can set the council by hand in each area's "Council area" box.
  - Area pages link to their council page. Council pages are added to the sitemap and are sent to Bing.
- **Quick answer** ("In short") at the top of service, area, local and council pages: who you are, where you go, how to book and the starting price. You can write your own in the "Quick answer" box on services, areas and pages.
- **Question headings** ("How much does it cost?", "How long does it take to get places from …?"), plus standard questions on area and council pages about power wheelchairs, carers and paying.
- **"Last updated" dates**, plus article and page details for Google (published and updated dates, author, publisher).
- **Expertise:**
  - a "Written by" author box on blog posts, filled in from Users → Profile → "About you as an author" (role, years of experience, bio)
  - `[tp_credentials]` lists your accreditations and checks
  - a draft starter page, "Driver Training & Safety", with [CONFIRM] notes
  - the year you started, shown in the quick answer
- **Video details for Google** for the wheelchair safety video, from YouTube or Vimeo, or an uploaded file.
- **Reports → Search performance** shows Google Search Console data, refreshed weekly:
  - clicks, times shown, click rate and average position
  - top searches and top landing pages
  - **"Almost on page 1"**: searches where you rank 8–20, with the page to improve
  - It uses the same Google OAuth client as Google profile posts. In Google Cloud, enable the Search Console API and add the redirect address shown on the page.
- **Marketing → AI & Bing search** gains four sections:
  - **AI and search bots reading your site:** ChatGPT search, Perplexity, Claude, Bing, Google, Apple and others, with visit counts and the pages they read.
  - **Bing searches:** your Bing searches and clicks, using a Bing Webmaster API key.
  - **Partner links:** how many partners link to you. Each partner page records the request status and the partner's page, and that page is checked weekly for a link to you.
  - **Monthly AI check:** questions with one-click "Ask" links for ChatGPT, Copilot, Perplexity and Google. Mark whether you were named, add notes and see the history month by month. A reminder email goes out on the 1st, and the Dashboard shows a notice until the check is done.

### New in v2.5 — search engines and AI assistants
- **Marketing → AI & Bing search** (new page). Helps you get recommended by Bing, ChatGPT search and Microsoft Copilot (both get much of their information from Bing).
  - **Results:** calls, bookings, quotes and revenue that came from ChatGPT, Copilot, Perplexity, Gemini, Claude and Bing. AI visits are recognised by where the visitor came from and by tracking tags such as `?utm_source=chatgpt.com`.
  - **Checklist (11 steps):** Bing Webmaster Tools, IndexNow, llms.txt, AI search bots allowed, business details, https, enough FAQs, Bing Places, Apple Business Connect, reviews, directories.
  - **Settings:** your Bing code, IndexNow, llms.txt and the AI crawler policy.
  - **"Send all pages to Bing now"** button.
  - **Preview** of what AI assistants read about you.
- **IndexNow:** new and changed pages are sent to Bing within a minute. The key file is created and served automatically.
- **`/llms.txt`:** a plain summary for AI assistants, built from your services, areas, hours, contact details and FAQs.
- **robots.txt AI rules:** AI search bots are allowed and AI training bots are blocked by default. You can change this.
- **Search Console and Bing verification:** paste the code, or the whole meta tag, under Taxi settings → Settings → Search engines & AI.
- **Richer business data for Google:**
  - street address, map coordinates, opening hours from your schedule, price range, languages
  - services offered, ABN, CPVV and NDIS numbers, and a booking action
  - on the home page: a TaxiService entry, plus site search for Google
- **Footer:**
  - business name, address and phone, exactly as on your Google profile
  - ABN, CPVV and NDIS numbers
  - a "Suburbs we cover" column
- **"Helpful official information" links** on service and local pages (NDIS, CPVV by default, editable).
- **Thin pages hidden from search results:** search results, tag, date and author archives, attachment pages.
- **Speed:** the home page photo loads first, and new uploads are saved as WebP when the server supports it.
- **Check connections now also checks search:**
  - search engines allowed
  - verification codes
  - sitemap
  - pages without their own description
  - images without alt text
  - duplicate titles
  - business details
  - AI search features
- **Partner pages:** a ready-made "link to us" snippet to send to each hospital's or home's web team.
- Not included, because free plugins already do it well: a redirect manager (for example the "Redirection" plugin) and page caching (your host's cache or a caching plugin).

### New in v2.4
- **Cleaner top bar.** The opening hours are on the left, and one **My account** menu is on the right. The menu holds:
  - sign in and create an account (for passengers, coordinators and drivers alike)
  - a link to manage a booking
  - the phone number, email address and "Text us"
  - once someone is signed in: My trips, My jobs (for drivers) or the dispatch board (for staff), plus sign out
- **Drivers are approved by the owner.** Nobody can sign up as a driver.
  - A driver first creates a normal account. The owner then clicks **Make driver** under their name in Users, either one at a time or as a bulk action.
  - The driver is emailed and sees their jobs after signing in.
  - **Make customer** removes driver access.
  - Public sign-up can never create a staff or driver account.
- **Admin menus split by job.** Each screen keeps its address, so old links and bookmarks still work.
  - **Bookings:** all bookings, add, dispatch board, change requests, calendar, run sheet, quotes, enquiries, lost property
  - **Invoices:** invoices & payments, NDIS trip log
  - **Reports:** owner dashboard, booking reports, leads & calls, activity log
  - **Marketing:** news & offers, promo codes, partner pages, events, destinations, Google profile
  - **Website content:** an overview page, plus services, areas, fleet, FAQs and topics, testimonials, partner logos and announcements
  - **Taxi settings:** settings, setup, check connections, privacy

### New in v2.3
- **Drivers log found items:** a "Found an item?" button on each finished job, plus one for items not linked to a trip.
  - The driver takes or picks a photo, types what it is and where it is now.
  - It appears under Bookings → Lost property with the photo, linked to the trip, driver and vehicle.
  - The office is emailed, including how many passenger reports may match.
- **Check connections** (Bookings → Check connections): one button that tests:
  - the flight data API key
  - the Google Business Profile connection
  - the SMS provider login
  - the SMS "STOP" reply address
  - whether phone notifications are ready
  - email (a test email is sent to you)
  - Each result is shown in plain words with a "Fix it" link.
- **Phone notifications with no app:** passengers tap "Notify me" on their booking page or confirmation.
  - They are notified when the booking is confirmed, the driver is on the way or has arrived, the pick-up time moves for a flight, or the booking is cancelled.
  - It uses standard Web Push with keys the site makes itself; there is nothing to sign up for.
  - On iPhone, Apple only allows this once the site is added to the Home Screen, and the page explains how.
  - The site must be on https.
- **Event pages** (Events, `/events/`): concerts, the footy, Christmas lights.
  - Each event has a date, venue, set accessible drop-off and pick-up points, "going home after" advice and an optional promo code.
  - "Book a trip here" and "Book a ride home from here" open a filled-in booking form, and bookings are tagged with the event.
  - Past events say so. Pages include event schema for Google. Shortcode: `[tp_events]`.
- **Review widgets:**
  - Partner pages show reviews that mention that hospital or home, using its name plus optional "other names".
  - Local pages show reviews that mention the suburb.
  - Reviews come from your Testimonials and your Google reviews. If none mention the place, the widget shows your best general reviews under "What passengers say".
  - Shortcode: `[tp_reviews_about terms="Frankston, Peninsula Health"]`.
- **Owner dashboard** (Bookings → Owner dashboard), for the last 7, 30, 90 or 365 days:
  - headline numbers: trips, completed, revenue, average fare, cancellation and no-show rates, compared with the previous period
  - charts of trips and revenue over time
  - a weekday × hour heat map of busiest pick-up times
  - top partners, where bookings come from, and trip outcomes
  - every chart has a table view
- **Marketing role:**
  - It can manage promo codes, partner pages, events, News & offers, Google posts, the Leads & calls report, and website content (posts, pages, announcements, media).
  - It cannot see bookings, customers, dispatch, quotes or finance.

### New in v2.2
- **Flight-linked airport pick-ups**:
  - customers add a flight number when booking
  - every 15 minutes on the day, the theme checks the flight using AeroDataBox via RapidAPI (Bookings → Settings → Flight tracking)
  - if the flight lands early or late, the pick-up moves by the same amount, keeping the gap the customer asked for
  - the office is emailed, and the driver and customer are texted
  - without an API key, the dispatch board and driver screen show a live-status link instead
- **Quote requests**:
  - `[tp_quote_request]` (starter page "Get a Quote") handles long trips (Geelong, regional, interstate), weddings and groups
  - the office prices the quote under Bookings → Quotes and emails it to the customer
  - the customer's "Book this trip" link opens a filled-in booking form, and the quote is marked "Booked"
- **Partner pages and QR codes**:
  - set up under Bookings → Partner pages, at `/book-from/{name}/`: one page for each hospital, aged-care home or NDIS provider
  - each page has the partner's logo and a booking card with the pick-up address filled in
  - an optional promo code and invoice details are applied automatically
  - a printable A4 poster carries a QR code; the QR library is bundled with the theme, so nothing is loaded from other sites
  - page visits, QR scans and bookings are counted for each partner
  - pages are kept out of search engines
- **NDIS trip log** (Bookings → NDIS trip log, and My Account → NDIS trip log for coordinators):
  - one monthly list for each participant
  - download it as a CSV, or print it and "Save as PDF" from the print dialog
  - the office can email it to the plan manager
  - optional: show your NDIS provider number and support item on the log
- **Lost property**:
  - `[tp_lost_property]` (starter page "Lost Property") is the form for passengers
  - the office logs items found in vehicles
  - each report shows likely matches by trip, driver, vehicle, date and wording
  - "Match & tell passenger" emails the passenger, and texts them when SMS is set up
- **Promo codes** (Bookings → Promo codes):
  - a percentage or a dollar amount off
  - optional start and end dates, a use limit, and first-trip-only
  - codes are checked on the booking form
  - the discount comes off when the office enters the fare, and shows on the receipt, the Finance page and its CSV
- **News & offers by email and SMS**, built for the Spam Act 2003:
  - the opt-in box is never pre-ticked, and the consent wording, time, page and IP address are stored
  - email addresses are double opt-in
  - every message names the business and has an unsubscribe link; emails also carry the one-click List-Unsubscribe header
  - "reply STOP" works through the inbound SMS address
  - sign-up comes from the booking form, the footer box, or `[tp_newsletter]` `[tp_events]` `[tp_reviews_about]`
  - messages go out in batches from Bookings → News & offers, which also lists subscribers and exports a CSV with consent records
  - sign-ups are included in privacy export and erase
- **Local service pages**:
  - `/wheelchair-taxi/{area}/{service}/`, e.g. "Airport transfers from Mornington"
  - each page has travel times, a map, the service's steps and fares, local questions with FAQ schema, and a booking card with the suburb filled in
  - pages are listed in the XML sitemap
  - turn them off, or set noindex, under Bookings → Settings → Local pages
- **Google Business Profile posts**:
  - tick "Post to Google" on an announcement, a promo code (posted as an Offer) or a blog post
  - setup is under Bookings → Google profile: an OAuth client and your business location
  - **Google must approve your project for the Business Profile APIs first**, which can take a few days
- **Call tracking** (Bookings → Leads & calls):
  - each visit's source is remembered: UTM tags, Google Ads, Facebook, search engines, referring sites and partner QR codes
  - counted against it: taps on phone, WhatsApp, SMS and email links; bookings; quotes; and sign-ups
  - reports are by source, campaign, page and partner
  - optional tracking numbers show a different phone number to visitors from a given source

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
  - fluid text sizes that follow the visitor's own device settings (text size, zoom, dark mode, high contrast, reduced motion)
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
- **Sizes and weights:** base text size (90–125%) and heading weight.
- **Shapes:** button shape (pill, rounded, square), card corner size and shadow strength (none, soft, strong).

**Header** (*Design → Header*)
- **Layouts on computers:** logo left with menu right, everything centred, or a logo row above the menu.
- **Sticky header:** on or off.
- **Header buttons:** show or hide the phone and "Book" buttons and change their wording.
- **Top bar:** contact details or none. It is hidden on phones, where the bottom bar has Call. (Since v2.1 there are no on-page text-size, contrast or dark-mode buttons: the site follows each visitor's device settings.)
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

### Animations (v1.6)
Lightweight animated illustrations, drawn in SVG and CSS. There are no video files or scripts to download, and they use your chosen colours.
- **Taxis driving by:** a maxi taxi and taxis drive along a road at the bottom of the home page hero, under every page title banner, and along the top of the footer.
- **Door-to-door pickup scene:** a maxi taxi pulls up at a house, lowers its rear ramp, and a passenger in a wheelchair rolls aboard before it drives off.
- **Airport scene:** a taxi drops a traveller at *Departures* while a plane takes off, then a maxi taxi picks up a wheelchair passenger at *Arrivals*.
- **Where the scenes show:**
  - the home page section "How a trip works" (move or hide it in *Home page layout*)
  - every service page (airport services get the airport scene, the rest get the pickup scene)
  - anywhere you add `[tp_scene name="pickup"]`, `[tp_scene name="airport"]` or `[tp_scene name="road"]`, or the matching block patterns
- **Accessibility:**
  - every scene has a text description for screen readers, and the moving roads are hidden from them
  - a **Pause animations** button on each scene stops everything and is remembered for that visitor
  - anyone whose device asks for reduced motion sees still pictures
  - high contrast mode hides the moving roads
  - animations only run while they're on screen
- **Settings** (Customize → Design → *Animations*): turn animations on or off, and choose where the moving road appears (hero, page banners, footer). When animations are off, the scenes show as still pictures.

### Roles: operators, drivers, customers, coordinators, accounts (v1.7)
**User types**
| Role | Signs in to | Can do |
|---|---|---|
| Administrator / Booking Manager (operator) | wp-admin | Everything below: dispatch, requests, bookings, reports, finance |
| Driver | Driver Jobs page (phone) | Only their own trips |
| Customer | My Account page | Their own trips, passengers, addresses, receipts |
| Coordinator / facility | My Account page | Book for many clients, see all their bookings, invoices on account |
| Accounts (finance) | wp-admin → Invoices | Fares, invoices, payments and finance CSV only — no dispatch |

**Operator** (Bookings menu)
- **Dispatch board:** one screen per day, with "Needs a driver" at the top and a column for each driver. Change the driver or vehicle from a dropdown and it saves straight away: the driver gets a text and the customer is told. Assigning without choosing a vehicle uses the driver's **usual vehicle** (set on their user profile).
- **Clash warnings:** shown on the board, after assigning, and on the booking screen:
  - a driver who already has a trip at that time (trip length plus 15 minutes to load)
  - a vehicle without wheelchair access, or with too few wheelchair positions or seats
  - a Maxi booking given a one-wheelchair van
  - warnings never block an assignment
- **Change requests:** every customer change or cancellation request in one list, with **Approve** and **Decline** buttons. A change can be applied right there (new date, time, addresses) and include a reply. The customer gets an email and SMS either way. Approving "cancel all remaining trips" cancels the rest of a repeat booking.

**Driver** (Driver Jobs page)
- **Trip buttons:** *Accept trip → On my way → Arrived → Passenger on board → Completed*, or *No-show* once arrived.
- **Customer texts:** "On my way" and "Arrived" text the passenger automatically (Bookings → Settings → SMS). Every step is time-stamped.
- **Decline with a reason:** the trip goes back to "Needs a driver" with the reason shown, and the office is emailed and texted.
- **New driver must accept:** when a trip is reassigned, the new driver has to accept it.
- **My week:** trips for the next 7 days, plus a weekly **timesheet** with on-my-way, on-board and finish times, minutes with passengers, km, no-shows and the driver's average rating. It can be printed.

**Customer** (My Account page, created by Bookings → Setup)
- **Sign-up:** create an account (name, email, mobile, password) with the same spam protection as the booking form. Bookings made while signed in appear automatically. Older ones can be added with their reference and phone number.
- **Trips:**
  - upcoming and past trips with live status (the progress bar now shows *On the way* and *Arrived*)
  - **Book again** and **Book the return**, which copy the trip with the addresses swapped for the return
- **Passengers and addresses:** passenger profiles with wheelchair type, chair size and weight, and needs. Choosing one on the booking form fills in their details and adds the chair notes for the driver. Saved addresses can be picked from a list.
- **Receipts and invoices:**
  - receipts open from the account, the booking page and the "completed" email
  - once a fare is entered they show GST (1/11 of the fare)
  - with your **ABN** set (Bookings → Settings → Payments) they become **tax invoices**
  - account invoices show "Amount due" and your payment terms
  - print or save as PDF from the browser
- **Ratings:** customers rate a completed trip from 1 to 5 stars with an optional comment. Ratings of 3 stars or lower email the office. Driver averages show on the dispatch board and in the driver's week.

**Coordinator / facility account**
- **Sign-up:** choose "Clients or residents" when signing up and enter the organisation name. The office is emailed about each new organisation account.
- **Clients:** save each client once, with their NDIS number and wheelchair details. Bookings default to **account / invoice**, with the organisation's invoice details filled in.
- **Bookings:** see all bookings made for clients, filtered by client. Coordinators get the emails; each client gets the texts on their own mobile.

**Accounts (finance)** (wp-admin → Invoices)
- **Trip list:** finished trips and online deposits by date range, payment method, invoice status or search (invoice to, passenger, reference, NDIS number).
- **Fares and invoices:** enter the final fare and mark account invoices *Invoice sent* or *Paid*.
- **Totals:** fares, GST, paid online, amount owing on account and trips missing a fare.
- **Exports:** a finance **CSV export**, plus a link to each tax invoice. Accounts staff cannot open the dispatch board or edit bookings.

### Design upgrades (v1.8)

All of these follow your logo colours and work in dark mode and high contrast. They can be switched in **Appearance → Customize → Design**.

- **Illustrations:** drawn icons for services, "Who we help", destinations and the contact chooser, in the same style as the maxi taxi. Set *Fonts & shape → Icon style* to *Line* to use the simple outline icons instead.
- **Section dividers:** a gentle wave or a dashed road line between sections, or none (*Fonts & shape → Section dividers*).
- **Book a Taxi page:**
  - progress steps 1–4 (Trip, Needs, Payment, Your details) that fill in as you go
  - a sticky "Your trip" summary with the route, time, people, vehicle and fare estimate
  - a friendlier confirmation screen: what happens next, **Add to calendar** (.ics with a 30-minute reminder), view the booking and book the return trip
- **My Account dashboard:** a "Your next trip" card with a live countdown, trip progress, and the driver's photo and vehicle once assigned, plus small stats tiles.
- **Driver phone screen:**
  - big colour-coded step buttons, a large time display and a "Next job" marker
  - Navigate buttons switch from pick-up to drop-off once the passenger is on board
  - swipe left or right between days, with a bottom bar (Previous, Today, Next) for one-handed use
- **Dispatch timeline:** a "Timeline" view on the dispatch board shows each driver as a row of blocks across the day, with clashes outlined and a line for the current time. The board remembers your view.
- **Branded emails and invoices:** every email is sent as HTML with your logo, colours, trip-details table, buttons and contact footer, plus a plain-text copy. Receipts and tax invoices carry the same brand band.
- **Micro-interactions:** buttons show a spinner while sending, success messages pop in with a tick, and sections fade in as you scroll (turn off under *Fonts & shape*). All of this is skipped for visitors who prefer reduced motion.
- **Empty states:** friendly illustrations when there are no trips, no jobs, nothing to dispatch or no search results.
- **Mobile bottom bar:** Book, My trips, Call and Menu along the bottom on phones (*Header → Mobile bottom bar*).
- **Typography:** tighter headings, an accent bar on section titles and pull-quote testimonials.
- **App icons and share images:**
  - if no Site Icon is set, the theme makes a favicon, Apple touch icon and web app manifest from your colours
  - every page without a featured image gets a generated 1200×630 share card with its title, your phone number and the maxi taxi symbol (cached in `uploads/tp-share`)

### Languages, reviews, activity log and privacy (v1.9)

**Languages** — Tiếng Việt, 简体中文, Ελληνικά, Italiano and العربية
- A globe button in the header (and a list in the footer) switches the language. The choice is remembered, and `?lang=vi` works on any link. Visitors whose browser prefers one of these languages are offered it once, in that language.
- Translated: menus, the booking form and confirmation, Manage My Booking, My Account, the driver screen, receipts, built-in page titles, and the emails and texts customers get. Customer emails and SMS go out in the language the booking was made in, even when staff or a driver trigger them. Office emails, booking history and the activity log stay in English.
- Arabic switches the whole layout to right-to-left (`assets/css/main-rtl.css`). If you edit `main.css`, regenerate it with `npx rtlcss assets/css/main.css assets/css/main-rtl.css`.
- **Your own page text:** open a page and use the **Languages** box → *Add translation*. It makes a draft copy to translate in the editor, and visitors who chose that language are sent to it automatically. The Pages list has a Languages column.
- **Home page text you changed:** Appearance → Customize → Taxi Peninsula → *Translated home page text*.
- **Settings:** Customize → Taxi Peninsula → *Languages* turns each language on or off, shows the free interpreter line (TIS National 131 450, National Relay Service 133 677), and sets the **Auslan video** (shown by `[tp_auslan]` on the Accessibility Statement page and linked from the language menu).
- Pages not yet translated show a short note in the visitor's language with the interpreter details.
- Translation files are in `languages/` (`.po` to edit with Poedit or Loco Translate, `.mo`/`.l10n.php` used by WordPress). Vietnamese, Chinese, Italian and Arabic are complete; Greek is partly done (about 80% — untranslated lines show in English). They were written for this theme but **should be checked by a native speaker before launch**, especially anything about fares, NDIS and cancellations.
- Page caching: exclude the `tp_lang` cookie from your cache (or vary on it) so each visitor gets their language.

**Google review invitations**
- After a customer rates a trip in My Account or Manage My Booking, they are invited to leave a Google review, with a one-tap button. An email invitation follows (at most once every 6 months per address). Clicks are recorded in the booking history.
- Bookings → Settings → Google reviews: paste your *Write-a-review link* (Google Business Profile → Ask for reviews) and choose who is invited. The default is **after a 4 or 5 star rating**, as requested. Google's review policy and the ACCC's guidance say businesses should not ask only happy customers, and Google may remove reviews gathered that way, so *After any rating* is the safer choice.

**Activity log** (Bookings → Activity log)
- One record of who did what: bookings created, every status change, driver assignments and declines, each edited field (old → new; notes are logged as "changed" without their text), change requests, payments, ratings, deleted bookings, privacy requests and staff sign-ins (including failed passwords).
- Filter by booking, person, type or date, and export CSV. Each booking's History box links to its full activity. Visible to administrators and booking managers.

**Privacy (Australian Privacy Principles)**
- **Bookings → Privacy:** requests waiting, what the clean-up will remove next, *Run clean-up now*, and *Create the privacy policy page*.
- **Automatic clean-up** (daily, Bookings → Settings → Privacy): passenger details are removed from trips older than 24 months (names, phone numbers, emails, street addresses, notes and comments). References, dates, suburbs, fares and payments are kept, and account invoice details are kept for 5 years for tax. Messages and driver applications are deleted after 12 months, activity log entries after 24 months. All the periods can be changed.
- **Requests:** WordPress's Tools → Export / Erase Personal Data now include bookings, saved passengers, clients, addresses and messages. Upcoming trips are kept until they are over.
- **Customers:** My Account → My details → *Your privacy* lets customers **download their data** and **delete their account** themselves. Anyone can ask for a copy or deletion with the form on the privacy policy page (`[tp_privacy_request]`); they confirm by email first.
- **Privacy policy:** `[tp_privacy_policy]` writes a policy following the APPs from your settings: what you collect (including health and mobility information as sensitive information), who sees it, overseas services (SMS, Stripe, Google, spam protection, plus any you list), retention periods, access and correction, and complaints to the OAIC. Fill in *Where your website and email are hosted* and have it reviewed by your adviser before publishing.
- The booking form's consent now covers using mobility and health details to arrange the trip, links to the privacy policy, and records when consent was given.

### Home page redesign (v2.0)

A shorter home page that gets people booking faster: about half the length on phones.

- **New order:** hero → audience shortcuts → trust numbers → services → how it works → vehicles → animated trip scenes → reviews → areas map → FAQ → partner logos → call to action. "Who we help", "Why choose us", the full booking form, popular destinations and blog posts are still available but off by default.
- **Section editor:** Appearance → Customize → Taxi Peninsula → *Home page layout*. Drag sections (or use ▲ ▼), tick to show or hide, and **Use the recommended layout** resets to the new order. Sites that had already saved a layout keep it; press that button to switch.
- **Hero:**
  - "Book online" goes straight to the Book a Taxi page
  - trust badges (Accredited drivers, NDIS-friendly, MPTP accepted, 24/7 or 7 days) and your Google star rating sit under the buttons
  - without a hero photo, the animated maxi taxi pick-up now shows beside the text (Design → Hero → *Beside the text*)
- **Quick-book card:** from, to, date, time and wheelchair (none, 1 or 2). *Continue* opens the full form with these filled in and the right vehicle chosen. The "Booking" section can show this card or, if you prefer, the full form (*Home page layout*).
- **Audience shortcuts** under the hero: I'm a passenger, I'm booking for someone, NDIS & aged care, Hospital discharge. Edit the labels and links in *Who we help & shortcuts*.
- **How it works:** three illustrated steps, your reply time (Contact details → *Enquiry reply time*), and Book and Call buttons.
- **Services:** a tidy 3 × 2 grid with *Learn more*. Add an optional price hint ("From $45") on each Service under *Price hint*.
- **Vehicles:** up to three published Fleet vehicles with photo, wheelchair and seat numbers, ramp or hoist, and a new *Largest wheelchair it takes* field.
- **Reviews:** a featured quote (Home page headings & text → *Featured quote*, or your first testimonial) above a sideways-scrolling slider with previous and next buttons.
- **Areas map:** a built-in map of the bay and the peninsula with your service suburbs marked. No map service or API key is needed and nothing loads from elsewhere. Suburbs the map doesn't know are left off; the suburb checker sits beside it.
- **Partners** show as a slim greyscale logo strip.
- **Phones:** tighter spacing, services as a compact two-column list, vehicles swipe sideways, and the map replaces the long suburb list. The bottom bar keeps Book and Call one tap away.

### Fixes, design and WhatsApp (v2.1)

**Fixes**
- Pick-up and return times accept any minute (9:53 used to be rejected).
- **Menu on computers:**
  - the full menu shows from 1025px wide
  - the language button is a compact globe
  - if the menu still doesn't fit, the Menu button sits at the right and opens a tidy drop-down panel
  - the header slims down as you scroll
- **Drop-downs:** each parent item has a ▾ button that opens its sub-menu by click, tap or keyboard (Escape closes). On phones, sub-items fold away under that button.
- **Address boxes without a Google Maps key** no longer suggest bare suburb names (which replaced the street address). A "We cover …" hint shows instead.
- **Booking checks**, in the browser and on the server:
  - the return pick-up must be after the first pick-up
  - passengers and wheelchairs must fit the chosen vehicle (filter `tp_vehicle_capacity` to match your fleet)
  - online bookings can be made up to 12 months ahead (Bookings → Settings → Recurring trips)
- **Removed display options:** the text-size, high-contrast, dark-mode and pause-animation buttons are no longer shown to visitors. The site follows each visitor's own device settings instead.
- **Smaller fixes:**
  - choosing "No wheelchair" in the quick-book card now explains why a standard taxi was picked
  - "Hospital discharge" goes to booking
  - videos load nothing from YouTube until play is pressed
  - the vehicle scroller works with a keyboard
  - heading levels are fixed on the Services and Blog pages
  - the top bar is marked as a page area for screen readers
  - the top bar is hidden on phones
- **Saved passengers:** choosing a saved passenger or client on the booking form fills in their details again. A script name clash had broken this in v1.9–v2.0.

**Design**
- **Services mega-menu:** the Services menu item opens every service with its icon and a short description, plus a Book now card. Add the CSS class `mega` to use it on another top-level item.
- **Shorter hero on computers:** the quick-book card sits beside the text (Design → Hero → *Beside the text*).
- **Photos:** services use their featured image when one is set. Fleet, drivers and the hero already take photos. Use your own photos of your vehicles, drivers and (with consent) passengers rather than stock images.
- **Service pages:**
  - a "What to expect" timeline
  - a "Typical fares" card
  - questions for that service
  - a booking card in the sidebar
  - each Service has new fields for these
- **Area pages:** the map highlights the suburb, with rough travel times to the city, the airport and nearby hospitals.
- **Meet the drivers** on the home page, with languages spoken and years driving (Users → driver → Public driver profile).
- **Reviews:** one overall rating, with no stars on every card, and a "Read all reviews on Google" button.
- **Booking form:** "I am the passenger — use my account details", and larger boxes and tick boxes.
- **Friendlier 404 and empty search pages**, with Book, Call, search and popular pages.
- **Print:** a print layout for trip confirmations. The booking page and Manage My Booking have a Print button.

**WhatsApp**
- A floating "WhatsApp us" button opens WhatsApp on your number with a message already typed. On the Manage My Booking page the message includes the booking reference.
- Settings: Customize → Taxi Peninsula → Contact details (WhatsApp number, show the button, message).

## Install

1. Zip the `taxi-peninsula` folder and upload it under **Appearance → Themes → Add New → Upload**. Then activate it.
2. **Settings → General:** set the timezone to **Melbourne**.
3. **Settings → Permalinks:** choose "Post name".
4. **Bookings → Setup:** click **Create starter content**.
5. **Settings → Reading:** choose a static front page (for example a page called *Home*), and set a *Blog* page as the posts page.
6. **Appearance → Customize → Taxi Peninsula:** check the contact details, hours, hero text and service areas. Upload your logo under *Site Identity* and choose colours and fonts under *Design*.
7. **Bookings → Settings:** connect SMS, Stripe and spam protection as needed (details below).
8. **Users → Add New:** add drivers with the **Driver** role, their mobile number and usual vehicle; office staff as **Booking Manager**; bookkeepers as **Accounts (finance)**. Customers and coordinators sign up themselves on the My Account page.
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
3. Paste the key in Taxi settings → Settings → Maps, addresses & fare estimates (or use `TP_GOOGLE_MAPS_KEY` in wp-config.php).
4. Enter your fare rates, then tick **Show a fare estimate**.

### Addressfinder (Australian address suggestions)
1. Sign up at addressfinder.com.au and copy your licence key from Account → Credentials.
2. Paste it in Taxi settings → Settings → Maps, addresses & fare estimates, and choose "Addressfinder" or "Both".

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
`[tp_booking_form]` `[tp_booking_lookup]` `[tp_contact_form]` `[tp_faq]` `[tp_faq topic="booking"]` `[tp_fleet]` `[tp_fleet limit="2"]` `[tp_ndis]` `[tp_testimonials]` `[tp_driver_jobs]` `[tp_services]` `[tp_areas]` `[tp_contact_details]` `[tp_map]` `[tp_map q="Frankston VIC"]` `[tp_stats]` `[tp_suburb_checker]` `[tp_destinations]` `[tp_drivers]` `[tp_video]` `[tp_partners]` `[tp_driver_apply]` `[tp_contact_chooser]` `[tp_faq featured="yes" limit="4" search="no"]` `[tp_quote_request]` `[tp_lost_property]` `[tp_newsletter]` `[tp_callback]` `[tp_places]` `[tp_places kind="to"]` `[tp_pay_options]` `[tp_cancellation_policy]`

## Before going live
- **Complaints page:** replace the [CONFIRM] response times and add the regulators' current contact details, then publish it. The chooser and the auto-reply link to it once it's published.
- **Opening hours:** if you're not truly 24/7, untick "Open 24 hours" and enter real hours. The live status, contact page and Google data all use them.
- **Google reviews:** Google requires its logo next to reviews shown without a map. Upload the official logo from Google's brand resources and paste its URL in Bookings → Settings → Google reviews.
- **Driver profiles and partner logos:** only tick "show" once you have the person's or organisation's permission.
- **Terms and Accessibility pages:** replace every **[CONFIRM]** note with your real policy, have the terms checked, then publish. Customers only have to accept the terms once that page is published.
- **Fare estimates:** switch them on only when the rates match your real fares.
- **Check the wording against your real service.** This covers the starter FAQs and service text, and home page claims such as "24 hours", "accredited drivers" and "ramp & hoist vehicles".
- **Add local detail to suburb pages** (nearby hospitals, landmarks). Pages that only use the default text are thin content for Google.
- **Place pages and glossary terms:** check every [CONFIRM] detail on site (entrances, drop-off points, MPTP subsidy amounts) before publishing.
- **BPAY:** you need a biller code from your bank before the BPAY box appears. Check whether your bank uses the standard check digit (MOD10V01) for reference numbers; if not, choose "No check digit".
- **Cancellation fees:** make the fees in Taxi settings → Settings → Recurring trips match your published terms.
- **Security hardening** is now on by default. If you use Jetpack or the WordPress mobile app, untick "Block XML-RPC".
- **Exclude the booking, Manage My Booking and Contact pages from page caching.** Their forms use security tokens that go stale when cached.
