import assert from "node:assert/strict";
import test, { afterEach, beforeEach } from "node:test";
import {
  ApiError,
  getDailyUtilityAnalytics,
  getDashboard,
  getAreaLiveStatuses,
  getRecentlyResolvedElectricityEvents,
  getAreas,
  getLiveStatuses,
  getLiveSummary,
  getLocalityLiveStatus,
  getSubAreas,
  submitUtilityReport,
} from "./client.ts";

const validToken = `ar1_${"a".repeat(43)}`;
const replacementToken = `ar1_${"b".repeat(43)}`;
const storage = new Map<string, string>();
const originalFetch = globalThis.fetch;

beforeEach(() => {
  process.env.NEXT_PUBLIC_API_BASE_URL = "http://api.test/api/v1";
  storage.clear();
  Object.defineProperty(globalThis, "window", {
    configurable: true,
    value: {
      localStorage: {
        getItem: (key: string) => storage.get(key) ?? null,
        setItem: (key: string, value: string) => storage.set(key, value),
        removeItem: (key: string) => storage.delete(key),
      },
    },
  });
});

afterEach(() => {
  globalThis.fetch = originalFetch;
  Reflect.deleteProperty(globalThis, "window");
});

test("areas load through the configured centralized API base URL", async () => {
  globalThis.fetch = async (input) => {
    assert.equal(input, "http://api.test/api/v1/areas");
    return Response.json({ data: [{ id: 1, name: "Pallabi", slug: "pallabi", city_corporation: "DNCC" }] });
  };

  const areas = await getAreas();
  assert.equal(areas[0]?.name, "Pallabi");
});

test("sub-areas are loaded only for the selected canonical area ID", async () => {
  globalThis.fetch = async (input) => {
    assert.equal(input, "http://api.test/api/v1/areas/42/sub-areas");
    return Response.json({ data: [{ id: 103, name: "Palash Nagar", slug: "palash-nagar" }] });
  };

  const subAreas = await getSubAreas(42);
  assert.equal(subAreas[0]?.id, 103);
});

test("locality live status loads through the typed read API", async () => {
  globalThis.fetch = async (input) => {
    assert.equal(input, "http://api.test/api/v1/sub-areas/103/status");
    return Response.json({
      data: {
        sub_area: { id: 103, name: "Palash Nagar", area: { id: 10, name: "Pallabi" } },
        electricity: {
          status: "INSUFFICIENT_DATA",
          confidence: null,
          status_since: null,
          recent_reports: 0,
          supporting_reports: 0,
          contradicting_reports: 0,
          last_report_at: null,
        },
        gas: {
          status: "LOW",
          confidence: "MEDIUM",
          status_since: null,
          recent_reports: 3,
          supporting_reports: 3,
          contradicting_reports: 0,
          last_report_at: "2026-09-01T06:00:00.000000Z",
        },
      },
    });
  };

  const status = await getLocalityLiveStatus(103);
  assert.equal(status.electricity.status, "INSUFFICIENT_DATA");
  assert.equal(status.gas.confidence, "MEDIUM");
});

test("live status listing sends only bounded typed filters", async () => {
  globalThis.fetch = async (input) => {
    assert.equal(
      input,
      "http://api.test/api/v1/live-statuses?utility=ELECTRICITY&status=UNAVAILABLE&limit=10",
    );
    return Response.json({ data: [] });
  };

  assert.deepEqual(
    await getLiveStatuses({ utility: "ELECTRICITY", status: "UNAVAILABLE", limit: 10 }),
    [],
  );
});

test("daily analytics uses the canonical locality and optional Dhaka date", async () => {
  globalThis.fetch = async (input) => {
    assert.equal(input, "http://api.test/api/v1/sub-areas/103/analytics?date=2026-09-01");
    return Response.json({
      data: {
        sub_area: { id: 103, name: "Palash Nagar", area: { id: 10, name: "Pallabi" } },
        date: "2026-09-01",
        timezone: "Asia/Dhaka",
        window: {
          started_at: "2026-08-31T18:00:00.000000Z",
          ended_at: "2026-09-01T18:00:00.000000Z",
          duration_seconds: 86400,
          partial: false,
        },
        electricity: {
          outage_count: 0,
          total_outage_seconds: 0,
          longest_outage_seconds: 0,
          ongoing_outage_seconds: 0,
          state_seconds: { available: 0, unavailable: 0, unstable: 0 },
          coverage: { observed_seconds: 0, unknown_seconds: 86400 },
          events: [],
        },
        gas: {
          state_seconds: { normal: 0, low: 0, very_low: 0, unavailable: 0 },
          coverage: { observed_seconds: 0, unknown_seconds: 86400 },
          intervals: [],
        },
      },
    });
  };

  const analytics = await getDailyUtilityAnalytics(103, "2026-09-01");
  assert.equal(analytics.timezone, "Asia/Dhaka");
  assert.equal(analytics.electricity.coverage.unknown_seconds, 86400);
});

test("dashboard reads use bounded cohesive public endpoints", async () => {
  globalThis.fetch = async (input) => {
    assert.equal(input, "http://api.test/api/v1/dashboard");
    return Response.json({ data: { calculated_at: "2026-09-01T12:00:00Z", fresh_projection_counts: { electricity: 1, gas: 1 }, struggling: [], recent_changes: [] } });
  };
  assert.equal((await getDashboard()).fresh_projection_counts.electricity, 1);
});

test("the live city summary is read from the Laravel API, never derived in the browser", async () => {
  globalThis.fetch = async (input) => {
    assert.equal(input, "http://api.test/api/v1/live-summary");
    return Response.json({ data: { window_minutes: 30, reports: 12, localities_updated: 5, electricity_issue_localities: 3, gas_issue_localities: 2, currently_struggling_localities: 4, calculated_at: "2026-09-01T12:00:00.000000Z" } });
  };
  const summary = await getLiveSummary();
  assert.equal(summary.reports, 12);
  assert.equal(summary.currently_struggling_localities, 4);
});

test("a failing live summary surfaces a typed error instead of fabricated counts", async () => {
  globalThis.fetch = async () => new Response(null, { status: 500 });
  await assert.rejects(getLiveSummary(), (error: unknown) => error instanceof ApiError && error.kind === "server");
});

test("area status and resolved-event reads remain canonical and bounded", async () => {
  let calls = 0;
  globalThis.fetch = async (input) => {
    calls += 1;
    if (calls === 1) {
      assert.equal(input, "http://api.test/api/v1/areas/pallabi/statuses");
      return Response.json({ data: { area: { id: 1, name: "Pallabi", slug: "pallabi", city_corporation: "DNCC" }, localities: [] } });
    }
    assert.equal(input, "http://api.test/api/v1/electricity-events/recently-resolved?limit=6");
    return Response.json({ data: [] });
  };
  assert.equal((await getAreaLiveStatuses("pallabi")).localities.length, 0);
  assert.deepEqual(await getRecentlyResolvedElectricityEvents(), []);
});

test("report submission reuses the stored token and preserves duplicate success", async () => {
  storage.set("achenaki.anonymous-reporter.v1", validToken);
  globalThis.fetch = async (input, init) => {
    assert.equal(input, "http://api.test/api/v1/utility-reports");
    assert.equal(new Headers(init?.headers).get("X-Anonymous-Reporter"), validToken);
    assert.deepEqual(JSON.parse(String(init?.body)), {
      area_id: 1,
      sub_area_id: 2,
      utility_type: "ELECTRICITY",
      status: "AVAILABLE",
      time_bucket: "NOW",
    });
    return Response.json({ data: reportResponse(4), meta: { duplicate: true } });
  };

  const result = await submitUtilityReport({
    area_id: 1,
    sub_area_id: 2,
    utility_type: "ELECTRICITY",
    status: "AVAILABLE",
    time_bucket: "NOW",
  });
  assert.equal(result.meta.duplicate, true);
});

test("invalid anonymous token response causes one safe renewal and retry", async () => {
  storage.set("achenaki.anonymous-reporter.v1", validToken);
  let calls = 0;
  globalThis.fetch = async (input, init) => {
    calls += 1;
    if (calls === 1) {
      return Response.json(
        { error: { code: "validation_failed", message: "Invalid", details: { anonymous_reporter: ["Invalid"] } } },
        { status: 422 },
      );
    }
    if (calls === 2) {
      assert.equal(input, "http://api.test/api/v1/anonymous-session");
      return Response.json({ data: { token: replacementToken } });
    }

    assert.equal(new Headers(init?.headers).get("X-Anonymous-Reporter"), replacementToken);
    return Response.json({ data: reportResponse(9, "GAS", "LOW"), meta: { duplicate: false } }, { status: 201 });
  };

  await submitUtilityReport({
    area_id: 1,
    sub_area_id: 2,
    utility_type: "GAS",
    status: "LOW",
    time_bucket: "MIN_5",
  });
  assert.equal(calls, 3);
});

test("rate limit and network failures become safe typed errors", async () => {
  storage.set("achenaki.anonymous-reporter.v1", validToken);
  globalThis.fetch = async () => new Response(null, { status: 429 });

  await assert.rejects(
    submitUtilityReport({
      area_id: 1,
      sub_area_id: 2,
      utility_type: "GAS",
      status: "NORMAL",
      time_bucket: "UNKNOWN",
    }),
    (error: unknown) => error instanceof ApiError && error.kind === "rate-limit",
  );

  globalThis.fetch = async () => {
    throw new TypeError("connection refused");
  };
  await assert.rejects(getAreas(), (error: unknown) => error instanceof ApiError && error.kind === "network");
});

test("malformed successful JSON does not expose a raw parsing error", async () => {
  globalThis.fetch = async () => new Response("not-json", { status: 200 });

  await assert.rejects(
    getAreas(),
    (error: unknown) => error instanceof ApiError && error.kind === "server",
  );
});

test("backend validation details remain structured without exposing raw JSON to UI code", async () => {
  storage.set("achenaki.anonymous-reporter.v1", validToken);
  globalThis.fetch = async () => Response.json(
    {
      error: {
        code: "validation_failed",
        message: "Invalid",
        details: { sub_area_id: ["The selected locality is invalid."] },
      },
    },
    { status: 422 },
  );

  await assert.rejects(
    submitUtilityReport({
      area_id: 1,
      sub_area_id: 999,
      utility_type: "ELECTRICITY",
      status: "UNSTABLE",
      time_bucket: "HOUR_1",
    }),
    (error: unknown) =>
      error instanceof ApiError &&
      error.kind === "validation" &&
      Boolean(error.fields.sub_area_id),
  );
});

function reportResponse(id: number, utilityType: "ELECTRICITY" | "GAS" = "ELECTRICITY", status = "AVAILABLE") {
  return {
    id,
    utility_type: utilityType,
    status,
    area_id: 1,
    sub_area_id: 2,
    reported_at: "2026-09-01T09:30:00.000000Z",
    time_bucket: "NOW",
    estimated_started_at: "2026-09-01T09:30:00.000000Z",
    ...(utilityType === "GAS" ? { can_cook: null } : {}),
  };
}
