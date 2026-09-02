import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import { fileURLToPath } from "node:url";

// The Bangla hero is finalised copy. It has no shared module to import, so this reads the page
// source directly: any reword, retranslation or lost line break must fail here.
const homepageSource = readFileSync(fileURLToPath(new URL("./page.tsx", import.meta.url)), "utf8");

const heroLines = [
  "কারেন্ট কই? ⚡ গ্যাস কই? 🔥 নাকি দুজনেই ছুটিতে?",
  "এলাকার লাইভ কাহিনি - কে আছে, কে নাই!",
];

test("the homepage hero keeps its finalised Bangla copy", () => {
  for (const line of heroLines) {
    assert.ok(homepageSource.includes(line), `Hero line is missing: ${line}`);
  }
});

test("the two hero sentences stay on separate lines", () => {
  const firstLineEnd = homepageSource.indexOf(heroLines[0]) + heroLines[0].length;
  const between = homepageSource.slice(firstLineEnd, homepageSource.indexOf(heroLines[1]));

  assert.ok(/<br\s*\/?>/.test(between), "The hero line break between the two sentences was removed.");
});
