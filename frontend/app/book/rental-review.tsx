"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { type FormEvent, useEffect, useRef, useState } from "react";
import { apiRequest } from "@/lib/api";
import type { ApiProblem, RentalDates } from "@/lib/rentals";
import { Icon, LoadingRows, RentalSteps } from "../rental-ui";
import { BookingRecoveryNotice } from "./booking-recovery-notice";
import { pendingBooking, type Quote } from "./pending-booking";
import { money, TripDetails } from "./rental-details";

type Contact = { name: string; email: string; phone: string };
type Review = { quote: Quote; retrying?: boolean } | { error: string; retry: boolean; sessionExpired?: boolean } | { pendingHref: string };
type BookingProblem = { message: string; next?: "refresh" | "search" | "dates"; errors?: ApiProblem["errors"] };
const blankContact: Contact = { name: "", email: "", phone: "" };

const contactFields = [
  { name: "name", label: "Full name", type: "text", maxLength: 120, autoComplete: "name" },
  { name: "email", label: "Email address", type: "email", maxLength: 254, autoComplete: "email" },
  { name: "phone", label: "Phone number", type: "tel", maxLength: 40, autoComplete: "tel" },
] as const;

function bookingConfirmation(reference: string) {
  return `/book/confirmation/${encodeURIComponent(reference)}`;
}

async function loadReview(vehicleId: string, dates: RentalDates, signal: AbortSignal, fallback?: Quote): Promise<Review | { reference: string; quoteToken: string }> {
  pendingBooking.restore();
  const pending = pendingBooking.read() ?? fallback;
  if (pending && (String(pending.vehicle.id) !== vehicleId || pending.pickup_date !== dates.pickup_date || pending.return_date !== dates.return_date)) {
    return { pendingHref: pendingBooking.href(pending) };
  }

  try {
    const session = await apiRequest("/api/session", { signal, cache: "no-store" });
    if (!session.ok) throw new Error("Unable to open rental session.");

    if (pending) {
      const recovery = await apiRequest("/api/bookings/recover", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ quote_token: pending.quote_token }),
        signal,
      });
      if (recovery.status === 204) return { quote: pending, retrying: true };
      if (recovery.ok) return { reference: (await recovery.json()).reference, quoteToken: pending.quote_token };
      if (recovery.status !== 422) throw new Error("Unable to check the booking.");

      if (!signal.aborted) pendingBooking.clear(pending.quote_token);
      return {
        error: "This session can no longer recover your previous booking. Contact the rental desk to check its status before booking again.",
        retry: true,
        sessionExpired: true,
      };
    }

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
  return { error: pending ? "We couldn’t check your previous booking. Please try again." : "We couldn’t load your quote. Please try again.", retry: true };
}

export function RentalReview({ vehicleId, dates, searchUrl }: { vehicleId: string; dates: RentalDates; searchUrl: string }) {
  const router = useRouter();
  const [review, setReview] = useState<Review>();
  const [quoteVersion, setQuoteVersion] = useState(0);
  const [bookingProblem, setBookingProblem] = useState<BookingProblem>();
  const [submitting, setSubmitting] = useState(false);
  const [needsRecovery, setNeedsRecovery] = useState(false);
  const [contact, setContact] = useState(blankContact);
  const bookingRequest = useRef<AbortController | null>(null);
  const unresolvedQuote = useRef<Quote | undefined>(undefined);
  const { pickup_date, return_date } = dates;

  useEffect(() => {
    const controller = new AbortController();
    void loadReview(vehicleId, { pickup_date, return_date }, controller.signal, unresolvedQuote.current).then((result) => {
      if (controller.signal.aborted) return;
      if ("reference" in result) {
        pendingBooking.clear(result.quoteToken);
        router.replace(bookingConfirmation(result.reference));
        return;
      }
      if ("error" in result && result.sessionExpired) {
        unresolvedQuote.current = undefined;
        setContact(blankContact);
      }
      setReview(result);
    });
    return () => controller.abort();
  }, [vehicleId, pickup_date, return_date, quoteVersion, router]);

  useEffect(() => () => bookingRequest.current?.abort(), []);

  function refreshQuote() {
    setReview(undefined);
    setBookingProblem(undefined);
    setNeedsRecovery(false);
    setQuoteVersion((version) => version + 1);
  }

  async function submitBooking(event: FormEvent<HTMLFormElement>, quote: Quote) {
    event.preventDefault();
    if (bookingRequest.current || bookingProblem?.next || needsRecovery) return;
    const previous = pendingBooking.read();
    if (previous && previous.quote_token !== quote.quote_token) {
      setReview({ pendingHref: pendingBooking.href(previous) });
      return;
    }

    const controller = new AbortController();
    bookingRequest.current = controller;
    unresolvedQuote.current = quote;
    pendingBooking.save(quote);
    setSubmitting(true);
    setBookingProblem(undefined);
    let booked = false;
    let rejected = false;

    try {
      const response = await apiRequest("/api/bookings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ...contact, quote_token: quote.quote_token }),
        signal: controller.signal,
      });
      if (response.ok) {
        const reference: unknown = (await response.json())?.reference;
        if (!controller.signal.aborted && typeof reference === "string" && reference) {
          router.replace(bookingConfirmation(reference));
          pendingBooking.clear(quote.quote_token);
          booked = true;
        }
        return;
      }

      if ([409, 419, 422].includes(response.status)) {
        const problem: ApiProblem = await response.json();
        if (controller.signal.aborted) return;

        const datesInvalid = !!(problem.errors?.pickup_date || problem.errors?.return_date);
        const next = datesInvalid ? "dates" : problem.code === "vehicle_unavailable" ? "search" : undefined;
        const needsQuote = response.status === 419 || problem.code === "price_changed" ||
          problem.code === "vehicle_changed" || !!problem.errors?.quote_token;
        const message = needsQuote
          ? problem.errors?.quote_token?.[0] ?? (response.status === 419 ? "Your session expired. Review an updated quote to continue." : problem.message)
          : problem.errors?.pickup_date?.[0] ?? problem.errors?.return_date?.[0] ?? problem.message;

        const hasContactErrors = contactFields.some(({ name }) => problem.errors?.[name]?.length);
        rejected = true;
        unresolvedQuote.current = undefined;
        if (response.status === 419 || problem.errors?.quote_token) setContact(blankContact);
        pendingBooking.clear(quote.quote_token);
        setReview({ quote });
        setBookingProblem({
          message: hasContactErrors ? "Check the highlighted details and try again." : message ?? "Check your details and try again.",
          next: needsQuote ? "refresh" : next,
          errors: problem.errors,
        });
      } else if (response.status === 429 && !controller.signal.aborted) {
        setBookingProblem({ message: "Too many booking attempts. Wait a minute before trying to confirm again." });
      }
    } catch {
      // Keep the original submission until we get a confirmation or a definite rejection.
    } finally {
      if (!booked) {
        if (!controller.signal.aborted) {
          setSubmitting(false);
          setNeedsRecovery(!rejected);
        }
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
      {review && "pendingHref" in review && <BookingRecoveryNotice href={review.pendingHref} />}
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
            {review.retrying && (
              <p>No confirmation was found. Enter your contact details to retry this booking, or{" "}
                <Link className="back-link" href={searchUrl} onNavigate={() => pendingBooking.clear(review.quote.quote_token)}>
                  choose another vehicle
                </Link>.
              </p>
            )}
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
                      value={contact[name]}
                      onChange={(event) => setContact({ ...contact, [name]: event.target.value })}
                      readOnly={submitting || needsRecovery}
                      required
                      aria-invalid={error ? true : undefined}
                      aria-describedby={error ? `${name}-error` : undefined}
                    />
                    {error && <p className="error" id={`${name}-error`}>{error}</p>}
                  </div>
                );
              })}
              {bookingProblem && <p className="error booking-error" role="alert">{bookingProblem.message}</p>}
              {needsRecovery && (
                <p className="booking-error" role="status">Your booking may already be confirmed. Check its status before trying again.</p>
              )}
              <div className="guest-action">
                {bookingProblem?.next === "search" || bookingProblem?.next === "dates" ? (
                  <Link className="button" href={searchUrl}>
                    {bookingProblem.next === "dates" ? "Change rental dates" : "Find another vehicle"} <Icon name="arrow" />
                  </Link>
                ) : bookingProblem?.next === "refresh" ? (
                  <button className="button" type="button" onClick={refreshQuote}>Review updated quote <Icon name="arrow" /></button>
                ) : needsRecovery ? (
                  <button className="button" type="button" onClick={refreshQuote}>Recover confirmation <Icon name="arrow" /></button>
                ) : (
                  <button className="button" type="submit" disabled={submitting}>
                    {submitting ? "Confirming booking…" : review.retrying ? "Retry booking" : "Confirm booking"}
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
