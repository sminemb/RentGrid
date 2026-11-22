"use client";

import { type FormEvent, useState } from "react";
import { apiRequest } from "@/lib/api";
import type { ApiProblem } from "@/lib/rentals";
import { Icon } from "../../rental-ui";

export function StaffSignIn() {
  const [pending, setPending] = useState(false);
  const [error, setError] = useState("");

  async function signIn(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (pending) return;

    const credentials = Object.fromEntries(new FormData(event.currentTarget));
    setPending(true);
    setError("");

    try {
      const session = await apiRequest("/api/session", { cache: "no-store" });
      if (!session.ok) throw new Error("Staff sign-in unavailable.");

      const response = await apiRequest("/api/staff/sign-in", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(credentials),
      });

      if (response.ok) {
        window.location.replace("/staff");
        return;
      }

      if (response.status === 429) {
        setError("Too many sign-in attempts. Wait a minute and try again.");
      } else if (response.status === 422) {
        const problem: ApiProblem = await response.json();
        setError(problem.message ?? "Check your email and password and try again.");
      } else {
        throw new Error("Staff sign-in unavailable.");
      }
    } catch {
      setError("We couldn’t sign you in. Try again in a moment.");
    }
    setPending(false);
  }

  return (
    <form className="trip-panel staff-form" method="post" onSubmit={signIn}>
      <div className="field">
        <label htmlFor="staff-email">Email address</label>
        <input id="staff-email" name="email" type="email" autoComplete="username" maxLength={254} required />
      </div>
      <div className="field">
        <label htmlFor="staff-password">Password</label>
        <input id="staff-password" name="password" type="password" autoComplete="current-password" maxLength={255} required />
      </div>
      {error && <p className="error" role="alert">{error}</p>}
      <button className="button" type="submit" disabled={pending}>
        {pending ? "Signing in…" : "Sign in"} <Icon name="arrow" />
      </button>
    </form>
  );
}
