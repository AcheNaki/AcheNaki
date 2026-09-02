# Realtime Live Updates

## Selected transport: smart polling

Ache Naki? uses browser-side, visibility-aware polling of Laravel's existing public read APIs. Laravel remains the only writer and the existing flow remains raw reports → aggregation → live projection → inferred events and analytics → read delivery.

Supabase Realtime is intentionally deferred: it would require a carefully restricted public projection surface, RLS and publication operations, browser credentials, connection lifecycle handling, and production verification. Laravel SSE would add long-lived connection and proxy behavior to Render/Vercel before evidence justifies it. Polling meets the product target of updates within several seconds to tens of seconds without creating a second source of truth.

## Intervals and behavior

- Locality current status: 15 seconds.
- Dashboard projection lists: 20 seconds.
- Recently restored events: 30 seconds.
- Daily analytics/timelines: 45 seconds.

Polling pauses while a document is hidden or offline. A visibility return or `online` event triggers an immediate targeted refresh. Failed reads retain the last successful response, show a restrained delayed indicator, and retry at 30 then 60 seconds before returning to the normal interval. Requests do not overlap; cleanup aborts in-flight work on navigation or locality changes.

At 1,000 simultaneously active homepage viewers, a 20-second dashboard refresh is roughly 50 dashboard requests/sec, plus only the subset with a saved locality issuing the 15-second locality read. This is deliberately bounded and avoids repeated taxonomy reads. Analytics refreshes are slower because they are more expensive and do not need second-level freshness.

## Caching and privacy

Public locality server renders use `cache: no-store`. Client live reads use the existing Laravel APIs, whose stale-projection guards still convert expired evidence to `INSUFFICIENT_DATA`. No cached red outage is retained after a successful insufficient-data response.

No Supabase client, subscription, raw report stream, reporter token, reporter identity, or internal confidence score is exposed. ETags are deferred: current payloads are bounded and the added protocol complexity is not yet justified.

## Limitations and upgrade path

This is periodic live delivery, not push transport; it is expected to update within the documented intervals. A later measured need may justify Supabase Realtime over a dedicated, RLS-protected public derived view, or Laravel push infrastructure. Neither should subscribe to `utility_reports` or bypass Laravel writes.
