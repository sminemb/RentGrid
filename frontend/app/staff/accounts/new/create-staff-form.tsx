"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { type FormEvent, useTransition } from "react";
import { StaffFeedback, StaffField, useStaffMutation } from "../../staff-form";

export function CreateStaffForm() {
  const router = useRouter();
  const [navigating, startNavigation] = useTransition();
  const { pending: saving, problem, status, submit } = useStaffMutation();
  const pending = saving || navigating;
  const errors = problem.errors;

  function createStaff(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (pending) return;
    const account = Object.fromEntries(new FormData(event.currentTarget));

    void submit("/api/staff/accounts", "POST", account, () => {
      startNavigation(() => router.replace("/staff?staff_created=1"));
      return "";
    });
  }

  return (
    <form className="trip-panel staff-form staff-account-form" method="post" onSubmit={createStaff} aria-busy={pending}>
      <fieldset className="staff-account-section">
        <legend>Account details</legend>
        <div className="staff-account-fields">
          <StaffField
            name="name"
            label="Full name"
            autoComplete="name"
            maxLength={255}
            error={errors?.name?.[0]}
            disabled={pending}
            required
          />
          <StaffField
            name="email"
            label="Email address"
            type="email"
            autoComplete="email"
            maxLength={254}
            error={errors?.email?.[0]}
            disabled={pending}
            required
          />
        </div>
      </fieldset>

      <fieldset className="staff-account-section">
        <legend>Password</legend>
        <div className="staff-account-fields">
          <StaffField
            name="password"
            label="Initial password"
            type="password"
            autoComplete="new-password"
            minLength={12}
            maxLength={72}
            hint="Use at least 12 characters. The password can be up to 72 bytes."
            error={errors?.password?.[0]}
            disabled={pending}
            required
          />
          <StaffField
            name="password_confirmation"
            label="Confirm password"
            type="password"
            autoComplete="new-password"
            maxLength={72}
            error={errors?.password_confirmation?.[0]}
            disabled={pending}
            required
          />
        </div>
      </fieldset>

      <div className="staff-account-footer">
        <StaffFeedback problem={problem} status={status} showValidationMessage />
        <div className="staff-account-actions">
          <button className="button" type="submit" disabled={pending}>
            {pending ? "Creating account…" : "Create staff account"}
          </button>
          <Link className="back-link" href="/staff">Cancel</Link>
        </div>
      </div>
    </form>
  );
}
