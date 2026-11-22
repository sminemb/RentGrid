"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { apiRequest } from "@/lib/api";
import { Icon, LoadingRows, RentalSteps } from "../../../rental-ui";
import { money, type Trip, TripDetails } from "../../rental-details";

type Booking = Trip & {
  reference: string;
  status: "confirmed" | "cancelled";
  pickup_instructions: string | null;
  cancellation_terms: string | null;
};

type Confirmation = { booking: Booking } | { error: "unavailable" | "retry" };

export function BookingConfirmation({ reference }: { reference: string }) {
  const [confirmation, setConfirmation] = useState<Confirmation>();
  const [requestNumber, setRequestNumber] = useState(0);
  const heading = useRef<HTMLHeadingElement>(null);

  useEffect(() => {
    const controller = new AbortController();
    async function load() {
      let result: Confirmation;
      try {
        const response = await apiRequest(`/api/bookings/${encodeURIComponent(reference)}`, {
          cache: "no-store",
          signal: controller.signal,
        });
        result = response.ok ? { booking: await response.json() } : {
          error: response.status === 404 ? "unavailable" : "retry",
        };
      } catch {
        result = { error: "retry" };
      }

      if (!controller.signal.aborted) setConfirmation(result);
    }
    void load();
    return () => controller.abort();
  }, [reference, requestNumber]);

  useEffect(() => {
    if (confirmation) heading.current?.focus();
  }, [confirmation]);

  const booking = confirmation && "booking" in confirmation ? confirmation.booking : undefined;
  const error = confirmation && "error" in confirmation ? confirmation.error : undefined;
  const confirmed = booking?.status === "confirmed";

  return (
    <>
      <RentalSteps current="confirmation" />
      <header className="review-heading confirmation-heading">
        {confirmed && <span className="confirmed-icon"><Icon name="check" /></span>}
        <div>
          <h1 ref={heading} tabIndex={-1}>
            {booking ? (confirmed ? "Booking confirmed" : "Booking cancelled") : "Booking confirmation"}
          </h1>
          {confirmed && <p>Save your booking reference for pickup.</p>}
        </div>
      </header>
      {!confirmation && <LoadingRows>Loading your confirmation…</LoadingRows>}
      {error && (
        <div className="state-message" role="alert">
          <p>{error === "unavailable"
            ? "Open this confirmation in the browser session you used to book."
            : "We couldn’t load your confirmation."}</p>
          {error === "retry" && (
            <button className="button button-secondary" type="button" onClick={() => {
              setConfirmation(undefined);
              setRequestNumber((number) => number + 1);
            }}>Try again</button>
          )}
        </div>
      )}
      {booking && (
        <>
          <div className="review-grid">
            <TripDetails trip={booking} pickupInstructions={confirmed ? booking.pickup_instructions : null} />
            <section className="receipt confirmation-receipt" aria-labelledby="reference-heading">
              <div className="receipt-header">
                <h2 id="reference-heading">Booking reference</h2>
                <p className="booking-reference">{booking.reference}</p>
              </div>
              <dl className="price-breakdown">
                <div>
                  <dt>{confirmed ? "Amount due at pickup" : "Original booking total"}</dt>
                  <dd>{money(booking.amount_due_minor, booking.currency)}</dd>
                </div>
              </dl>
            </section>
          </div>
          {confirmed && (
            <p className="confirmation-note">
              {booking.cancellation_terms || "Contact the rental business to request changes or cancellations."}
            </p>
          )}
        </>
      )}
      <Link className="back-link confirmation-back" href="/"><Icon name="back" /> Back to vehicle search</Link>
    </>
  );
}
