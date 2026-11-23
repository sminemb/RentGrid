import Link from "next/link";

export function BookingRecoveryNotice({ href }: { href: string }) {
  return (
    <div className="state-message booking-recovery" role="status">
      <p>Your previous booking may already be confirmed. Check it before booking another vehicle.</p>
      <Link className="button" href={href}>Recover previous booking</Link>
    </div>
  );
}
