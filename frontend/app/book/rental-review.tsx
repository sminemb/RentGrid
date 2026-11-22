"use client";

import Link from "next/link";
import { type FormEvent, useEffect, useRef, useState } from "react";
import { apiRequest } from "@/lib/api";
import type { ApiProblem, RentalDates, Vehicle } from "@/lib/rentals";
import { Icon, LoadingRows, RentalSteps } from "../rental-ui";

type Trip = RentalDates & { pickup_location: string; currency: string; amount_due_minor: number };
type Quote = Trip & {
  vehicle: Vehicle;
  quote_token: string;
  days: number;
  rental_minor: number;
  fee_minor: number;
  deposit_minor: number;
};
type Confirmation = Trip & { reference: string };
type Review = { quote: Quote } | { error: string; retry: boolean };
type BookingProblem = { message: string; next?: "refresh" | "search"; errors?: ApiProblem["errors"] };

const contactFields = [
  { name: "name", label: "Full name", type: "text", maxLength: 120, autoComplete: "name" },
  { name: "email", label: "Email address", type: "email", maxLength: 254, autoComplete: "email" },
  { name: "phone", label: "Phone number", type: "tel", maxLength: 40, autoComplete: "tel" },
] as const;

const dateFormatter = new Intl.DateTimeFormat("en", { dateStyle: "medium", timeZone: "UTC" });

function money(amount: number, currencyCode: string) {
  const currency = new Intl.NumberFormat("en", { style: "currency", currency: currencyCode, currencyDisplay: "code" });
  const digits = currency.resolvedOptions().maximumFractionDigits ?? 2;
  return currency.format(amount / 10 ** digits);
}

async function loadQuote(vehicleId: string, dates: RentalDates, signal: AbortSignal): Promise<Review> {
  try {
    const session = await apiRequest("/api/session", { signal, cache: "no-store" });
    if (!session.ok) throw new Error("Unable to open rental session.");

    const response = await apiRequest("/api/booking-quotes", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ ...dates, vehicle_id: vehicleId }),
      signal,
    });
    if (response.ok) return { quote: await response.json() };

    if ([409, 422, 503].includes(response.status)) {
      const problem: ApiProblem = await response.json();
      const error = Object.values(problem.errors ?? {}).flat()[0] ?? problem.message;
      if (error) return { error, retry: response.status === 503 };
    }
  } catch {
    // The caller ignores the result when the selection has been abandoned.
  }
  return { error: "We couldn’t load your quote. Please try again.", retry: true };
}

export function RentalReview({ vehicleId, dates, searchUrl }: { vehicleId: string; dates: RentalDates; searchUrl: string }) {
  const [review, setReview] = useState<Review>();
  const [quoteVersion, setQuoteVersion] = useState(0);
  const [bookingProblem, setBookingProblem] = useState<BookingProblem>();
  const [confirmation, setConfirmation] = useState<Confirmation>();
  const [submitting, setSubmitting] = useState(false);
  const [contact, setContact] = useState({ name: "", email: "", phone: "" });
  const bookingRequest = useRef<AbortController | null>(null);
  const { pickup_date, return_date } = dates;

  useEffect(() => {
    const controller = new AbortController();
    void loadQuote(vehicleId, { pickup_date, return_date }, controller.signal).then((result) => {
      if (!controller.signal.aborted) setReview(result);
    });
    return () => controller.abort();
  }, [vehicleId, pickup_date, return_date, quoteVersion]);

  useEffect(() => () => bookingRequest.current?.abort(), []);

  function refreshQuote() {
    setReview(undefined);
    setBookingProblem(undefined);
    setQuoteVersion((version) => version + 1);
  }

  async function submitBooking(event: FormEvent<HTMLFormElement>, quote: Quote) {
    event.preventDefault();
    if (bookingRequest.current || bookingProblem?.next) return;

    const controller = new AbortController();
    bookingRequest.current = controller;
    setSubmitting(true);
    setBookingProblem(undefined);

    try {
      const response = await apiRequest("/api/bookings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ...contact, quote_token: quote.quote_token }),
        signal: controller.signal,
      });
      if (response.ok) {
        const booked: Confirmation = await response.json();
        if (!controller.signal.aborted) setConfirmation(booked);
        return;
      }

      if ([409, 419, 422].includes(response.status)) {
        const problem: ApiProblem & { code?: string } = await response.json();
        if (controller.signal.aborted) return;

        const next = problem.code === "vehicle_unavailable" ? "search" : undefined;
        const needsQuote = response.status === 419 || problem.code === "price_changed" || !!problem.errors?.quote_token;
        const message = needsQuote
          ? problem.errors?.quote_token?.[0] ?? (response.status === 419 ? "Your session expired. Review an updated quote to continue." : problem.message)
          : problem.message;

        const hasContactErrors = contactFields.some(({ name }) => problem.errors?.[name]?.length);
        setBookingProblem({
          message: hasContactErrors ? "Check the highlighted details and try again." : message ?? "Check your details and try again.",
          next: needsQuote ? "refresh" : next,
          errors: problem.errors,
        });
        return;
      }
      throw new Error("Unable to confirm booking.");
    } catch {
      if (!controller.signal.aborted) {
        setBookingProblem({ message: "We couldn’t verify whether the booking went through. Check with the rental business before booking again." });
      }
    } finally {
      if (!controller.signal.aborted) setSubmitting(false);
      bookingRequest.current = null;
    }
  }

  if (confirmation && review && "quote" in review) {
    return <BookingConfirmation booking={confirmation} vehicle={review.quote.vehicle} />;
  }

  return (
    <>
      <RentalSteps current="review" />
      <header className="review-heading">
        <Link className="back-link" href={searchUrl}><Icon name="back" /> Back to vehicle search</Link>
        <h1>Review your rental</h1>
      </header>
      {!review && <LoadingRows>Loading your quote…</LoadingRows>}
      {review && "error" in review && (
        <div className="state-message" role="alert">
          <p className="error">{review.error}</p>
          {review.retry && <button type="button" className="button button-secondary" onClick={refreshQuote}>Try again</button>}
        </div>
      )}
      {review && "quote" in review && (
        <div className="review-grid review-grid-with-guest">
          <TripDetails trip={review.quote} vehicle={review.quote.vehicle} />
          <PriceReceipt quote={review.quote} />
          <section className="guest-panel" aria-labelledby="guest-heading">
            <h2 id="guest-heading">Your contact details</h2>
            <form className="guest-form" onSubmit={(event) => void submitBooking(event, review.quote)}>
              {contactFields.map(({ name, label, ...input }) => {
                const error = bookingProblem?.errors?.[name]?.[0];
                return (
                  <div className="field" key={name}>
                    <label htmlFor={name}>{label}</label>
                    <input
                      {...input}
                      id={name}
                      name={name}
                      value={contact[name]}
                      onChange={(event) => setContact({ ...contact, [name]: event.target.value })}
                      required
                      aria-invalid={error ? true : undefined}
                      aria-describedby={error ? `${name}-error` : undefined}
                    />
                    {error && <p className="error" id={`${name}-error`}>{error}</p>}
                  </div>
                );
              })}
              {bookingProblem && <p className="error booking-error" role="alert">{bookingProblem.message}</p>}
              <div className="guest-action">
                {bookingProblem?.next === "search" ? (
                  <Link className="button" href={searchUrl}>Find another vehicle <Icon name="arrow" /></Link>
                ) : bookingProblem?.next === "refresh" ? (
                  <button className="button" type="button" onClick={refreshQuote}>Review updated quote <Icon name="arrow" /></button>
                ) : (
                  <button className="button" type="submit" disabled={submitting}>{submitting ? "Confirming booking…" : "Confirm booking"} <Icon name="arrow" /></button>
                )}
              </div>
            </form>
          </section>
        </div>
      )}
    </>
  );
}

function TripDetails({ trip, vehicle }: { trip: Trip; vehicle: Vehicle }) {
  return (
    <section className="trip-panel" aria-labelledby="vehicle-heading">
      <div className="trip-vehicle">
        <div className="vehicle-icon"><Icon name="car" /></div>
        <div className="vehicle-name">
          <h2 id="vehicle-heading">{vehicle.display_label}</h2>
          <p>{vehicle.make} {vehicle.model}</p>
        </div>
      </div>
      <dl className="itinerary">
        <div><dt>Pickup date</dt><dd><time dateTime={trip.pickup_date}>{dateFormatter.format(new Date(trip.pickup_date))}</time></dd></div>
        <div><dt>Return date</dt><dd><time dateTime={trip.return_date}>{dateFormatter.format(new Date(trip.return_date))}</time></dd></div>
        <div className="pickup-location"><dt><Icon name="pin" /> Pickup location</dt><dd>{trip.pickup_location}</dd></div>
      </dl>
    </section>
  );
}

function PriceReceipt({ quote }: { quote: Quote }) {
  return (
    <section className="receipt price" aria-labelledby="price-heading">
      <div className="receipt-header">
        <h2 id="price-heading">Amount due at pickup</h2>
        <p className="total">{money(quote.amount_due_minor, quote.currency)}</p>
      </div>
      <dl className="price-breakdown">
        <div>
          <dt>
            <span>Rental for {quote.days} {quote.days === 1 ? "day" : "days"}</span>
            <span className="billing-note">{quote.days === 1 ? "Same-day rentals count as one day." : "Includes pickup and return dates."}</span>
          </dt>
          <dd>{money(quote.rental_minor, quote.currency)}</dd>
        </div>
        {quote.fee_minor > 0 && <div><dt>Fee</dt><dd>{money(quote.fee_minor, quote.currency)}</dd></div>}
        {quote.deposit_minor > 0 && <div><dt>Deposit</dt><dd>{money(quote.deposit_minor, quote.currency)}</dd></div>}
      </dl>
    </section>
  );
}

function BookingConfirmation({ booking, vehicle }: { booking: Confirmation; vehicle: Vehicle }) {
  const heading = useRef<HTMLHeadingElement>(null);
  useEffect(() => heading.current?.focus(), []);

  return (
    <>
      <RentalSteps current="confirmation" />
      <header className="review-heading confirmation-heading">
        <span className="confirmed-icon"><Icon name="check" /></span>
        <div>
          <h1 ref={heading} tabIndex={-1}>Booking confirmed</h1>
          <p>Save your booking reference for pickup.</p>
        </div>
      </header>
      <div className="review-grid">
        <TripDetails trip={booking} vehicle={vehicle} />
        <section className="receipt confirmation-receipt" aria-labelledby="reference-heading">
          <div className="receipt-header">
            <h2 id="reference-heading">Booking reference</h2>
            <p className="booking-reference">{booking.reference}</p>
          </div>
          <dl className="price-breakdown">
            <div><dt>Amount due at pickup</dt><dd>{money(booking.amount_due_minor, booking.currency)}</dd></div>
          </dl>
        </section>
      </div>
      <Link className="back-link confirmation-back" href="/"><Icon name="back" /> Back to vehicle search</Link>
    </>
  );
}
