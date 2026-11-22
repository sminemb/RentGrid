import type { RentalDates, Vehicle } from "@/lib/rentals";
import { Icon } from "../rental-ui";

export type Trip = RentalDates & {
  vehicle: Vehicle;
  pickup_location: string;
  currency: string;
  amount_due_minor: number;
};

const dateFormatter = new Intl.DateTimeFormat("en", { dateStyle: "medium", timeZone: "UTC" });

export function money(amount: number, currencyCode: string) {
  const currency = new Intl.NumberFormat("en", { style: "currency", currency: currencyCode, currencyDisplay: "code" });
  const digits = currency.resolvedOptions().maximumFractionDigits ?? 2;
  return currency.format(amount / 10 ** digits);
}

export function TripDetails({ trip, pickupInstructions }: { trip: Trip; pickupInstructions?: string | null }) {
  const { vehicle } = trip;

  return (
    <section className="trip-panel" aria-labelledby="vehicle-heading">
      <div className="trip-vehicle">
        <div className="vehicle-icon"><Icon name="car" /></div>
        <div className="vehicle-name">
          <h2 id="vehicle-heading">{vehicle.display_label}</h2>
          <p>{vehicle.make} {vehicle.model}</p>
        </div>
      </div>
      <dl className="itinerary">
        <div><dt>Pickup date</dt><dd><time dateTime={trip.pickup_date}>{dateFormatter.format(new Date(trip.pickup_date))}</time></dd></div>
        <div><dt>Return date</dt><dd><time dateTime={trip.return_date}>{dateFormatter.format(new Date(trip.return_date))}</time></dd></div>
        <div className="pickup-location"><dt><Icon name="pin" /> Pickup location</dt><dd>{trip.pickup_location}</dd></div>
      </dl>
      {pickupInstructions && <p className="trip-note">{pickupInstructions}</p>}
    </section>
  );
}
