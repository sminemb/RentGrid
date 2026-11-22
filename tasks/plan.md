# RentGrid V1 Implementation Plan

The [product specification](../SPEC.md) defines a customer booking flow for a single rental business. Build a complete path from date search to confirmation for an exact vehicle, then give staff the tools to maintain the fleet, block dates, and cancel bookings. The business collects payment at pickup. Track implementation in the [task breakdown](todo.md).

## Stack and structure

- **Backend.** Use Laravel 13, PHP 8.4, Composer, and PostgreSQL 17 through `pdo_pgsql`. Scaffold Laravel in `backend/` and commit its Composer lockfile. Put Eloquent models in `backend/app/Models/`, booking and availability services in `backend/app/Services/`, HTTP input handling in `backend/app/Http/`, and staff policies in `backend/app/Policies/`. Keep migrations and factories in `backend/database/` and PHPUnit tests in `backend/tests/`. [Laravel release notes](https://laravel.com/docs/13.x/releases#support-policy) · [Laravel database configuration](https://laravel.com/docs/13.x/database#configuration)
- **Frontend.** Use Next.js 16 with the App Router, TypeScript, Node.js 24, and npm for customer and staff screens. Scaffold it in `frontend/` and commit its npm lockfile. Put pages under `frontend/app/` and the browser API client in `frontend/lib/`. Next.js handles navigation, forms, and feedback. Laravel owns validation, pricing, availability, authorization, and writes to PostgreSQL. [Next.js installation](https://nextjs.org/docs/app/getting-started/installation)
- **Application code.** Give each class one responsibility and pass required collaborators through its constructor. Controllers and form requests delegate booking and fleet changes to the classes that own those operations. Use named domain methods to preserve valid state and keep stateless helpers as functions, following `AGENTS.md`.
- **Configuration.** Keep the Laravel application key, database credentials, and business time zone in backend configuration. Set the Laravel upstream address in the frontend's server configuration. Use synthetic fleet and booking fixtures for development and a dedicated PostgreSQL database for tests.

## API and sessions

- Route browser requests under `/api/*` to Laravel on the same public origin as the Next.js app. Use a rewrite in `frontend/next.config.ts` for local development and keep the same public URL shape in production. Register those JSON endpoints in `backend/routes/web.php` so Laravel's session and CSRF middleware apply. [Next.js rewrites to external URLs](https://nextjs.org/docs/app/api-reference/config/next-config-js/rewrites#rewriting-to-an-external-url) · [Laravel CSRF protection](https://laravel.com/docs/13.x/csrf)
- Bootstrap the Laravel session and CSRF cookies with a same-origin `GET /api/session` request. Have the frontend client send `Accept: application/json` and use the URL-decoded `XSRF-TOKEN` cookie value as `X-XSRF-TOKEN` on mutations. Mark guest confirmation and other session-dependent responses private and non-cacheable. Verify cookie handling through the proxy in a browser.
- Define request and response shapes as each feature is built. The customer API covers date search, quote review, booking submission, and guest-session confirmation. Staff APIs cover sign-in, fleet listings, availability blocks, and reservation lookup and cancellation. Use JSON field errors for invalid input and distinct conflict codes for stale availability and price changes so Next.js can show the right recovery path.

## Booking and availability decisions

- Use a persistent identifier for each vehicle and a customer-facing label that distinguishes similar vehicles. Store confirmed and cancelled reservations so staff can find either state by booking reference. Use one shared overlap rule for reservations and staff blocks. Under the confirmed calendar-day policy, two ranges overlap if each starts on or before the other ends. Evaluate the current date in the business time zone.
- Serialize writes that affect availability for a particular vehicle. Inside `DB::transaction()`, lock the vehicle row by ID using `lockForUpdate()`. Read its current publication state and check date conflicts after taking the lock, then write the change. Booking, availability blocks, cancellations, and publication changes use the same vehicle lock. Under PostgreSQL Read Committed, the post-lock queries see changes committed by the previous writer. Return the confirmation after the reservation commits. [Laravel pessimistic locking](https://laravel.com/docs/13.x/queries#pessimistic-locking) · [PostgreSQL transaction isolation](https://www.postgresql.org/docs/17/transaction-iso.html#XACT-READ-COMMITTED)
- Issue a booking attempt key tied to the guest session and the reviewed vehicle, dates, and price. Enforce uniqueness in the database. A replay from the same session returns the original reference and current status, including after cancellation. Handle a uniqueness race by looking up the committed booking. Validate booking details on the server and require a new review if the price has changed. Store the agreed amount with the booking so later rate edits do not change the amount shown on the confirmation.
- Keep customer confirmation in the booking guest session. Use Laravel's session guard for staff sign-in with server-provisioned accounts. Protect `/staff` with `auth` and a staff-access gate, and authorize fleet and reservation actions with policies. Staff can search by reference and invoke named operations such as cancellation. Route these writes through the same services used by the booking flow. Keep customer contact details out of public search results and application logs. [Laravel authentication](https://laravel.com/docs/13.x/authentication) · [Laravel authorization](https://laravel.com/docs/13.x/authorization)

## Build order

| Phase | Tasks | Outcome |
| --- | --- | --- |
| Foundation | 01–03 | A runnable Next.js app connected to Laravel, with persistent vehicle records and tested date and overlap rules. |
| Customer search and booking core | 04–06 | Customers can search and review a rental. The database can commit a reservation safely under contention. |
| Confirmation and staff access | 07–09 | Customers can submit, recover a confirmation after a retry, and recover from a stale result. Staff access is protected. |
| Staff fleet and calendar | 10–11 | Staff can update vehicle listings and manage unavailable dates. The customer search reflects both. |
| Cancellation and acceptance | 12–13 | Staff can look up and cancel bookings. The complete customer and staff flows are verified against the spec. |

Staff access setup can start after the foundation while customer screens are in progress. Coordinate listing and calendar changes because they share routes and transaction tests.

## Risks and verification

| Risk | Verification |
| --- | --- |
| Stale search results or overlapping writes create conflicting reservations. | Use PostgreSQL transaction tests with separate connections for two customer bookings, booking versus a staff block, and booking versus listing withdrawal. Assert the committed records and final availability. |
| A lost response creates a second reservation or returns a stale confirmation. | Replay the same attempt before and after cancellation. Assert that one reservation exists and the status is current. |
| Calendar boundaries differ between search and submission. | Test one-day rentals, overlapping and adjacent ranges, and the business's current date near midnight. Verify that search, booking, and staff use the same rules. |
| Staff tools expose guest details or bypass the booking rules. | Test guest, non-staff, and unauthorized staff access. Verify policy checks on staff routes and exercise staff actions against the booking and availability services. |
| Session or CSRF cookies fail across the frontend proxy. | Test guest confirmation and staff sign-in, sign-out, and staff actions through the Next.js URL. Verify that direct API calls from a separate session are denied access to protected records. |

After Task 01, follow the [local setup instructions](../README.md#local-setup) to configure both PostgreSQL databases and generate the application keys. Run these commands from the repository root in a backend terminal.

```powershell
Set-Location backend
composer install
php artisan migrate
composer validate
php artisan route:list
php artisan test
php artisan serve --host=127.0.0.1 --port=8000
```

In a second terminal, set `LARAVEL_API_ORIGIN=http://127.0.0.1:8000` in `frontend/.env.local` and run these commands from the repository root.

```powershell
Set-Location frontend
npm ci
npm run lint
npm run build
npm run dev
```

Use PHPUnit through `php artisan test`. Use Laravel's HTTP testing tools for validation, session, authorization, and confirmation checks. Run concurrency tests with committed fixtures and independent PostgreSQL connections outside the transaction wrapper used for ordinary database feature tests. Use a browser for the guest and staff walkthrough through Next.js, including error recovery and cookie handling. [Laravel database testing](https://laravel.com/docs/13.x/database-testing)

## Business decisions still open

- **Pricing.** Task 05 needs the currency, rate calculation, fees and deposits, and the amount payable at pickup. Build review-screen fixtures while these are under discussion.
- **Pickup and cancellations.** Task 08 needs the pickup address and the instructions and cancellation terms to display. Task 12 needs any rules staff must enforce when cancelling. The draft still assumes guest checkout, one location, and an on-screen confirmation.
