"use client";

interface SubmissionFeedbackProps {
  duplicate: boolean;
  locality: string;
  status: string;
  onAgain: () => void;
}

export function SubmissionFeedback({ duplicate, locality, status, onAgain }: SubmissionFeedbackProps) {
  return (
    <section aria-labelledby="success-heading" role="status" className="rounded-[1.75rem] border border-emerald-200 bg-white p-6 text-center shadow-xl shadow-emerald-950/5 sm:p-9">
      <div aria-hidden="true" className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-3xl text-emerald-700">
        ✓
      </div>
      <p className="mt-5 text-sm font-bold uppercase tracking-[0.18em] text-emerald-700">Observation received</p>
      <h1 id="success-heading" className="mt-2 text-3xl font-bold tracking-tight text-slate-950">
        {duplicate ? "Already received" : "Report submitted"} <span aria-hidden="true">✓</span>
      </h1>
      <p className="mx-auto mt-3 max-w-md leading-7 text-slate-600">
        {duplicate
          ? "You’ve recently submitted the same update, so we didn’t create a duplicate."
          : "Thanks — your observation helps Ache Naki? understand what’s happening in your area."}
      </p>
      <div className="mx-auto mt-6 max-w-sm rounded-2xl bg-slate-50 p-4 text-left">
        <p className="text-sm font-semibold text-slate-500">Reporting for</p>
        <p className="mt-1 font-bold text-slate-950">{locality}</p>
        <p className="mt-3 text-sm text-slate-700">{status} · Reported just now</p>
      </div>
      <button type="button" onClick={onAgain} className="mt-7 min-h-12 w-full rounded-xl bg-slate-950 px-6 font-bold text-white transition hover:bg-teal-800 sm:w-auto">
        Report Another Update
      </button>
    </section>
  );
}
