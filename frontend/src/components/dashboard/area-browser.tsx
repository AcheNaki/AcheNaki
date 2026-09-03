"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { searchLocations } from "@/lib/api/client";
import type { LocationSearchResult } from "@/lib/api/types";
import { useAreas } from "@/hooks/use-location-data";

const SEARCH_DEBOUNCE_MS = 250;

export function AreaBrowser() {
  const { data: areas, loading, error, retry } = useAreas();
  const [query, setQuery] = useState("");
  const [results, setResults] = useState<LocationSearchResult[] | null>(null);
  const [searchError, setSearchError] = useState(false);
  const majorAreaMatches = useMemo(() => areas.filter((area) => area.name.toLowerCase().includes(query.trim().toLowerCase())).slice(0, 10), [areas, query]);

  useEffect(() => {
    if (query.trim().length < 2) {
      // Resetting a remote-search result when the input is no longer searchable is hydration-safe.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setResults(null);
      setSearchError(false);
      return;
    }
    const controller = new AbortController();
    // Debounced so a typed word issues one search instead of one request per keystroke.
    const timer = setTimeout(() => {
      searchLocations(query.trim(), controller.signal)
        .then((data) => { setResults(data); setSearchError(false); })
        .catch((reason: unknown) => {
          // A superseded keystroke aborts the previous request; that is not a search failure.
          if (reason instanceof Error && reason.name === "AbortError") return;
          setSearchError(true);
        });
    }, SEARCH_DEBOUNCE_MS);
    return () => { clearTimeout(timer); controller.abort(); };
  }, [query]);

  const display = results ?? majorAreaMatches.map((area) => ({ kind: "AREA" as const, name: area.name, area: { name: area.name, slug: area.slug }, sub_area_slug: null }));
  return <section className="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="area-browser-heading"><p className="text-sm font-bold uppercase tracking-[0.16em] text-teal-700">ঘুরে দেখি ঢাকা 👀</p><h2 id="area-browser-heading" className="mt-1 text-2xl font-black tracking-tight text-slate-950">কোন এলাকার কী অবস্থা?</h2><p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600">এলাকার নাম লিখুন, বাকিটা Ache Naki? দেখে নেবে</p>{error ? <div role="alert" className="mt-4 flex items-center justify-between gap-3 rounded-2xl bg-rose-50 p-4 text-sm text-rose-900">Couldn&apos;t load areas.<button type="button" onClick={retry} className="font-bold underline">Try again</button></div> : <><label htmlFor="area-search" className="sr-only">Search Dhaka major areas and localities</label><input id="area-search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search Mirpur, Dhanmondi, Pallabi..." className="mt-5 min-h-13 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-slate-950 placeholder:text-slate-400 focus:border-teal-600 focus:outline-none" /><p className="sr-only" role="status">{searchError ? "Location search failed." : `${display.length} ${display.length === 1 ? "location" : "locations"} listed.`}</p>{loading ||(query.trim().length >= 2 && results === null && !searchError) ? <div className="mt-4 h-24 animate-pulse rounded-2xl bg-slate-100" /> : searchError ? <p role="alert" className="mt-4 text-sm text-rose-800">Couldn&apos;t search localities. Try again.</p> : <ul className="mt-3 grid gap-2 sm:grid-cols-2">{display.map((item) => <li key={`${item.kind}-${item.area.slug}-${item.sub_area_slug ?? ""}`}><Link href={item.sub_area_slug ? `/area/${item.area.slug}/${item.sub_area_slug}` : `/area/${item.area.slug}`} className="flex min-h-12 items-center justify-between rounded-xl px-4 font-bold text-slate-800 transition hover:bg-teal-50 hover:text-teal-900"><span>{item.name}{item.kind === "SUB_AREA" ? <span className="ml-2 text-xs font-medium text-slate-500">{item.area.name}</span> : null}</span><span aria-hidden="true">→</span></Link></li>)}</ul>}</>}</section>;
}
