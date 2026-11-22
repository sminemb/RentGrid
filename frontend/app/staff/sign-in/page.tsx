import { StaffSignIn } from "./staff-sign-in";

export const metadata = { title: "Staff sign-in", robots: { index: false } };

export default async function StaffSignInPage({ searchParams }: PageProps<"/staff/sign-in">) {
  const { signed_out } = await searchParams;

  return (
    <main id="main-content" className="shell staff-shell">
      <header className="review-heading">
        <h1>Staff sign-in</h1>
        {signed_out === "1" && <p role="status">You’re signed out.</p>}
      </header>
      <StaffSignIn />
    </main>
  );
}
