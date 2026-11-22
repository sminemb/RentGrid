import Link from "next/link";
import { Icon } from "../../rental-ui";
import { staffRequest } from "../staff-api";
import type { StaffVehicle } from "./fleet-types";
import { FleetBack, FleetError, PublicationStatus } from "./fleet-ui";

export const metadata = { title: "Fleet", robots: { index: false } };

export default async function FleetPage() {
  const response = await staffRequest("/api/staff/vehicles");
  const fleet: { data: StaffVehicle[] } | undefined = response?.ok ? await response.json() : undefined;

  return (
    <main id="main-content" className="shell fleet-shell">
      <header className="review-heading">
        <FleetBack home />
        <div className="fleet-heading">
          <h1>Fleet</h1>
          {fleet && <Link className="button" href="/staff/vehicles/new">Add vehicle</Link>}
        </div>
      </header>
      {!fleet ? (
        <FleetError status={response?.status} retry="/staff/vehicles" />
      ) : fleet.data.length === 0 ? (
        <section className="trip-panel staff-form">
          <h2>No vehicles yet</h2>
          <p>Add a vehicle to start building your fleet.</p>
        </section>
      ) : (
        <ul className="vehicle-list" aria-label="Fleet vehicles" role="list">
          {fleet.data.map((vehicle) => (
            <li className="fleet-row" key={vehicle.id}>
              <span className="vehicle-icon"><Icon name="car" /></span>
              <div className="vehicle-name">
                <h2>{vehicle.display_label}</h2>
                <p>{vehicle.make} {vehicle.model}</p>
              </div>
              <PublicationStatus published={vehicle.is_published} />
              <Link
                className="action-link"
                href={`/staff/vehicles/${vehicle.id}`}
                aria-label={`Manage ${vehicle.display_label}`}
              >
                Manage <Icon name="arrow" />
              </Link>
            </li>
          ))}
        </ul>
      )}
    </main>
  );
}
