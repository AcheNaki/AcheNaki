export interface SavedLocality {
  areaId: number;
  areaName: string;
  subAreaId: number;
  subAreaName: string;
}

const LOCALITY_STORAGE_KEY = "achenaki.reporting-locality.v1";

export function parseSavedLocality(value: string | null): SavedLocality | null {
  if (!value) return null;

  try {
    const parsed: unknown = JSON.parse(value);
    if (!parsed || typeof parsed !== "object") return null;

    const candidate = parsed as Record<string, unknown>;
    const areaId = candidate.areaId;
    const subAreaId = candidate.subAreaId;
    const areaName = candidate.areaName;
    const subAreaName = candidate.subAreaName;
    if (typeof areaId !== "number" || !Number.isSafeInteger(areaId) || areaId <= 0) return null;
    if (typeof subAreaId !== "number" || !Number.isSafeInteger(subAreaId) || subAreaId <= 0) return null;
    if (typeof areaName !== "string" || areaName.trim().length === 0 || areaName.length > 120) return null;
    if (typeof subAreaName !== "string" || subAreaName.trim().length === 0 || subAreaName.length > 120) return null;

    return {
      areaId,
      areaName,
      subAreaId,
      subAreaName,
    };
  } catch {
    return null;
  }
}

export function loadSavedLocality(): SavedLocality | null {
  if (typeof window === "undefined") return null;

  let raw: string | null;
  try {
    raw = window.localStorage.getItem(LOCALITY_STORAGE_KEY);
  } catch {
    return null;
  }
  const locality = parseSavedLocality(raw);
  if (raw && !locality) {
    try {
      window.localStorage.removeItem(LOCALITY_STORAGE_KEY);
    } catch {
      // Storage may be disabled; treating the locality as absent is safe.
    }
  }
  return locality;
}

export function saveLocality(locality: SavedLocality): void {
  if (typeof window !== "undefined") {
    try {
      window.localStorage.setItem(LOCALITY_STORAGE_KEY, JSON.stringify(locality));
    } catch {
      // A remembered locality is a convenience, never a prerequisite to reporting.
    }
  }
}

export function clearSavedLocality(): void {
  if (typeof window !== "undefined") {
    try {
      window.localStorage.removeItem(LOCALITY_STORAGE_KEY);
    } catch {
      // Storage may be disabled; the in-memory UI state is still cleared by callers.
    }
  }
}
