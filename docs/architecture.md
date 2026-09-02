# Initial Architecture

## System context

AcheNaki? starts as a modular monorepo containing two separately deployable applications backed by one authoritative API and database.

```text
Next.js UI
    ↓
Laravel REST API
    ↓
Supabase PostgreSQL
    ↓
Derived live status
    ↓
Next.js dashboard
```

This is a modular monolith. Additional services, queues, and realtime infrastructure will be introduced only when measured requirements justify them.

## Responsibilities

### Next.js

- Render the public dashboard, locality pages, and reporting interface.
- Collect structured user input and provide client-side interaction and feedback.
- Remember a locality locally where appropriate.
- Treat client-side validation as usability support, not a security boundary.
- Never authoritatively calculate report timestamps, inferred events, confidence, or aggregate state.

### Laravel

- Expose the REST API.
- Validate and normalize all submitted location IDs, statuses, and time buckets.
- Generate authoritative server timestamps.
- Interpret structured time buckets without inventing precision.
- Apply rate limits and duplicate handling.
- Perform authoritative database writes.
- Own deterministic aggregation and confidence calculation.
- Reconcile utility-specific inferred history without mutating or directly associating raw reports.

Business rules should live in cohesive domain/application services rather than giant controllers.

### Supabase PostgreSQL

- Store normalized areas and dependent sub-areas.
- Store individual reports plus rebuildable inferred electricity events and gas state intervals.
- Enforce relational constraints and useful indexes.
- Hold a rebuildable derived representation of current locality status when introduced.
- Preserve authoritative data from which projections can be recomputed.

Supabase initially acts primarily as managed PostgreSQL. Laravel owns raw report writes and schema evolution. The browser must not write raw utility reports directly to Supabase.

Laravel migrations are the sole application-schema migration mechanism. Staging and production schema changes must be reproducible from the repository; ad hoc Supabase SQL is not the primary workflow. This ownership decision is recorded in ADR 0002.

## Location foundation

The MVP location hierarchy uses two normalized entities:

- `areas` for major Dhaka areas
- `sub_areas` for predefined localities belonging to exactly one area

Both use stable numeric IDs and canonical slugs. Areas and sub-areas have active lifecycle flags; public endpoints exclude inactive records. Area deletion is restricted while child sub-areas exist, avoiding accidental cascading loss when reports reference these records later.

City corporation is a constrained `DNCC` or `DSCC` domain value on an area rather than a separate table. Bangla names are nullable until reviewed translations are available. Geospatial fields and additional administrative hierarchy are deferred.

`backend/database/data/dhaka-locations.json` is the single canonical source for the predefined user-facing taxonomy. The seeder validates this file, matches records by stable slug, preserves existing IDs, and deactivates removed records rather than deleting them. The current curated dataset covers 55 major-area groupings and 334 sub-areas across DNCC and DSCC. It is grounded in official/public geographic sources but is not a legal boundary dataset or government-certified taxonomy; methodology and known ambiguities are documented in `docs/location-taxonomy.md`.

Read-only location endpoints are versioned under `/api/v1`:

- `GET /areas`
- `GET /areas/{area}/sub-areas`

API resources expose only stable public fields. Missing, invalid, and inactive area routes return HTTP 404 with this resource-error shape:

```json
{
  "error": {
    "code": "not_found",
    "message": "The requested resource was not found."
  }
}
```

Future reporting validation should verify the submitted `sub_area_id` with a scoped server-side existence rule requiring the submitted `area_id` and an active sub-area. Client-side cascading selection is not a security boundary.

## Database testing strategy

Fast unit and basic feature tests use an in-memory SQLite database. Current tests exercise migrations, relationships, foreign keys, uniqueness, seeding, and API behavior without external credentials.

SQLite does not prove PostgreSQL behavior. PostgreSQL integration tests remain required for PostgreSQL-specific type and constraint behavior, case/collation semantics, transaction/concurrency behavior, and production migration execution. No schema behavior should rely on a database-specific feature without coverage against PostgreSQL.

## Generated Laravel tables

Laravel's generated users/password/session, cache, and job migrations are retained deliberately as framework foundation. Optional authentication is planned later, and the standard runtime tables may be useful as the operational design matures. Their presence is not an implementation of authentication, queues, reputation, or notifications, and those features remain deferred. The migrations should be reviewed again before the first production migration run.

## Domain distinctions

### Report

An individual immutable observation submitted for one utility and locality. Reports are evidence, not absolute truth, and should generally be append-only. Moderation or exclusion should be explicit and auditable.

### Event

An inferred electricity outage/restoration episode or a utility-specific state transition supported by one or more reports. An event is not created blindly for every report.

### Live status

The current estimated aggregate state of a locality, based on recent supporting and contradictory evidence. It must include an insufficient-data outcome and remain rebuildable from authoritative records.

### Analytics

Statistics derived from sufficiently reliable event or state information. Analytics must preserve uncertainty and distinguish unknown periods from observed availability.

## Anonymous identity and privacy direction

The MVP does not require an account. Laravel issues a cryptographically random, opaque anonymous reporter token through `POST /api/v1/anonymous-session`. Because the planned frontend and backend use separate origins, the frontend keeps this non-PII token in local storage and sends it in `X-Anonymous-Reporter`; the design does not depend on cross-site cookies. Laravel persists only an HMAC-SHA-256 pseudonymous representation in an internal reporter row after the first report.

The token is a browser continuity signal for duplicate suppression and throttling, not proof of one person. No raw token, plaintext IP, user agent, hardware fingerprint, coordinate, or household address is stored. Reporter-row retention currently follows report retention; production data governance must define a deletion or anonymization schedule before public collection.

The authoritative ingestion pipeline is:

```text
Frontend structured observation
        ↓
Anonymous reporter identity
        ↓
Laravel location and domain validation
        ↓
Server UTC time and time-bucket normalization
        ↓
Rate limit and transactional duplicate check
        ↓
Immutable individual utility report
    ↓
Synchronous rebuildable live-status projection
    ↓
Stabilized electricity events / gas intervals
    ↓
Coverage-aware daily analytics
```

Equivalent observations within three minutes return the existing report idempotently. The transaction locks the reporter row so concurrent requests from that identity cannot trivially race the duplicate check. Status changes and observations for another utility or locality are not suppressed.

After a new report transaction commits, Laravel synchronously recalculates the affected sub-area and utility from a bounded recent evidence window. The refresh transaction locks the canonical sub-area row to serialize concurrent locality projection writes. The projection write is separate because immutable raw evidence has priority; a projection failure is logged without rolling back an accepted report and can be repaired with `utility-status:rebuild`. Public reads apply a query-time freshness guard, so an old projection is returned as insufficient data rather than stale active truth.

Inside the derived refresh transaction, `MEDIUM/HIGH` live evidence is passed to utility-specific history reconciliation. Electricity uses candidate/active/resolved outage episodes; gas uses categorical state intervals with an explicit evidence-expiry boundary. Both require stabilized transitions and are rebuildable by chronological report replay. Analytics remain sub-area scoped, use Dhaka local-day boundaries converted to UTC, clip cross-midnight records, and report unknown time separately from classified coverage. See `docs/utility-events-and-analytics.md`.

Advanced device fingerprinting is out of MVP scope. The system must not claim that a device identifier proves a unique person, and it must never publicly expose exact household coordinates.

## Deployment and cross-origin concerns

The planned Vercel frontend and Render backend will normally use separate origins. Before deployment, the design must explicitly address:

- A strict CORS allowlist
- CSRF protections appropriate to the chosen session/authentication model
- Cookie `Secure` and `SameSite` behavior
- Preview and production origins
- API environment configuration
- Rate-limit identity and trusted proxy configuration

Authentication is deferred, so its cookie or token strategy is not decided here.

Anonymous reporting uses a non-cookie header and does not enable credentialed CORS. Local development explicitly allows `http://localhost:3000`; deployed Vercel and preview origins must be allowlisted through environment configuration.

## Deferred architecture

- Live delivery uses visibility-aware, bounded client polling of Laravel's derived public read APIs. It does not change database ownership or create a browser write path; details and future push-transport criteria are in `docs/realtime.md`.
- Maps and geospatial processing are deferred from the first MVP.
- Queues are deferred until synchronous processing becomes unreliable or too slow.
- Machine learning is not planned for the initial confidence model.
