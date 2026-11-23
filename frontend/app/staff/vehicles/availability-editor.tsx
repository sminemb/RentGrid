"use client";

import Link from "next/link";
import { type FormEvent } from "react";
import type { CalendarRange, StaffCalendar } from "./fleet-types";
import { StaffFeedback, StaffField, useStaffMutation } from "../staff-form";

const dateFormatter = new Intl.DateTimeFormat("en", { dateStyle: "medium", timeZone: "UTC" });

function CalendarDate({ date }: { date: string }) {
  return <time dateTime={date}>{dateFormatter.format(new Date(date))}</time>;
}

function RangeLabel({ starts_on, ends_on }: CalendarRange) {
  return (
    <strong>
      <CalendarDate date={starts_on} />
      {ends_on !== starts_on && <> to <CalendarDate date={ends_on} /></>}
    </strong>
  );
}

export function AvailabilityEditor({ vehicleId, calendar, showPastBlocks }: {
  vehicleId: number;
  calendar: StaffCalendar;
  showPastBlocks: boolean;
}) {
  const { pending, problem, status, submit } = useStaffMutation();
  const path = `/api/staff/vehicles/${vehicleId}/availability/blocks` as const;
  const ranges = [
    ...calendar.blocks,
    ...calendar.bookings,
  ].sort((a, b) => {
    const aPast = a.ends_on < calendar.today;
    const bPast = b.ends_on < calendar.today;
    return Number(aPast) - Number(bPast) || a.starts_on.localeCompare(b.starts_on);
  });

  async function addBlock(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    await submit(path, "POST", Object.fromEntries(new FormData(form)), () => {
      form.reset();
      return "Dates blocked.";
    });
  }

  return (
    <>
      <form className="block-form" method="post" onSubmit={addBlock} aria-label="Block dates">
        <p>Block dates for maintenance or other downtime. Both dates are included.</p>
        <div className="block-fields">
          <StaffField label="Start date" name="starts_on" type="date" min={calendar.today} error={problem.errors?.starts_on?.[0]} disabled={pending} required />
          <StaffField label="End date" name="ends_on" type="date" min={calendar.today} error={problem.errors?.ends_on?.[0]} disabled={pending} required />
        </div>
        <button className="button" type="submit" disabled={pending}>Block dates</button>
      </form>
      <StaffFeedback problem={problem} status={status} />
      <div className="calendar-agenda">
        <h3>Unavailable dates</h3>
        {ranges.length === 0 ? (
          <p>No confirmed bookings or blocked dates.</p>
        ) : (
          <ul role="list">
            {ranges.map((range) => (
              <li className="calendar-row" key={"id" in range ? `block-${range.id}` : range.reference}>
                <div>
                  <RangeLabel {...range} />
                  <p>{"id" in range ? (range.ends_on < calendar.today ? "Past block" : "Staff block") : "Confirmed booking"}</p>
                </div>
                {"id" in range && (
                  <button
                    className="action-link"
                    type="button"
                    aria-label={`Remove block for ${range.starts_on}${range.ends_on !== range.starts_on ? ` to ${range.ends_on}` : ""}`}
                    disabled={pending}
                    onClick={() => submit(`${path}/${range.id}`, "DELETE", undefined, () => "Block removed.")}
                  >
                    Remove block
                  </button>
                )}
                {"reference" in range && (
                  <Link className="action-link" href={`/staff/reservations?${new URLSearchParams({ reference: range.reference })}`}>
                    {range.reference}
                  </Link>
                )}
              </li>
            ))}
          </ul>
        )}
        <Link className="button button-secondary" prefetch={false}
          href={`/staff/vehicles/${vehicleId}${showPastBlocks ? "" : "?include_past_blocks=1"}#availability`}>
          {showPastBlocks ? "Hide past blocks" : "Show past blocks"}
        </Link>
      </div>
    </>
  );
}
