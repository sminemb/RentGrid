import type { Metadata } from "next";
import Link from "next/link";
import "./globals.css";

export const metadata: Metadata = {
  title: "RentGrid",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en">
      <body>
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
