import type {
  ApiCollection,
  ApiValidationError,
  Area,
  AreaLiveStatuses,
  DashboardData,
  ElectricityReportPayload,
  DailyUtilityAnalytics,
  GasReportPayload,
  LiveStatusListItem,
  LiveSummary,
  LocalityLiveStatus,
  ProjectionStatus,
  SubArea,
  RecentlyResolvedElectricityEvent,
  LocationSearchResult,
  UtilityType,
  UtilityReportSubmission,
} from "./types";

const TOKEN_STORAGE_KEY = "achenaki.anonymous-reporter.v1";
const TOKEN_PATTERN = /^ar1_[A-Za-z0-9_-]{43}$/;

function apiBaseUrl(): string {
  const value = process.env.NEXT_PUBLIC_API_BASE_URL?.replace(/\/$/, "");

  if (!value) {
    throw new Error("NEXT_PUBLIC_API_BASE_URL is not configured.");
  }

  return value;
}

export type ApiErrorKind =
  | "configuration"
  | "network"
  | "validation"
  | "rate-limit"
  | "anonymous-token"
  | "server";

export class ApiError extends Error {
  public readonly kind: ApiErrorKind;
  public readonly status?: number;
  public readonly fields: Record<string, string[]>;

  constructor(
    kind: ApiErrorKind,
    message: string,
    status?: number,
    fields: Record<string, string[]> = {},
  ) {
    super(message);
    this.name = "ApiError";
    this.kind = kind;
    this.status = status;
    this.fields = fields;
  }
}

function storedToken(): string | null {
  if (typeof window === "undefined") return null;

  let value: string | null;
  try {
    value = window.localStorage.getItem(TOKEN_STORAGE_KEY);
  } catch {
    return null;
  }
  if (value && TOKEN_PATTERN.test(value)) return value;

  if (value) {
    try {
      window.localStorage.removeItem(TOKEN_STORAGE_KEY);
    } catch {
      // Treat inaccessible storage as an absent token.
    }
  }
  return null;
}

function clearStoredToken(): void {
  if (typeof window !== "undefined") {
    try {
      window.localStorage.removeItem(TOKEN_STORAGE_KEY);
    } catch {
      // A fresh session request can still proceed without storage access.
    }
  }
}

async function fetchFromApi(path: string, init?: RequestInit): Promise<Response> {
  let url: string;

  try {
    url = `${apiBaseUrl()}${path}`;
  } catch {
    throw new ApiError("configuration", "The API address is not configured.");
  }

  try {
    return await fetch(url, init);
  } catch (error) {
    if (error instanceof Error && error.name === "AbortError") throw error;
    throw new ApiError("network", "Could not reach Ache Naki? right now.");
  }
}

async function validationError(response: Response): Promise<ApiError> {
  let body: ApiValidationError | null = null;

  try {
    body = (await response.json()) as ApiValidationError;
  } catch {
    // The UI still receives a safe generic message for malformed server errors.
  }

  const fields = body?.error?.details ?? {};
  const kind = fields.anonymous_reporter ? "anonymous-token" : "validation";

  return new ApiError(kind, "The submitted report was invalid.", response.status, fields);
}

async function responseError(response: Response): Promise<ApiError> {
  if (response.status === 422) return validationError(response);
  if (response.status === 429) {
    return new ApiError("rate-limit", "Too many reports in a short time.", 429);
  }

  return new ApiError("server", "Ache Naki? could not process the request.", response.status);
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function isUtilityType(value: unknown): value is UtilityType {
  return value === "ELECTRICITY" || value === "GAS";
}

function isTimeBucket(value: unknown): boolean {
  return value === "NOW" || value === "MIN_5" || value === "MIN_15" || value === "MIN_30"
    || value === "HOUR_1" || value === "HOUR_2" || value === "OVER_2_HOURS" || value === "UNKNOWN";
}

function isUtilityReport(value: unknown): value is UtilityReportSubmission["data"] {
  if (!isRecord(value)
    || !Number.isSafeInteger(value.id)
    || !Number.isSafeInteger(value.area_id)
    || !Number.isSafeInteger(value.sub_area_id)
    || !isUtilityType(value.utility_type)
    || typeof value.status !== "string"
    || typeof value.reported_at !== "string"
    || !isTimeBucket(value.time_bucket)) {
    return false;
  }

  return value.estimated_started_at === null || typeof value.estimated_started_at === "string";
}

async function dataFromResponse<T>(response: Response): Promise<T> {
  let body: unknown;
  try {
    body = await response.json();
  } catch {
    throw new ApiError("server", "Ache Naki? returned an invalid response.", response.status);
  }

  if (!isRecord(body) || !("data" in body)) {
    throw new ApiError("server", "Ache Naki? returned an invalid response.", response.status);
  }

  return body.data as T;
}

export async function getAreas(signal?: AbortSignal): Promise<Area[]> {
  const response = await fetchFromApi("/areas", {
    headers: { Accept: "application/json" },
    signal,
  });

  if (!response.ok) throw await responseError(response);
  return dataFromResponse<ApiCollection<Area>["data"]>(response);
}

export async function getSubAreas(areaId: number, signal?: AbortSignal): Promise<SubArea[]> {
  const response = await fetchFromApi(`/areas/${areaId}/sub-areas`, {
    headers: { Accept: "application/json" },
    signal,
  });

  if (!response.ok) throw await responseError(response);
  return dataFromResponse<ApiCollection<SubArea>["data"]>(response);
}

export async function getLocalityLiveStatus(
  subAreaId: number,
  signal?: AbortSignal,
): Promise<LocalityLiveStatus> {
  const response = await fetchFromApi(`/sub-areas/${subAreaId}/status`, {
    headers: { Accept: "application/json" },
    signal,
  });

  if (!response.ok) throw await responseError(response);
  return dataFromResponse<LocalityLiveStatus>(response);
}

interface LiveStatusFilters {
  utility?: UtilityType;
  status?: Exclude<ProjectionStatus, "INSUFFICIENT_DATA">;
  limit?: number;
  signal?: AbortSignal;
}

export async function getLiveStatuses(filters: LiveStatusFilters = {}): Promise<LiveStatusListItem[]> {
  const parameters = new URLSearchParams();
  if (filters.utility) parameters.set("utility", filters.utility);
  if (filters.status) parameters.set("status", filters.status);
  if (filters.limit !== undefined) parameters.set("limit", String(filters.limit));
  const query = parameters.size ? `?${parameters.toString()}` : "";
  const response = await fetchFromApi(`/live-statuses${query}`, {
    headers: { Accept: "application/json" },
    signal: filters.signal,
  });

  if (!response.ok) throw await responseError(response);
  return dataFromResponse<ApiCollection<LiveStatusListItem>["data"]>(response);
}

export async function getDailyUtilityAnalytics(
  subAreaId: number,
  date?: string,
  signal?: AbortSignal,
): Promise<DailyUtilityAnalytics> {
  const query = date ? `?${new URLSearchParams({ date }).toString()}` : "";
  const response = await fetchFromApi(`/sub-areas/${subAreaId}/analytics${query}`, {
    headers: { Accept: "application/json" },
    signal,
  });

  if (!response.ok) throw await responseError(response);
  return dataFromResponse<DailyUtilityAnalytics>(response);
}

export async function getSlugLocalityLiveStatus(areaSlug: string, subAreaSlug: string, signal?: AbortSignal): Promise<LocalityLiveStatus> {
  const response = await fetchFromApi(`/areas/${encodeURIComponent(areaSlug)}/sub-areas/${encodeURIComponent(subAreaSlug)}/status`, { headers: { Accept: "application/json" }, signal });
  if (!response.ok) throw await responseError(response);
  return dataFromResponse<LocalityLiveStatus>(response);
}

export async function getSlugDailyUtilityAnalytics(areaSlug: string, subAreaSlug: string, signal?: AbortSignal): Promise<DailyUtilityAnalytics> {
  const response = await fetchFromApi(`/areas/${encodeURIComponent(areaSlug)}/sub-areas/${encodeURIComponent(subAreaSlug)}/analytics`, { headers: { Accept: "application/json" }, signal });
  if (!response.ok) throw await responseError(response);
  return dataFromResponse<DailyUtilityAnalytics>(response);
}

export async function getDashboard(signal?: AbortSignal): Promise<DashboardData> {
  const response = await fetchFromApi("/dashboard", { headers: { Accept: "application/json" }, signal });
  if (!response.ok) throw await responseError(response);
  return dataFromResponse<DashboardData>(response);
}

export async function getLiveSummary(signal?: AbortSignal): Promise<LiveSummary> {
  const response = await fetchFromApi("/live-summary", { headers: { Accept: "application/json" }, signal });
  if (!response.ok) throw await responseError(response);
  return dataFromResponse<LiveSummary>(response);
}

export async function getRecentlyResolvedElectricityEvents(
  limit = 6,
  signal?: AbortSignal,
): Promise<RecentlyResolvedElectricityEvent[]> {
  const response = await fetchFromApi(`/electricity-events/recently-resolved?limit=${limit}`, {
    headers: { Accept: "application/json" }, signal,
  });
  if (!response.ok) throw await responseError(response);
  return dataFromResponse<ApiCollection<RecentlyResolvedElectricityEvent>["data"]>(response);
}

export async function getAreaLiveStatuses(areaSlug: string, signal?: AbortSignal): Promise<AreaLiveStatuses> {
  const response = await fetchFromApi(`/areas/${encodeURIComponent(areaSlug)}/statuses`, {
    headers: { Accept: "application/json" }, signal,
  });
  if (!response.ok) throw await responseError(response);
  return dataFromResponse<AreaLiveStatuses>(response);
}

export async function searchLocations(query: string, signal?: AbortSignal): Promise<LocationSearchResult[]> {
  const parameters = new URLSearchParams({ q: query, limit: "8" });
  const response = await fetchFromApi(`/locations/search?${parameters.toString()}`, {
    headers: { Accept: "application/json" }, signal,
  });
  if (!response.ok) throw await responseError(response);
  return dataFromResponse<ApiCollection<LocationSearchResult>["data"]>(response);
}

export async function ensureAnonymousReporterToken(): Promise<string> {
  const existing = storedToken();
  if (existing) return existing;

  const response = await fetchFromApi("/anonymous-session", {
    method: "POST",
  });

  if (!response.ok) throw await responseError(response);

  const tokenData = await dataFromResponse<unknown>(response);
  if (!isRecord(tokenData) || typeof tokenData.token !== "string" || !TOKEN_PATTERN.test(tokenData.token)) {
    throw new ApiError("server", "The API returned an invalid anonymous token.");
  }

  if (typeof window !== "undefined") {
    try {
      window.localStorage.setItem(TOKEN_STORAGE_KEY, tokenData.token);
    } catch {
      // The token remains usable for this submission even when persistence is unavailable.
    }
  }

  return tokenData.token;
}

export async function submitUtilityReport(
  payload: ElectricityReportPayload | GasReportPayload,
  allowTokenRecovery = true,
): Promise<UtilityReportSubmission> {
  const token = await ensureAnonymousReporterToken();
  const response = await fetchFromApi("/utility-reports", {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-Anonymous-Reporter": token,
    },
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    const error = await responseError(response);

    if (error.kind === "anonymous-token" && allowTokenRecovery) {
      clearStoredToken();
      await ensureAnonymousReporterToken();
      return submitUtilityReport(payload, false);
    }

    throw error;
  }

  let body: unknown;
  try {
    body = await response.json();
  } catch {
    throw new ApiError("server", "Ache Naki? returned an invalid response.", response.status);
  }
  if (!isRecord(body) || !isUtilityReport(body.data) || !isRecord(body.meta) || typeof body.meta.duplicate !== "boolean") {
    throw new ApiError("server", "Ache Naki? returned an invalid response.", response.status);
  }

  return {
    data: body.data,
    meta: { duplicate: body.meta.duplicate },
  };
}
