"use client";

import type { ChoiceOption } from "@/lib/reporting/domain";

interface ChoiceGroupProps<T extends string> {
  legend: string;
  helper?: string;
  options: ChoiceOption<T>[];
  value: T | null | undefined;
  onChange: (value: T) => void;
  layout?: "cards" | "chips";
}

export function ChoiceGroup<T extends string>({
  legend,
  helper,
  options,
  value,
  onChange,
  layout = "cards",
}: ChoiceGroupProps<T>) {
  return (
    <fieldset className="animate-reveal">
      <legend className="text-lg font-bold text-slate-950">{legend}</legend>
      {helper ? <p className="mt-1 text-sm leading-6 text-slate-600">{helper}</p> : null}
      <div className={layout === "chips" ? "mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4" : "mt-4 grid gap-3 sm:grid-cols-2"}>
        {options.map((option) => {
          const selected = option.value === value;
          return (
            <button
              key={option.value}
              type="button"
              aria-pressed={selected}
              onClick={() => onChange(option.value)}
              className={
                layout === "chips"
                  ? `min-h-12 rounded-xl border px-3 py-2 text-sm font-bold transition ${selected ? "border-teal-700 bg-teal-700 text-white shadow-md shadow-teal-900/10" : "border-slate-200 bg-white text-slate-700 hover:border-teal-400 hover:bg-teal-50"}`
                  : `flex min-h-[5.25rem] items-center gap-4 rounded-2xl border p-4 text-left transition ${selected ? "border-teal-700 bg-teal-50 ring-2 ring-teal-700/15" : "border-slate-200 bg-white hover:border-teal-400 hover:bg-teal-50/50"}`
              }
            >
              {layout === "cards" && option.symbol ? (
                <span aria-hidden="true" className={`grid h-11 w-11 shrink-0 place-items-center rounded-xl text-lg ${selected ? "bg-teal-700 text-white" : "bg-slate-100 text-slate-700"}`}>
                  {option.symbol}
                </span>
              ) : null}
              <span>
                <span className={layout === "cards" ? "block font-bold text-slate-950" : ""}>{option.label}</span>
                {layout === "cards" && option.description ? <span className="mt-1 block text-sm text-slate-600">{option.description}</span> : null}
              </span>
              {layout === "cards" && selected ? <span className="ml-auto text-teal-700" aria-hidden="true">✓</span> : null}
            </button>
          );
        })}
      </div>
    </fieldset>
  );
}
