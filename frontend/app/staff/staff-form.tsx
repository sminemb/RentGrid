"use client";

import { type ComponentProps, useId, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { apiRequest } from "@/lib/api";
import type { ApiProblem } from "@/lib/rentals";

export function useStaffMutation() {
  const router = useRouter();
  const [sending, setSending] = useState(false);
  const [refreshing, startTransition] = useTransition();
  const [problem, setProblem] = useState<ApiProblem>({});
  const [status, setStatus] = useState("");
  const pending = sending || refreshing;

  async function submit(
    path: `/api/staff/${string}`,
    method: "POST" | "PUT" | "DELETE",
    body: object | undefined,
    onSuccess: (response: Response) => string | Promise<string>,
  ) {
    if (pending) return;
    setSending(true);
    setProblem({});
    setStatus("");

    try {
      const session = await apiRequest("/api/session", { cache: "no-store" });
      if (!session.ok) throw new Error();
      const response = await apiRequest(path, {
        method,
        headers: body ? { "Content-Type": "application/json" } : undefined,
        body: body ? JSON.stringify(body) : undefined,
      });
      if (response.ok) {
        const message = await onSuccess(response);
        if (message) {
          setStatus(message);
          startTransition(() => router.refresh());
        }
      } else if (response.status === 401) {
        window.location.replace("/staff/sign-in");
      } else if (response.status === 403) {
        setProblem({ message: "This account does not have staff access." });
      } else if (response.status === 409 || response.status === 422) {
        setProblem(await response.json());
        if (response.status === 409) startTransition(() => router.refresh());
      } else if (response.status === 404) {
        setProblem({ message: "This item is no longer available. The page has been refreshed." });
        startTransition(() => router.refresh());
      } else {
        throw new Error();
      }
    } catch {
      setProblem({ message: "We couldn’t complete the update. Refresh to check the current state before trying again." });
    } finally {
      setSending(false);
    }
  }

  return { pending, problem, status, submit };
}

export function StaffField({ label, error, ...props }: ComponentProps<"input"> & { label: string; error?: string }) {
  const id = useId();

  return (
    <div className="field">
      <label htmlFor={id}>{label}</label>
      <input {...props} id={id} aria-invalid={error ? true : undefined} aria-describedby={error ? `${id}-error` : undefined} />
      {error && <p className="error" id={`${id}-error`}>{error}</p>}
    </div>
  );
}

export function StaffFeedback({ problem, status }: { problem: ApiProblem; status: string }) {
  return (
    <>
      {problem.message && (
        <p className="error" role="alert">{problem.errors ? "Check the highlighted fields." : problem.message}</p>
      )}
      {status && <p role="status">{status}</p>}
    </>
  );
}
