import { staffRequest } from "../../staff-api";
import { FleetBack, FleetError } from "../fleet-ui";
import { VehicleForm } from "../vehicle-form";

export const metadata = { title: "Add vehicle", robots: { index: false } };

export default async function NewVehiclePage() {
  const session = await staffRequest("/api/staff/session");

  return (
    <main id="main-content" className="shell staff-shell">
      <header className="review-heading">
        <FleetBack />
        <h1>Add vehicle</h1>
        {session?.ok && <p>Choose a public label that distinguishes this vehicle. You can publish it after saving.</p>}
      </header>
      {session?.ok ? <VehicleForm /> : <FleetError status={session?.status} retry="/staff/vehicles/new" />}
    </main>
  );
}
