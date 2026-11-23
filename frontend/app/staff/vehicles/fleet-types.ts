import type { Vehicle } from "@/lib/rentals";

export type StaffVehicle = Vehicle & { is_published: boolean };

export type CalendarRange = {
  starts_on: string;
  ends_on: string;
};

export type StaffCalendar = {
  today: string;
  bookings: (CalendarRange & { reference: string })[];
  blocks: (CalendarRange & { id: number })[];
};
