# Booking integration changes

## Implemented

- Completed `BookingRepo` while retaining the existing queued `CrmSyncJob` flow.
- Updated the booking payload to the confirmed CRM fields: `name`, `email`, and `city`.
- Updated the CRM job to insert into `crm.crm_example` and write `created_at` plus the requested `update_at` column.
- Added a dedicated Laravel database connection named `crm`, configurable with `CRM_DB_*` variables.
- Updated the form reader to read `crm.forms` using `id`, `section`, `form_name`, and `forms`.
- Kept the three existing section keys: `MBA_IN_INDIA`, `UG_COLLEGE_TEST`, and `STUDY_ABROAD`.
- Registered booking and form-reader routes with the existing API routing setup.
- Replaced `login.html` with `booking.html`; old `/login.html` requests redirect to `/booking.html`.
- Updated all internal login links to booking links and added section-specific booking links on the three section overview pages.
- The booking page fetches current section HTML without browser caching, adds OTP verification controls, and submits the three CRM fields to the booking endpoint.

## Database contract used

Database: `crm`

Table `crm_example`: `id`, `name`, `email`, `city`, `created_at`, `update_at`.

Table `forms`: `id`, `section`, `form_name`, `forms`.

`form_name` is the column identifier used for the display name; `forms` contains the raw HTML. The CRM-owned tables are not created by Laravel migrations.

## Stored HTML requirements

Each row in `forms.forms` should contain a complete HTML `<form>` with controls named exactly `name`, `email`, and `city`. The frontend adds the OTP input and send button. Only those three values are stored in `crm_example`; additional fields in a stored HTML form are rendered but are not persisted by the current CRM schema.

Because `forms` is deliberately rendered as HTML, only trusted markup should be written into that column by the Python application.

## Runtime setup

- Configure `CRM_DB_HOST`, `CRM_DB_PORT`, `CRM_DB_DATABASE=crm`, `CRM_DB_USERNAME`, and `CRM_DB_PASSWORD` in `Backend/.env`.
- Enable PHP `pdo_mysql`.
- Configure real SMTP settings in Laravel if OTP emails must be delivered. The supplied example uses `MAIL_MAILER=log`, which writes mail output to logs instead of sending email.
- Run `php artisan queue:work` to process `CrmSyncJob` and insert confirmed bookings. The configured queue backend and its jobs table must be available.

## Validation performed

- All modified PHP files passed `php -l`.
- `node --check erudite_homepage/script.js` passed.
- `php artisan route:list --path=api` listed booking OTP, booking confirmation, and form-reader endpoints.
- All 24 frontend HTML pages parsed, and all local links/assets resolved.
- ZIP archive integrity is checked before delivery.

## Validation not possible in this environment

A real CRM database read/write test could not run because this runtime lacks the PHP `pdo_mysql` extension. `php artisan test` could not run because PHP extensions `dom`, `mbstring`, and `xmlwriter` are absent. The code is linted and routes are registered, but database connectivity, SMTP delivery, and background queue execution still need verification in the deployment environment.
