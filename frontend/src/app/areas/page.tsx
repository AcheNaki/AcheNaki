import type { Metadata } from "next";
import { AreaBrowser } from "@/components/dashboard/area-browser";

export const metadata: Metadata = { title: "Browse Dhaka areas | Ache Naki?", description: "Browse community-reported electricity and gas status by predefined Dhaka locality." };
export default function AreasPage() {
  return (
    <main className="report-backdrop min-h-[calc(100dvh-4rem)] px-4 py-8 sm:px-6 sm:py-12">
      <div className="mx-auto max-w-4xl">
        {/* AreaBrowser opens at h2 because the dashboard nests it under the page h1, so this
            route needs its own top-level heading to keep the hierarchy from starting at h2. */}
        <h1 className="text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Browse Dhaka areas</h1>
        <p className="mt-2 max-w-2xl text-slate-600">
          Community-reported electricity and household gas estimates for predefined Dhaka localities.
        </p>
        <div className="mt-7">
          <AreaBrowser />
        </div>
      </div>
    </main>
  );
}
