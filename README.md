# Taxi Peninsula — WordPress theme

A WordPress theme for a Melbourne wheelchair taxi service. It includes online bookings, a bookings manager in wp-admin, and a blog.

- **Phone:** 0468 323 211 · **Email:** support@taxipeninsula.com.au
- **Theme folder:** [`taxi-peninsula/`](taxi-peninsula)

## Features

**Website**
- Home page with a hero section, "why choose us", the booking form, services, service areas, latest blog posts and a call-to-action band.
- A **Book a Taxi** page template. The same form is also available anywhere through the `[tp_booking_form]` shortcode.
- The booking form collects:
  - pick-up and drop-off addresses (with service-area suggestions)
  - date and time, and an optional return trip
  - vehicle type (WAT, Maxi WAT or sedan)
  - mobility aid, passengers and wheelchairs
  - MPTP membership
  - notes and contact details
- **Dynamic type:** the type and spacing scale is fluid (`clamp()`/`rem`). It follows the viewport and the visitor's browser font size.
- **Accessibility:**
  - text-size buttons (A− A A+ A++) and a high-contrast toggle, remembered per visitor
  - the Atkinson Hyperlegible font
  - a skip link, visible focus styles and large tap targets
  - error messages linked to their fields and announced to screen readers
  - support for reduced motion
  - a "Call now" button on mobile
- A blog with sidebar, search, categories, tags and comments.

**Backend (wp-admin)**
- **Bookings** menu with a pending-count badge.
  - The list shows pick-up time, route, contact, vehicle and a status badge.
  - Status links across the top: Pending, Confirmed, Driver assigned, Completed, Cancelled.
  - Filter by pick-up date (Today, Tomorrow, Upcoming, Past, or a date range), search, and sort by pick-up time or status.
  - Bulk "Mark as …" actions.
  - **Export CSV** exports whatever the current filter shows.
- Booking edit screen:
  - all trip fields are editable
  - internal notes
  - status change, optionally emailing the customer
  - a status history log
- **Add booking** lets staff enter phone bookings by hand.
- A **Taxi bookings** dashboard widget shows counts and the next pick-ups.
- A **Booking Manager** user role for dispatch staff. It can manage bookings only, with no access to posts or settings.
- Emails:
  - a new-booking alert to the office, with Reply-To set to the customer
  - a confirmation to the customer
  - optional status-update emails to the customer
- Posting blogs uses the standard WordPress editor (Posts → Add New).

**Security**
- Nonces on every form, a honeypot field and per-IP rate limiting on public bookings.
- Capability checks on every admin action.
- All input is sanitised and all output escaped.
- The CSV export guards against formula injection.
- Bookings are stored as a private post type. They are never publicly viewable.

## Install

1. Zip the `taxi-peninsula` folder, or copy it to `wp-content/themes/`.
2. Go to **Appearance → Themes** and activate **Taxi Peninsula**.
3. Go to **Settings → General** and set the timezone to **Melbourne**. Booking times and "minimum notice" use this timezone.
4. Create the pages:
   - **Home** — a normal page.
   - **Blog** — an empty page.
   - **Book a Taxi** — choose the *Book a Taxi* template under Page Attributes.
5. Under **Settings → Reading**, choose "A static page". Set Homepage to *Home* and Posts page to *Blog*.
6. Under **Appearance → Menus**, build a menu and assign it to *Primary menu*. Optionally add a *Footer menu* too.
7. Under **Appearance → Customize → Taxi Peninsula**, check:
   - contact details and hours
   - hero text and photo
   - service areas
   - the booking alert email
   - minimum notice
   - the MPTP question and customer emails
8. Set up email delivery. Many hosts don't deliver PHP `mail()` reliably, so install an SMTP plugin (for example WP Mail SMTP).
9. Add staff under **Users → Add New** and give them the **Booking Manager** role.

## Notes

- The home page copy includes service claims such as "24 hours", "accredited drivers" and "ramp & hoist vehicles". Review it and adjust it in `front-page.php` and the Customizer so it matches the real service.
- If you use a page-caching plugin, exclude the booking page from the cache. The form nonce must stay fresh.
- The theme needs WordPress 6.2+ and PHP 7.4+. It was tested on WordPress 7.1 and PHP 8.4.
