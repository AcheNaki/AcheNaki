import assert from "node:assert/strict";
import test, { afterEach, beforeEach } from "node:test";
import {
  ServerApiError,
  getServerAreaLiveStatuses,
  getSlugLocalityAnalytics,
  getSlugLocalityStatus,
} from "./server.ts";

const originalFetch = globalThis.fetch;

beforeEach(() => {
  process.env.API_BASE_URL = "http://api.test/api/v1";
  delete process.env.NEXT_PUBLIC_API_BASE_URL;
});

afterEach(() => {
  globalThis.fetch = originalFetch;
});

test("server locality reads use scoped slug routes and never cache", async () => {
  const seen: { url: string; cache?: string }[] = [];
  globalThis.fetch = async (input, init) => {
    seen.push({ url: String(input), cache: init?.cache });
    return Response.json({ data: { sub_area: { id: 1, name: "Palash Nagar" } } });
  };

  await getSlugLocalityStatus("pallabi", "palash-nagar");
  await getSlugLocalityAnalytics("pallabi", "palash-nagar", "2026-09-01");
  await getServerAreaLiveStatuses("pallabi");

  assert.deepEqual(seen.map((entry) => entry.url), [
    "http://api.test/api/v1/areas/pallabi/sub-areas/palash-nagar/status",
    "http://api.test/api/v1/areas/pallabi/sub-areas/palash-nagar/analytics?date=2026-09-01",
    "http://api.test/api/v1/areas/pallabi/statuses",
  ]);
  assert.deepEqual(new Set(seen.map((entry) => entry.cache)), new Set(["no-store"]));
});

test("slugs are encoded so a crafted segment cannot escape the API path", async () => {
  let url = "";
  globalThis.fetch = async (input) => {
    url = String(input);
    return Response.json({ data: {} });
  };

  await getSlugLocalityStatus("../../admin", "a b/c").catch(() => undefined);
  assert.equal(url, "http://api.test/api/v1/areas/..%2F..%2Fadmin/sub-areas/a%20b%2Fc/status");
});

test("a 404 stays distinguishable so only missing localities render not-found", async () => {
  globalThis.fetch = async () => new Response("", { status: 404 });

  const error = await getSlugLocalityStatus("pallabi", "nope").catch((reason: unknown) => reason);
  assert.ok(error instanceof ServerApiError);
  assert.equal(error.status, 404);
});

test("an unreachable API is not reported as a missing locality", async () => {
  globalThis.fetch = async () => {
    throw new TypeError("fetch failed");
  };

  const error = await getSlugLocalityStatus("pallabi", "palash-nagar").catch((reason: unknown) => reason);
  assert.ok(error instanceof ServerApiError);
  assert.equal(error.status, undefined);
});

test("a 500 is not reported as a missing locality", async () => {
  globalThis.fetch = async () => new Response("<html>Server Error</html>", { status: 500 });

  const error = await getServerAreaLiveStatuses("pallabi").catch((reason: unknown) => reason);
  assert.ok(error instanceof ServerApiError);
  assert.equal(error.status, 500);
});

test("a malformed successful body fails safely instead of rendering undefined data", async () => {
  for (const body of ["not json", JSON.stringify({}), JSON.stringify({ data: null }), JSON.stringify([1, 2])]) {
    globalThis.fetch = async () =>
      new Response(body, { status: 200, headers: { "content-type": "application/json" } });

    const error = await getSlugLocalityStatus("pallabi", "palash-nagar").catch((reason: unknown) => reason);
    assert.ok(error instanceof ServerApiError, `Body ${body} should not resolve.`);
  }
});
