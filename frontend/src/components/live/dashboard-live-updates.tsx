"use client";

import { useEffect, useRef } from "react";
import { getDashboard, getDailyUtilityAnalytics, getLocalityLiveStatus, getRecentlyResolvedElectricityEvents } from "@/lib/api/client";
import type { DailyUtilityAnalytics, DashboardData, LocalityLiveStatus, RecentlyResolvedElectricityEvent } from "@/lib/api/types";
import { liveRefreshIntervals } from "@/lib/live/config";
import { useLiveResource } from "@/lib/live/refresh";

export function DashboardLiveUpdates({ subAreaId, refreshKey, onLocality, onLocalityError, onAnalytics, onAnalyticsError, onDashboard, onDashboardError, onRestored, onAnnouncement, onState }: { subAreaId: number | null; refreshKey: number; onLocality: (value: LocalityLiveStatus) => void; onLocalityError: (reason: unknown, hadData: boolean) => void; onAnalytics: (value: DailyUtilityAnalytics) => void; onAnalyticsError: (hadData: boolean) => void; onDashboard: (value: DashboardData) => void; onDashboardError: (hadData: boolean) => void; onRestored: (value: RecentlyResolvedElectricityEvent[]) => void; onAnnouncement: (message: string) => void; onState: (state: { paused: boolean; refreshing: boolean; failed: boolean }) => void }) {
  const prior = useRef<LocalityLiveStatus | null>(null);
  useEffect(() => { prior.current = null; }, [subAreaId]);
  const local = useLiveResource({ key: subAreaId === null ? null : `${subAreaId}-${refreshKey}`, intervalMs: liveRefreshIntervals.localityStatusMs, maxBackoffMs: liveRefreshIntervals.maxBackoffMs, load: (signal) => getLocalityLiveStatus(subAreaId!, signal), onData: (next) => { const old = prior.current; prior.current = next; onLocality(next); if (old && old.electricity.status !== next.electricity.status) onAnnouncement(`Electricity status updated: ${next.electricity.status.toLowerCase().replaceAll("_", " ")}.`); if (old && old.gas.status !== next.gas.status) onAnnouncement(`Gas status updated: ${next.gas.status.toLowerCase().replaceAll("_", " ")}.`); }, onError: (reason, previous) => onLocalityError(reason, previous !== null) });
  useLiveResource({ key: subAreaId === null ? null : `${subAreaId}-${refreshKey}`, intervalMs: liveRefreshIntervals.analyticsMs, maxBackoffMs: liveRefreshIntervals.maxBackoffMs, load: (signal) => getDailyUtilityAnalytics(subAreaId!, undefined, signal), onData: (next) => onAnalytics(next), onError: (_reason, previous) => onAnalyticsError(previous !== null) });
  const dashboard = useLiveResource({ key: refreshKey, intervalMs: liveRefreshIntervals.dashboardMs, maxBackoffMs: liveRefreshIntervals.maxBackoffMs, load: getDashboard, onData: onDashboard, onError: (_reason, previous) => onDashboardError(previous !== null) });
  useLiveResource({ key: refreshKey, intervalMs: liveRefreshIntervals.restoredEventsMs, maxBackoffMs: liveRefreshIntervals.maxBackoffMs, load: (signal) => getRecentlyResolvedElectricityEvents(6, signal), onData: onRestored });
  useEffect(() => { onState({ paused: local.paused || dashboard.paused, refreshing: local.refreshing || dashboard.refreshing, failed: local.error || dashboard.error }); }, [dashboard.error, dashboard.paused, dashboard.refreshing, local.error, local.paused, local.refreshing, onState]);
  return null;
}
