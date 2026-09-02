import type { Metadata } from "next";
import { ReportExperience } from "@/components/report/report-experience";

export const metadata: Metadata = {
  title: "Report a utility update | Ache Naki?",
  description: "Share a structured electricity or household gas observation for your Dhaka locality.",
};

export default function ReportPage() {
  return (
    <main className="report-backdrop min-h-[calc(100dvh-4rem)] px-4 py-6 sm:px-6 sm:py-10">
      <div className="mx-auto max-w-2xl">
        <h1 className="sr-only">Report a utility update</h1>
        <ReportExperience />
        <p className="mx-auto mt-6 max-w-lg text-center text-xs leading-5 text-slate-500">
          Reports are community observations, not utility-provider confirmation. No account or exact address is required.
        </p>
      </div>
    </main>
  );
}
