"use client";

import { type FormEvent } from "react";
import { StaffFeedback, StaffField, useStaffMutation } from "../../staff-form";

export function CreateStaffForm() {
  const { pending, problem, status, submit } = useStaffMutation();
  const errors = problem.errors;

  function createStaff(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const account = Object.fromEntries(new FormData(form));

    void submit("/api/staff/accounts", "POST", account, () => {
      form.reset();
      return "Staff account created. The new staff member can now sign in.";
    });
  }

  return (
    <form className="trip-panel staff-form" method="post" onSubmit={createStaff} aria-busy={pending}>
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
      <StaffFeedback problem={problem} status={status} />
      <button className="button" type="submit" disabled={pending}>
        {pending ? "Creating account…" : "Create staff account"}
      </button>
    </form>
  );
}
