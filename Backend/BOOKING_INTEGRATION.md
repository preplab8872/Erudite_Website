# Booking and shared forms integration

## Database connection

Laravel uses a dedicated MySQL connection named `crm`. Configure these variables in `Backend/.env`:

```dotenv
CRM_DB_HOST=127.0.0.1
CRM_DB_PORT=3306
CRM_DB_DATABASE=crm
CRM_DB_USERNAME=your_mysql_user
CRM_DB_PASSWORD=your_mysql_password
```

The application expects these existing tables in database `crm`:

### `crm_example`

- `id` — primary key, auto-increment
- `name`
- `email`
- `city`
- `created_at`
- `update_at` — note the singular `update` spelling; this is used as supplied

### `forms`

- `id` — primary key
- `section` — one of `MBA_IN_INDIA`, `UG_COLLEGE_TEST`, `STUDY_ABROAD`
- `form_name` — display name of the form
- `forms` — HTML markup to display

The app does not create or migrate these CRM-owned tables.

## Form HTML contract

The Python application remains responsible for adding, replacing, renaming, and removing rows in `forms`. Laravel only reads the rows for the selected section and returns the stored HTML.

Each stored HTML record must contain a `<form>` and controls whose `name` attributes are exactly `name`, `email`, and `city`. The booking page adds an OTP field and a **Send OTP** control to each returned form. Extra HTML controls can be rendered, but the current CRM schema only stores the three values `name`, `email`, and `city`.

Only trusted HTML should be inserted into `forms.forms`, because Laravel deliberately renders that column as HTML rather than escaping it.

## Endpoints

- `GET /api/forms/MBA_IN_INDIA`
- `GET /api/forms/UG_COLLEGE_TEST`
- `GET /api/forms/STUDY_ABROAD`
- `POST /api/booking/otp/send` with `recipient`
- `POST /api/booking/confirm` with `name`, `email`, `city`, and `otp`

The booking page is `erudite_homepage/booking.html`. The former `/login.html` path redirects to `/booking.html`.

## Runtime requirements

1. Enable PHP's `pdo_mysql` extension.
2. Set the `CRM_DB_*` variables.
3. Configure Laravel SMTP settings; the example environment uses the `log` mailer and will not send real email.
4. Run `php artisan queue:work` so `CrmSyncJob` can insert confirmed bookings into `crm.crm_example`.
5. Ensure the application's configured queue connection is available and its jobs table has been migrated if using the database queue driver.
