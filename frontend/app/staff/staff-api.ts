import { headers } from "next/headers";
import { redirect } from "next/navigation";

export type StaffSession = { name: string; can_create_staff: boolean };

export async function staffRequest(path: `/api/staff/${string}`) {
  const cookie = (await headers()).get("cookie");
  const origin = process.env.LARAVEL_API_ORIGIN ?? "http://127.0.0.1:8000";
  const response = await fetch(new URL(path, origin), {
    headers: { Accept: "application/json", ...(cookie ? { Cookie: cookie } : {}) },
    cache: "no-store",
    redirect: "error",
  }).catch(() => undefined);

  if (response?.status === 401) redirect("/staff/sign-in");
  return response;
}
