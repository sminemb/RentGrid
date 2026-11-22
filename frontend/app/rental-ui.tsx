import type { ReactNode } from "react";

const rentalSteps = [
  { key: "search", label: "Find a vehicle" },
  { key: "review", label: "Review & book" },
  { key: "confirmation", label: "Confirmation" },
] as const;

export function RentalSteps({ current }: { current: (typeof rentalSteps)[number]["key"] }) {
  return (
    <ol className="rental-steps" role="list" aria-label="Booking progress">
      {rentalSteps.map(({ key, label }, index) => (
        <li key={key} aria-current={key === current ? "step" : undefined}>
          <span aria-hidden="true">{index + 1}</span>
          {label}
        </li>
      ))}
    </ol>
  );
}

const iconPaths = {
  arrow: <path d="M4 12h15m-6-6 6 6-6 6" />,
  back: <path d="M20 12H5m6 6-6-6 6-6" />,
  car: <><path d="m5 10 1.5-4h11L19 10M3 16v2m18-2v2M5 13h2m10 0h2" /><rect x="3" y="10" width="18" height="6" rx="2" /></>,
  check: <path d="m5 12 4 4L19 6" />,
  pin: <><path d="M19 10c0 5-7 10-7 10S5 15 5 10a7 7 0 1 1 14 0Z" /><circle cx="12" cy="10" r="2" /></>,
};

export function Icon({ name }: { name: keyof typeof iconPaths }) {
  return (
    <svg className="icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.65" strokeLinecap="round" strokeLinejoin="round">
      {iconPaths[name]}
    </svg>
  );
}

export function LoadingRows({ children }: { children: ReactNode }) {
  return (
    <div className="loading-state" role="status">
      <p>{children}</p>
      <span className="loading-line" aria-hidden="true" />
      <span className="loading-line loading-line-short" aria-hidden="true" />
    </div>
  );
}
