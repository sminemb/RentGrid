# RentGrid V1 Product Specification

## Objective

Let customers book a specific vehicle from one rental business’s fleet. A customer chooses rental dates, sees available vehicles, and gets an immediate confirmation for the vehicle they select. The business collects payment at pickup. A confirmed reservation holds the vehicle for that customer throughout the rental period.

## Draft operating assumptions

- Customers book as guests using their name, email address, and phone number.
- The business operates from one pickup and return location and uses that location’s time zone for rental dates.
- Reservations cover calendar days, including both the pickup and return dates. A same-day rental occupies one full calendar day.
- The booking confirmation appears on screen. Customers contact the business to request changes or cancellations.

## Requirements

| ID | Requirement |
| --- | --- |
| R1 | Customers enter a pickup date and a return date. Pickup must be today or later in the business’s time zone, and return must be on or after pickup. Search shows each published vehicle that is free for the entire period. Each result identifies the individual vehicle so customers can distinguish similar vehicles. |
| R2 | Customers review the exact vehicle, rental dates, price due at pickup, and pickup location before submitting their contact details and booking. When a search has no matches, customers can revise their dates. |
| R3 | The system rechecks availability as part of saving the booking. If the vehicle is available, it saves the confirmed reservation and shows a unique booking reference, vehicle, dates, pickup details, and amount due at pickup. If the vehicle has become unavailable, the customer can return to updated results with their dates retained. |
| R4 | Two confirmed reservations for the same vehicle cannot overlap. Repeating a submission for a booking attempt that already succeeded returns the existing booking reference and current status. |
| R5 | Authorized staff can add, edit, publish, and withdraw vehicle listings, add and remove availability blocks, look up bookings by reference, and cancel confirmed bookings. Withdrawing a listing closes it to new bookings. A block and a confirmed reservation cannot overlap for the same vehicle. Cancelled bookings remain visible to staff and release the vehicle for those dates, subject to other availability rules. |

## Acceptance criteria

| Scenario | Expected result |
| --- | --- |
| Search across several days | A vehicle booked or blocked on any selected day is excluded. Two available vehicles of the same make and model appear as distinct choices. |
| Search on a return date | A vehicle booked June 10–12 is unavailable for a pickup on June 12 and available for a pickup on June 13 if otherwise free. |
| Submit invalid dates | The system rejects a pickup date in the past or a return date before pickup and explains how to correct the input. |
| Complete a booking | The confirmation identifies the exact vehicle and rental dates, includes a booking reference and pickup details, and shows the amount due at pickup. Staff can find the same reservation by its reference. |
| Submit two overlapping requests at once | Exactly one request receives a confirmation for that vehicle. The other receives an availability message and can refresh its search. |
| Retry after a lost response | The customer receives the original booking reference and current status. Exactly one reservation exists. |
| Block dates while a customer books | Exactly one of the two conflicting changes succeeds. Staff and customers see the resulting availability. |
| Cancel a confirmed booking | Staff can see that the booking was cancelled. The vehicle appears in a fresh search for those dates if it is otherwise available. |

## Verification and implementation conventions

- Test date boundaries and overlap rules with focused tests. Use integration tests against the chosen data store for concurrent submissions, staff blocks, cancellations, and retries. Verify the customer flow end to end from search through confirmation, and verify that staff functions require authorization.
- Group booking, fleet, and availability behavior with the objects responsible for it. Prefer composition and keep stateless date helpers as functions, following `AGENTS.md`.
- Treat contact details as private reservation data and restrict staff booking access to authorized users. Confirm availability and save the reservation as one atomic operation.
- The technology stack, source and test directories, and executable build, run, and test commands are decisions for the implementation plan.

## Business rules to confirm

- Do customers need pickup and return times or same-day turnaround between rentals?
- How should the business calculate rental charges, and what fees or deposits should customers see before booking?
- What cancellation rules and pickup instructions should customers see on the confirmation?
