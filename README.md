# AcheNaki?

AcheNaki? is a crowdsourced platform for understanding current electricity and household gas availability across Dhaka.

## Overview

The platform will turn recent, structured community observations into cautious locality-level utility estimates:

```text
Individual observations
        ↓
Aggregation
        ↓
Confidence
        ↓
Estimated locality status
```

Crowdsourced reports are not guaranteed utility-provider ground truth. AcheNaki? must communicate evidence, recency, disagreement, and confidence instead of presenting a single report as a certain area-wide fact.

## MVP features

- Predefined Dhaka major-area and sub-area selection
- Fast anonymous electricity and household gas reporting
- Structured status and time inputs with no normal-flow free text
- Locality-level live status with recent evidence and confidence
- Support for conflicting and stale reports
- Electricity outage/restoration events and duration analytics
- Gas availability state history and duration analytics
- Mobile-first public dashboard and locality detail pages with periodic live updates

## Reporting model

A report is an individual observation. Multiple reports may support or contradict an inferred utility event or current state. The backend will validate and aggregate recent observations into an estimated live status, while preserving uncertainty and an insufficient-data state.

## Planned tech stack

- **Frontend:** Next.js, React, TypeScript, Tailwind CSS, shadcn/ui where appropriate
- **Backend:** Laravel REST API
- **Database:** Supabase PostgreSQL
- **Hosting later:** Vercel, Render, and Supabase

The initialized framework versions and local commands are recorded in `docs/development-environment.md`.

## Repository structure

The monorepo layout is:

```text
AseNaki/
├── frontend/   # Next.js application
├── backend/    # Laravel REST API
├── docs/       # Product, architecture, and engineering decisions
├── AGENTS.md
├── README.md
└── .gitignore
```

## Development status

### Implemented in the current MVP

- Canonical Dhaka locality taxonomy (55 major areas, 334 sub-areas)
- Anonymous, structured electricity and household gas reporting
- Electricity live status and gas live status
- Confidence bands, `MIXED`, and an explicit `INSUFFICIENT_DATA` state
- Inferred electricity outage event lifecycle
- Gas state intervals with explicit unknown coverage
- Locality daily analytics on `Asia/Dhaka` day boundaries
- Public dashboard, major-area browsing, and locality detail pages
- Periodic visibility-aware live refresh

### Deferred (not implemented)

- Interactive map — pending verified locality centroids; no coordinates were fabricated
- User accounts and authentication
- Notifications
- Official DESCO / DPDC / Titas data integration
- Historical manual backfill reporting
- Prediction or machine learning
- Deployment

### Verification status

The suite runs green on both in-memory SQLite and real PostgreSQL 17. Migrations, canonical
seeding, migration rollback, the rebuild commands, and concurrent multi-process writes have
been exercised against a local disposable PostgreSQL container, and the critical browser
flows have been run against a production build in Chrome.

**Actual Supabase connectivity is still not verified**, because no project credentials have
been provided. Managed pooling, TLS, statement timeouts, and production-scale query plans
remain untested. See `docs/development-environment.md` for how to run the suite against
PostgreSQL locally.

API endpoints (all 14 routes currently registered under `/api/v1`):

- `GET /api/v1/health`
- `GET /api/v1/areas`
- `GET /api/v1/areas/{area}/sub-areas`
- `GET /api/v1/areas/{areaSlug}/statuses`
- `GET /api/v1/areas/{areaSlug}/sub-areas/{subAreaSlug}/status`
- `GET /api/v1/areas/{areaSlug}/sub-areas/{subAreaSlug}/analytics`
- `GET /api/v1/locations/search`
- `POST /api/v1/anonymous-session`
- `POST /api/v1/utility-reports`
- `GET /api/v1/sub-areas/{subArea}/status`
- `GET /api/v1/sub-areas/{subArea}/analytics`
- `GET /api/v1/live-statuses`
- `GET /api/v1/dashboard`
- `GET /api/v1/electricity-events/recently-resolved`

The backend reduces recent evidence to the latest observation per anonymous reporter, preserves disagreement and stale uncertainty, stabilizes durable transitions, and distinguishes observed coverage from unknown time. See `docs/dashboard.md`, `docs/realtime.md`, `docs/map.md`, `docs/reporting-api.md`, `docs/reporting-ui.md`, `docs/live-status.md`, and `docs/utility-events-and-analytics.md` for the exact contracts.

The current seed is a curated user-facing Dhaka taxonomy covering both city corporations. It is grounded in official/public geographic sources but is not government-certified; its methodology, limitations, and correction process are documented in `docs/location-taxonomy.md`.

## Privacy and data-quality principles

- Never expose exact household or private coordinates.
- Use normalized locality IDs and validated structured inputs.
- Generate authoritative report timestamps on the server.
- Treat reports as observations rather than certain facts.
- Account for contradictory and stale evidence.
- Treat missing recent evidence as insufficient data, not availability.
- Keep raw report writes behind the Laravel API.
