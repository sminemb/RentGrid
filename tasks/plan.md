# RentGrid V1 Implementation Plan

The [product specification](../SPEC.md) defines a customer booking flow for a single rental business. Build a complete path from date search to confirmation for an exact vehicle, then give staff the tools to maintain the fleet, block dates, and cancel bookings. The business collects payment at pickup. Track implementation in the [task breakdown](todo.md).

## Proposed stack and structure

- Use Python 3.13, Django 5.2 LTS, and PostgreSQL 17 with the Psycopg 3 driver. Server-rendered templates cover the customer flow. Django admin provides staff sign-in and permissioned fleet and reservation screens. Pin compatible patches when scaffolding the project. Use PostgreSQL in development and integration tests so the transaction tests exercise the same database behavior as the application. [Django 5.2 release notes](https://docs.djangoproject.com/en/5.2/releases/5.2/) · [Django database support](https://docs.djangoproject.com/en/5.2/ref/databases/#postgresql-notes) · [Django admin actions](https://docs.djangoproject.com/en/5.2/ref/contrib/admin/actions/)
- Keep configuration and routes under `rentgrid/`. Put the rental models, booking and availability services, customer views, and admin configuration in `rentals/`. Put templates under `rentals/templates/rentals/` and tests under `rentals/tests/`. Group state and behavior by responsibility and use functions for stateless date calculations, as required by `AGENTS.md`.
- Load database credentials, the Django secret, and the business time zone from configuration. Use synthetic fleet and booking fixtures for development and tests.

## Booking and availability decisions

- Use a persistent identifier for each vehicle and a customer-facing label that distinguishes similar vehicles. Store confirmed and cancelled reservations so staff can find either state by booking reference. Use one shared overlap rule for reservations and staff blocks. Under the draft calendar-day policy, two ranges overlap if each starts on or before the other ends. Evaluate the current date in the business time zone.
- Serialize writes that affect availability for a particular vehicle. Start a database transaction, lock that vehicle's row, then query its current publication status and date conflicts in subsequent statements before writing. Booking, availability blocks, cancellations, and publication changes use the same vehicle lock. With PostgreSQL at Read Committed, the post-lock queries see changes committed by the previous writer. A customer receives a confirmation only after the reservation commits. [Django row locking](https://docs.djangoproject.com/en/5.2/ref/models/querysets/#select-for-update) · [PostgreSQL transaction isolation](https://www.postgresql.org/docs/17/transaction-iso.html#XACT-READ-COMMITTED)
- Issue a booking attempt key tied to the guest session and the reviewed vehicle, dates, and price. Enforce uniqueness in the database. A replay from the same session returns the original reference and current status, including after cancellation. Handle a uniqueness race by looking up the committed booking. Validate booking details on the server and require a new review if the price has changed. Store the agreed amount with the booking so later rate edits do not change the amount shown on the confirmation.
- Keep customer confirmation in the booking guest session and let authorized staff search by booking reference. Apply model and action permissions server-side. Expose staff mutations through the same services used by the booking flow, with the booking record itself displayed read-only. Keep customer contact details out of public search results and application logs.

## Build order

| Phase | Tasks | Outcome |
| --- | --- | --- |
| Foundation | 01–03 | A runnable app with persistent, individually identifiable vehicles and tested date and overlap rules. |
| Customer search and booking core | 04–06 | Customers can search and review a rental. The database can commit a reservation safely under contention. |
| Confirmation and staff access | 07–09 | Customers can submit, recover a confirmation after a retry, and recover from a stale result. Staff access is protected. |
| Staff fleet and calendar | 10–11 | Staff can update vehicle listings and manage unavailable dates. The customer search reflects both. |
| Cancellation and acceptance | 12–13 | Staff can look up and cancel bookings. The complete customer and staff flows are verified against the spec. |

Staff access setup can start after the foundation while customer screens are in progress. Coordinate listing and calendar changes because they share admin code and transaction tests.

## Risks and verification

| Risk | Verification |
| --- | --- |
| Stale search results or overlapping writes create conflicting reservations. | Use PostgreSQL transaction tests with separate connections for two customer bookings, booking versus a staff block, and booking versus listing withdrawal. Assert the committed records and final availability. |
| A lost response creates a second reservation or returns a stale confirmation. | Replay the same attempt before and after cancellation. Assert that one reservation exists and the status is current. |
| Calendar boundaries differ between search and submission. | Test one-day rentals, overlapping and adjacent ranges, and the business's current date near midnight. Verify that search, booking, and staff use the same rules. |
| Staff tools expose guest details or bypass the booking rules. | Test guest, non-staff, and unauthorized staff access. Run admin action tests against the same booking and availability services. |

After Task 01, run these commands from the repository root with the project's Python environment active and a local PostgreSQL database configured.

```powershell
python -m pip install -r requirements.txt
python manage.py migrate
python manage.py check
python manage.py test
python manage.py runserver
```

Use Django's `TransactionTestCase` for tests that need to observe commits across connections. Use a browser for the final guest and staff walkthrough. [Django testing tools](https://docs.djangoproject.com/en/5.2/topics/testing/tools/#transactiontestcase)

## Business decisions still open

- **Rental timing.** The plan assumes whole calendar days, including the return day. Confirm this before implementing Task 03, or amend the spec if timed pickup, timed return, or same-day turnaround is required.
- **Pricing.** Task 05 needs the currency, rate calculation, fees and deposits, and the amount payable at pickup. Build review-screen fixtures while these are under discussion.
- **Pickup and cancellations.** Task 08 needs the pickup address and the instructions and cancellation terms to display. Task 12 needs any rules staff must enforce when cancelling. The draft still assumes guest checkout, one location, and an on-screen confirmation.
