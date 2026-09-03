export const liveRefreshIntervals = {
  localityStatusMs: 15_000,
  dashboardMs: 20_000,
  liveSummaryMs: 30_000,
  restoredEventsMs: 30_000,
  analyticsMs: 45_000,
  maxBackoffMs: 60_000,
} as const;
