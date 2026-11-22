import Link from "next/link";
import { Icon } from "../../rental-ui";

export function FleetBack({ home = false }: { home?: boolean }) {
  return (
    <Link className="back-link" href={home ? "/staff" : "/staff/vehicles"}>
      <Icon name="back" /> {home ? "Staff access" : "Fleet"}
    </Link>
  );
}

export function PublicationStatus({ published }: { published: boolean }) {
  return (
    <span className="fleet-status" data-published={published}>
      {published ? "Published" : "Withdrawn"}
    </span>
  );
}

export function FleetError({ status, retry }: { status?: number; retry: string }) {
  const message = status === 403
    ? "This account does not have staff access."
    : status === 404
      ? "This vehicle could not be found."
      : "We couldn’t load fleet information. Try again.";

  return (
    <section className="trip-panel staff-form">
      <p role="alert">{message}</p>
      {status !== 403 && status !== 404 && <a className="button button-secondary" href={retry}>Try again</a>}
    </section>
  );
}
