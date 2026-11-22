# RentGrid V1 Task Breakdown

Follow the [implementation plan](plan.md) and [product specification](../SPEC.md). Run `php artisan test` from `backend/` against the dedicated PostgreSQL test database and run `npm run lint` and `npm run build` from `frontend/` at each checkpoint.

## Foundation

### Task 01 — Bootstrap the application

- [x] Scaffold Laravel 13 in `backend/` and Next.js 16 with the App Router and TypeScript in `frontend/`. Commit both lockfiles and document the PostgreSQL and local API rewrite setup. Add the `/api/session` endpoint and a shared browser API client that supports session cookies and CSRF headers.
- **Verify** by installing from the lockfiles, migrating an empty PostgreSQL database, building Next.js, and checking that the session endpoint returns JSON and sets Laravel's cookies through the Next.js URL.
- **Depends on** none. **Scope** M, framework scaffolds and configuration. **Likely paths** `backend/`, `frontend/`, `README.md`.

### Task 02 — Store individual fleet vehicles

- [x] Persist each vehicle's stable ID, public display label, make and model, and publication state. Two otherwise identical vehicles can be entered and distinguished.
- **Verify** with Laravel model tests for vehicle identity and publication state and a synthetic two-vehicle fixture.
- **Depends on** 01. **Scope** M. **Likely paths** `backend/app/Models/Vehicle.php`, `backend/database/`, `backend/tests/Feature/FleetTest.php`.

### Task 03 — Model the rental calendar

- [x] Implement the confirmed date policy in a shared availability module. Persist reservation states and staff availability blocks with their vehicle and date range. A published vehicle is available for a range only when no confirmed reservation or block overlaps it.
- **Verify** with tests for same-day rentals, the return-day boundary, reversed and past dates, business-local today, and overlap against confirmed, cancelled, and blocked periods.
- **Depends on** 02 and the rental timing decision. **Scope** M. **Likely paths** `backend/app/Models/`, `backend/database/migrations/`, `backend/app/Services/AvailabilityService.php`, `backend/tests/Feature/AvailabilityTest.php`.

**Checkpoint after Task 03**

- [x] An empty PostgreSQL database migrates successfully, and a Laravel JSON response reaches the browser through Next.js. The date-policy tests pass and the fixtures distinguish two vehicles of the same make and model.

## Customer search and booking core

### Task 04 — Find available vehicles

- [x] Build a Next.js date search and a Laravel JSON availability endpoint. Show field errors and exact-vehicle results from the server. Keep submitted dates when results are empty so customers can revise the search.
- **Verify** by exercising valid, invalid, empty, and mixed-availability searches through the API and the Next.js page.
- **Depends on** 03. **Scope** M. **Likely paths** `backend/routes/web.php`, `backend/app/Http/`, `backend/tests/Feature/VehicleSearchTest.php`, `frontend/app/`, `frontend/lib/api.ts`.

### Task 05 — Review a rental before submitting

- [x] Build a Next.js review page for the selected vehicle, dates, pickup location, and amount due. Use the shared browser client for the session-bound quote request. Calculate the quote and define validation of the guest's name, email address, and phone number in Laravel.
- **Verify** the quote and contact-field validation in Laravel, and check the displayed quote and session cookies through the Next.js URL. Include fees and deposits when the business rules require them.
- **Depends on** 04 and the pricing and pickup location decisions. **Scope** M. **Likely paths** `backend/routes/web.php`, `backend/app/Http/`, `backend/app/Services/`, `frontend/app/book/`, `backend/tests/Feature/`.

### Task 06 — Save reservations atomically

- [x] Use Laravel's `DB::transaction()` and `lockForUpdate()` on the selected vehicle. Recheck publication, date availability, and the reviewed price before saving the confirmed booking with its unique reference and agreed amount. Return a conflict if a competing reservation wins or the price has changed.
- **Verify** with separate PostgreSQL connections submitting overlapping requests for the same vehicle. Assert one confirmation and one committed reservation. Check that two independent vehicles can both be booked.
- **Depends on** 03 and 05. **Scope** M. **Likely paths** `backend/app/Services/BookingService.php`, `backend/app/Models/Reservation.php`, `backend/database/migrations/`, `backend/tests/Feature/BookingConcurrencyTest.php`.

**Checkpoint after Task 06**

- [x] A customer can search and review the selected rental. The competing-booking test proves exactly one reservation commits for overlapping dates on one vehicle.

## Confirmation and staff access

### Task 07 — Make booking submissions safe to retry

- [x] Persist a unique attempt key tied to the guest's reviewed booking. A retry from the same session returns the original reference and current status. A mismatched session or changed selection cannot reuse the attempt.
- **Verify** with simultaneous duplicate requests, a replay after losing the response, a replay after cancellation, and a request using an attempt from another guest session.
- **Depends on** 06. **Scope** M. **Likely paths** `backend/app/Services/BookingService.php`, `backend/app/Models/Reservation.php`, `backend/database/migrations/`, `backend/tests/Feature/BookingRetryTest.php`.

### Task 08 — Complete the customer booking flow

- [x] Connect the Next.js review form to Laravel's booking and confirmation endpoints. After commit, show the reference, exact vehicle, dates, pickup details, and amount due. Handle stale availability and changed prices while preserving the selected dates.
- **Verify** with API feature tests and a browser walkthrough through Next.js covering confirmed booking, retry, changed quote, and a stale search result. Check confirmation access from a different guest session.
- **Depends on** 07 and the approved pickup and cancellation text. **Scope** M. **Likely paths** `backend/routes/web.php`, `backend/app/Http/`, `backend/tests/Feature/CheckoutTest.php`, `frontend/app/book/`, `frontend/lib/api.ts`.
- **Business wording** Configure the approved pickup instructions and cancellation terms when available. The confirmation uses the spec's contact wording for cancellation requests by default.

### Task 09 — Restrict staff access

- [x] Build Next.js staff sign-in and sign-out screens backed by Laravel session authentication. Rate-limit sign-in, rotate the session on successful sign-in, and invalidate it on sign-out. Add the staff gate and document server-side staff account provisioning.
- **Verify** sign-in and sign-out through the Next.js URL, then use Laravel feature tests to check direct staff API requests from guests and accounts without staff permission.
- **Depends on** 01 and 03. **Scope** M. **Likely paths** `backend/routes/web.php`, `backend/app/Http/`, `backend/app/Providers/`, `backend/tests/Feature/StaffAccessTest.php`, `frontend/app/staff/`, `README.md`.

**Checkpoint after Task 09**

- [x] A guest can complete a booking and recover its reference after retrying. Staff access checks pass for authorized and unauthorized accounts.

## Staff fleet and calendar

### Task 10 — Maintain fleet listings

- [ ] Build Next.js staff fleet screens for adding, editing, publishing, and withdrawing individual vehicles. Apply Laravel policies to the write endpoints. Publish and withdrawal use the same vehicle lock as booking. Withdrawal removes the listing from search and stops new submissions after the withdrawal commits.
- **Verify** with policy and endpoint tests, a customer search before and after withdrawal, and a transaction test where withdrawal wins against a booking submission.
- **Depends on** 06 and 09. **Scope** M. **Likely paths** `backend/app/Http/`, `backend/app/Policies/`, `backend/app/Services/`, `backend/tests/Feature/`, `frontend/app/staff/vehicles/`.

### Task 11 — Manage unavailable dates

- [ ] Build a Next.js availability editor backed by policy-protected Laravel endpoints. Staff can add and remove blocks. A block cannot overlap a confirmed booking, and adding a block uses the same transaction lock as customer booking.
- **Verify** by adding and removing a block, rejecting a block over an existing booking, and racing a new block against a booking on separate PostgreSQL connections. Assert exactly one write succeeds and search reflects it.
- **Depends on** 06 and 09. **Scope** M. **Likely paths** `backend/app/Http/`, `backend/app/Policies/`, `backend/app/Services/AvailabilityService.php`, `backend/tests/Feature/`, `frontend/app/staff/vehicles/`.

**Checkpoint after Task 11**

- [ ] A staff listing or calendar change takes effect in the customer search. The booking, block, and withdrawal contention tests pass on PostgreSQL.

## Cancellation and acceptance

### Task 12 — Look up and cancel reservations

- [ ] Build Next.js reservation lookup and cancellation screens for authorized staff. Laravel policies protect lookup and cancellation. The booking service updates status under the vehicle lock. Staff can still find the cancelled record, and eligible dates become available again.
- **Verify** with staff lookup, unauthorized and repeated-cancellation cases, and a new customer search and booking for the released dates.
- **Depends on** 08, 09, and the cancellation rules. **Scope** M. **Likely paths** `backend/app/Http/`, `backend/app/Policies/`, `backend/app/Services/BookingService.php`, `backend/tests/Feature/StaffReservationsTest.php`, `frontend/app/staff/reservations/`.

### Task 13 — Verify the complete V1 flow

- [ ] Run every acceptance scenario in the spec with a clean database and synthetic data. In a browser, use Next.js to search, review, confirm a booking, sign in as staff, look up and cancel the booking, and book the released vehicle. Verify cookie and authorization behavior across both apps.
- **Verify** with a clean migration, `php artisan test`, `npm run lint`, `npm run build`, and the browser walkthrough. Record any remaining acceptance failures by requirement ID.
- **Depends on** 08, 10, 11, and 12. **Scope** S. **Likely paths** `backend/tests/`, `frontend/app/`, `README.md`.

**Final checkpoint after Task 13**

- [ ] All R1–R5 requirements and spec acceptance scenarios pass. Customer confirmation and staff operations work from their intended entry points.
