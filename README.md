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

## Install

1. Zip the `taxi-peninsula` folder and upload it under **Appearance → Themes → Add New → Upload**. Then activate it.
2. **Settings → General:** set the timezone to **Melbourne**.
3. **Settings → Permalinks:** choose "Post name".
4. **Bookings → Setup:** click **Create starter content**.
5. **Settings → Reading:** choose a static front page (for example a page called *Home*), and set a *Blog* page as the posts page.
6. **Appearance → Customize → Taxi Peninsula:** check the contact details, hours, hero text and service areas.
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

### Keys in wp-config.php (optional)
Any setting can be fixed in `wp-config.php` instead of the database. For example:

```php
define( 'TP_STRIPE_SECRET_KEY', 'sk_live_...' );
define( 'TP_CLICKSEND_API_KEY', '...' );
```

## Shortcodes
`[tp_booking_form]` `[tp_booking_lookup]` `[tp_contact_form]` `[tp_faq]` `[tp_faq topic="booking"]` `[tp_fleet]` `[tp_ndis]` `[tp_testimonials]` `[tp_driver_jobs]`

## Before going live
- **Check the wording against your real service.** This covers the starter FAQs and service text, and home page claims such as "24 hours", "accredited drivers" and "ramp & hoist vehicles".
- **Add local detail to suburb pages** (nearby hospitals, landmarks). Pages that only use the default text are thin content for Google.
- **Exclude the booking, Manage My Booking and Contact pages from page caching.** Their forms use security tokens that go stale when cached.
