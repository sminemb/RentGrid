import { readRentalDates } from "@/lib/rentals";
import { VehicleSearch } from "./vehicle-search";

export default async function Home({ searchParams }: PageProps<"/">) {
  const params = await searchParams;

  return (
    <main className="shell search-page">
      <div className="search-hero">
        <div className="hero-copy">
          <h1>Find your rental vehicle</h1>
          <p>Choose your pickup and return dates to see which vehicles are available.</p>
        </div>
        <div className="hero-photo" aria-hidden="true" />
      </div>
      <VehicleSearch
        dates={readRentalDates(params)}
        searched={"pickup_date" in params || "return_date" in params}
      />
    </main>
  );
}
