import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import { fileURLToPath } from "node:url";

// The Bangla/English homepage copy is finalised product copy with no shared module to import,
// so this reads the sources directly: any reword, retranslation, lost emoji or reverted legacy
// string must fail here. Whitespace is normalised because JSX wraps long lines.
function source(relativePath: string): string {
  return readFileSync(fileURLToPath(new URL(relativePath, import.meta.url)), "utf8").replace(/\s+/g, " ");
}

const homepage = source("./page.tsx");
const layout = source("./layout.tsx");
const dashboard = source("../components/dashboard/dashboard-experience.tsx");
const areaBrowser = source("../components/dashboard/area-browser.tsx");
const snapshot = source("../components/analytics/daily-snapshot.tsx");
const liveCard = source("../components/live/dhaka-right-now-card.tsx");
const liveIndicator = source("../components/live/live-indicator.tsx");

function assertContains(haystack: string, phrases: string[], where: string) {
  for (const phrase of phrases) {
    assert.ok(haystack.includes(phrase), `${where} is missing finalised copy: ${phrase}`);
  }
}

test("the hero keeps its finalised copy, emoji and calls to action", () => {
  assertContains(homepage, [
    "DHAKA · কে আছে, কে নাই!",
    "Ache Naki? <span aria-hidden=\"true\">⚡🔥</span>",
    "কারেন্ট কই? ⚡ গ্যাস কই? 🔥 নাকি দুজনেই ছুটিতে?",
    "Check My Area 👀",
    "Report Now",
    "পাড়ার খবর, অফিসিয়াল ঘোষণা না",
  ], "The hero");
});

test("replaced hero and disclaimer copy is not reintroduced", () => {
  for (const legacy of [
    "Dhaka · Community powered",
    "এলাকার লাইভ কাহিনি",
    "Community observations, not official utility-provider truth.",
  ]) {
    assert.ok(!homepage.includes(legacy), `Legacy homepage copy came back: ${legacy}`);
  }
});

test("the confidence section keeps its four paragraphs and cautious closing claim", () => {
  assertContains(homepage, [
    "HOW CONFIDENCE WORKS",
    "Ache Naki? এত কিছু জানে কীভাবে? 👀",
    "এলাকার মানুষ যত বেশি recent আর একই ধরনের report দেয়, আমাদের confidence তত বাড়ে।",
    "Report মিল না হলে confidence কমে যায় — কারণ ঢাকার কারেন্ট-গ্যাসও মাঝে মাঝে relationship status-এর মতো complicated. 😅",
    "Recent report না থাকলে আমরা সোজাসুজি বলি: “Not enough data.”",
    "আর হ্যাঁ — এটা community-powered signal, কোনো official utility-provider confirmation না।",
  ], "The confidence section");
});

test("the live Dhaka card keeps its heading and never hard-codes a count", () => {
  assert.ok(liveCard.includes("DHAKA RIGHT NOW 👀"), "The live card heading changed.");
  assert.ok(liveCard.includes("liveSummaryMetrics"), "The live card must render backend-derived metrics.");
});

test("the your-area section keeps its heading, dynamic locality and report calls to action", () => {
  assertContains(dashboard, [
    "YOUR AREA",
    "আপনার এলাকার হালচাল 👀",
    "এলাকার মানুষের latest report — অফিসিয়াল ঘোষণা না।",
    "এলাকার খবর দিন",
    "পুরো কাহিনি দেখুন →",
  ], "The your-area section");
  // The locality under the heading stays interpolated from the API, falling back to the saved
  // locality — no locality name may ever be baked into the markup.
  assert.match(
    dashboard,
    /id="my-area-locality"[^>]*>\{localityMatchesSaved \? `\$\{locality\.sub_area\.name\}, \$\{locality\.sub_area\.area\.name\}` : `\$\{saved\.subAreaName\}, \$\{saved\.areaName\}`\}/,
    "The displayed locality must stay dynamic.",
  );
  // The link renders its own arrow, so nothing else may add a second one.
  assert.equal(dashboard.split("→").length - 1, 2, "Expected exactly the two intentional arrows.");
});

test("live-update controls and locality signal copy stay intact", () => {
  assertContains(liveIndicator, ["Refreshing…", "Live updates on", "Refresh"], "The live indicator");
  assertContains(dashboard, [
    "LIVE LOCALITY SIGNALS",
    "কোথায় ঝামেলা চলছে? 👀",
    "Browse areas →",
    "Recent Changes",
    "কে ফিরলো? কে আবার উধাও?",
  ], "The locality signal sections");
});

test("the locality search and snapshot headings keep their finalised copy", () => {
  assertContains(areaBrowser, [
    "ঘুরে দেখি ঢাকা 👀",
    "কোন এলাকার কী অবস্থা?",
    "এলাকার নাম লিখুন, বাকিটা Ache Naki? দেখে নেবে",
    "Search Mirpur, Dhanmondi, Pallabi...",
  ], "The locality search");
  assert.ok(snapshot.includes("আজকের হিসাব-নিকাশ 📊"), "The snapshot heading changed.");
});

test("the footer keeps the brand lockup and the community-signal disclaimer", () => {
  assertContains(layout, [
    "Ache Naki? <span aria-hidden=\"true\">⚡🔥</span>",
    "ঢাকার কারেন্ট-গ্যাসের খবর - মানুষ জানায়, সবাই জানে।",
    "পাড়ার খবর, অফিসিয়াল ঘোষণা না। 👀",
  ], "The footer");
});
