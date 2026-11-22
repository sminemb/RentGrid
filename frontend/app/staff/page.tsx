import Link from "next/link";
import { headers } from "next/headers";
import { redirect } from "next/navigation";
import { StaffSignOut } from "./staff-sign-out";

export const metadata = { title: "Staff access", robots: { index: false } };

async function staffSession() {
  const cookie = (await headers()).get("cookie");
  const origin = process.env.LARAVEL_API_ORIGIN ?? "http://127.0.0.1:8000";
  try {
    return await fetch(new URL("/api/staff/session", origin), {
      headers: { Accept: "application/json", ...(cookie ? { Cookie: cookie } : {}) },
      cache: "no-store",
      redirect: "error",
    });
  } catch {
    return undefined;
  }
}

export default async function StaffPage() {
  const session = await staffSession();
  if (session?.status === 401) redirect("/staff/sign-in");
  const staff: { name: string } | undefined = session?.ok ? await session.json() : undefined;
  const forbidden = session?.status === 403;

  return (
    <main id="main-content" className="shell staff-shell">
      <header className="review-heading">
        <h1>Staff access</h1>
      </header>
      <section className="trip-panel staff-form" aria-label="Staff session">
        {staff ? (
          <>
            <p>Signed in as <strong>{staff.name}</strong></p>
            <StaffSignOut />
          </>
        ) : forbidden ? (
          <>
            <p>This account does not have staff access.</p>
            <StaffSignOut />
          </>
        ) : (
          <>
            <p>Staff access is temporarily unavailable.</p>
            <Link className="button button-secondary" href="/staff/sign-in">Go to staff sign-in</Link>
          </>
        )}
      </section>
    </main>
  );
}
