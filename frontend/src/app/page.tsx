import Link from "next/link";
import { AreaBrowser } from "@/components/dashboard/area-browser";
import { DashboardExperience } from "@/components/dashboard/dashboard-experience";
import { DhakaRightNowCard } from "@/components/live/dhaka-right-now-card";

export default function Home() {
  return (
    <main className="report-backdrop min-h-[calc(100dvh-4rem)] px-4 py-8 sm:px-6 sm:py-12">
      <div className="mx-auto max-w-6xl">
        {/* The live card sits beside the hero on wide screens and stacks under it below `lg`. */}
        <div className="grid items-start gap-8 py-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
          <section aria-labelledby="page-title" className="max-w-3xl">
            {/* Bangla conjuncts read as broken at the eyebrow's English tracking, so the
                Bangla half opts out of the letter-spacing while staying on the same line. */}
            <p className="text-sm font-bold uppercase tracking-[0.2em] text-teal-700">
              DHAKA ·{" "}
              <span className="font-semibold normal-case tracking-normal">কে আছে, কে নাই!</span>
            </p>

            <h1
              id="page-title"
              className="mt-3 text-4xl font-black tracking-tight text-slate-950 sm:text-6xl"
            >
              Ache Naki? <span aria-hidden="true">⚡🔥</span>
            </h1>

            <p className="mt-4 max-w-2xl text-lg leading-8 text-slate-600">
              কারেন্ট কই? ⚡ গ্যাস কই? 🔥 নাকি দুজনেই ছুটিতে?
            </p>

            <div className="mt-7 flex flex-wrap gap-3">
              <a
                href="#my-area"
                className="inline-flex min-h-13 items-center rounded-2xl bg-slate-950 px-6 font-bold text-white shadow-lg shadow-slate-950/15 hover:bg-teal-800"
              >
                Check My Area 👀
              </a>

              <Link
                href="/report"
                className="inline-flex min-h-13 items-center rounded-2xl border border-slate-300 bg-white px-6 font-bold text-slate-800 hover:border-teal-500"
              >
                Report Now
              </Link>
            </div>
          </section>

          <DhakaRightNowCard />
        </div>

        <DashboardExperience />

        <section className="mt-10">
          <AreaBrowser />
        </section>

        <section className="mt-10 rounded-[2rem] border border-teal-100 bg-teal-50 p-5 sm:p-7">
          <p className="text-sm font-bold uppercase tracking-[0.16em] text-teal-800">
            HOW CONFIDENCE WORKS
          </p>

          <h2 className="mt-1 text-2xl font-black tracking-tight text-slate-950">
            Ache Naki? এত কিছু জানে কীভাবে? 👀
          </h2>

          <div className="mt-3 max-w-3xl space-y-3 text-sm leading-6 text-slate-700">
            <p>এলাকার মানুষ যত বেশি recent আর একই ধরনের report দেয়, আমাদের confidence তত বাড়ে।</p>
            {/* One source line: the emoji must stay inline with the sentence it closes. */}
            <p>Report মিল না হলে confidence কমে যায় - কারণ ঢাকার কারেন্ট-গ্যাসও মাঝে মাঝে relationship status-এর মতো complicated. 😅</p>
            <p>Recent report না থাকলে আমরা সোজাসুজি বলি: “Not enough data.”</p>
            <p>আর হ্যাঁ - এটা community-powered signal, কোনো official utility-provider confirmation না।</p>
          </div>
        </section>
      </div>
    </main>
  );
}
