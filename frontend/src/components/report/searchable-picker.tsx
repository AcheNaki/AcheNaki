"use client";

import { useEffect, useId, useMemo, useRef, useState } from "react";

export interface PickerOption {
  id: number;
  name: string;
}

interface SearchablePickerProps {
  label: string;
  searchLabel: string;
  placeholder: string;
  options: PickerOption[];
  selectedId: number | null;
  onSelect: (option: PickerOption) => void;
  disabled?: boolean;
  loading?: boolean;
  emptyMessage?: string;
}

export function SearchablePicker({
  label,
  searchLabel,
  placeholder,
  options,
  selectedId,
  onSelect,
  disabled = false,
  loading = false,
  emptyMessage = "No matching locations found.",
}: SearchablePickerProps) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const titleId = useId();
  const searchRef = useRef<HTMLInputElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const dialogRef = useRef<HTMLElement>(null);
  const selected = options.find((option) => option.id === selectedId);
  const filteredOptions = useMemo(() => {
    const normalized = query.trim().toLocaleLowerCase();
    if (!normalized) return options;
    return options.filter((option) => option.name.toLocaleLowerCase().includes(normalized));
  }, [options, query]);

  useEffect(() => {
    if (!open) return;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    window.requestAnimationFrame(() => searchRef.current?.focus());

    const handleDialogKeys = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setOpen(false);
        window.requestAnimationFrame(() => triggerRef.current?.focus());
        return;
      }

      if (event.key !== "Tab") return;
      const focusable = dialogRef.current?.querySelectorAll<HTMLElement>(
        'button:not([disabled]), input:not([disabled]), [href], [tabindex]:not([tabindex="-1"])',
      );
      if (!focusable?.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };
    window.addEventListener("keydown", handleDialogKeys);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener("keydown", handleDialogKeys);
    };
  }, [open]);

  function choose(option: PickerOption) {
    onSelect(option);
    setOpen(false);
    setQuery("");
    window.requestAnimationFrame(() => triggerRef.current?.focus());
  }

  function close() {
    setOpen(false);
    window.requestAnimationFrame(() => triggerRef.current?.focus());
  }

  return (
    <div>
      <span className="mb-2 block text-sm font-semibold text-slate-800">{label}</span>
      <button
        ref={triggerRef}
        type="button"
        onClick={() => setOpen(true)}
        disabled={disabled || loading}
        aria-haspopup="dialog"
        className="flex min-h-14 w-full items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 text-left text-base shadow-sm transition hover:border-teal-400 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
      >
        <span className={selected ? "font-medium text-slate-900" : "text-slate-500"}>
          {loading ? "Loading…" : selected?.name ?? placeholder}
        </span>
        <svg aria-hidden="true" viewBox="0 0 20 20" className="h-5 w-5 shrink-0 text-slate-400">
          <path d="m6 8 4 4 4-4" fill="none" stroke="currentColor" strokeLinecap="round" strokeWidth="1.8" />
        </svg>
      </button>

      {open ? (
        <div
          className="fixed inset-0 z-50 flex items-end bg-slate-950/35 p-0 backdrop-blur-[2px] sm:items-center sm:justify-center sm:p-6"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) close();
          }}
        >
          <section
            ref={dialogRef}
            role="dialog"
            aria-modal="true"
            aria-labelledby={titleId}
            className="flex max-h-[85dvh] w-full flex-col rounded-t-[1.75rem] bg-white shadow-2xl sm:max-w-lg sm:rounded-[1.75rem]"
          >
            <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
              <h2 id={titleId} className="text-lg font-bold text-slate-950">
                {label}
              </h2>
              <button
                type="button"
                onClick={close}
                aria-label={`Close ${label}`}
                className="grid h-11 w-11 place-items-center rounded-full text-2xl text-slate-500 transition hover:bg-slate-100"
              >
                ×
              </button>
            </div>
            <div className="px-5 pt-4">
              <label htmlFor={`${titleId}-search`} className="sr-only">
                {searchLabel}
              </label>
              <div className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 focus-within:border-teal-500 focus-within:ring-4 focus-within:ring-teal-500/10">
                <svg aria-hidden="true" viewBox="0 0 20 20" className="h-5 w-5 text-slate-400">
                  <circle cx="8.5" cy="8.5" r="5.5" fill="none" stroke="currentColor" strokeWidth="1.7" />
                  <path d="m13 13 4 4" stroke="currentColor" strokeLinecap="round" strokeWidth="1.7" />
                </svg>
                <input
                  ref={searchRef}
                  id={`${titleId}-search`}
                  type="search"
                  value={query}
                  onChange={(event) => setQuery(event.target.value)}
                  placeholder={searchLabel}
                  className="min-h-12 w-full bg-transparent py-3 text-base text-slate-950 outline-none placeholder:text-slate-400"
                />
              </div>
            </div>
            <div className="mt-3 overflow-y-auto px-3 pb-[max(1rem,env(safe-area-inset-bottom))]">
              {filteredOptions.length ? (
                <ul className="space-y-1" aria-label={label}>
                  {filteredOptions.map((option) => (
                    <li key={option.id}>
                      <button
                        type="button"
                        onClick={() => choose(option)}
                        aria-pressed={option.id === selectedId}
                        className="flex min-h-12 w-full items-center justify-between rounded-xl px-3 py-3 text-left font-medium text-slate-800 transition hover:bg-teal-50 aria-pressed:bg-teal-50 aria-pressed:text-teal-900"
                      >
                        {option.name}
                        {option.id === selectedId ? (
                          <span aria-hidden="true" className="text-teal-700">
                            ✓
                          </span>
                        ) : null}
                      </button>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="px-3 py-10 text-center text-sm text-slate-500">{emptyMessage}</p>
              )}
            </div>
          </section>
        </div>
      ) : null}
    </div>
  );
}
