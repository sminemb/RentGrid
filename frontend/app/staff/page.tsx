import Link from "next/link";
import type { ComponentProps } from "react";
import { Icon } from "../rental-ui";
import { staffRequest, type StaffSession } from "./staff-api";
import { StaffSignOut } from "./staff-sign-out";

export const metadata = { title: "Staff access", robots: { index: false } };

export default async function StaffPage({ searchParams }: PageProps<"/staff">) {
  const { staff_created } = await searchParams;
  const session = await staffRequest("/api/staff/session");
  const staff: StaffSession | undefined = session?.ok ? await session.json() : undefined;
  const forbidden = session?.status === 403;

  return (
    <main id="main-content" className="shell staff-workspace">
      <header className="review-heading">
        <h1>Staff access</h1>
      </header>
      {staff?.can_create_staff && staff_created === "1" && (
        <p className="staff-created-notice" role="status">
          <Icon name="check" />
          <span><strong>Staff account created.</strong> The new staff member can now sign in.</span>
        </p>
      )}
      {staff ? (
        <div className="staff-workspace-grid">
          <nav className="staff-tools" aria-label="Staff tools">
            <ul role="list">
              <StaffToolLink
                href="/staff/vehicles"
                icon="car"
                title="Manage fleet"
                description="Update vehicles and block unavailable dates."
              />
              <StaffToolLink
                href="/staff/reservations"
                icon="calendar"
                title="Find a reservation"
                description="Look up a reservation by its booking reference."
              />
              {staff.can_create_staff && (
                <StaffToolLink
                  href="/staff/accounts/new"
                  icon="user-plus"
                  title="Create staff account"
                  description="Set up sign-in for a new staff member."
                />
              )}
            </ul>
          </nav>
          <section className="staff-session" aria-label="Staff session">
            <div>
              <p>Signed in as <strong>{staff.name}</strong></p>
              <p>{staff.can_create_staff ? "Staff administrator" : "Staff member"}</p>
            </div>
            <div className="staff-session-actions">
              <StaffSignOut />
            </div>
          </section>
        </div>
      ) : (
        <section className="trip-panel staff-form staff-access-notice" aria-label="Staff session">
          {forbidden ? (
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
      )}
    </main>
  );
}

function StaffToolLink({ href, icon, title, description }: {
  href: string;
  icon: ComponentProps<typeof Icon>["name"];
  title: string;
  description: string;
}) {
  return (
    <li>
      <Link className="staff-tool-link" href={href}>
        <span className="staff-tool-icon"><Icon name={icon} /></span>
        <span>
          <strong>{title}</strong>
          <span className="staff-tool-description">{description}</span>
        </span>
        <Icon name="arrow" />
      </Link>
    </li>
  );
}
