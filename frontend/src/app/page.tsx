import Link from "next/link";
import { AreaBrowser } from "@/components/dashboard/area-browser";
import { DashboardExperience } from "@/components/dashboard/dashboard-experience";

export default function Home() {
  return (
    <main className="report-backdrop min-h-[calc(100dvh-4rem)] px-4 py-8 sm:px-6 sm:py-12">
      <div className="mx-auto max-w-6xl">
        <section aria-labelledby="page-title" className="max-w-3xl py-4">
          <p className="text-sm font-bold uppercase tracking-[0.2em] text-teal-700">
            Dhaka · Community powered
          </p>

          <h1
            id="page-title"
            className="mt-3 text-4xl font-black tracking-tight text-slate-950 sm:text-6xl"
          >
            Ache Naki? <span aria-hidden="true">⚡🔥</span>
          </h1>

          <p className="mt-4 max-w-2xl text-lg leading-8 text-slate-600">
            কারেন্ট কই? ⚡ গ্যাস কই? 🔥 নাকি দুজনেই ছুটিতে?
            <br />
            এলাকার লাইভ কাহিনি - কে আছে, কে নাই!
          </p>

          <div className="mt-7 flex flex-wrap gap-3">
            <a
              href="#my-area"
              className="inline-flex min-h-13 items-center rounded-2xl bg-slate-950 px-6 font-bold text-white shadow-lg shadow-slate-950/15 hover:bg-teal-800"
            >
              Check My Area
            </a>

            <Link
              href="/report"
              className="inline-flex min-h-13 items-center rounded-2xl border border-slate-300 bg-white px-6 font-bold text-slate-800 hover:border-teal-500"
            >
              Report Now
            </Link>
          </div>

          <p className="mt-5 text-sm text-slate-500">
            Community observations, not official utility-provider truth.
          </p>
        </section>

        <DashboardExperience />

        <section className="mt-10">
          <AreaBrowser />
        </section>

        <section className="mt-10 rounded-[2rem] border border-teal-100 bg-teal-50 p-5 sm:p-7">
          <p className="text-sm font-bold uppercase tracking-[0.16em] text-teal-800">
            How confidence works
          </p>

          <h2 className="mt-1 text-2xl font-black tracking-tight text-slate-950">
            How does Ache Naki? know?
          </h2>

          <p className="mt-3 max-w-3xl text-sm leading-6 text-slate-700">
            Ache Naki? combines recent independent community observations. More
            recent and consistent reports increase the evidence level.
            Conflicting reports reduce it. No recent reports means we show “Not
            enough data.” Confidence is not an official utility-provider
            probability.
          </p>
        </section>
      </div>
    </main>
  );
}
