"use client";

import Link from "next/link";
import { useCallback, useEffect, useState, type ReactNode } from "react";
import { DashboardLiveUpdates } from "@/components/live/dashboard-live-updates";
import { LiveIndicator } from "@/components/live/live-indicator";
import { ApiError } from "@/lib/api/client";
import type { DailyUtilityAnalytics, DashboardData, LocalityLiveStatus, RecentlyResolvedElectricityEvent } from "@/lib/api/types";
import { formatRelativeTime, localityFullName, reportCountLabel, statusCopy, strugglingEmptyStateCopy } from "@/lib/dashboard/domain";

// The dashboard API is already bounded, but the homepage shows a digest of it: a long wall of
// locality cards buries the rest of the page. "Browse areas" stays the path to the full list.
const HOMEPAGE_LIST_LIMIT = 6;
import { useAreas, useSubAreas } from "@/hooks/use-location-data";
import { clearSavedLocality, loadSavedLocality, saveLocality, type SavedLocality } from "@/lib/reporting/locality-storage";
import { StatusCard } from "@/components/status/status-card";
import { DailySnapshot } from "@/components/analytics/daily-snapshot";
import { LocationSelector } from "@/components/report/location-selector";

function LocalityLink({ areaSlug, subAreaSlug, children }: { areaSlug: string; subAreaSlug: string; children: ReactNode }) {
  return <Link href={`/area/${areaSlug}/${subAreaSlug}`} className="font-bold text-teal-800 underline decoration-teal-300 underline-offset-4 hover:text-teal-950">{children}</Link>;
}

// Two visual lines, one announced name: a locality such as "Block C" exists under several major
// areas, so these cards must never present the sub-area name on its own.
function LocalityHeading({ subArea }: { subArea: { name: string; area: { name: string } } }) {
  return <p className="font-black text-slate-950">
    <span className="sr-only">{localityFullName(subArea)}</span>
    <span aria-hidden="true">{subArea.name}</span>
    <span aria-hidden="true" className="block text-xs font-medium text-slate-500">{subArea.area.name}</span>
  </p>;
}

export function DashboardExperience() {
  const areas = useAreas();
  const [saved, setSaved] = useState<SavedLocality | null | undefined>();
  const [locality, setLocality] = useState<LocalityLiveStatus | null>(null);
  const [localityError, setLocalityError] = useState(false);
  const [analytics, setAnalytics] = useState<DailyUtilityAnalytics | null>(null);
  const [analyticsError, setAnalyticsError] = useState(false);
  const [choosing, setChoosing] = useState(false);
  const [areaId, setAreaId] = useState<number | null>(null);
  const [subAreaId, setSubAreaId] = useState<number | null>(null);
  const subAreas = useSubAreas(areaId);
  const [dashboard, setDashboard] = useState<DashboardData | null>(null);
  const [dashboardError, setDashboardError] = useState(false);
  const [restored, setRestored] = useState<RecentlyResolvedElectricityEvent[]>([]);
  const [refreshKey, setRefreshKey] = useState(0);
  const [liveState, setLiveState] = useState({ paused: false, refreshing: false, failed: false });
  const [announcement, setAnnouncement] = useState("");
  const handleLiveState = useCallback((state: { paused: boolean; refreshing: boolean; failed: boolean }) => setLiveState(state), []);

  useEffect(() => {
    // Client storage is intentionally read after hydration.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setSaved(loadSavedLocality());
  }, []);

  const selectedArea = areas.data.find((area) => area.id === areaId);
  const selectedSubArea = subAreas.data.find((subArea) => subArea.id === subAreaId);
  // The remembered names are a browser copy; the API response is canonical. Prefer it once
  // it has loaded for this exact locality so a renamed locality never displays a stale name.
  const localityMatchesSaved = locality !== null && saved != null && locality.sub_area.id === saved.subAreaId;
  function confirmLocation() {
    if (!selectedArea || !selectedSubArea) return;
    const next = { areaId: selectedArea.id, areaName: selectedArea.name, subAreaId: selectedSubArea.id, subAreaName: selectedSubArea.name };
    saveLocality(next);
    // Every locality-scoped view must be dropped together, or the previous locality's
    // status stays on screen under the newly chosen locality's heading until the next poll.
    setLocality(null); setLocalityError(false); setAnalytics(null); setAnalyticsError(false);
    setSaved(next); setChoosing(false);
  }
  return <>
    <DashboardLiveUpdates subAreaId={saved?.subAreaId ?? null} refreshKey={refreshKey} onLocality={(data) => { setLocality(data); setLocalityError(false); }} onLocalityError={(reason, hadData) => { if (reason instanceof ApiError && reason.status === 404) { clearSavedLocality(); setSaved(null); setLocality(null); setAnalytics(null); return; } if (!hadData) setLocalityError(true); }} onAnalytics={(data) => { setAnalytics(data); setAnalyticsError(false); }} onAnalyticsError={(hadData) => { if (!hadData) setAnalyticsError(true); }} onDashboard={(data) => { setDashboard(data); setDashboardError(false); }} onDashboardError={(hadData) => { if (!hadData) setDashboardError(true); }} onRestored={setRestored} onAnnouncement={setAnnouncement} onState={handleLiveState} />
    <p className="sr-only" aria-live="polite">{announcement}</p>
    <section id="my-area" className="mt-10" aria-labelledby="my-area-heading my-area-locality">
      {saved === undefined ? <AreaSkeleton /> : choosing || !saved ? <LocationSelector areas={areas.data} subAreas={subAreas.data} areaId={areaId} subAreaId={subAreaId} areasLoading={areas.loading} areasError={areas.error} subAreasLoading={subAreas.loading} subAreasError={subAreas.error} canCancel={Boolean(saved)} onAreaChange={(area) => { setAreaId(area.id); setSubAreaId(null); }} onSubAreaChange={(subArea) => setSubAreaId(subArea.id)} onRetryAreas={areas.retry} onRetrySubAreas={subAreas.retry} onConfirm={confirmLocation} onCancel={() => setChoosing(false)} /> : <section className="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <div className="flex flex-wrap items-start justify-between gap-4"><div><p className="text-sm font-bold uppercase tracking-[0.16em] text-teal-700">YOUR AREA</p><h2 id="my-area-heading" className="mt-1 text-2xl font-black tracking-tight text-slate-950">আপনার এলাকার হালচাল 👀</h2><p id="my-area-locality" className="mt-1 font-bold text-slate-800">{localityMatchesSaved ? `${locality.sub_area.name}, ${locality.sub_area.area.name}` : `${saved.subAreaName}, ${saved.areaName}`}</p><p className="mt-2 text-sm text-slate-600">এলাকার মানুষের latest report — অফিসিয়াল ঘোষণা না।</p><div className="mt-2"><LiveIndicator paused={liveState.paused} refreshing={liveState.refreshing} failed={liveState.failed} onRefresh={() => setRefreshKey((value) => value + 1)} /></div></div><button type="button" onClick={() => { setAreaId(saved.areaId); setSubAreaId(saved.subAreaId); setChoosing(true); }} className="min-h-11 rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50">Change</button></div>
        {localityError ? <InlineError message="Couldn’t load the current status for your area." onRetry={() => setRefreshKey((value) => value + 1)} /> : locality ? <div className="mt-5 grid gap-4 md:grid-cols-2"><StatusCard utility="ELECTRICITY" status={locality.electricity} /><StatusCard utility="GAS" status={locality.gas} /></div> : <AreaSkeleton />}
        {analytics ? <DailySnapshot analytics={analytics} /> : analyticsError ? <p role="alert" className="mt-5 text-sm text-amber-800">Today&apos;s analytics couldn&apos;t load. Current status is still available.</p> : <div className="mt-5 h-28 animate-pulse rounded-2xl bg-slate-100"><span className="sr-only">Loading today&apos;s snapshot…</span></div>}
        <div className="mt-5 flex flex-wrap gap-3"><Link href="/report" className="inline-flex min-h-12 items-center rounded-xl bg-slate-950 px-5 font-bold text-white hover:bg-teal-800">এলাকার খবর দিন</Link>{locality?.sub_area.area.slug && locality.sub_area.slug ? <LocalityLink areaSlug={locality.sub_area.area.slug} subAreaSlug={locality.sub_area.slug}>পুরো কাহিনি দেখুন →</LocalityLink> : null}</div>
      </section>}
    </section>
    <section className="mt-10" aria-labelledby="struggling-heading"><div className="flex items-end justify-between gap-4"><div><p className="text-sm font-bold uppercase tracking-[0.16em] text-rose-700">LIVE LOCALITY SIGNALS</p><h2 id="struggling-heading" className="mt-1 text-2xl font-black tracking-tight text-slate-950">কোথায় ঝামেলা চলছে? 👀</h2></div><Link href="/areas" className="text-sm font-bold text-teal-800">Browse areas →</Link></div>{dashboardError ? <InlineError message="Couldn’t load live locality updates." onRetry={() => setRefreshKey((value) => value + 1)} /> : dashboard ? <ProjectionList items={dashboard.struggling.slice(0, HOMEPAGE_LIST_LIMIT)} empty={strugglingEmptyStateCopy} /> : <ListSkeleton />}</section>
    {restored.length ? <section className="mt-10" aria-labelledby="restored-heading"><p className="text-sm font-bold uppercase tracking-[0.16em] text-amber-700">Community-inferred events</p><h2 id="restored-heading" className="mt-1 text-2xl font-black tracking-tight text-slate-950">⚡ Batti Is Back</h2><div className="mt-4 grid gap-3 md:grid-cols-2">{restored.map((event) => <article key={`${event.sub_area.slug}-${event.ended_at}`} className="rounded-2xl border border-amber-200 bg-amber-50 p-4"><LocalityHeading subArea={event.sub_area} /><p className="mt-1 text-sm text-slate-700">Power appears restored {event.ended_at ? formatRelativeTime(event.ended_at) : "recently"}.</p><LocalityLink areaSlug={event.sub_area.area.slug} subAreaSlug={event.sub_area.slug}>View details</LocalityLink></article>)}</div></section> : null}
    {dashboard ? <section className="mt-10" aria-labelledby="changes-heading"><h2 id="changes-heading" className="text-2xl font-black tracking-tight text-slate-950">Recent Changes</h2><p className="mt-1 text-sm text-slate-600">কে ফিরলো? কে আবার উধাও?</p><div className="mt-4"><ProjectionList items={dashboard.recent_changes.slice(0, HOMEPAGE_LIST_LIMIT)} empty="No recent locality projections yet." /></div></section> : null}
  </>;
}

function ProjectionList({ items, empty }: { items: DashboardData["struggling"]; empty: string }) { return items.length ? <div className="mt-4 grid gap-3 md:grid-cols-2">{items.map((item) => { const copy = statusCopy(item.utility_type, item.status); return <article key={`${item.utility_type}-${item.sub_area.id}`} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex items-start gap-3"><span aria-hidden="true" className="grid size-9 place-items-center rounded-xl bg-slate-100">{copy.icon}</span><div className="min-w-0 flex-1"><LocalityHeading subArea={item.sub_area} /><p className="mt-1 text-sm font-semibold text-slate-700">{copy.label}</p><p className="mt-1 text-xs text-slate-500">{item.confidence ? `${item.confidence[0]}${item.confidence.slice(1).toLowerCase()} confidence · ` : ""}{reportCountLabel(item.recent_reports)} · Updated {item.last_report_at ? formatRelativeTime(item.last_report_at) : "recently"}</p></div></div><div className="mt-3"><LocalityLink areaSlug={item.sub_area.area.slug} subAreaSlug={item.sub_area.slug}>View details</LocalityLink></div></article>; })}</div> : <p className="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-sm text-slate-600">{empty}</p>; }
function AreaSkeleton() { return <div role="status" className="mt-5 grid gap-4 md:grid-cols-2"><div className="h-44 animate-pulse rounded-3xl bg-slate-100" /><div className="h-44 animate-pulse rounded-3xl bg-slate-100" /><span className="sr-only">Loading locality data…</span></div>; }
function ListSkeleton() { return <div role="status" className="mt-4 grid gap-3 md:grid-cols-2"><div className="h-28 animate-pulse rounded-2xl bg-slate-100" /><div className="h-28 animate-pulse rounded-2xl bg-slate-100" /><span className="sr-only">Loading live localities…</span></div>; }
function InlineError({ message, onRetry }: { message: string; onRetry: () => void }) { return <div role="alert" className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900"><span>{message}</span><button type="button" onClick={onRetry} className="min-h-10 rounded-xl bg-white px-3 font-bold">Try again</button></div>; }
