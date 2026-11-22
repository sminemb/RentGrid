import Link from "next/link";
import { readRentalDates } from "@/lib/rentals";
import { RentalReview } from "./rental-review";

export default async function Book({ searchParams }: PageProps<"/book">) {
  const params = await searchParams;
  const dates = readRentalDates(params);
  const vehicleId = typeof params.vehicle_id === "string" ? params.vehicle_id : "";
  const searchUrl = `/?${new URLSearchParams(dates)}`;

  return (
    <main className="shell review-page">
      <Link className="back-link" href={searchUrl}>Back to vehicle search</Link>
      <h1>Review your rental</h1>
      <div className="review-content">
        {vehicleId && dates.pickup_date && dates.return_date ? (
          <RentalReview key={`${vehicleId}:${dates.pickup_date}:${dates.return_date}`} vehicleId={vehicleId} dates={dates} />
        ) : (
          <p className="state-message">Choose a vehicle and rental dates to review a quote.</p>
        )}
      </div>
    </main>
  );
}
