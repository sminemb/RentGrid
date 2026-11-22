"use client";

import { type FormEvent } from "react";
import { useRouter } from "next/navigation";
import type { StaffVehicle } from "./fleet-types";
import { PublicationStatus } from "./fleet-ui";
import { StaffFeedback, StaffField, useStaffMutation } from "./staff-form";

export function VehicleForm({ vehicle }: { vehicle?: StaffVehicle }) {
  const router = useRouter();
  const { pending, problem, status, submit } = useStaffMutation();

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const values = Object.fromEntries(new FormData(event.currentTarget));
    await submit(vehicle ? `/api/staff/vehicles/${vehicle.id}` : "/api/staff/vehicles", vehicle ? "PUT" : "POST", values, async (response) => {
      if (vehicle) return "Vehicle details saved.";
      const { data }: { data: StaffVehicle } = await response.json();
      router.replace(`/staff/vehicles/${data.id}`);
      return "";
    });
  }

  return (
    <form className="trip-panel staff-form" onSubmit={save} method="post" aria-label="Vehicle details">
      {vehicle && <h2>Vehicle details</h2>}
      <StaffField label="Display label" name="display_label" defaultValue={vehicle?.display_label} error={problem.errors?.display_label?.[0]} maxLength={255} required />
      <StaffField label="Make" name="make" defaultValue={vehicle?.make} error={problem.errors?.make?.[0]} maxLength={255} required />
      <StaffField label="Model" name="model" defaultValue={vehicle?.model} error={problem.errors?.model?.[0]} maxLength={255} required />
      <StaffFeedback problem={problem} status={status} />
      <button className="button" type="submit" disabled={pending}>
        {pending ? "Saving…" : vehicle ? "Save details" : "Add vehicle"}
      </button>
    </form>
  );
}

export function PublicationControl({ vehicle }: { vehicle: StaffVehicle }) {
  const { pending, problem, status, submit } = useStaffMutation();
  const published = vehicle.is_published;

  function changePublication() {
    return submit(`/api/staff/vehicles/${vehicle.id}/publication`, "PUT", { is_published: !published },
      () => published ? "Listing withdrawn." : "Listing published.");
  }

  return (
    <section className="trip-panel staff-form" aria-labelledby="publication-heading">
      <div className="fleet-heading">
        <h2 id="publication-heading">Listing</h2>
        <PublicationStatus published={published} />
      </div>
      <p>{published ? "Customers can book this vehicle on available dates." : "Publish this listing to open the vehicle to bookings."}</p>
      <StaffFeedback problem={problem} status={status} />
      <button
        className={published ? "button button-secondary" : "button"}
        type="button"
        disabled={pending}
        onClick={changePublication}
      >
        {pending ? (published ? "Withdrawing…" : "Publishing…") : (published ? "Withdraw listing" : "Publish listing")}
      </button>
    </section>
  );
}
