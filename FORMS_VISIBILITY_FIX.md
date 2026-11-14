# Forms visibility fix

## Intended behavior

- `GET /` serves only `erudite_homepage/index.html`.
- The homepage must not contain the database-form mount (`#booking-forms`) or request `/api/forms/{section}`.
- Database-backed HTML forms are loaded only on `booking.html`, after a visitor selects one of the three supported sections.

## Changes made

1. `Backend/routes/web.php` now sends explicit no-store/no-cache headers for static frontend responses, preventing an older cached homepage from continuing to show stale markup.
2. `erudite_homepage/script.js` now requires both a `/booking.html` URL and the booking form mount before it can fetch database forms. The shared script is loaded on all pages, so this extra page check prevents the booking loader from running on the homepage or other pages.
3. Added `Backend/tests/Feature/HomepageDoesNotRenderDatabaseFormsTest.php` to verify that `/` has no booking form mount and `/booking.html` has the expected booking mount and section selector.

## Validation performed

- PHP syntax checks passed for the changed route and regression test.
- JavaScript syntax check passed.
- Laravel route listing succeeded and confirmed the homepage, booking page, and form API routes.
- Local PHP HTTP smoke test: `/` returned HTTP 200 and contained neither `#booking-forms` nor `/api/forms/`; `/booking.html` contained both `#booking-forms` and `#booking-section`.
- The PHPUnit test runner could not execute in this environment because the PHP CLI lacks the `dom`, `mbstring`, and `xmlwriter` extensions.

## Important distinction

The form API remains available because the booking page needs to retrieve and display the selected section's forms. This means the HTML is public to visitors who use the booking page; it is not confidential content. The change prevents those forms from being injected into the homepage.
