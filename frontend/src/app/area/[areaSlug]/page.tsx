import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { StatusCard } from "@/components/status/status-card";
import { getServerAreaLiveStatuses, ServerApiError } from "@/lib/api/server";

export async function generateMetadata({ params }: { params: Promise<{ areaSlug: string }> }): Promise<Metadata> { const { areaSlug } = await params; return { title: `${areaSlug.replaceAll("-", " ")} electricity & gas | Ache Naki?`, description: "Community-reported electricity and household gas status for a Dhaka major area." }; }
export default async function AreaPage({ params }: { params: Promise<{ areaSlug: string }> }) {
  const { areaSlug } = await params;
  let data;

  try {
    data = await getServerAreaLiveStatuses(areaSlug);
  } catch (error) {
    if (error instanceof ServerApiError && error.status === 404) notFound();

    return <UnavailableAreaPage />;
  }

  return <main className="report-backdrop min-h-[calc(100dvh-4rem)] px-4 py-8 sm:px-6 sm:py-12"><div className="mx-auto max-w-6xl"><Link href="/areas" className="text-sm font-bold text-teal-800">← All areas</Link><p className="mt-5 text-sm font-bold uppercase tracking-[0.16em] text-teal-700">Dhaka locality browsing</p><h1 className="mt-1 text-4xl font-black tracking-tight text-slate-950">{data.area.name}</h1><p className="mt-2 text-slate-600">Current community-derived estimates for predefined localities.</p><div className="mt-7 grid gap-4 md:grid-cols-2">{data.localities.map((locality) => <article key={locality.sub_area.id} className="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex items-start justify-between gap-3"><h2 className="text-xl font-black text-slate-950">{locality.sub_area.name}</h2><Link href={`/area/${data.area.slug}/${locality.sub_area.slug}`} className="shrink-0 text-sm font-bold text-teal-800">Details →</Link></div><div className="mt-4 grid gap-3 sm:grid-cols-2"><StatusCard utility="ELECTRICITY" status={locality.electricity} compact /><StatusCard utility="GAS" status={locality.gas} compact /></div></article>)}</div></div></main>;
}

function UnavailableAreaPage() {
  return <main className="report-backdrop min-h-[calc(100dvh-4rem)] px-4 py-8 sm:px-6 sm:py-12"><div className="mx-auto max-w-xl rounded-3xl border border-amber-200 bg-amber-50 p-6 text-amber-950" role="alert"><h1 className="text-2xl font-black">Area information is temporarily unavailable</h1><p className="mt-2 text-sm leading-6">The live-status service could not be reached. Please try again shortly.</p><Link href="/areas" className="mt-5 inline-flex min-h-11 items-center rounded-xl bg-white px-4 text-sm font-bold text-teal-800">Back to areas</Link></div></main>;
}
