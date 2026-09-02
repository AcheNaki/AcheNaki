"use client";

export function LiveIndicator({ paused, refreshing, failed, onRefresh }: { paused: boolean; refreshing: boolean; failed: boolean; onRefresh?: () => void }) {
  const label = paused ? "Live updates paused" : failed ? "Live updates delayed" : refreshing ? "Refreshing…" : "Live updates on";
  return <div className="flex items-center gap-2 text-xs text-slate-500"><span aria-hidden="true" className={`size-2 rounded-full ${paused || failed ? "bg-amber-500" : "bg-emerald-500"}`} /> <span>{label}</span>{onRefresh ? <button type="button" onClick={onRefresh} disabled={refreshing} className="ml-1 font-bold text-teal-800 underline underline-offset-2 disabled:text-slate-400">Refresh</button> : null}</div>;
}
