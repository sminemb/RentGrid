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

For an optional synthetic fleet, run `php artisan db:seed --class=FleetSeeder` before starting the development server. It adds two published vehicles with the same make and model and distinct display labels.

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

`GET /api/vehicles/availability` accepts `pickup_date` and `return_date` in `YYYY-MM-DD` format. After bootstrapping the session with `GET /api/session`, `POST /api/booking-quotes` accepts those dates and `vehicle_id`. `POST /api/bookings` accepts the returned `quote_token` and the guest's `name`, `email`, and `phone`. Both POST requests use the same session and CSRF cookie. The quote fixes the selection for that booking attempt. A new booking returns HTTP 201. Sending the same quote token again from the same session returns HTTP 200 with the existing reference and current status. Conflicts return `vehicle_unavailable` or `price_changed`.

The browser opens `/book/confirmation/{reference}` after submission. It reads the saved booking from `GET /api/bookings/{reference}`, which requires the original guest session and returns a private, non-cacheable response. Configure `RENTAL_PICKUP_INSTRUCTIONS` and `RENTAL_CANCELLATION_TERMS` to include approved business wording. RentGrid saves that wording at booking time. With no configured cancellation terms, the confirmation directs guests to contact the business to request changes or cancellations.

Quotes issued before booking retries were added require a fresh review.

## Staff access

After running the migrations, use the following command from the backend directory on the server to provision each staff account.

```powershell
php artisan rentgrid:staff-create staff@example.test "Staff Member"
```

The command prompts for the password and stores its hash. Accounts created by other means start without staff access. Open `/staff` on the Next.js origin to sign in and use the **Sign out** button there to end the session.

Laravel handles `POST /api/staff/sign-in` and `POST /api/staff/sign-out` with its session guard and CSRF protection. Sign-in allows five attempts per minute for a given email and IP and thirty per IP. The staff page and `GET /api/staff/session` require an authenticated account that passes the `access-staff` gate.
