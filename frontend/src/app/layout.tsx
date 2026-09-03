import type { Metadata } from "next";
import type { ReactNode } from "react";
import { SiteHeader } from "@/components/site-header";
import "./globals.css";

export const metadata: Metadata = {
  title: "Ache Naki? — Dhaka Electricity & Gas Status",
  description: "Crowdsourced, community-reported electricity and household gas estimates for Dhaka.",
};

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="en">
      <body>
        <SiteHeader />
        {children}
        <footer className="border-t border-slate-200 bg-white px-4 py-8 sm:px-6">
          <div className="mx-auto flex max-w-6xl flex-col justify-between gap-3 text-sm text-slate-600 sm:flex-row sm:items-center">
            <p>
              <span className="font-black text-slate-950">Ache Naki? <span aria-hidden="true">⚡🔥</span></span>
              <span className="mt-1 block">ঢাকার কারেন্ট-গ্যাসের খবর - মানুষ জানায়, সবাই জানে।</span>
            </p>
            <p>পাড়ার খবর, অফিসিয়াল ঘোষণা না। 👀</p>
          </div>
        </footer>
      </body>
    </html>
  );
}
