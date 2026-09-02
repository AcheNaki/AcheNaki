import assert from "node:assert/strict";
import test from "node:test";
import {
  buildReportPayload,
  electricityStatusOptions,
  gasStatusOptions,
  statusOptionsFor,
  timeBucketOptions,
} from "./domain.ts";
import { electricityStatusCopy, gasStatusCopy } from "../dashboard/domain.ts";
import { parseSavedLocality } from "./locality-storage.ts";

test("electricity and gas expose only their compatible statuses", () => {
  assert.deepEqual(statusOptionsFor("ELECTRICITY"), electricityStatusOptions);
  assert.deepEqual(statusOptionsFor("GAS"), gasStatusOptions);
  assert.deepEqual(statusOptionsFor(null), []);
  assert.equal(electricityStatusOptions.some((option) => String(option.value) === "VERY_LOW"), false);
  assert.equal(gasStatusOptions.some((option) => String(option.value) === "UNSTABLE"), false);
});

test("all canonical time buckets are presented", () => {
  assert.deepEqual(
    timeBucketOptions.map((option) => option.value),
    ["NOW", "MIN_5", "MIN_15", "MIN_30", "HOUR_1", "HOUR_2", "OVER_2_HOURS", "UNKNOWN"],
  );
});

// These assertions target the copy the UI actually renders (lib/dashboard/domain), not a
// parallel label map: a second copy of the same strings can drift without anything failing.
test("live projection states have uncertainty-preserving public labels", () => {
  assert.equal(electricityStatusCopy.UNAVAILABLE.label, "Likely loadshedding");
  assert.equal(electricityStatusCopy.MIXED.label, "Mixed electricity reports");
  assert.equal(electricityStatusCopy.INSUFFICIENT_DATA.label, "Not enough recent reports");
  assert.equal(gasStatusCopy.VERY_LOW.label, "Very low gas pressure reported");
  assert.equal(gasStatusCopy.MIXED.label, "Mixed gas reports");
  assert.equal(gasStatusCopy.INSUFFICIENT_DATA.label, "Not enough recent reports");
});

test("no projection state renders as certainty or as an unlabelled fallback", () => {
  for (const copy of [...Object.values(electricityStatusCopy), ...Object.values(gasStatusCopy)]) {
    assert.equal(typeof copy.label, "string");
    assert.ok(copy.label.length > 0);
    assert.ok(copy.tone.length > 0);
  }
  assert.equal(electricityStatusCopy.INSUFFICIENT_DATA.tone, "muted");
  assert.equal(gasStatusCopy.INSUFFICIENT_DATA.tone, "muted");
  assert.equal(electricityStatusCopy.MIXED.tone, "mixed");
  assert.equal(gasStatusCopy.MIXED.tone, "mixed");
});

test("electricity payload contains canonical IDs and enum values only", () => {
  assert.deepEqual(
    buildReportPayload({
      areaId: 10,
      subAreaId: 103,
      utility: "ELECTRICITY",
      status: "UNAVAILABLE",
      timeBucket: "MIN_15",
      canCook: false,
    }),
    {
      area_id: 10,
      sub_area_id: 103,
      utility_type: "ELECTRICITY",
      status: "UNAVAILABLE",
      time_bucket: "MIN_15",
    },
  );
});

test("gas payload maps each structured cookability state", () => {
  const base = {
    areaId: 1,
    subAreaId: 2,
    utility: "GAS" as const,
    status: "VERY_LOW" as const,
    timeBucket: "MIN_30" as const,
  };

  const usable = buildReportPayload({ ...base, canCook: true });
  const unusable = buildReportPayload({ ...base, canCook: false });
  const untried = buildReportPayload({ ...base, canCook: null });
  assert.equal(usable.utility_type === "GAS" ? usable.can_cook : undefined, true);
  assert.equal(unusable.utility_type === "GAS" ? unusable.can_cook : undefined, false);
  assert.equal(untried.utility_type === "GAS" ? untried.can_cook : undefined, null);
  assert.equal("can_cook" in buildReportPayload(base), false);
});

test("saved locality parsing accepts minimal canonical display data", () => {
  assert.deepEqual(
    parseSavedLocality(JSON.stringify({ areaId: 10, areaName: "Pallabi", subAreaId: 103, subAreaName: "Palash Nagar" })),
    { areaId: 10, areaName: "Pallabi", subAreaId: 103, subAreaName: "Palash Nagar" },
  );
});

test("saved locality parsing rejects corrupted or incomplete values", () => {
  assert.equal(parseSavedLocality("not-json"), null);
  assert.equal(parseSavedLocality(JSON.stringify({ areaId: 10, subAreaId: 103 })), null);
  assert.equal(parseSavedLocality(JSON.stringify({ areaId: "10", areaName: "Pallabi", subAreaId: 103, subAreaName: "Palash Nagar" })), null);
  assert.equal(parseSavedLocality(JSON.stringify({ areaId: 0, areaName: "Pallabi", subAreaId: 103, subAreaName: "Palash Nagar" })), null);
  assert.equal(parseSavedLocality(JSON.stringify({ areaId: 10, areaName: " ", subAreaId: 103, subAreaName: "Palash Nagar" })), null);
});
