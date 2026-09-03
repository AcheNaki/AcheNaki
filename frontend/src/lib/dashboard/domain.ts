import type {
  ConfidenceLevel,
  DailyUtilityAnalytics,
  ElectricityProjectionStatus,
  GasProjectionStatus,
  LiveSummary,
  LiveUtilityStatus,
  UtilityType,
} from "@/lib/api/types";

export const electricityStatusCopy: Record<ElectricityProjectionStatus, { label: string; icon: string; tone: string }> = {
  AVAILABLE: { label: "Electricity appears available", icon: "⚡", tone: "positive" },
  UNAVAILABLE: { label: "Likely loadshedding", icon: "◌", tone: "critical" },
  UNSTABLE: { label: "Electricity appears unstable", icon: "⚠", tone: "warning" },
  MIXED: { label: "Mixed electricity reports", icon: "↯", tone: "mixed" },
  INSUFFICIENT_DATA: { label: "Not enough recent reports", icon: "○", tone: "muted" },
};

export const gasStatusCopy: Record<GasProjectionStatus, { label: string; icon: string; tone: string }> = {
  NORMAL: { label: "Gas appears normal", icon: "◒", tone: "positive" },
  LOW: { label: "Low gas pressure reported", icon: "◔", tone: "warning" },
  VERY_LOW: { label: "Very low gas pressure reported", icon: "◔", tone: "critical" },
  UNAVAILABLE: { label: "Gas appears unavailable", icon: "○", tone: "critical" },
  MIXED: { label: "Mixed gas reports", icon: "↯", tone: "mixed" },
  INSUFFICIENT_DATA: { label: "Not enough recent reports", icon: "○", tone: "muted" },
};

export function statusCopy(utility: UtilityType, status: ElectricityProjectionStatus | GasProjectionStatus) {
  return utility === "ELECTRICITY"
    ? electricityStatusCopy[status as ElectricityProjectionStatus]
    : gasStatusCopy[status as GasProjectionStatus];
}

// Only the "no recent evidence" state invites a report in a light-hearted way. Every real
// status keeps the cautious wording in the status-copy tables above.
export const insufficientDataCopy: Record<UtilityType, { heading: string; prompt: string }> = {
  ELECTRICITY: {
    heading: "কারেন্টের খবর এখনও ঝাপসা",
    prompt: "খবরটা জানেন? তাহলে চুপ কেন - একটা report দিয়ে যান। 😄",
  },
  GAS: {
    heading: "গ্যাসের খবর এখনও রহস্য",
    prompt: "আপনার report না এলে এই রহস্যের তদন্ত এগোবে কীভাবে? 👀",
  },
};

export interface LiveSummaryMetric {
  key: string;
  icon: string;
  value: number;
  label: string;
}

// The rolling window comes from the API so the card never hard-codes an evidence window the
// backend could change.
export function liveSummaryMetrics(summary: LiveSummary): LiveSummaryMetric[] {
  return [
    { key: "reports", icon: "📡", value: summary.reports, label: `reports in the last ${summary.window_minutes} minutes` },
    { key: "localities", icon: "📍", value: summary.localities_updated, label: "localities updated" },
    { key: "electricity", icon: "⚡", value: summary.electricity_issue_localities, label: "reporting electricity issues" },
    { key: "gas", icon: "🔥", value: summary.gas_issue_localities, label: "reporting gas trouble" },
    { key: "struggling", icon: "🚨", value: summary.currently_struggling_localities, label: "currently struggling" },
  ];
}

export function confidenceLabel(confidence: ConfidenceLevel | null, mixed = false): string | null {
  if (!confidence) return null;
  return mixed ? "Reports are mixed" : `${confidence[0]}${confidence.slice(1).toLowerCase()} confidence`;
}

export function formatDuration(seconds: number, compact = false): string {
  // An exact zero is a real measurement ("this never happened today"), not a rounded-down
  // sub-minute duration. Rendering it as "<1 min" claims evidence that does not exist.
  if (seconds <= 0) return compact ? "0m" : "0 min";
  if (seconds < 60) return compact ? "<1m" : "<1 min";
  const minutes = Math.floor(seconds / 60);
  const hours = Math.floor(minutes / 60);
  const remainingMinutes = minutes % 60;
  if (hours === 0) return `${minutes}${compact ? "m" : " min"}`;
  return remainingMinutes === 0 ? `${hours}${compact ? "h" : "h"}` : `${hours}${compact ? "h " : "h "}${remainingMinutes}${compact ? "m" : "m"}`;
}

export const NO_VALUE_DASH = "—";

// With no confirmed outage there is no "longest" one to measure, so a duration of any kind —
// including "0 min" — would read as a measured outage. An em dash keeps the metric empty.
export function formatLongestOutage(outageCount: number, longestOutageSeconds: number): string {
  return outageCount === 0 ? NO_VALUE_DASH : formatDuration(longestOutageSeconds);
}

export function reportCountLabel(count: number): string {
  return `${count} recent ${count === 1 ? "report" : "reports"}`;
}

// A locality name such as "Block C" repeats across major areas, so any card that shows one
// outside its area page must carry the parent area with it to stay unambiguous.
export function localityFullName(locality: { name: string; area: { name: string } }): string {
  return `${locality.name}, ${locality.area.name}`;
}

// "No issues" and "no evidence" are different claims; the empty state must not collapse them.
export const strugglingEmptyStateCopy =
  "No recent community issue signals detected right now. Some areas may not have enough recent data.";

export function formatDhakaTime(iso: string): string {
  return new Intl.DateTimeFormat("en-US", {
    timeZone: "Asia/Dhaka", hour: "numeric", minute: "2-digit", hour12: true,
  }).format(new Date(iso));
}

export function formatRelativeTime(iso: string, now = Date.now()): string {
  const minutes = Math.max(0, Math.floor((now - new Date(iso).getTime()) / 60_000));
  if (minutes < 1) return "just now";
  if (minutes < 60) return `${minutes} min ago`;
  const hours = Math.floor(minutes / 60);
  return hours === 1 ? "1h ago" : `${hours}h ago`;
}

export interface TimelineSegment {
  status: string;
  startedAt: string;
  endedAt: string;
  durationSeconds: number;
}

export function withUnknownGaps(
  window: DailyUtilityAnalytics["window"],
  segments: TimelineSegment[],
): TimelineSegment[] {
  const start = new Date(window.started_at).getTime();
  const end = new Date(window.ended_at).getTime();
  let cursor = start;
  const result: TimelineSegment[] = [];
  for (const segment of [...segments].sort((a, b) => a.startedAt.localeCompare(b.startedAt))) {
    const segmentStart = Math.max(cursor, new Date(segment.startedAt).getTime());
    const segmentEnd = Math.min(end, new Date(segment.endedAt).getTime());
    if (segmentStart > cursor) result.push({ status: "UNKNOWN", startedAt: new Date(cursor).toISOString(), endedAt: new Date(segmentStart).toISOString(), durationSeconds: Math.floor((segmentStart - cursor) / 1000) });
    if (segmentEnd > segmentStart) result.push({ ...segment, startedAt: new Date(segmentStart).toISOString(), endedAt: new Date(segmentEnd).toISOString(), durationSeconds: Math.floor((segmentEnd - segmentStart) / 1000) });
    cursor = Math.max(cursor, segmentEnd);
  }
  if (cursor < end) result.push({ status: "UNKNOWN", startedAt: new Date(cursor).toISOString(), endedAt: new Date(end).toISOString(), durationSeconds: Math.floor((end - cursor) / 1000) });
  return result;
}

export function evidenceSummary(status: LiveUtilityStatus): string | null {
  if (status.status === "INSUFFICIENT_DATA" || status.recent_reports === 0) return null;
  if (status.status === "MIXED") return `${status.supporting_reports} supporting · ${status.contradicting_reports} different`;
  return reportCountLabel(status.recent_reports);
}

export function analyticsHasObservedCoverage(analytics: DailyUtilityAnalytics): boolean {
  return analytics.electricity.coverage.observed_seconds > 0 || analytics.gas.coverage.observed_seconds > 0;
}
