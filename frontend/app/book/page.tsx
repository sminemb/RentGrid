import Link from "next/link";
import { readRentalDates } from "@/lib/rentals";
import { Icon, RentalSteps } from "../rental-ui";
import { RentalReview } from "./rental-review";

export const metadata = { title: "Review your rental" };

export default async function Book({ searchParams }: PageProps<"/book">) {
  const params = await searchParams;
  const dates = readRentalDates(params);
  const vehicleId = typeof params.vehicle_id === "string" ? params.vehicle_id : "";
  const search = new URLSearchParams(Object.entries(dates).filter(([, date]) => date));
  const searchUrl = search.size ? `/?${search}` : "/";

  return (
    <main id="main-content" className="shell">
      {vehicleId && dates.pickup_date && dates.return_date ? (
        <RentalReview key={`${vehicleId}:${dates.pickup_date}:${dates.return_date}`} vehicleId={vehicleId} dates={dates} searchUrl={searchUrl} />
      ) : (
        <>
          <RentalSteps current="review" />
          <header className="review-heading">
            <Link className="back-link" href={searchUrl}><Icon name="back" /> Back to vehicle search</Link>
            <h1>Review your rental</h1>
          </header>
          <p className="state-message">Choose a vehicle and rental dates to review a quote.</p>
        </>
      )}
    </main>
  );
}
