import assert from "node:assert/strict";
import test from "node:test";
import {
  confidenceLabel,
  evidenceSummary,
  formatDhakaTime,
  formatDuration,
  formatLongestOutage,
  formatRelativeTime,
  insufficientDataCopy,
  liveSummaryMetrics,
  localityFullName,
  reportCountLabel,
  statusCopy,
  strugglingEmptyStateCopy,
  withUnknownGaps,
} from "./domain.ts";
import type { LiveSummary, LiveUtilityStatus } from "../api/types.ts";

test("public status copy preserves insufficient and mixed uncertainty", () => {
  assert.equal(statusCopy("ELECTRICITY", "INSUFFICIENT_DATA").label, "Not enough recent reports");
  assert.equal(statusCopy("GAS", "MIXED").label, "Mixed gas reports");
  assert.equal(confidenceLabel(null), null);
  assert.equal(confidenceLabel("HIGH", true), "Reports are mixed");
  assert.equal(confidenceLabel("MEDIUM"), "Medium confidence");
});

test("duration formatting remains human-readable and integer based", () => {
  assert.equal(formatDuration(42), "<1 min");
  assert.equal(formatDuration(300), "5 min");
  assert.equal(formatDuration(3900), "1h 5m");
});

test("timeline retains unknown time and clips cross-window evidence", () => {
  const timeline = withUnknownGaps(
    { started_at: "2026-09-01T18:00:00.000Z", ended_at: "2026-09-01T19:00:00.000Z", duration_seconds: 3600, partial: false },
    [{ status: "UNAVAILABLE", startedAt: "2026-09-01T17:50:00.000Z", endedAt: "2026-09-01T18:15:00.000Z", durationSeconds: 1500 }],
  );
  assert.deepEqual(timeline.map((segment) => [segment.status, segment.durationSeconds]), [["UNAVAILABLE", 900], ["UNKNOWN", 2700]]);
});

const hourWindow = {
  started_at: "2026-09-01T18:00:00.000Z",
  ended_at: "2026-09-01T19:00:00.000Z",
  duration_seconds: 3600,
  partial: false,
};

test("a day with no observed evidence is entirely unknown, never available", () => {
  const timeline = withUnknownGaps(hourWindow, []);

  assert.deepEqual(timeline.map((segment) => [segment.status, segment.durationSeconds]), [["UNKNOWN", 3600]]);
});

test("multiple unknown gaps between observed segments are all preserved", () => {
  const timeline = withUnknownGaps(hourWindow, [
    { status: "UNAVAILABLE", startedAt: "2026-09-01T18:10:00.000Z", endedAt: "2026-09-01T18:20:00.000Z", durationSeconds: 600 },
    { status: "AVAILABLE", startedAt: "2026-09-01T18:40:00.000Z", endedAt: "2026-09-01T18:50:00.000Z", durationSeconds: 600 },
  ]);

  assert.deepEqual(timeline.map((segment) => [segment.status, segment.durationSeconds]), [
    ["UNKNOWN", 600],
    ["UNAVAILABLE", 600],
    ["UNKNOWN", 1200],
    ["AVAILABLE", 600],
    ["UNKNOWN", 600],
  ]);
  assert.equal(timeline.reduce((sum, segment) => sum + segment.durationSeconds, 0), 3600);
});

test("out-of-order and overlapping segments still reconcile to the window duration", () => {
  const timeline = withUnknownGaps(hourWindow, [
    { status: "AVAILABLE", startedAt: "2026-09-01T18:30:00.000Z", endedAt: "2026-09-01T19:30:00.000Z", durationSeconds: 3600 },
    { status: "UNAVAILABLE", startedAt: "2026-09-01T18:00:00.000Z", endedAt: "2026-09-01T18:45:00.000Z", durationSeconds: 2700 },
  ]);

  assert.equal(timeline.reduce((sum, segment) => sum + segment.durationSeconds, 0), 3600);
  assert.equal(timeline[0]?.status, "UNAVAILABLE");
  assert.ok(timeline.every((segment) => segment.durationSeconds >= 0));
});

test("Dhaka display time is stable across the local midnight boundary", () => {
  // Dhaka is UTC+6 with no daylight saving, so these are the exact boundary instants.
  assert.equal(formatDhakaTime("2026-09-01T17:59:00.000Z"), "11:59 PM");
  assert.equal(formatDhakaTime("2026-09-01T18:00:00.000Z"), "12:00 AM");
  assert.equal(formatDhakaTime("2026-09-01T18:01:00.000Z"), "12:01 AM");
});

test("relative time never reports a future backend timestamp as elapsed", () => {
  const now = Date.parse("2026-09-01T12:00:00.000Z");

  assert.equal(formatRelativeTime("2026-09-01T12:05:00.000Z", now), "just now");
  assert.equal(formatRelativeTime("2026-09-01T12:00:00.000Z", now), "just now");
  assert.equal(formatRelativeTime("2026-09-01T11:45:00.000Z", now), "15 min ago");
  assert.equal(formatRelativeTime("2026-09-01T11:00:00.000Z", now), "1h ago");
  assert.equal(formatRelativeTime("2026-09-01T09:00:00.000Z", now), "3h ago");
});

test("duration formatting stays exact at unit boundaries", () => {
  assert.equal(formatDuration(59), "<1 min");
  assert.equal(formatDuration(60), "1 min");
  assert.equal(formatDuration(3600), "1h");
  assert.equal(formatDuration(3600, true), "1h");
  assert.equal(formatDuration(300, true), "5m");
});

test("a measured zero is never presented as an unmeasured sub-minute duration", () => {
  assert.equal(formatDuration(0), "0 min");
  assert.equal(formatDuration(0, true), "0m");
  assert.equal(formatDuration(1), "<1 min");
  assert.equal(formatDuration(1, true), "<1m");
  assert.equal(formatDuration(59), "<1 min");
  assert.equal(formatDuration(60), "1 min");
  // Longer durations keep the formatting they already had.
  assert.equal(formatDuration(300), "5 min");
  assert.equal(formatDuration(3900), "1h 5m");
});

test("longest outage is blank rather than zero when no outage was confirmed", () => {
  assert.equal(formatLongestOutage(0, 0), "—");
  assert.equal(formatLongestOutage(1, 0), "0 min");
  assert.equal(formatLongestOutage(1, 45), "<1 min");
  assert.equal(formatLongestOutage(2, 5400), "1h 30m");
});

test("recent report counts agree with their own number", () => {
  assert.equal(reportCountLabel(0), "0 recent reports");
  assert.equal(reportCountLabel(1), "1 recent report");
  assert.equal(reportCountLabel(2), "2 recent reports");
});

test("a locality name is always announced with the major area that disambiguates it", () => {
  assert.equal(
    localityFullName({ name: "Block C", area: { name: "Bashundhara Residential Area" } }),
    "Block C, Bashundhara Residential Area",
  );
  assert.equal(
    localityFullName({ name: "Block C", area: { name: "Banasree" } }),
    "Block C, Banasree",
  );
});

test("the struggling empty state separates absent signals from absent data", () => {
  assert.equal(
    strugglingEmptyStateCopy,
    "No recent community issue signals detected right now. Some areas may not have enough recent data.",
  );
  assert.ok(/not have enough recent data/i.test(strugglingEmptyStateCopy));
});

test("the empty-state invitation is per utility and never replaces a real status label", () => {
  assert.equal(insufficientDataCopy.ELECTRICITY.heading, "কারেন্টের খবর এখনও ঝাপসা");
  assert.equal(insufficientDataCopy.GAS.heading, "গ্যাসের খবর এখনও রহস্য");
  assert.equal(insufficientDataCopy.ELECTRICITY.prompt, "খবরটা জানেন? তাহলে চুপ কেন - একটা report দিয়ে যান। 😄");
  assert.equal(insufficientDataCopy.GAS.prompt, "আপনার report না এলে এই রহস্যের তদন্ত এগোবে কীভাবে? 👀");
  // Real states keep the cautious wording; the playful copy is reachable only from the tables above.
  assert.equal(statusCopy("ELECTRICITY", "UNAVAILABLE").label, "Likely loadshedding");
  assert.equal(statusCopy("GAS", "LOW").label, "Low gas pressure reported");
});

test("the live summary renders backend counts and the backend's own window", () => {
  const summary: LiveSummary = {
    window_minutes: 30,
    reports: 12,
    localities_updated: 5,
    electricity_issue_localities: 3,
    gas_issue_localities: 2,
    currently_struggling_localities: 4,
    calculated_at: "2026-09-01T12:00:00.000000Z",
  };

  assert.deepEqual(
    liveSummaryMetrics(summary).map((metric) => `${metric.icon} ${metric.value} ${metric.label}`),
    [
      "📡 12 reports in the last 30 minutes",
      "📍 5 localities updated",
      "⚡ 3 reporting electricity issues",
      "🔥 2 reporting gas trouble",
      "🚨 4 currently struggling",
    ],
  );
  assert.equal(
    liveSummaryMetrics({ ...summary, window_minutes: 45 })[0].label,
    "reports in the last 45 minutes",
  );
});

function liveStatus(overrides: Partial<LiveUtilityStatus>): LiveUtilityStatus {
  return {
    status: "AVAILABLE",
    confidence: "MEDIUM",
    status_since: null,
    recent_reports: 0,
    supporting_reports: 0,
    contradicting_reports: 0,
    last_report_at: null,
    ...overrides,
  };
}

test("evidence summary keeps disagreement explicit and hides absent evidence", () => {
  assert.equal(evidenceSummary(liveStatus({ status: "INSUFFICIENT_DATA", recent_reports: 0 })), null);
  assert.equal(evidenceSummary(liveStatus({ status: "AVAILABLE", recent_reports: 0 })), null);
  assert.equal(evidenceSummary(liveStatus({ status: "AVAILABLE", recent_reports: 1 })), "1 recent report");
  assert.equal(evidenceSummary(liveStatus({ status: "AVAILABLE", recent_reports: 4 })), "4 recent reports");
  assert.equal(
    evidenceSummary(liveStatus({ status: "MIXED", recent_reports: 6, supporting_reports: 3, contradicting_reports: 3 })),
    "3 supporting · 3 different",
  );
});

test("confidence is never presented as a probability", () => {
  for (const level of ["LOW", "MEDIUM", "HIGH"] as const) {
    const label = confidenceLabel(level);
    assert.ok(label);
    assert.ok(!/%|probab|chance|likelihood/i.test(label));
  }
});
