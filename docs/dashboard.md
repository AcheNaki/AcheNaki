# Public Dashboard and Locality Pages

The Ache Naki? dashboard is a public read experience for community-derived locality information. It never presents reports, inferred events, or live projections as utility-provider confirmation.

## Routes

- `/` is the dashboard. It reads the browser's existing saved locality and sends users to the existing `/report` flow to submit an observation.
- `/areas` searches the canonical major-area taxonomy; users then browse canonical localities at `/area/{areaSlug}`.
- `/area/{areaSlug}/{subAreaSlug}` is the public locality detail view. Slugs are scoped to their parent major area and do not expose numeric IDs in URLs.

## Current status wording

Electricity and gas labels are centralized in the frontend. `MIXED` remains mixed, and `INSUFFICIENT_DATA` remains insufficient. Confidence is displayed only as low, medium, or high evidence; it is never shown as a probability. `status_since` is shown as an approximate local Dhaka time only when the backend supplied it.

## Dashboard reads

- `GET /api/v1/dashboard` returns a bounded set of fresh, non-insufficient projections for Current Struggling and Recent Changes. Its `fresh_projection_counts` count only fresh projections, not all Dhaka localities, and the UI deliberately does not turn them into a city-wide percentage.
- `GET /api/v1/electricity-events/recently-resolved?limit=…` returns bounded, resolved events ending in the last 24 hours. The UI says “Power appears restored,” never provider-confirmed restoration.
- `GET /api/v1/areas/{areaSlug}/statuses` returns one major area plus all of its active localities and their two status snapshots, avoiding browser N+1 requests.
- Scoped slug status and analytics reads power locality pages. The older numeric read endpoints remain supported for the reporting UI and compatibility.

All dashboard reads exclude stale live projections and expose no raw reports, reporter identities, tokens, hashes, or internal confidence scores.

## Daily history and unknown coverage

Locality analytics use `Asia/Dhaka` day boundaries. Electricity includes real classified coverage segments; gas uses derived state intervals. The frontend fills gaps explicitly as `UNKNOWN`, rather than green availability or normal gas. Duration cards report integer seconds as human-readable durations and do not calculate an availability percentage.

Analytics remain community-inferred history: outage episodes are not official utility records. A request failure for analytics does not hide a successfully loaded current locality status.

## Live refresh

Dashboard and locality detail pages periodically revalidate only dynamic, bounded Laravel read endpoints. See `docs/realtime.md` for intervals, privacy boundaries, stale-data behavior, and the transport decision.
