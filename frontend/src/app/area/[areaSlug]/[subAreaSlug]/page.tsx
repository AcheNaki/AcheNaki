import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { LiveLocalityDetail } from "@/components/live/live-locality-detail";
import { getSlugLocalityAnalytics, getSlugLocalityStatus, ServerApiError } from "@/lib/api/server";

export async function generateMetadata({ params }: { params: Promise<{ areaSlug: string; subAreaSlug: string }> }): Promise<Metadata> { const { subAreaSlug } = await params; const name = subAreaSlug.replaceAll("-", " "); return { title: `${name} Electricity & Gas Status | Ache Naki?`, description: `Crowdsourced, community-reported electricity and household gas estimates for ${name}, Dhaka.` }; }
export default async function LocalityPage({ params }: { params: Promise<{ areaSlug: string; subAreaSlug: string }> }) {
  const { areaSlug, subAreaSlug } = await params;
  let statusResult;

  try {
    statusResult = await getSlugLocalityStatus(areaSlug, subAreaSlug);
  } catch (error) {
    if (error instanceof ServerApiError && error.status === 404) notFound();

    return <UnavailableLocalityPage areaSlug={areaSlug} />;
  }

  const analyticsResult = await getSlugLocalityAnalytics(areaSlug, subAreaSlug).catch(() => null);
  return <main className="report-backdrop min-h-[calc(100dvh-4rem)] px-4 py-8 sm:px-6 sm:py-12"><div className="mx-auto max-w-6xl"><Link href={`/area/${areaSlug}`} className="text-sm font-bold text-teal-800">← {statusResult.sub_area.area.name}</Link><p className="mt-5 text-sm font-bold uppercase tracking-[0.16em] text-teal-700">Locality detail</p><h1 className="mt-1 text-4xl font-black tracking-tight text-slate-950">{statusResult.sub_area.name}</h1><p className="mt-2 text-slate-600">{statusResult.sub_area.area.name}, Dhaka · Community observations, not official utility status.</p><LiveLocalityDetail areaSlug={areaSlug} subAreaSlug={subAreaSlug} initialStatus={statusResult} initialAnalytics={analyticsResult} /><div className="mt-6"><Link href="/report" className="inline-flex min-h-12 items-center rounded-xl bg-slate-950 px-5 font-bold text-white hover:bg-teal-800">Report current status</Link></div><section className="mt-8 rounded-3xl border border-slate-200 bg-white p-5"><h2 className="text-xl font-black text-slate-950">How this estimate works</h2><p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Ache Naki? combines recent independent community observations. Consistent and recent reports can raise an evidence level; conflicting reports remain mixed. Unknown time is never shown as available or normal. Confidence is not an official utility-provider probability.</p></section></div></main>;
}

function UnavailableLocalityPage({ areaSlug }: { areaSlug: string }) {
  return <main className="report-backdrop min-h-[calc(100dvh-4rem)] px-4 py-8 sm:px-6 sm:py-12"><div className="mx-auto max-w-xl rounded-3xl border border-amber-200 bg-amber-50 p-6 text-amber-950" role="alert"><h1 className="text-2xl font-black">Locality information is temporarily unavailable</h1><p className="mt-2 text-sm leading-6">The live-status service could not be reached. Please try again shortly.</p><Link href={`/area/${areaSlug}`} className="mt-5 inline-flex min-h-11 items-center rounded-xl bg-white px-4 text-sm font-bold text-teal-800">Back to the area</Link></div></main>;
}
