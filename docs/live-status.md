# Live Locality Status

AcheNaki? derives a cautious current estimate for each `sub_area_id + utility_type`. A raw `utility_report` remains an immutable individual observation and the authoritative evidence. A `utility_live_status` row is only a rebuildable read projection; it is never treated as source evidence and is never an inferred outage or restoration event.

## Projection states

Electricity projections can be `AVAILABLE`, `UNAVAILABLE`, `UNSTABLE`, `MIXED`, or `INSUFFICIENT_DATA`.

Gas projections can be `NORMAL`, `LOW`, `VERY_LOW`, `UNAVAILABLE`, `MIXED`, or `INSUFFICIENT_DATA`.

`MIXED` and `INSUFFICIENT_DATA` exist only in the projection domain. They do not pollute the raw report status enums. No reports, no recent reports, and stale projections always resolve publicly to `INSUFFICIENT_DATA`; they never imply electricity is available or gas is normal.

## Evidence window and reporter reduction

The live evidence window is 30 minutes, configured in `config/reporting.php`. Queries are bounded by sub-area, utility, and this window.

Within that window, only the newest accepted observation from each anonymous reporter contributes for the same sub-area and utility. For example, one reporter's `UNAVAILABLE` followed by `AVAILABLE` contributes only `AVAILABLE`. Different reporter tokens count independently, but a token is a browser continuity signal rather than proof of a unique human.

Duplicate attempts that return an existing report do not trigger recalculation and do not increase evidence.

## Recency weights

The deterministic v1 model uses integer weights:

| Report age | Weight |
| --- | ---: |
| Up to 5 minutes | 100 |
| Over 5 through 15 minutes | 75 |
| Over 15 through 30 minutes | 40 |
| Older than 30 minutes | 0 |

Weights are categorical vote strength, not probability. Gas remains categorical; severity values are never averaged. `UNSTABLE` is also an independent electricity category.

## Estimated status and MIXED

Weights are summed by status. The highest weighted category is the estimate unless the top two category weights differ by no more than 15% of total evidence weight. With at least two represented categories, that closeness rule returns `MIXED`.

This allows a strong majority to remain visible while preserving close disagreement. For a mixed result, `supporting_reports` is the number of reporters in the deterministic top category and `contradicting_reports` is every other independent reporter; this keeps disagreement explicit rather than presenting all conflict as support.

## Confidence score and levels

The internal confidence score is an application evidence score from 0–100. **It is not a statistically calibrated probability.** Public APIs initially expose only `LOW`, `MEDIUM`, or `HIGH`.

Three integer components are combined:

- 50% weighted agreement: top category weight divided by total evidence weight.
- 20% average recency: total evidence weight divided by the maximum possible weight for the contributing reporters.
- 30% independent-reporter volume: configured scores of 25, 45, 65, 75, 85, and 100 for one through six-or-more reporters.

Level gates prevent volume-poor evidence from looking certain:

- `LOW`: any non-insufficient estimate that does not meet higher gates. One or two reporters remain low regardless of numeric score.
- `MEDIUM`: score at least 60, at least 3 independent reporters, and at least 3 supporters for the top category.
- `HIGH`: score at least 80, at least 6 independent reporters, and at least 5 supporters for the top category.

Contradictions lower weighted agreement. More recent, independent, consistent evidence can raise confidence. User reputation, GPS, browser fingerprints, and machine learning are not inputs.

## `status_since`

`status_since` is null for `MIXED`, `INSUFFICIENT_DATA`, and unsafe temporal evidence. It is derived only when the selected category has at least two independent supporting reports with non-null `estimated_started_at` values and those estimates are within 15 minutes of one another.

When safe, the latest supporting estimate is used as a conservative representative time: the API does not claim the condition started earlier than all consistent estimates support. `UNKNOWN` and `OVER_2_HOURS` have null estimates and never fabricate a timestamp. Widely conflicting estimates also produce null.

## Projection updates and recovery

After a new raw report commits, Laravel recalculates the affected sub-area and utility synchronously. The projection write uses its own transaction and locks the canonical sub-area row while reading the bounded evidence set and updating the projection. That keeps concurrent refreshes for a locality from racing without introducing distributed infrastructure. This boundary deliberately prioritizes raw evidence integrity: if projection refresh fails, the accepted report remains stored, the failure is logged without reporter secrets, and reconciliation can rebuild the projection. The report response remains an acknowledgement of the individual observation, not of an aggregate result.

Run a full rebuild with:

```text
php artisan utility-status:rebuild
```

Optional scopes:

```text
php artisan utility-status:rebuild --sub-area=103
php artisan utility-status:rebuild --utility=ELECTRICITY
php artisan utility-status:rebuild --sub-area=103 --utility=GAS
```

The command replaces only matching derived projection rows inside a transaction, recalculates from raw reports, and never deletes or updates raw reports. Repeated runs are idempotent.

## Stale-data safety

No incoming report means there is no automatic write to expire a row. Public reads therefore enforce freshness at query time:

- A locality projection whose `last_report_at` is older than the 30-minute window is returned as `INSUFFICIENT_DATA` with no active confidence, counts, or timestamps.
- Stale rows are excluded from the live-status listing.

This prevents a past `UNAVAILABLE — HIGH` projection from remaining active indefinitely even if no scheduled reconciliation command runs. A future scheduler may refresh stored rows operationally, but public correctness does not depend on it.

## Public APIs

`GET /api/v1/sub-areas/{subArea}/status` returns locality identity plus electricity and gas snapshots. Missing projections are synthesized as insufficient data. Inactive or nonexistent localities return 404.

`GET /api/v1/live-statuses` returns a bounded, newest-first list of fresh non-insufficient projections. Optional parameters are:

- `utility=ELECTRICITY|GAS`
- `status=<compatible projection status except INSUFFICIENT_DATA>`
- `limit=1..100` (default 25)

Responses expose no raw reports, anonymous reporter IDs, hashes, tokens, IP signals, internal confidence scores, or anti-abuse metadata.

## Limitations

- Anonymous reporter tokens can be cleared, copied, or recreated and do not establish unique humans.
- The score thresholds are an explainable product heuristic and need evaluation against real usage; they are not calibrated statistics.
- The model does not understand utility feeders, gas networks, household coordinates, planned maintenance, weather, or provider data.
- Electricity outage events, gas intervals, and locality daily analytics are implemented as separate derived models documented in `docs/utility-events-and-analytics.md`. The public dashboard and locality pages are implemented; realtime push subscriptions and locality rankings remain unimplemented.
- The suite runs on both in-memory SQLite and real PostgreSQL. PostgreSQL 17 was verified locally against a disposable container: migrations, canonical seeding, the full suite, rebuild commands, and concurrent projection writes. Managed-Supabase behaviour (pooling, TLS, statement timeouts) and production query plans are still unverified.
