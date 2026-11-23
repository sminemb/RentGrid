"use client";

import Link from "next/link";
import { type ReactNode, useEffect, useState, useSyncExternalStore } from "react";
import { apiRequest } from "@/lib/api";
import type { ApiProblem, RentalDates, Vehicle } from "@/lib/rentals";
import { Icon, LoadingRows } from "./rental-ui";
import { BookingRecoveryNotice } from "./book/booking-recovery-notice";
import { pendingBooking } from "./book/pending-booking";

type SearchResult = { data: Vehicle[] } | { problem: ApiProblem };

export function VehicleSearch({ dates, searched, children }: { dates: RentalDates; searched: boolean; children: ReactNode }) {
  const query = new URLSearchParams(dates).toString();
  const [search, setSearch] = useState<{ query: string; result: SearchResult }>();
  const result = search?.query === query ? search.result : undefined;
  const problem = result && "problem" in result ? result.problem : undefined;
  const vehicles = result && "data" in result ? result.data : undefined;
  const pendingHref = useSyncExternalStore(pendingBooking.subscribe, pendingBooking.currentHref, () => undefined);

  useEffect(() => pendingBooking.restore(), []);

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

  const dateForm = (
    <form action="/" className="search-form" aria-label="Search by rental dates" noValidate key={query}>
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
      <button className="button" type="submit">Search vehicles <Icon name="arrow" /></button>
    </form>
  );

  return (
    <>
      <div className={searched ? "search-intro" : "search-hero"}>
        {children}
        {dateForm}
      </div>

      {pendingHref && <BookingRecoveryNotice href={pendingHref} />}

      {searched && (
        <section className="results" aria-labelledby="results-heading">
          <div className="results-heading">
            <h2 id="results-heading">Available vehicles</h2>
            {!!vehicles?.length && <p className="results-count" role="status">{vehicles.length} {vehicles.length === 1 ? "vehicle" : "vehicles"} available</p>}
          </div>
          {!result && <LoadingRows>Checking availability…</LoadingRows>}
          {problem?.errors && <p className="state-message error" role="alert">Check the highlighted dates and search again.</p>}
          {problem && !problem.errors && <p className="state-message error" role="alert">Search is unavailable. Please try again.</p>}
          {vehicles?.length === 0 && <p className="state-message" role="status">No vehicles are available for these dates. Try changing your dates.</p>}
          {!!vehicles?.length && (
            <ul className="vehicle-list" role="list">
              {vehicles.map((vehicle) => (
                <li className="vehicle" key={vehicle.id}>
                  <div className="vehicle-icon"><Icon name="car" /></div>
                  <div className="vehicle-name">
                    <h3>{vehicle.display_label}</h3>
                    <p>{vehicle.make} {vehicle.model}</p>
                  </div>
                  <Link
                    className="action-link"
                    href={`/book?${new URLSearchParams({ ...dates, vehicle_id: String(vehicle.id) })}`}
                    aria-label={`Review rental for ${vehicle.display_label}`}
                  >
                    Review rental <Icon name="arrow" />
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
