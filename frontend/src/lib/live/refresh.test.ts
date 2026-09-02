import assert from "node:assert/strict";
import test from "node:test";
import { nextRefreshDelay, statusChanged } from "./refresh.ts";

test("live refresh backs off without becoming aggressive", () => {
  assert.equal(nextRefreshDelay(15_000, 0, 60_000), 15_000);
  assert.equal(nextRefreshDelay(15_000, 1, 60_000), 30_000);
  assert.equal(nextRefreshDelay(15_000, 3, 60_000), 60_000);
});

test("status announcements are only needed for changed saved-locality data", () => {
  assert.equal(statusChanged(null, "UNAVAILABLE", (a, b) => a === b), false);
  assert.equal(statusChanged("UNAVAILABLE", "UNAVAILABLE", (a, b) => a === b), false);
  assert.equal(statusChanged("UNAVAILABLE", "AVAILABLE", (a, b) => a === b), true);
});
