"use client";

import { useRef, useState } from "react";
import { StaffFeedback, useStaffMutation } from "../staff-form";

export function CancelReservation({ reference, confirmed }: { reference: string; confirmed: boolean }) {
  const [confirming, setConfirming] = useState(false);
  const trigger = useRef<HTMLButtonElement>(null);
  const { pending, problem, status, submit } = useStaffMutation();

  return (
    <>
      {confirmed && (
        <div className="reservation-cancellation">
          <button ref={trigger} className="button button-secondary" type="button"
            disabled={pending} aria-expanded={confirming} aria-controls="cancel-confirmation"
            onClick={() => setConfirming(!confirming)}>
            Cancel reservation
          </button>
          <div id="cancel-confirmation" hidden={!confirming}>
            <p>Cancelling releases these dates if the vehicle is otherwise available.</p>
            <div className="staff-actions">
              <button className="button button-secondary reservation-cancel" type="button" disabled={pending}
                onClick={() => submit(`/api/staff/reservations/${encodeURIComponent(reference)}/cancel`, "POST", undefined, () => "Reservation cancelled.")}>
                {pending ? "Cancelling…" : "Confirm cancellation"}
              </button>
              <button className="button button-secondary" type="button" disabled={pending} onClick={() => {
                setConfirming(false);
                trigger.current?.focus();
              }}>
                Keep reservation
              </button>
            </div>
          </div>
        </div>
      )}
      <StaffFeedback problem={problem} status={status} />
    </>
  );
}
