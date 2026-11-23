# RentGrid

RentGrid uses Laravel 13 for the rental API and Next.js 16 for the web app. The foundation includes PostgreSQL-backed sessions and fleet records, plus the rental calendar rules used to determine vehicle availability.

## Local setup

Use PHP 8.4 with `pdo_pgsql`, Composer, PostgreSQL 17, and Node.js 24 with npm. In PostgreSQL, create two empty databases named `rentgrid` and `rentgrid_test`. Give the development and test roles ownership of their respective databases so Laravel can run migrations.

From the repository root, open a terminal for Laravel.

```powershell
Set-Location backend
Copy-Item .env.example .env
Copy-Item .env.testing.example .env.testing
composer install
```

Set the PostgreSQL host, port, username, and password in each environment file. PHPUnit uses the fixed `rentgrid_test` database name so its migrations target the test database. Set `BUSINESS_TIMEZONE` in `backend/.env` to the pickup location's IANA time zone. The local example uses `Asia/Manila`.

```powershell
php artisan key:generate
php artisan key:generate --env=testing
php artisan migrate
php artisan test
php artisan serve --host=127.0.0.1 --port=8000
```

For an optional synthetic fleet, run `php artisan db:seed --class=FleetSeeder` before starting the development server. It creates 100 published vehicles across five models, with labels such as `Vios 101 · Silver` and `Innova 501 · Silver`. Rerunning the seeder fills in missing fleet vehicles and updates labels from the original seeder on the existing records.

In a second terminal, start from the repository root.

```powershell
Set-Location frontend
Copy-Item .env.local.example .env.local
npm ci
npm run lint
npm run build
npm run dev
```

`LARAVEL_API_ORIGIN` is the server-side upstream for the Next.js `/api/*` rewrite. Set it before building or starting Next.js. Visiting [the session endpoint through Next.js](http://localhost:3000/api/session) should return `{"ready":true}` and set `rentgrid_session` and `XSRF-TOKEN` cookies for localhost.

If the session endpoint reports `could not find driver`, run `php --ini` in the Laravel terminal and enable `pdo_pgsql` in that PHP configuration, then restart Laravel. Apply pending migrations with `php artisan migrate` before provisioning staff accounts.

Browser code can call `apiRequest("/api/session")` from `frontend/lib/api.ts` before sending a session-bound mutation. The client sends cookies to the same origin, requests JSON, and copies the decoded `XSRF-TOKEN` into the mutation header. In production, serve browser requests over HTTPS on the Next.js origin, set Laravel's `APP_URL` to that public origin, and enable `SESSION_SECURE_COOKIE`.

## Rental calendar

Rentals and staff blocks occupy all calendar days in their range, including both endpoints. A same-day rental occupies one day. The pickup location's local date determines whether a pickup is in the past. A published vehicle is available when it has no overlapping confirmed reservation or staff block.

## Customer booking

Search from the home page and select a vehicle to review the dates, pickup location, and amount due. Configure the booking terms in `backend/.env`. The following values are synthetic examples for local testing.

```dotenv
RENTAL_PICKUP_LOCATION="Demo Rental Desk (synthetic fixture)"
RENTAL_CURRENCY=PHP
RENTAL_DAILY_RATE_MINOR=150000
RENTAL_FEE_MINOR=10000
RENTAL_DEPOSIT_MINOR=50000
```

Set an ISO 4217 currency code and enter amounts in its smallest unit, such as centavos for PHP. The quote charges the daily rate for each day from pickup through return, plus any one-time fee and deposit. The example charges PHP 1,500 per day, a PHP 100 fee, and a PHP 500 deposit. Omit the fee and deposit settings when they are zero. Quotes require a configured pickup location, currency, and daily rate.

`RENTAL_MAX_DAYS` defaults to 30 inclusive rental days. `RENTAL_MAX_ADVANCE_DAYS` defaults to pickup up to 180 days after the current date in the business time zone. Search, quotes, and new bookings enforce both settings. Booking submissions allow five attempts per minute per session and 30 per client IP.

`GET /api/vehicles/availability` accepts `pickup_date` and `return_date` in `YYYY-MM-DD` format. After bootstrapping the session with `GET /api/session`, `POST /api/booking-quotes` accepts those dates and `vehicle_id`. `POST /api/bookings` accepts the returned `quote_token` and the guest's `name`, `email`, and `phone`. Both POST requests use the same session and CSRF cookie. The quote records the selected vehicle, its displayed details, and the rental terms. A new booking returns HTTP 201. Sending the same quote token again from the same session returns HTTP 200 with the existing reference and current status. Conflicts return `vehicle_unavailable`, `vehicle_changed`, or `price_changed`. Changes to the vehicle details or rental terms require the guest to review an updated quote.

The browser opens `/book/confirmation/{reference}` after submission. It reads the saved booking from `GET /api/bookings/{reference}`, which requires the original guest session and returns a private, non-cacheable response. The confirmation shows the saved vehicle label, make, and model. For existing guest bookings, the migration saves the vehicle details available at migration time. Configure `RENTAL_PICKUP_INSTRUCTIONS` and `RENTAL_CANCELLATION_TERMS` to include approved business wording. RentGrid saves that wording at booking time. With no configured cancellation terms, the confirmation directs guests to contact the business to request changes or cancellations.

If the confirmation response is interrupted, search and checkout prompt the guest to recover the previous booking before confirming a replacement. The signed quote is saved in the same tab for 30 minutes. `POST /api/bookings/recover` checks the original guest session and returns the saved reference if the booking exists. If the lookup finds no confirmation, the guest can re-enter their contact details and retry the same attempt or return to search. The app keeps contact details out of tab storage.

Quotes issued before vehicle details were added require a fresh review for new bookings. Retries can still recover an existing booking when the quote identifies its saved attempt.

## Staff access

After running `php artisan migrate`, use the following command from the backend directory on the server to provision a staff administrator.

```powershell
php artisan rentgrid:staff-create admin@example.test "Staff Administrator" --admin
```

The command prompts for the password and stores its hash. Sign in at `/staff` on the Next.js origin, choose **Create staff account**, and enter the staff member’s name, email address, and initial password. They can sign in at `/staff` with those credentials. The command also provisions regular staff when run without `--admin`.

Staff administrators can create regular staff accounts through the dashboard. The `POST /api/staff/accounts` endpoint requires the staff creation permission and CSRF protection. Use **Sign out** on the staff dashboard to end the session.

Choose **Manage fleet** to add or edit an individual vehicle. New vehicles start withdrawn. Publish a listing to open it to bookings, or withdraw it to remove it from customer search and close it to new bookings. A vehicle’s availability calendar shows current and upcoming blocks and confirmed bookings. Choose **Show past blocks** to load ended blocks. Follow a booking reference to look up or cancel the reservation. A new block starts today or later in the business time zone and includes both dates. The calendar rejects blocks that overlap a confirmed booking.

Vehicle edits include the originally loaded `display_label`, `make`, and `model` under `original_details`. If another staff member saved different details, the edit returns HTTP 409 with `vehicle_details_conflict`. Reload the vehicle details before applying the edit again.

Choose **Find a reservation** and enter the reference shown on the guest’s confirmation or the calendar. Lookup accepts either letter case. The migration assigns references to older reservations that had none. Check the vehicle, dates, and guest contact details before selecting **Cancel reservation**. Once you confirm the cancellation, staff can still look up the cancelled record, and customers can book those dates if the vehicle is otherwise available.

`GET /api/staff/reservations/{reference}` and `POST /api/staff/reservations/{reference}/cancel` require staff access. Repeating a cancellation returns the same cancelled reservation. The original guest’s confirmation and booking retry also show the current status.

Laravel handles `POST /api/staff/sign-in` and `POST /api/staff/sign-out` with its session guard and CSRF protection. Sign-in allows five attempts per minute for a given email and IP and thirty per IP. The staff page and `GET /api/staff/session` require an authenticated account that passes the `access-staff` gate.

In production, put a trusted ingress in front of Next.js and restrict Next.js to that ingress. Keep Laravel private to its upstream proxy. Have the ingress replace `X-Forwarded-For` with the verified client IP. For example, an Nginx ingress that directly receives client connections can use `proxy_set_header X-Forwarded-For $remote_addr;`. The Next.js rewrite forwards that header to Laravel.

Set `TRUSTED_PROXY_IPS` in the Laravel environment to the comma-separated IPv4 or IPv6 addresses that connect directly to Laravel. With the Next.js rewrite, use the Next.js server addresses as seen by Laravel. Rebuild Laravel's configuration cache after changing the setting. When the list is empty, Laravel uses the connection IP.

## Browser regression tests

Use the dedicated `rentgrid_test` PostgreSQL database. Run `php artisan migrate --env=testing` from `backend`, then start `php artisan serve --env=testing` with the sample booking terms above and `SESSION_DRIVER=database`. Point the running Next.js app at that Laravel server. Run these commands from `frontend`.

```powershell
npm ci
npx playwright install chromium
npm run test:e2e
```

The tests default to `http://localhost:3000`. Set `RENTGRID_E2E_URL` if the local frontend uses a different port. The booking recovery test cleans up its vehicles and reservation, and the staff onboarding test cleans up its test accounts and signed-in sessions.
