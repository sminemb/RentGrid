"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { apiRequest } from "@/lib/api";
import type { ApiProblem, RentalDates, Vehicle } from "@/lib/rentals";

type SearchResult = { data: Vehicle[] } | { problem: ApiProblem };

export function VehicleSearch({ dates, searched }: { dates: RentalDates; searched: boolean }) {
  const query = new URLSearchParams(dates).toString();
  const [search, setSearch] = useState<{ query: string; result: SearchResult }>();
  const result = search?.query === query ? search.result : undefined;
  const problem = result && "problem" in result ? result.problem : undefined;
  const vehicles = result && "data" in result ? result.data : undefined;

  useEffect(() => {
    if (!searched) return;

    const controller = new AbortController();
    async function loadVehicles() {
      try {
        const response = await apiRequest(`/api/vehicles/availability?${query}`, {
          signal: controller.signal,
          cache: "no-store",
        });
        const body = await response.json();
        if (!controller.signal.aborted) {
          if (response.ok) {
            setSearch({ query, result: { data: body.data } });
          } else {
            setSearch({ query, result: { problem: body } });
          }
        }
      } catch {
        if (!controller.signal.aborted) {
          setSearch({ query, result: { problem: {} } });
        }
      }
    }

    void loadVehicles();
    return () => controller.abort();
  }, [query, searched]);

  return (
    <>
      <form action="/" className="search-form" noValidate key={query}>
        {([
          ["pickup_date", "Pickup date"],
          ["return_date", "Return date"],
        ] as const).map(([field, label]) => {
          const error = problem?.errors?.[field]?.[0];

          return (
            <div className="field" key={field}>
              <label htmlFor={field}>{label}</label>
              <input
                required
                type="date"
                id={field}
                name={field}
                defaultValue={dates[field]}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? `${field}-error` : undefined}
              />
              {error && <p className="error" id={`${field}-error`}>{error}</p>}
            </div>
          );
        })}
        <button type="submit">Search vehicles</button>
      </form>

      {searched && (
        <section className="results" aria-labelledby="results-heading">
          <div className="results-heading">
            <h2 id="results-heading">Available vehicles</h2>
            <div role="status" className={vehicles?.length ? "results-count" : "state-message"}>
              {!result && <p>Checking availability…</p>}
              {problem?.errors && <p className="error">Check the highlighted dates and search again.</p>}
              {problem && !problem.errors && <p className="error">Search is unavailable. Please try again.</p>}
              {vehicles?.length === 0 && <p>No vehicles are available for these dates. Try changing your dates.</p>}
              {!!vehicles?.length && <p>{vehicles.length} {vehicles.length === 1 ? "vehicle" : "vehicles"} available</p>}
            </div>
          </div>
          {!!vehicles?.length && (
            <ul className="vehicle-list">
              {vehicles.map((vehicle) => (
                <li className="vehicle" key={vehicle.id}>
                  <div className="vehicle-name">
                    <h3>{vehicle.display_label}</h3>
                    <p>{vehicle.make} {vehicle.model}</p>
                  </div>
                  <Link
                    className="action-link"
                    href={`/book?${new URLSearchParams({ ...dates, vehicle_id: String(vehicle.id) })}`}
                    aria-label={`Review rental for ${vehicle.display_label}`}
                  >
                    Review rental
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </section>
      )}
    </>
  );
}
