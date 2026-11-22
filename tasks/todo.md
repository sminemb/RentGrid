# RentGrid V1 Task Breakdown

Follow the [implementation plan](plan.md) and [product specification](../SPEC.md). Paths describe where changes are expected in this greenfield repository. Each checkpoint includes `python manage.py check` and `python manage.py test` with PostgreSQL configured.

## Foundation

### Task 01 — Bootstrap the application

- [ ] Create the Django project and rental app, pin dependencies, and document the environment settings and local setup commands. The app starts against PostgreSQL and can migrate an empty local database.
- **Verify** by installing the declared dependencies, running migrations and system checks, and opening the local Django server.
- **Depends on** none. **Scope** M, framework scaffold and configuration. **Likely paths** `manage.py`, `rentgrid/`, `rentals/apps.py`, `requirements.txt`, `README.md`.

### Task 02 — Store individual fleet vehicles

- [ ] Persist each vehicle's stable ID, public display label, make and model, and publication state. Two otherwise identical vehicles can be entered and distinguished.
- **Verify** with model tests for vehicle identity and publication state and a synthetic two-vehicle fixture.
- **Depends on** 01. **Scope** M. **Likely paths** `rentals/models.py`, `rentals/migrations/`, `rentals/tests/test_fleet.py`.

### Task 03 — Model the rental calendar

- [ ] Implement the confirmed date policy in a shared availability module. Persist reservation states and staff availability blocks with their vehicle and date range. A published vehicle is available for a range only when no confirmed reservation or block overlaps it.
- **Verify** with tests for same-day rentals, the return-day boundary, reversed and past dates, business-local today, and overlap against confirmed, cancelled, and blocked periods.
- **Depends on** 02 and the rental timing decision. **Scope** M. **Likely paths** `rentals/models.py`, `rentals/migrations/`, `rentals/availability.py`, `rentals/tests/test_availability.py`.

**Checkpoint after Task 03**

- [ ] An empty PostgreSQL database migrates successfully. The date-policy tests pass, and the fixtures distinguish two vehicles of the same make and model.

## Customer search and booking core

### Task 04 — Find available vehicles

- [ ] Provide a pickup and return date form with server-side validation, helpful errors, and results for individually identifiable vehicles available for the full period. Keep submitted dates when results are empty so customers can revise the search.
- **Verify** by exercising valid, invalid, empty, and mixed-availability searches through the customer-facing route.
- **Depends on** 03. **Scope** M. **Likely paths** `rentals/forms.py`, `rentals/views.py`, `rentals/urls.py`, `rentals/templates/rentals/search.html`, `rentals/tests/test_search.py`.

### Task 05 — Review a rental before submitting

- [ ] Show the selected vehicle, dates, pickup location, and amount due at pickup. Accept and validate the guest's name, email address, and phone number with the approved pricing rules calculated server-side.
- **Verify** with form and screen tests for a valid quote, incomplete or invalid contact details, and the configured calculation, including fees and deposits where applicable.
- **Depends on** 04 and the pricing and pickup location decisions. **Scope** M. **Likely paths** `rentals/forms.py`, `rentals/pricing.py`, `rentals/views.py`, `rentals/templates/rentals/review.html`, `rentals/tests/test_review.py`.

### Task 06 — Save reservations atomically

- [ ] Lock the selected vehicle in a transaction. Recheck publication, date availability, and the reviewed price before saving the confirmed booking with its unique reference and agreed amount. Return a conflict if a competing reservation wins or the price has changed.
- **Verify** with separate PostgreSQL connections submitting overlapping requests for the same vehicle. Assert one confirmation and one committed reservation. Check that two independent vehicles can both be booked.
- **Depends on** 03 and 05. **Scope** M. **Likely paths** `rentals/booking.py`, `rentals/models.py`, `rentals/migrations/`, `rentals/tests/test_booking_transactions.py`.

**Checkpoint after Task 06**

- [ ] A customer can search and review the selected rental. The competing-booking test proves exactly one reservation commits for overlapping dates on one vehicle.

## Confirmation and staff access

### Task 07 — Make booking submissions safe to retry

- [ ] Persist a unique attempt key tied to the guest's reviewed booking. A retry from the same session returns the original reference and current status. A mismatched session or changed selection cannot reuse the attempt.
- **Verify** with simultaneous duplicate requests, a replay after losing the response, a replay after cancellation, and a request using an attempt from another guest session.
- **Depends on** 06. **Scope** M. **Likely paths** `rentals/booking.py`, `rentals/models.py`, `rentals/migrations/`, `rentals/tests/test_booking_retries.py`.

### Task 08 — Complete the customer booking flow

- [ ] Connect review and submission to the booking service. After commit, show the reference, exact vehicle, dates, pickup details, and amount due. If availability or price changes before booking, preserve the dates and let the customer search again or review the updated price.
- **Verify** with route tests and a browser walkthrough of confirmed booking, retry, changed quote, and a stale search result. Confirm that another guest session cannot open the confirmation.
- **Depends on** 07 and the approved pickup and cancellation text. **Scope** M. **Likely paths** `rentals/views.py`, `rentals/urls.py`, `rentals/templates/rentals/review.html`, `rentals/templates/rentals/confirmation.html`, `rentals/tests/test_checkout.py`.

### Task 09 — Restrict staff access

- [ ] Configure Django admin sign-in and staff permissions for fleet and reservation work. Document how to provision an authorized staff user with the permissions needed for their job.
- **Verify** that guests and non-staff cannot open admin and that a staff user without the relevant model permissions cannot view or change rental records.
- **Depends on** 01 and 03. **Scope** M. **Likely paths** `rentgrid/urls.py`, `rentals/admin.py`, `rentals/tests/test_staff_access.py`, `README.md`.

**Checkpoint after Task 09**

- [ ] A guest can complete a booking and recover its reference after retrying. Staff access checks pass for authorized and unauthorized accounts.

## Staff fleet and calendar

### Task 10 — Maintain fleet listings

- [ ] Authorized staff can add, edit, publish, and withdraw individual vehicles. Publish and withdrawal use the same vehicle lock as booking. Withdrawal removes the listing from search and stops new submissions after the withdrawal commits.
- **Verify** with admin action tests, a customer search before and after withdrawal, and a transaction test where withdrawal wins against a booking submission.
- **Depends on** 06 and 09. **Scope** M. **Likely paths** `rentals/admin.py`, `rentals/fleet.py`, `rentals/tests/test_staff_fleet.py`, `rentals/tests/test_booking_transactions.py`.

### Task 11 — Manage unavailable dates

- [ ] Authorized staff can add and remove a vehicle's availability blocks. A block cannot overlap a confirmed booking, and adding a block uses the same transaction lock as customer booking.
- **Verify** by adding and removing a block, rejecting a block over an existing booking, and racing a new block against a booking on separate PostgreSQL connections. Assert exactly one write succeeds and search reflects it.
- **Depends on** 06 and 09. **Scope** M. **Likely paths** `rentals/admin.py`, `rentals/availability.py`, `rentals/tests/test_staff_blocks.py`, `rentals/tests/test_booking_transactions.py`.

**Checkpoint after Task 11**

- [ ] A staff listing or calendar change takes effect in the customer search. The booking, block, and withdrawal contention tests pass on PostgreSQL.

## Cancellation and acceptance

### Task 12 — Look up and cancel reservations

- [ ] Authorized staff can find a reservation by reference and cancel it under the approved rules. Cancellation updates status through the booking service under the vehicle lock. Staff can still find the cancelled record, and eligible dates become available again.
- **Verify** with staff lookup, unauthorized and repeated-cancellation cases, and a new customer search and booking for the released dates.
- **Depends on** 08, 09, and the cancellation rules. **Scope** M. **Likely paths** `rentals/admin.py`, `rentals/booking.py`, `rentals/tests/test_staff_reservations.py`.

### Task 13 — Verify the complete V1 flow

- [ ] Run every acceptance scenario in the spec with a clean database and synthetic data. Walk through search, review, instant confirmation, staff lookup and cancellation, and booking the released vehicle in a browser.
- **Verify** with a clean migration, `python manage.py check`, `python manage.py test`, and the browser walkthrough. Record any remaining acceptance failures by requirement ID.
- **Depends on** 08, 10, 11, and 12. **Scope** S. **Likely paths** `rentals/tests/`, `README.md`.

**Final checkpoint after Task 13**

- [ ] All R1–R5 requirements and spec acceptance scenarios pass. Customer confirmation and staff operations work from their intended entry points.
