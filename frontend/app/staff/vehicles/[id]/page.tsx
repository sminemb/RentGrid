import { staffRequest } from "../../staff-api";
import { notFound } from "next/navigation";
import { FleetBack, FleetError } from "../fleet-ui";
import type { StaffCalendar, StaffVehicle } from "../fleet-types";
import { AvailabilityEditor } from "../availability-editor";
import { PublicationControl, VehicleForm } from "../vehicle-form";

export const metadata = { title: "Manage vehicle", robots: { index: false } };

export default async function VehiclePage({ params }: PageProps<"/staff/vehicles/[id]">) {
  const { id } = await params;
  if (!/^[0-9]+$/.test(id)) notFound();
  const path = `/staff/vehicles/${id}` as const;
  const [details, availability] = await Promise.all([
    staffRequest(`/api${path}`),
    staffRequest(`/api${path}/availability`),
  ]);
  const vehicle: { data: StaffVehicle } | undefined = details?.ok ? await details.json() : undefined;
  const calendar: StaffCalendar | undefined = availability?.ok ? await availability.json() : undefined;

  return (
    <main id="main-content" className="shell fleet-shell">
      <header className="review-heading">
        <FleetBack />
        <h1>{vehicle?.data.display_label ?? "Manage vehicle"}</h1>
        {vehicle && <p>{vehicle.data.make} {vehicle.data.model}</p>}
        {calendar && <a className="calendar-jump back-link" href="#availability">Go to availability calendar</a>}
      </header>
      {vehicle ? (
        <div className="fleet-detail">
          <div className="fleet-sidebar">
            <PublicationControl vehicle={vehicle.data} />
            <VehicleForm vehicle={vehicle.data} />
          </div>
          {calendar ? (
            <section id="availability" className="trip-panel availability-panel" aria-labelledby="availability-heading">
              <h2 id="availability-heading">Availability calendar</h2>
              <AvailabilityEditor vehicleId={vehicle.data.id} calendar={calendar} />
            </section>
          ) : <FleetError status={availability?.status} retry={path} />}
        </div>
      ) : <FleetError status={details?.status} retry={path} />}
    </main>
  );
}
