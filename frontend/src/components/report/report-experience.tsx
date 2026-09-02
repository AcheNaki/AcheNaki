"use client";

import { useEffect, useState } from "react";
import { ensureAnonymousReporterToken } from "@/lib/api/client";
import { useAreas, useSubAreas } from "@/hooks/use-location-data";
import {
  clearSavedLocality,
  loadSavedLocality,
  saveLocality,
  type SavedLocality,
} from "@/lib/reporting/locality-storage";
import { LocationSelector } from "./location-selector";
import { SubmissionFeedback } from "./submission-feedback";
import { UtilityReportForm, type SubmissionSnapshot } from "./utility-report-form";

export function ReportExperience() {
  const areas = useAreas();
  const [savedCandidate, setSavedCandidate] = useState<SavedLocality | null | undefined>(undefined);
  const [restorationDone, setRestorationDone] = useState(false);
  const [activeLocality, setActiveLocality] = useState<SavedLocality | null>(null);
  const [choosingLocation, setChoosingLocation] = useState(true);
  const [areaId, setAreaId] = useState<number | null>(null);
  const [subAreaId, setSubAreaId] = useState<number | null>(null);
  const subAreas = useSubAreas(areaId);

  const [submission, setSubmission] = useState<SubmissionSnapshot | null>(null);

  useEffect(() => {
    // Client storage cannot be read during server rendering; this one transition is hydration-safe.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setSavedCandidate(loadSavedLocality());
    ensureAnonymousReporterToken().catch(() => {
      // Submission presents a contextual retryable error if prefetching fails.
    });
  }, []);

  useEffect(() => {
    if (restorationDone || savedCandidate === undefined || areas.loading || areas.error) return;

    if (savedCandidate === null) {
      // This state machine advances only after client storage and the area API have resolved.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setRestorationDone(true);
      return;
    }

    const canonicalArea = areas.data.find((area) => area.id === savedCandidate.areaId);
    if (!canonicalArea) {
      clearSavedLocality();
      setSavedCandidate(null);
      setRestorationDone(true);
      return;
    }

    setAreaId(canonicalArea.id);
  }, [areas.data, areas.error, areas.loading, restorationDone, savedCandidate]);

  useEffect(() => {
    if (
      restorationDone ||
      !savedCandidate ||
      areaId !== savedCandidate.areaId ||
      subAreas.loading ||
      subAreas.error
    ) {
      return;
    }

    const canonicalArea = areas.data.find((area) => area.id === savedCandidate.areaId);
    const canonicalSubArea = subAreas.data.find((subArea) => subArea.id === savedCandidate.subAreaId);
    if (!canonicalArea || !canonicalSubArea) {
      clearSavedLocality();
      // The persisted relationship was rejected by the authoritative API response.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setSavedCandidate(null);
      setSubAreaId(null);
      setRestorationDone(true);
      return;
    }

    const verified = {
      areaId: canonicalArea.id,
      areaName: canonicalArea.name,
      subAreaId: canonicalSubArea.id,
      subAreaName: canonicalSubArea.name,
    };
    saveLocality(verified);
    setSubAreaId(verified.subAreaId);
    setActiveLocality(verified);
    setChoosingLocation(false);
    setRestorationDone(true);
  }, [areaId, areas.data, restorationDone, savedCandidate, subAreas.data, subAreas.error, subAreas.loading]);

  const selectedArea = areas.data.find((area) => area.id === areaId) ?? null;
  const selectedSubArea = subAreas.data.find((subArea) => subArea.id === subAreaId) ?? null;
  function changeArea(nextAreaId: number) {
    setAreaId(nextAreaId);
    setSubAreaId(null);
  }

  function confirmLocation() {
    if (!selectedArea || !selectedSubArea) return;

    const locality = {
      areaId: selectedArea.id,
      areaName: selectedArea.name,
      subAreaId: selectedSubArea.id,
      subAreaName: selectedSubArea.name,
    };
    saveLocality(locality);
    setActiveLocality(locality);
    setChoosingLocation(false);
    setSubmission(null);
  }

  function cancelLocationChange() {
    if (!activeLocality) return;
    setAreaId(activeLocality.areaId);
    setSubAreaId(activeLocality.subAreaId);
    setChoosingLocation(false);
  }

  if (!restorationDone && !areas.error) {
    return <LoadingCard />;
  }

  if (submission) {
    return (
      <SubmissionFeedback
        duplicate={submission.duplicate}
        locality={submission.locality}
        status={submission.status}
        onAgain={() => setSubmission(null)}
      />
    );
  }

  if (choosingLocation || !activeLocality) {
    return (
      <LocationSelector
        areas={areas.data}
        subAreas={subAreas.data}
        areaId={areaId}
        subAreaId={subAreaId}
        areasLoading={areas.loading}
        areasError={areas.error}
        subAreasLoading={subAreas.loading}
        subAreasError={subAreas.error}
        canCancel={activeLocality !== null}
        onAreaChange={(area) => changeArea(area.id)}
        onSubAreaChange={(subArea) => setSubAreaId(subArea.id)}
        onRetryAreas={areas.retry}
        onRetrySubAreas={subAreas.retry}
        onConfirm={confirmLocation}
        onCancel={cancelLocationChange}
      />
    );
  }

  return (
    <div className="space-y-5">
      <section className="flex items-center justify-between gap-4 rounded-2xl border border-teal-200 bg-teal-50 px-4 py-3 sm:px-5">
        <div className="min-w-0">
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Reporting for</p>
          <p className="mt-1 truncate font-bold text-slate-950">{activeLocality.subAreaName}, {activeLocality.areaName}</p>
        </div>
        <button type="button" onClick={() => setChoosingLocation(true)} className="min-h-11 shrink-0 rounded-xl bg-white px-4 text-sm font-bold text-teal-800 shadow-sm transition hover:bg-teal-100">
          Change
        </button>
      </section>

      <UtilityReportForm
        locality={activeLocality}
        onSubmitted={setSubmission}
        onInvalidLocality={() => {
          clearSavedLocality();
          setActiveLocality(null);
          setChoosingLocation(true);
        }}
      />
    </div>
  );
}

function LoadingCard() {
  return (
    <section role="status" aria-live="polite" className="rounded-[1.75rem] border border-white/80 bg-white p-6 shadow-xl shadow-slate-900/5">
      <div className="h-11 w-11 animate-pulse rounded-2xl bg-teal-100" />
      <div className="mt-5 h-4 w-28 animate-pulse rounded bg-slate-200" />
      <div className="mt-3 h-8 w-3/4 animate-pulse rounded bg-slate-200" />
      <div className="mt-7 h-14 animate-pulse rounded-2xl bg-slate-100" />
      <span className="sr-only">Loading your reporting location…</span>
    </section>
  );
}
