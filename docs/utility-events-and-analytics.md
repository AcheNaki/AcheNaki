# Utility Events, State Intervals, and Daily Analytics

AcheNaki? converts sufficiently supported locality observations into inferred electricity outage episodes and gas state intervals. **These are inferred community-observation events, not official utility-provider outage records.** Raw `utility_reports` remain immutable authoritative evidence; live status remains a rebuildable current projection; events and intervals are rebuildable inferred records; daily analytics are derived views.

## Durable-transition policy

Only `MEDIUM` or `HIGH` live projections can create or change durable history. `LOW` evidence is retained in raw reports and live aggregation but cannot transition an event or interval. The minimum confidence and the two-minute stabilization period are centralized in `config/reporting.php`.

The stabilization rule requires two qualifying projections for the same proposed transition at least two minutes apart and no more than one live evidence window apart. Older pending transitions restart instead of confirming immediately after a long unknown gap. A different reliable state or an uncertain `MIXED` projection interrupts a pending transition. This deliberately favors fewer, more defensible episodes over reacting to short-lived crowd noise.

## Electricity outage events

`electricity_outage_events` has a small lifecycle:

- `CANDIDATE`: the first `MEDIUM/HIGH UNAVAILABLE` projection established possible outage evidence.
- `ACTIVE`: another qualifying `UNAVAILABLE` projection confirmed the candidate after the stabilization period.
- `RESOLVED`: two stabilized `MEDIUM/HIGH AVAILABLE` projections supported restoration.

The first candidate uses the live projection's safe `status_since` when available. Otherwise it uses the time sufficient evidence was first established; it never invents an earlier start for `UNKNOWN` or `OVER_2_HOURS`. `started_at` is an inferred conservative time, not the provider's exact outage time.

An active outage is not resolved by one available contradiction, `LOW` confidence, `MIXED`, `INSUFFICIENT_DATA`, stale evidence, absence of reports, or `UNSTABLE`. `UNSTABLE` neither creates nor resolves an outage. A brief qualifying available candidate followed by unavailable evidence is cleared and the original outage remains active, preventing episode splitting.

A partial unique database index permits only one open candidate/active event per sub-area. Reconciliation also runs while the canonical sub-area row is locked. Duration is never persisted: completed duration is `ended_at - started_at`; ongoing duration is derived against the requested analytics-window end.

Raw reports do not have an `event_id`, and no report association table is introduced. Event membership is derived by canonical sub-area and time during deterministic replay. This avoids electricity-specific coupling in the shared electricity/gas report table, preserves append-only raw evidence, and permits full reconciliation. Rebuilds recreate inferred IDs.

## Gas state intervals

Gas uses `gas_state_intervals`, not outage lifecycle semantics. Reliable states are `NORMAL`, `LOW`, `VERY_LOW`, and `UNAVAILABLE`; `MIXED` and `INSUFFICIENT_DATA` never become intervals.

The first reliable gas projection opens an interval. A different reliable state becomes pending and opens only after a second matching projection at least two minutes later. The previous interval ends at the first sufficiently supported transition time, and the next begins at that same conservative boundary. Rapid alternation therefore does not create many short intervals.

Each interval stores `observed_until_at`, derived from the latest contributing report plus the 30-minute live evidence window. A lack of reports never fabricates a transition. Analytics stop classified coverage at `observed_until_at`; subsequent time is unknown. If reliable reporting later resumes—even with the same gas state—the old interval ends at its evidence boundary and a new interval opens, preserving the unknown gap. An open database interval is publicly `ongoing` only while its evidence remains unexpired.

## Observation coverage

Coverage and utility state are separate concepts. Every daily response reports integer seconds of:

- reliably classified/observed time; and
- unknown or unobserved time.

Unknown time is never treated as electricity availability or normal gas. Electricity coverage is rebuilt at query time from raw reports scoped to the requested day, its preceding aggregation window, and the sub-area. It uses the same latest-reporter reduction, confidence threshold, stabilization rule, and evidence expiry. This avoids assuming that gaps between outage events were available.

Gas coverage comes from state intervals clipped by both their transition end and `observed_until_at`. State durations plus unknown duration reconcile to the requested analysis-window duration. An observed availability percentage is intentionally omitted in v1 rather than presenting false precision.

## Daily and timezone semantics

`GET /api/v1/sub-areas/{subArea}/analytics?date=YYYY-MM-DD` returns one locality day. The fixed analytics timezone is `Asia/Dhaka`; local midnight boundaries are converted to UTC for indexed queries and all API timestamps remain ISO 8601 UTC.

When `date` is omitted, the current Dhaka date is used. Today's window ends at the current server time, so future hours are not counted as unknown. Completed historical dates use the full local day. Future dates and malformed dates are rejected.

Events and intervals are clipped to the analysis window. An episode spanning 23:30–01:00 contributes 30 minutes to the first local day and 60 minutes to the second. Ongoing records are clipped at the current-day endpoint. No increasing duration counter is stored.

The response contains:

- locality, date, timezone, UTC window, duration, and partial-day flag;
- electricity outage count, total/longest/ongoing outage seconds, classified state seconds, coverage, and a daily event timeline;
- gas state seconds, coverage, and a daily interval timeline.

Outage count means confirmed outage episodes overlapping the requested day. Candidate events are not public analytics. Timeline durations are clipped to the requested day even when their original timestamps cross its boundary. Reporter IDs, tokens, hashes, report IDs, and internal confidence scores are never returned.

## Rebuild and reconciliation

Accepted reports synchronously follow:

```text
raw report commit
    ↓
locked live projection refresh
    ↓
utility-specific history reconciliation
```

If projection/history reconciliation fails, the raw report remains accepted and the whole derived refresh transaction rolls back for later repair.

Rebuild inferred electricity events:

```text
php artisan electricity-events:rebuild
php artisan electricity-events:rebuild --sub-area=103
```

Rebuild gas intervals:

```text
php artisan gas-intervals:rebuild
php artisan gas-intervals:rebuild --sub-area=103
```

Commands lock each locality, replay its reports chronologically through the current aggregation rules, replace only the selected derived records inside one transaction, and never update or delete raw reports. They are idempotent in domain output, but inferred event/interval IDs may change. A failure rolls back the selected rebuild transaction rather than exposing a partial replacement.

## Limitations

- Anonymous reporter tokens are browser continuity signals, not verified humans.
- Inference thresholds are explainable product heuristics, not statistically calibrated probabilities.
- Active electricity events remain unresolved through unknown periods; analytics separately expose observation coverage so this uncertainty is visible.
- Candidate confirmation and state changes require a later accepted report; there is no queue, timer, or scheduled transition worker.
- Historical accuracy depends on retaining raw reports and inferred history. No retention/deletion job exists yet.
- Query-time electricity coverage replay is bounded to one locality/day but should be load-tested with production-scale report density.
- PostgreSQL partial indexes, CHECK constraints, row locking, timezone storage, and concurrent single-open-record invariants were verified against a local PostgreSQL 17 container, including parallel-process writes. Production query plans at realistic report density, and managed-Supabase pooling/timeout behaviour, remain unverified.
- No official notices, provider data, interactive map, authentication, or notifications are implemented. Dashboard, locality analytics, and periodic live-update delivery are implemented; the map remains deferred pending verified centroid data.
