import type { Metadata } from "next";
import Link from "next/link";
import "./globals.css";

export const metadata: Metadata = {
  title: { default: "RentGrid | Vehicle rentals", template: "%s | RentGrid" },
  description: "Search available vehicles by date, review your rental, and book for payment at pickup.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en">
      <body>
        <a className="skip-link" href="#main-content">Skip to content</a>
        <header className="site-header">
          <div className="shell header-inner">
            <Link href="/" className="brand">
              <span className="brand-mark" aria-hidden="true" />
              RentGrid
            </Link>
          </div>
        </header>
        {children}
      </body>
    </html>
  );
}
