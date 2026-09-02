import type { ElectricityProjectionStatus, GasProjectionStatus, LiveUtilityStatus, UtilityType } from "@/lib/api/types";
import { confidenceLabel, evidenceSummary, formatDhakaTime, statusCopy } from "@/lib/dashboard/domain";

const tones: Record<string, string> = {
  positive: "border-emerald-200 bg-emerald-50/70",
  critical: "border-rose-200 bg-rose-50/70",
  warning: "border-amber-200 bg-amber-50/70",
  mixed: "border-violet-200 bg-violet-50/70",
  muted: "border-slate-200 bg-slate-50",
};

export function StatusCard({
  utility,
  status,
  compact = false,
}: {
  utility: UtilityType;
  status: LiveUtilityStatus<ElectricityProjectionStatus | GasProjectionStatus>;
  compact?: boolean;
}) {
  const copy = statusCopy(utility, status.status);
  const confidence = confidenceLabel(status.confidence, status.status === "MIXED");
  const evidence = evidenceSummary(status);
  return (
    <section className={`rounded-3xl border p-5 ${tones[copy.tone]}`} aria-label={`${utility === "ELECTRICITY" ? "Electricity" : "Gas"}: ${copy.label}`}>
      <div className="flex items-start gap-3">
        <span aria-hidden="true" className="grid size-10 shrink-0 place-items-center rounded-2xl bg-white/80 text-xl shadow-sm">{copy.icon}</span>
        <div className="min-w-0">
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-slate-600">{utility === "ELECTRICITY" ? "Electricity" : "Gas"}</p>
          <h3 className="mt-1 text-lg font-black tracking-tight text-slate-950">{copy.label}</h3>
        </div>
      </div>
      {!compact && status.status === "INSUFFICIENT_DATA" ? (
        <p className="mt-4 text-sm leading-6 text-slate-600">We don&apos;t have enough recent community reports to estimate the current situation.</p>
      ) : null}
      {status.status !== "INSUFFICIENT_DATA" ? (
        <div className="mt-4 space-y-1 text-sm text-slate-700">
          {confidence ? <p className="font-semibold">{confidence}</p> : null}
          {status.status_since ? <p>Since approximately {formatDhakaTime(status.status_since)}</p> : status.status !== "MIXED" ? <p>Timing uncertain</p> : null}
          {evidence ? <p className="text-slate-600">{evidence}</p> : null}
          {status.last_report_at ? <p className="text-xs text-slate-500">Last community update {formatDhakaTime(status.last_report_at)}</p> : null}
        </div>
      ) : null}
    </section>
  );
}
