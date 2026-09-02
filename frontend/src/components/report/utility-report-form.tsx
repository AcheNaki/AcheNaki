"use client";

import { useMemo, useState } from "react";
import { ApiError, submitUtilityReport } from "@/lib/api/client";
import type { ElectricityStatus, GasStatus, TimeBucket, UtilityType } from "@/lib/api/types";
import {
  buildReportPayload,
  labelFor,
  statusOptionsFor,
  timeBucketOptions,
  utilityOptions,
} from "@/lib/reporting/domain";
import type { SavedLocality } from "@/lib/reporting/locality-storage";
import { ChoiceGroup } from "./report-controls";

type ReportStatus = ElectricityStatus | GasStatus;
type Cookability = "YES" | "NO" | "UNTRIED";

export interface SubmissionSnapshot {
  duplicate: boolean;
  locality: string;
  status: string;
}

interface UtilityReportFormProps {
  locality: SavedLocality;
  onSubmitted: (submission: SubmissionSnapshot) => void;
  onInvalidLocality: () => void;
}

const cookabilityOptions = [
  { value: "YES" as const, label: "Yes" },
  { value: "NO" as const, label: "No" },
  { value: "UNTRIED" as const, label: "Haven’t tried" },
];

export function UtilityReportForm({ locality, onSubmitted, onInvalidLocality }: UtilityReportFormProps) {
  const [utility, setUtility] = useState<UtilityType | null>(null);
  const [status, setStatus] = useState<ReportStatus | null>(null);
  const [timeBucket, setTimeBucket] = useState<TimeBucket | null>(null);
  const [cookability, setCookability] = useState<Cookability | undefined>(undefined);
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [locationInvalid, setLocationInvalid] = useState(false);
  const statusOptions = useMemo(() => statusOptionsFor(utility), [utility]);
  const readyToSubmit = Boolean(utility && status && timeBucket && !submitting);

  function selectUtility(nextUtility: UtilityType) {
    setUtility(nextUtility);
    setStatus(null);
    setTimeBucket(null);
    setCookability(undefined);
    setSubmitError(null);
  }

  async function submit() {
    if (!utility || !status || !timeBucket || submitting) return;

    const canCook = cookability === "YES" ? true : cookability === "NO" ? false : cookability === "UNTRIED" ? null : undefined;
    const payload = buildReportPayload({
      areaId: locality.areaId,
      subAreaId: locality.subAreaId,
      utility,
      status,
      timeBucket,
      canCook,
    });

    setSubmitting(true);
    setSubmitError(null);
    setLocationInvalid(false);

    try {
      const result = await submitUtilityReport(payload);
      onSubmitted({
        duplicate: result.meta.duplicate,
        locality: `${locality.subAreaName}, ${locality.areaName}`,
        status: labelFor(statusOptions, status),
      });
    } catch (error) {
      if (error instanceof ApiError) {
        if (error.kind === "rate-limit") {
          setSubmitError("Too many reports in a short time. Please wait a little before submitting another update.");
        } else if (error.kind === "network" || error.kind === "configuration" || error.kind === "server") {
          setSubmitError("Couldn’t reach Ache Naki? right now. Check your connection and try again.");
        } else if (error.fields.area_id || error.fields.sub_area_id) {
          setLocationInvalid(true);
          setSubmitError("The selected locality is no longer available. Please choose it again.");
        } else if (error.fields.status || error.fields.utility_type || error.fields.time_bucket) {
          setSubmitError("One of your selections is no longer valid. Please review the report and try again.");
        } else {
          setSubmitError("Your anonymous reporting session couldn’t be refreshed. Please try again.");
        }
      } else {
        setSubmitError("Something unexpected happened. Please try again.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <section className="rounded-[1.75rem] border border-white/80 bg-white p-5 shadow-xl shadow-slate-900/5 sm:p-7">
      <header className="mb-7">
        <p className="text-sm font-bold uppercase tracking-[0.18em] text-teal-700">Quick report</p>
        <h2 className="mt-2 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">What’s the current ase?</h2>
        <p className="mt-2 text-sm leading-6 text-slate-600">Share what you observe right now. Your report is one local observation.</p>
      </header>

      <div className="space-y-8">
        <ChoiceGroup legend="1. Choose a utility" options={utilityOptions} value={utility} onChange={selectUtility} />

        {utility ? (
          <ChoiceGroup<ReportStatus>
            legend={utility === "ELECTRICITY" ? "2. Current electricity condition" : "2. Gas kemon?"}
            options={statusOptions}
            value={status}
            onChange={(nextStatus) => {
              setStatus(nextStatus);
              setSubmitError(null);
            }}
          />
        ) : null}

        {status ? (
          <ChoiceGroup
            legend="3. When did this condition start?"
            helper="Choose the closest estimate — the server records submission time."
            options={timeBucketOptions}
            value={timeBucket}
            onChange={(bucket) => {
              setTimeBucket(bucket);
              setSubmitError(null);
            }}
            layout="chips"
          />
        ) : null}

        {utility === "GAS" && timeBucket ? (
          <ChoiceGroup<Cookability>
            legend="Can you cook with this pressure?"
            helper="Optional"
            options={cookabilityOptions}
            value={cookability}
            onChange={setCookability}
            layout="chips"
          />
        ) : null}

        {utility && status && timeBucket ? (
          <section aria-labelledby="summary-heading" className="animate-reveal rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
            <div className="flex items-start justify-between gap-3">
              <div>
                <p id="summary-heading" className="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Ready to send</p>
                <p className="mt-2 font-bold text-slate-950">{locality.subAreaName}, {locality.areaName}</p>
                <p className="mt-1 text-sm leading-6 text-slate-700">
                  {labelFor(utilityOptions, utility)} · {labelFor(statusOptions, status)} · {labelFor(timeBucketOptions, timeBucket)}
                </p>
                {utility === "GAS" && cookability ? (
                  <p className="mt-1 text-sm text-slate-600">Cookability: {labelFor(cookabilityOptions, cookability)}</p>
                ) : null}
              </div>
              <span aria-hidden="true" className="text-2xl">{utility === "ELECTRICITY" ? "⚡" : "◒"}</span>
            </div>
          </section>
        ) : null}

        {submitError ? (
          <div role="alert" className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm leading-6 text-rose-900">
            <p>{submitError}</p>
            {locationInvalid ? (
              <button type="button" onClick={onInvalidLocality} className="mt-3 min-h-11 rounded-xl bg-white px-4 font-bold shadow-sm hover:bg-rose-100">
                Choose locality again
              </button>
            ) : null}
          </div>
        ) : null}

        <button
          type="button"
          onClick={submit}
          disabled={!readyToSubmit}
          aria-busy={submitting}
          className="min-h-14 w-full rounded-2xl bg-slate-950 px-6 text-base font-bold text-white shadow-lg shadow-slate-950/15 transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500 disabled:shadow-none"
        >
          {submitting ? "Submitting report…" : "Submit Report"}
        </button>
      </div>
    </section>
  );
}
