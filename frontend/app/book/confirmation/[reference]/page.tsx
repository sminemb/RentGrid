import { BookingConfirmation } from "./booking-confirmation";

export const metadata = { title: "Booking confirmation", robots: { index: false } };

export default async function ConfirmationPage({ params }: PageProps<"/book/confirmation/[reference]">) {
  const { reference } = await params;

  return (
    <main id="main-content" className="shell">
      <BookingConfirmation key={reference} reference={reference} />
    </main>
  );
}
