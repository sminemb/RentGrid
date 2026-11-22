"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { type FormEvent, useEffect, useRef, useState } from "react";
import { apiRequest } from "@/lib/api";
import type { ApiProblem, RentalDates } from "@/lib/rentals";
import { Icon, LoadingRows, RentalSteps } from "../rental-ui";
import { money, type Trip, TripDetails } from "./rental-details";

type Quote = Trip & {
  quote_token: string;
  days: number;
  rental_minor: number;
  fee_minor: number;
  deposit_minor: number;
};
type Contact = { name: string; email: string; phone: string };
type PendingReview = { quote: Quote; pendingContact: Contact };
type Review = { quote: Quote; pendingContact?: Contact } | { error: string; retry: boolean };
type BookingProblem = { message: string; next?: "refresh" | "search"; errors?: ApiProblem["errors"] };

const contactFields = [
  { name: "name", label: "Full name", type: "text", maxLength: 120, autoComplete: "name" },
  { name: "email", label: "Email address", type: "email", maxLength: 254, autoComplete: "email" },
  { name: "phone", label: "Phone number", type: "tel", maxLength: 40, autoComplete: "tel" },
] as const;

class PendingBooking {
  private readonly key: string;

  constructor(vehicleId: string, { pickup_date, return_date }: RentalDates) {
    this.key = `rentgrid:pending-booking:${JSON.stringify([vehicleId, pickup_date, return_date])}`;
  }

  read(): PendingReview | undefined {
    try {
      const saved = sessionStorage.getItem(this.key);
      if (!saved) return;

      const attempt: PendingReview | null = JSON.parse(saved);
      if (typeof attempt?.quote?.quote_token === "string" &&
        contactFields.every(({ name }) => typeof attempt.pendingContact?.[name] === "string")) {
        return attempt;
      }
    } catch {
      // A missing or unreadable attempt can fall back to a fresh quote.
    }
  }

  save(quote: Quote, pendingContact: Contact) {
    try {
      sessionStorage.setItem(this.key, JSON.stringify({ quote, pendingContact }));
    } catch {
      // The open checkout can still recover the attempt when tab storage is unavailable.
    }
  }

  clear() {
    try {
      sessionStorage.removeItem(this.key);
    } catch {
      // Tab storage may be unavailable.
    }
  }
}

async function loadReview(vehicleId: string, dates: RentalDates, signal: AbortSignal): Promise<Review> {
  const pending = new PendingBooking(vehicleId, dates).read();
  if (pending) return pending;

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
  const router = useRouter();
  const [review, setReview] = useState<Review>();
  const [quoteVersion, setQuoteVersion] = useState(0);
  const [bookingProblem, setBookingProblem] = useState<BookingProblem>();
  const [submitting, setSubmitting] = useState(false);
  const [contact, setContact] = useState<Contact>({ name: "", email: "", phone: "" });
  const bookingRequest = useRef<AbortController | null>(null);
  const { pickup_date, return_date } = dates;
  const pendingContact = review && "quote" in review ? review.pendingContact : undefined;

  useEffect(() => {
    const controller = new AbortController();
    void loadReview(vehicleId, { pickup_date, return_date }, controller.signal).then((result) => {
      if (controller.signal.aborted) return;
      if ("quote" in result && result.pendingContact) setContact(result.pendingContact);
      setReview(result);
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
    const pending = new PendingBooking(vehicleId, dates);
    const submittedContact = pendingContact ?? contact;
    bookingRequest.current = controller;
    pending.save(quote, submittedContact);
    setReview({ quote, pendingContact: submittedContact });
    setSubmitting(true);
    setBookingProblem(undefined);
    let booked = false;

    try {
      const response = await apiRequest("/api/bookings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ...submittedContact, quote_token: quote.quote_token }),
        signal: controller.signal,
      });
      if (response.ok) {
        const reference: unknown = (await response.json())?.reference;
        if (!controller.signal.aborted && typeof reference === "string" && reference) {
          router.replace(`/book/confirmation/${encodeURIComponent(reference)}`);
          pending.clear();
          booked = true;
        }
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
        pending.clear();
        setReview({ quote });
        setBookingProblem({
          message: hasContactErrors ? "Check the highlighted details and try again." : message ?? "Check your details and try again.",
          next: needsQuote ? "refresh" : next,
          errors: problem.errors,
        });
      }
    } catch {
      // Keep the original submission until we get a confirmation or a definite rejection.
    } finally {
      if (!booked) {
        if (!controller.signal.aborted) setSubmitting(false);
        bookingRequest.current = null;
      }
    }
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
          <TripDetails trip={review.quote} />
          <PriceReceipt quote={review.quote} />
          <section className="guest-panel" aria-labelledby="guest-heading">
            <h2 id="guest-heading">Your contact details</h2>
            <form className="guest-form" method="post" onSubmit={(event) => void submitBooking(event, review.quote)}>
              {contactFields.map(({ name, label, ...input }) => {
                const error = bookingProblem?.errors?.[name]?.[0];
                return (
                  <div className="field" key={name}>
                    <label htmlFor={name}>{label}</label>
                    <input
                      {...input}
                      id={name}
                      name={name}
                      value={(pendingContact ?? contact)[name]}
                      onChange={(event) => setContact({ ...contact, [name]: event.target.value })}
                      readOnly={!!pendingContact}
                      required={!pendingContact}
                      aria-invalid={error ? true : undefined}
                      aria-describedby={error ? `${name}-error` : undefined}
                    />
                    {error && <p className="error" id={`${name}-error`}>{error}</p>}
                  </div>
                );
              })}
              {bookingProblem && <p className="error booking-error" role="alert">{bookingProblem.message}</p>}
              {pendingContact && !submitting && (
                <p className="error booking-error" role="alert">Your booking may already be saved. Recover your confirmation using the details you submitted.</p>
              )}
              <div className="guest-action">
                {bookingProblem?.next === "search" ? (
                  <Link className="button" href={searchUrl}>Find another vehicle <Icon name="arrow" /></Link>
                ) : bookingProblem?.next === "refresh" ? (
                  <button className="button" type="button" onClick={refreshQuote}>Review updated quote <Icon name="arrow" /></button>
                ) : (
                  <button className="button" type="submit" disabled={submitting}>
                    {submitting ? "Confirming booking…" : pendingContact ? "Recover confirmation" : "Confirm booking"}
                    <Icon name="arrow" />
                  </button>
                )}
              </div>
            </form>
          </section>
        </div>
      )}
    </>
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
