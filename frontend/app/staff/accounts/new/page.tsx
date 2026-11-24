import Link from "next/link";
import { Icon } from "../../../rental-ui";
import { staffRequest, type StaffSession } from "../../staff-api";
import { CreateStaffForm } from "./create-staff-form";

export const metadata = { title: "Create staff account", robots: { index: false } };

export default async function NewStaffAccountPage() {
  const session = await staffRequest("/api/staff/session");
  const staff: StaffSession | undefined = session?.ok ? await session.json() : undefined;
  const unavailable = !staff && session?.status !== 403;

  return (
    <main id="main-content" className="shell staff-account-shell">
      <header className="review-heading">
        <Link className="back-link" href="/staff"><Icon name="back" /> Staff access</Link>
        <h1>Create staff account</h1>
      </header>
      {staff?.can_create_staff ? <CreateStaffForm /> : (
        <section className="trip-panel staff-form staff-access-notice">
          {unavailable ? (
            <>
              <p role="alert">We couldn’t load staff access.</p>
              <a className="button button-secondary" href="/staff/accounts/new">Try again</a>
            </>
          ) : <p>Only staff administrators can create staff accounts.</p>}
        </section>
      )}
    </main>
  );
}
