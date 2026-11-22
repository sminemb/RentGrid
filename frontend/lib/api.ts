export function apiRequest(
  path: `/api/${string}`,
  options: RequestInit = {},
): Promise<Response> {
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");

  const method = (options.method ?? "GET").toUpperCase();
  if (!["GET", "HEAD", "OPTIONS"].includes(method)) {
    const csrfCookie = document.cookie
      .split(";")
      .map((cookie) => cookie.trim())
      .find((cookie) => cookie.startsWith("XSRF-TOKEN="));

    if (csrfCookie) {
      headers.set("X-XSRF-TOKEN", decodeURIComponent(csrfCookie.slice("XSRF-TOKEN=".length)));
    }
  }

  return fetch(path, {
    ...options,
    headers,
    method,
    credentials: "same-origin",
    mode: "same-origin",
  });
}
