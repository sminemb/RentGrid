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

Browser code can call `apiRequest("/api/session")` from `frontend/lib/api.ts` before sending a session-bound mutation. The client sends cookies to the same origin, requests JSON, and copies the decoded `XSRF-TOKEN` into the mutation header. In production, serve browser requests over HTTPS on the Next.js origin, set Laravel's `APP_URL` to that public origin, and enable `SESSION_SECURE_COOKIE`.

## Rental calendar

Rentals and staff blocks occupy all calendar days in their range, including both endpoints. A same-day rental occupies one day. The pickup location's local date determines whether a pickup is in the past. A published vehicle is available when it has no overlapping confirmed reservation or staff block.
