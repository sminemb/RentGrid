import Link from "next/link";
import { staffRequest, type StaffSession } from "./staff-api";
import { StaffSignOut } from "./staff-sign-out";

export const metadata = { title: "Staff access", robots: { index: false } };

export default async function StaffPage() {
  const session = await staffRequest("/api/staff/session");
  const staff: StaffSession | undefined = session?.ok ? await session.json() : undefined;
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
            <div className="staff-actions">
              <Link href="/staff/vehicles" className="button">Manage fleet</Link>
              <Link href="/staff/reservations" className="button button-secondary">Find a reservation</Link>
              {staff.can_create_staff && (
                <Link href="/staff/accounts/new" className="button button-secondary">Create staff account</Link>
              )}
            </div>
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
