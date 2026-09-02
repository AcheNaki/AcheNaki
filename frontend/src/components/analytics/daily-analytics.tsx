import type { DailyUtilityAnalytics, ElectricityStatus, GasStatus } from "@/lib/api/types";
import { formatDuration, formatDhakaTime, formatLongestOutage, withUnknownGaps } from "@/lib/dashboard/domain";

const electricityNames: Record<ElectricityStatus | "UNKNOWN", string> = {
  AVAILABLE: "Available evidence", UNAVAILABLE: "Loadshedding observed", UNSTABLE: "Unstable", UNKNOWN: "Unknown coverage",
};
const gasNames: Record<GasStatus | "UNKNOWN", string> = {
  NORMAL: "Normal", LOW: "Low", VERY_LOW: "Very low", UNAVAILABLE: "Unavailable", UNKNOWN: "Unknown coverage",
};
const timelineTones: Record<string, string> = {
  AVAILABLE: "bg-emerald-500", NORMAL: "bg-emerald-500", UNAVAILABLE: "bg-rose-500", LOW: "bg-amber-400", VERY_LOW: "bg-orange-500", UNSTABLE: "bg-amber-500", UNKNOWN: "bg-slate-300",
};

function Metric({ label, value, valueDescription }: { label: string; value: string; valueDescription?: string }) {
  // A placeholder such as "—" carries no meaning when read aloud, so it is announced in words.
  return <div className="rounded-2xl bg-slate-50 px-3 py-3"><dt className="text-xs font-semibold text-slate-500">{label}</dt><dd className="mt-1 text-base font-black text-slate-950">{valueDescription ? <><span aria-hidden="true">{value}</span><span className="sr-only">{valueDescription}</span></> : value}</dd></div>;
}

function Timeline({ title, segments, names }: { title: string; segments: { status: string; startedAt: string; endedAt: string; durationSeconds: number }[]; names: Record<string, string> }) {
  const total = segments.reduce((sum, segment) => sum + segment.durationSeconds, 0);
  return <section className="mt-6" aria-labelledby={`${title.toLowerCase().replaceAll(" ", "-")}-timeline`}>
    <div className="flex items-baseline justify-between gap-4"><h3 id={`${title.toLowerCase().replaceAll(" ", "-")}-timeline`} className="font-bold text-slate-950">{title} timeline</h3><span className="text-xs text-slate-500">00:00 — now</span></div>
    <div className="mt-3 flex h-8 overflow-hidden rounded-xl border border-slate-200 bg-slate-100" role="img" aria-label={segments.map((segment) => `${names[segment.status]} for ${formatDuration(segment.durationSeconds)}`).join("; ")}>
      {segments.map((segment, index) => <div key={`${segment.status}-${segment.startedAt}-${index}`} className={`${timelineTones[segment.status] ?? "bg-slate-300"} min-w-px`} style={{ width: `${total ? (segment.durationSeconds / total) * 100 : 0}%` }} />)}
    </div>
    <ul className="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-2">
      {segments.map((segment, index) => <li key={`${segment.endedAt}-${index}`} className="flex items-center gap-2"><span aria-hidden="true" className={`size-2 rounded-sm ${timelineTones[segment.status] ?? "bg-slate-300"}`} /><span>{names[segment.status]} · {formatDuration(segment.durationSeconds)} · {formatDhakaTime(segment.startedAt)}–{formatDhakaTime(segment.endedAt)}</span></li>)}
    </ul>
  </section>;
}

export function DailyAnalytics({ analytics }: { analytics: DailyUtilityAnalytics }) {
  const electricitySegments = withUnknownGaps(analytics.window, analytics.electricity.segments.map((segment) => ({ status: segment.status, startedAt: segment.started_at, endedAt: segment.ended_at, durationSeconds: segment.duration_seconds })));
  // A gas interval is only classified up to whichever comes first: its transition end or
  // the point its evidence expired. Beyond that the timeline must fall back to UNKNOWN.
  const gasSegments = withUnknownGaps(analytics.window, analytics.gas.intervals.map((interval) => ({
    status: interval.status,
    startedAt: interval.started_at,
    endedAt: interval.ended_at !== null && interval.ended_at < interval.observed_until_at
      ? interval.ended_at
      : interval.observed_until_at,
    durationSeconds: interval.duration_seconds,
  })));
  const e = analytics.electricity;
  const g = analytics.gas;
  return <div className="grid gap-5 lg:grid-cols-2">
    <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><p className="text-sm font-bold text-amber-700">⚡ Today&apos;s Electricity</p><dl className="mt-4 grid grid-cols-2 gap-3"><Metric label="Outages" value={String(e.outage_count)} /><Metric label="Total outage" value={formatDuration(e.total_outage_seconds)} /><Metric label="Longest outage" value={formatLongestOutage(e.outage_count, e.longest_outage_seconds)} valueDescription={e.outage_count === 0 ? "No confirmed outage today" : undefined} /><Metric label="Observed coverage" value={formatDuration(e.coverage.observed_seconds)} /><Metric label="Unknown" value={formatDuration(e.coverage.unknown_seconds)} />{e.ongoing_outage_seconds > 0 ? <Metric label="Current outage" value={`${formatDuration(e.ongoing_outage_seconds)} and counting`} /> : null}</dl>{e.outage_count === 0 ? <p className="mt-4 text-sm text-slate-600">No confirmed community-inferred outages yet today.</p> : null}<Timeline title="Electricity" segments={electricitySegments} names={electricityNames} /></section>
    <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><p className="text-sm font-bold text-teal-700">◒ Today&apos;s Gas</p><dl className="mt-4 grid grid-cols-2 gap-3"><Metric label="Normal" value={formatDuration(g.state_seconds.normal)} /><Metric label="Low" value={formatDuration(g.state_seconds.low)} /><Metric label="Very low" value={formatDuration(g.state_seconds.very_low)} /><Metric label="Unavailable" value={formatDuration(g.state_seconds.unavailable)} /><Metric label="Observed coverage" value={formatDuration(g.coverage.observed_seconds)} /><Metric label="Unknown" value={formatDuration(g.coverage.unknown_seconds)} /></dl><Timeline title="Gas" segments={gasSegments} names={gasNames} /></section>
  </div>;
}
