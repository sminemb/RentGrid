import Link from "next/link";
import type { RentalDates, Vehicle } from "@/lib/rentals";
import { Icon } from "../../rental-ui";
import { staffRequest } from "../staff-api";
import { CancelReservation } from "./cancel-reservation";

export const metadata = { title: "Reservations", robots: { index: false } };

type StaffReservation = RentalDates & {
  reference: string;
  status: "confirmed" | "cancelled";
  vehicle: Vehicle;
  guest: { name: string | null; email: string | null; phone: string | null };
};

const dateFormatter = new Intl.DateTimeFormat("en", { dateStyle: "medium", timeZone: "UTC" });

export default async function ReservationsPage({ searchParams }: {
  searchParams: Promise<{ reference?: string | string[] }>;
}) {
  const { reference: query } = await searchParams;
  const reference = typeof query === "string" ? query.trim() : "";
  const valid = /^[a-z0-9-]{1,29}$/i.test(reference);
  const response = await staffRequest(valid ? `/api/staff/reservations/${reference}` : "/api/staff/session");
  const canSearch = response?.ok || response?.status === 404;
  const reservation: StaffReservation | undefined = valid && response?.ok ? (await response.json()).data : undefined;
  const error = response?.status === 404
    ? "No reservation matches that reference. Check the booking confirmation and try again."
    : query !== undefined && !valid ? "Enter the booking reference shown on the confirmation." : "";

  return (
    <main id="main-content" className="shell staff-shell">
      <header className="review-heading">
        <Link className="back-link" href="/staff"><Icon name="back" /> Staff access</Link>
        <h1>Reservations</h1>
      </header>
      {canSearch ? (
        <>
          <form action="/staff/reservations" method="get" className="trip-panel staff-form" aria-label="Reservation lookup">
            <div className="field">
              <label htmlFor="reference">Booking reference</label>
              <input id="reference" name="reference" className="reservation-reference" defaultValue={reference} spellCheck={false} autoComplete="off"
                maxLength={29} required aria-invalid={error ? true : undefined} aria-describedby={error ? "reference-error" : undefined} />
              {error && <p className="error" id="reference-error" role="alert">{error}</p>}
            </div>
            <button className="button" type="submit">Look up reservation</button>
          </form>
          {reservation && <ReservationDetails reservation={reservation} />}
        </>
      ) : (
        <section className="trip-panel staff-form">
          <p role="alert">{response?.status === 403 ? "This account does not have staff access." : "Reservations are temporarily unavailable."}</p>
          {response?.status !== 403 && <Link className="button button-secondary" href="/staff/reservations">Try again</Link>}
        </section>
      )}
    </main>
  );
}

function ReservationDetails({ reservation }: { reservation: StaffReservation }) {
  const { reference, vehicle, guest, status } = reservation;
  const confirmed = status === "confirmed";

  return (
    <section className="trip-panel staff-form reservation-details" aria-labelledby="reservation-vehicle">
      <div className="fleet-heading">
        <code className="booking-reference">{reference}</code>
        <span className="fleet-status" data-published={confirmed}>{confirmed ? "Confirmed" : "Cancelled"}</span>
      </div>
      <div className="trip-vehicle">
        <div className="vehicle-icon"><Icon name="car" /></div>
        <div className="vehicle-name">
          <h2 id="reservation-vehicle">{vehicle.display_label}</h2>
          <p>{vehicle.make} {vehicle.model}</p>
        </div>
      </div>
      <dl className="itinerary reservation-itinerary">
        <div><dt>Pickup date</dt><dd><ReservationDate date={reservation.pickup_date} /></dd></div>
        <div><dt>Return date</dt><dd><ReservationDate date={reservation.return_date} /></dd></div>
        <div className="reservation-guest">
          <dt>Guest contact</dt>
          {guest.name && <dd>{guest.name}</dd>}
          {guest.email && <dd>{guest.email}</dd>}
          {guest.phone && <dd>{guest.phone}</dd>}
          {!guest.name && !guest.email && !guest.phone && <dd>No contact details on file</dd>}
        </div>
      </dl>
      <CancelReservation key={reference} reference={reference} confirmed={confirmed} />
    </section>
  );
}

function ReservationDate({ date }: { date: string }) {
  return <time dateTime={date}>{dateFormatter.format(new Date(date))}</time>;
}
