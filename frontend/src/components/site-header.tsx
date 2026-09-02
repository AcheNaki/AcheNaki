import Link from "next/link";

export function SiteHeader() {
  return (
    <header className="border-b border-slate-200/80 bg-white/90 backdrop-blur">
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
        <Link href="/" className="rounded-lg text-lg font-black tracking-tight text-slate-950 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-teal-500/20">
          Ache Naki? <span aria-hidden="true" className="text-amber-500">⚡🔥</span>
        </Link>
        <nav aria-label="Primary navigation" className="flex items-center gap-1 sm:gap-2">
          <Link href="/" className="hidden min-h-11 items-center rounded-xl px-3 text-sm font-bold text-slate-700 hover:bg-slate-100 sm:inline-flex">Home</Link>
          <Link href="/areas" className="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-bold text-slate-700 hover:bg-slate-100">Areas</Link>
          <Link href="/report" className="inline-flex min-h-11 items-center rounded-xl bg-teal-700 px-4 text-sm font-bold text-white transition hover:bg-teal-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-teal-500/25">
            Report
          </Link>
        </nav>
      </div>
    </header>
  );
}
