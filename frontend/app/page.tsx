import { readRentalDates } from "@/lib/rentals";
import { RentalSteps } from "./rental-ui";
import { VehicleSearch } from "./vehicle-search";

export default async function Home({ searchParams }: PageProps<"/">) {
  const params = await searchParams;
  const searched = "pickup_date" in params || "return_date" in params;

  return (
    <main id="main-content" className="shell">
      <RentalSteps current="search" />
      <VehicleSearch dates={readRentalDates(params)} searched={searched}>
        <div className="hero-copy">
          <h1>Find your rental vehicle</h1>
          <p>Choose your dates, reserve a specific vehicle, and pay when you pick it up.</p>
        </div>
      </VehicleSearch>
    </main>
  );
}
