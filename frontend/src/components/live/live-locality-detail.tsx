"use client";

import { useState } from "react";
import { DailyAnalytics } from "@/components/analytics/daily-analytics";
import { StatusCard } from "@/components/status/status-card";
import { LiveIndicator } from "@/components/live/live-indicator";
import { getSlugDailyUtilityAnalytics, getSlugLocalityLiveStatus } from "@/lib/api/client";
import type { DailyUtilityAnalytics, LocalityLiveStatus } from "@/lib/api/types";
import { liveRefreshIntervals } from "@/lib/live/config";
import { useLiveResource } from "@/lib/live/refresh";

export function LiveLocalityDetail({ areaSlug, subAreaSlug, initialStatus, initialAnalytics }: { areaSlug: string; subAreaSlug: string; initialStatus: LocalityLiveStatus; initialAnalytics: DailyUtilityAnalytics | null }) {
  const [version, setVersion] = useState(0);
  const status = useLiveResource({ key: `${areaSlug}/${subAreaSlug}/${version}`, initialData: initialStatus, intervalMs: liveRefreshIntervals.localityStatusMs, maxBackoffMs: liveRefreshIntervals.maxBackoffMs, load: (signal) => getSlugLocalityLiveStatus(areaSlug, subAreaSlug, signal) });
  const analytics = useLiveResource({ key: `${areaSlug}/${subAreaSlug}/${version}`, initialData: initialAnalytics, intervalMs: liveRefreshIntervals.analyticsMs, maxBackoffMs: liveRefreshIntervals.maxBackoffMs, load: (signal) => getSlugDailyUtilityAnalytics(areaSlug, subAreaSlug, signal) });
  const current = status.data ?? initialStatus;
  return <><div className="mt-4"><LiveIndicator paused={status.paused} refreshing={status.refreshing} failed={status.error} onRefresh={() => setVersion((value) => value + 1)} /></div><div className="mt-5 grid gap-4 md:grid-cols-2"><StatusCard utility="ELECTRICITY" status={current.electricity} /><StatusCard utility="GAS" status={current.gas} /></div><section className="mt-8" aria-labelledby="today-heading"><h2 id="today-heading" className="text-2xl font-black tracking-tight text-slate-950">Today&apos;s observed history</h2>{analytics.data ? <div className="mt-5"><DailyAnalytics analytics={analytics.data} /></div> : <p role="alert" className="mt-5 rounded-3xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950">Current status is available, but today&apos;s analytics couldn&apos;t load.</p>}</section></>;
}
