"use client";

import { useState } from "react";
import { apiRequest } from "@/lib/api";

export function StaffSignOut() {
  const [pending, setPending] = useState(false);
  const [error, setError] = useState("");

  async function signOut() {
    setPending(true);
    setError("");

    try {
      const session = await apiRequest("/api/session", { cache: "no-store" });
      if (!session.ok) throw new Error("Session unavailable.");

      const response = await apiRequest("/api/staff/sign-out", { method: "POST" });
      if (response.ok || response.status === 401) {
        window.location.replace("/staff/sign-in?signed_out=1");
        return;
      }
      throw new Error("Sign-out failed.");
    } catch {
      setError("We couldn’t sign you out. Try again.");
      setPending(false);
    }
  }

  return (
    <>
      {error && <p className="error" role="alert">{error}</p>}
      <button className="button button-secondary" type="button" onClick={signOut} disabled={pending}>
        {pending ? "Signing out…" : "Sign out"}
      </button>
    </>
  );
}
