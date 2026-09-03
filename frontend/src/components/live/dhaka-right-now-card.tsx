"use client";

import { getLiveSummary } from "@/lib/api/client";
import { liveSummaryMetrics, NO_VALUE_DASH } from "@/lib/dashboard/domain";
import { liveRefreshIntervals } from "@/lib/live/config";
import { useLiveResource } from "@/lib/live/refresh";

const PLACEHOLDER_ROWS = 5;

/**
 * City-wide live aggregate. It runs on the same shared live-resource hook as every other poll
 * on this page, so it inherits the non-overlapping, visibility- and offline-aware refresh
 * behaviour instead of adding an independent timer.
 */
export function DhakaRightNowCard() {
  const summary = useLiveResource({
    key: "dhaka-right-now",
    intervalMs: liveRefreshIntervals.liveSummaryMs,
    maxBackoffMs: liveRefreshIntervals.maxBackoffMs,
    load: getLiveSummary,
  });
  // A failed refresh keeps the last good counts on screen; only a card that never loaded
  // shows the unavailable state.
  const unavailable = summary.error && summary.data === null;
  const metrics = summary.data ? liveSummaryMetrics(summary.data) : null;

  return (
    <section
      aria-labelledby="dhaka-right-now-heading"
      className="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
    >
      <div className="flex items-center gap-2">
        <span
          aria-hidden="true"
          className={`size-2 shrink-0 rounded-full ${unavailable || summary.paused ? "bg-amber-500" : "bg-emerald-500"}`}
        />
        <h2
          id="dhaka-right-now-heading"
          className="text-sm font-bold uppercase tracking-[0.16em] text-teal-700"
        >
          DHAKA RIGHT NOW 👀
        </h2>
      </div>

      {unavailable ? (
        <p role="status" className="mt-4 text-sm leading-6 text-slate-600">
          Live city counts aren&apos;t available right now. Your area below still updates on its own.
        </p>
      ) : (
        <ul className="mt-4 space-y-3 text-sm text-slate-700">
          {metrics
            ? metrics.map((metric) => (
                <li key={metric.key} className="flex items-baseline gap-2">
                  <span aria-hidden="true" className="shrink-0">{metric.icon}</span>
                  <span className="min-w-0">
                    <span className="font-black tabular-nums text-slate-950">{metric.value}</span>{" "}
                    {metric.label}
                  </span>
                </li>
              ))
            : // Never a zero while loading: an unverified "0" would read as confirmed calm.
              Array.from({ length: PLACEHOLDER_ROWS }, (_, index) => (
                <li key={index} className="flex items-center gap-2" aria-hidden="true">
                  <span className="size-4 shrink-0 rounded-full bg-slate-100" />
                  <span className="font-black text-slate-300">{NO_VALUE_DASH}</span>
                  <span className="h-3 w-full animate-pulse rounded-full bg-slate-100" />
                </li>
              ))}
        </ul>
      )}

      {metrics ? null : unavailable ? null : (
        <p role="status" className="sr-only">Loading the live Dhaka summary…</p>
      )}

      <p className="mt-4 text-xs leading-5 text-slate-500">
        Community reports across Dhaka, counted by locality — not utility-provider confirmation.
      </p>
    </section>
  );
}
