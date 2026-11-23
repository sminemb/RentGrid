import type { Trip } from "./rental-details";

export type Quote = Trip & {
  quote_token: string;
  days: number;
  rental_minor: number;
  fee_minor: number;
  deposit_minor: number;
};

const key = "rentgrid:pending-booking";
const lifetime = 30 * 60 * 1000;

class PendingBooking {
  read(): Quote | undefined {
    try {
      const saved = JSON.parse(sessionStorage.getItem(key) ?? "null");
      if (typeof saved?.expiresAt === "number" && saved.expiresAt > Date.now() && this.isQuote(saved.quote)) {
        return saved.quote;
      }
    } catch {
      // Tab storage may be unavailable.
    }
  }

  save(quote: Quote) {
    try {
      if (this.read()?.quote_token === quote.quote_token) return;
      sessionStorage.setItem(key, JSON.stringify({ quote, expiresAt: Date.now() + lifetime }));
      window.dispatchEvent(new Event(key));
    } catch {
      // The open review still has its quote if tab storage is unavailable.
    }
  }

  clear(quoteToken: string) {
    try {
      if (this.read()?.quote_token !== quoteToken) return;
      sessionStorage.removeItem(key);
      window.dispatchEvent(new Event(key));
    } catch {
      // Tab storage may be unavailable.
    }
  }

  restore() {
    try {
      if (!this.read()) sessionStorage.removeItem(key);

      // Older tabs stored contact details with each selection. Keep only a quote for recovery.
      for (const legacyKey of Object.keys(sessionStorage).reverse()) {
        if (!legacyKey.startsWith(`${key}:`)) continue;
        const value = sessionStorage.getItem(legacyKey);
        sessionStorage.removeItem(legacyKey);
        if (!value || this.read()) continue;

        try {
          const { quote } = JSON.parse(value);
          if (this.isQuote(quote)) this.save(quote);
        } catch {
          // Discard an unreadable legacy entry and keep clearing the rest.
        }
      }
    } catch {
      // Tab storage may be unavailable.
    }
  }

  href(quote: Quote) {
    const { pickup_date, return_date, vehicle } = quote;
    return `/book?${new URLSearchParams({ pickup_date, return_date, vehicle_id: String(vehicle.id) })}`;
  }

  currentHref = () => {
    const quote = this.read();
    return quote ? this.href(quote) : undefined;
  };

  subscribe = (listener: () => void) => {
    window.addEventListener(key, listener);
    window.addEventListener("pageshow", listener);
    return () => {
      window.removeEventListener(key, listener);
      window.removeEventListener("pageshow", listener);
    };
  };

  private isQuote(quote: unknown): quote is Quote {
    const candidate = quote as Quote | null | undefined;
    return typeof candidate?.quote_token === "string" && typeof candidate.vehicle?.id === "number" &&
      typeof candidate.pickup_date === "string" && typeof candidate.return_date === "string";
  }
}

export const pendingBooking = new PendingBooking();
