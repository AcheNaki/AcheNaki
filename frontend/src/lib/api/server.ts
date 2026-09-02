import type { AreaLiveStatuses, DailyUtilityAnalytics, LocalityLiveStatus } from "./types";

export class ServerApiError extends Error {
  // Declared as a plain field rather than a constructor parameter property so this module
  // stays loadable by the type-stripping test runner as well as the Next.js build.
  public readonly status?: number;

  constructor(status?: number) {
    super(status ? `API request failed with ${status}.` : "The API could not be reached.");
    this.name = "ServerApiError";
    this.status = status;
  }
}

function serverApiBaseUrl(): string {
  const value = (process.env.API_BASE_URL ?? process.env.NEXT_PUBLIC_API_BASE_URL)?.replace(/\/$/, "");
  if (!value) throw new Error("API_BASE_URL is not configured for server rendering.");
  return value;
}

async function getServerJson<T>(path: string): Promise<T> {
  let response: Response;
  try {
    response = await fetch(`${serverApiBaseUrl()}${path}`, {
      headers: { Accept: "application/json" },
      cache: "no-store",
    });
  } catch {
    throw new ServerApiError();
  }

  if (!response.ok) throw new ServerApiError(response.status);

  let body: unknown;
  try {
    body = await response.json();
  } catch {
    throw new ServerApiError(response.status);
  }

  // A malformed 2xx body must surface as a transient API failure, not crash the page while
  // rendering `undefined.sub_area`. The caller only ever reads `data`.
  if (typeof body !== "object" || body === null || !("data" in body) || (body as { data: unknown }).data === null) {
    throw new ServerApiError(response.status);
  }

  return body as T;
}

export function getSlugLocalityStatus(areaSlug: string, subAreaSlug: string): Promise<LocalityLiveStatus> {
  return getServerJson<{ data: LocalityLiveStatus }>(
    `/areas/${encodeURIComponent(areaSlug)}/sub-areas/${encodeURIComponent(subAreaSlug)}/status`,
  ).then((response) => response.data);
}

export function getSlugLocalityAnalytics(
  areaSlug: string,
  subAreaSlug: string,
  date?: string,
): Promise<DailyUtilityAnalytics> {
  const query = date ? `?${new URLSearchParams({ date }).toString()}` : "";
  return getServerJson<{ data: DailyUtilityAnalytics }>(
    `/areas/${encodeURIComponent(areaSlug)}/sub-areas/${encodeURIComponent(subAreaSlug)}/analytics${query}`,
  ).then((response) => response.data);
}

export function getServerAreaLiveStatuses(areaSlug: string): Promise<AreaLiveStatuses> {
  return getServerJson<{ data: AreaLiveStatuses }>(`/areas/${encodeURIComponent(areaSlug)}/statuses`)
    .then((response) => response.data);
}
