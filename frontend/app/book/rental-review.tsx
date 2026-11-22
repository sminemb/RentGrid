"use client";

import { useEffect, useState } from "react";
import { apiRequest } from "@/lib/api";
import type { ApiProblem, RentalDates, Vehicle } from "@/lib/rentals";

type Quote = RentalDates & {
  vehicle: Vehicle;
  pickup_location: string;
  currency: string;
  days: number;
  rental_minor: number;
  fee_minor: number;
  deposit_minor: number;
  amount_due_minor: number;
};

type Review = { quote: Quote } | { error: string };

function formatDate(date: string) {
  return new Intl.DateTimeFormat("en", { dateStyle: "long", timeZone: "UTC" }).format(new Date(date));
}

export function RentalReview({ vehicleId, dates }: { vehicleId: string; dates: RentalDates }) {
  const [review, setReview] = useState<Review>();
  const { pickup_date, return_date } = dates;

  useEffect(() => {
    const controller = new AbortController();
    const { signal } = controller;

    async function loadQuote() {
      try {
        const session = await apiRequest("/api/session", { signal, cache: "no-store" });
        if (!session.ok) throw new Error("Unable to open rental session.");

        const response = await apiRequest("/api/booking-quotes", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ vehicle_id: vehicleId, pickup_date, return_date }),
          signal,
        });
        if (response.ok) {
          const quote: Quote = await response.json();
          if (!signal.aborted) setReview({ quote });
          return;
        }

        if (response.status === 409 || response.status === 422 || response.status === 503) {
          const problem: ApiProblem = await response.json();
          const error = Object.values(problem.errors ?? {}).flat()[0] ?? problem.message;
          if (!signal.aborted && error) {
            setReview({ error });
            return;
          }
        }
        throw new Error("Unable to load quote.");
      } catch {
        if (!signal.aborted) setReview({ error: "We couldn’t load your quote. Please reload the page to try again." });
      }
    }

    void loadQuote();
    return () => controller.abort();
  }, [vehicleId, pickup_date, return_date]);

  if (!review) return <p className="state-message" role="status">Loading your quote…</p>;
  if ("error" in review) return <p className="state-message error" role="alert">{review.error}</p>;

  const { quote } = review;
  const currency = new Intl.NumberFormat("en", { style: "currency", currency: quote.currency, currencyDisplay: "code" });
  const fractionDigits = currency.resolvedOptions().maximumFractionDigits ?? 2;
  const money = (amount: number) => currency.format(amount / 10 ** fractionDigits);

  return (
    <div className="review-grid">
      <section className="panel review-vehicle" aria-labelledby="vehicle-heading">
        <h2 id="vehicle-heading">{quote.vehicle.display_label}</h2>
        <p>{quote.vehicle.make} {quote.vehicle.model}</p>
        <dl className="details itinerary">
          <div className="itinerary-date"><dt>Pickup date</dt><dd><time dateTime={quote.pickup_date}>{formatDate(quote.pickup_date)}</time></dd></div>
          <div className="itinerary-date"><dt>Return date</dt><dd><time dateTime={quote.return_date}>{formatDate(quote.return_date)}</time></dd></div>
          <div className="pickup-location"><dt>Pickup location</dt><dd>{quote.pickup_location}</dd></div>
        </dl>
      </section>

      <section className="panel price" aria-labelledby="price-heading">
        <div className="price-total">
          <h2 id="price-heading">Amount due at pickup</h2>
          <p className="total">{money(quote.amount_due_minor)}</p>
        </div>
        <dl className="details price-breakdown">
          <div>
            <dt>
              <span>Rental for {quote.days} {quote.days === 1 ? "day" : "days"}</span>
              <span className="billing-note">
                {quote.days === 1 ? "Same-day rentals count as one day." : "Includes pickup and return dates."}
              </span>
            </dt>
            <dd>{money(quote.rental_minor)}</dd>
          </div>
          {quote.fee_minor > 0 && <div><dt>Fee</dt><dd>{money(quote.fee_minor)}</dd></div>}
          {quote.deposit_minor > 0 && <div><dt>Deposit</dt><dd>{money(quote.deposit_minor)}</dd></div>}
        </dl>
      </section>
    </div>
  );
}
