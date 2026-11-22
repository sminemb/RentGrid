export type RentalDates = {
  pickup_date: string;
  return_date: string;
};

export type Vehicle = {
  id: number;
  display_label: string;
  make: string;
  model: string;
};

export type ApiProblem = {
  code?: string;
  message?: string;
  errors?: Record<string, string[]>;
};

export function readRentalDates(params: Record<string, string | string[] | undefined>): RentalDates {
  return {
    pickup_date: typeof params.pickup_date === "string" ? params.pickup_date : "",
    return_date: typeof params.return_date === "string" ? params.return_date : "",
  };
}
