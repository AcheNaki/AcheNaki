"use client";

import type { Area, SubArea } from "@/lib/api/types";
import { SearchablePicker } from "./searchable-picker";

interface LocationSelectorProps {
  areas: Area[];
  subAreas: SubArea[];
  areaId: number | null;
  subAreaId: number | null;
  areasLoading: boolean;
  areasError: boolean;
  subAreasLoading: boolean;
  subAreasError: boolean;
  canCancel: boolean;
  onAreaChange: (area: Area) => void;
  onSubAreaChange: (subArea: SubArea) => void;
  onRetryAreas: () => void;
  onRetrySubAreas: () => void;
  onConfirm: () => void;
  onCancel: () => void;
}

export function LocationSelector(props: LocationSelectorProps) {
  const canContinue = props.areaId !== null && props.subAreaId !== null && !props.subAreasLoading;

  return (
    <section aria-labelledby="location-heading" className="rounded-[1.75rem] border border-white/80 bg-white p-5 shadow-xl shadow-slate-900/5 sm:p-7">
      <div className="mb-6">
        <div className="mb-3 grid h-11 w-11 place-items-center rounded-2xl bg-teal-100 text-teal-800" aria-hidden="true">
          <svg viewBox="0 0 24 24" className="h-6 w-6">
            <path d="M12 21s7-6.1 7-12a7 7 0 1 0-14 0c0 5.9 7 12 7 12Z" fill="none" stroke="currentColor" strokeWidth="1.8" />
            <circle cx="12" cy="9" r="2.4" fill="currentColor" />
          </svg>
        </div>
        <p className="text-sm font-bold uppercase tracking-[0.18em] text-teal-700">Your locality</p>
        <h2 id="location-heading" className="mt-2 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">
          Where are you reporting from?
        </h2>
        <p className="mt-2 text-sm leading-6 text-slate-600">Choose from our predefined Dhaka localities. No address needed.</p>
      </div>

      {props.areasError ? (
        <InlineRetry message="Couldn’t load Dhaka areas." onRetry={props.onRetryAreas} />
      ) : (
        <div className="space-y-5">
          <SearchablePicker
            label="Major Area"
            searchLabel="Search major areas"
            placeholder="Select area"
            options={props.areas}
            selectedId={props.areaId}
            onSelect={(option) => props.onAreaChange(option as Area)}
            loading={props.areasLoading}
            emptyMessage="No area matches that search."
          />

          <SearchablePicker
            label="Sub-area / Locality"
            searchLabel="Search localities"
            placeholder={props.areaId === null ? "Select a major area first" : "Select locality"}
            options={props.subAreas}
            selectedId={props.subAreaId}
            onSelect={(option) => props.onSubAreaChange(option as SubArea)}
            disabled={props.areaId === null || props.subAreasError}
            loading={props.subAreasLoading}
            emptyMessage="No active localities are available for this area."
          />

          {props.subAreasError ? (
            <InlineRetry message="Couldn’t load localities for this area." onRetry={props.onRetrySubAreas} />
          ) : null}
        </div>
      )}

      <div className="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        {props.canCancel ? (
          <button type="button" onClick={props.onCancel} className="min-h-12 rounded-xl px-5 font-semibold text-slate-600 hover:bg-slate-100">
            Keep current location
          </button>
        ) : null}
        <button
          type="button"
          onClick={props.onConfirm}
          disabled={!canContinue}
          className="min-h-12 rounded-xl bg-slate-950 px-6 font-bold text-white shadow-lg shadow-slate-950/15 transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500 disabled:shadow-none"
        >
          Continue
        </button>
      </div>
    </section>
  );
}

function InlineRetry({ message, onRetry }: { message: string; onRetry: () => void }) {
  return (
    <div role="alert" className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
      <span>{message}</span>
      <button type="button" onClick={onRetry} className="min-h-11 rounded-xl bg-white px-4 font-bold shadow-sm hover:bg-rose-100">
        Try again
      </button>
    </div>
  );
}
