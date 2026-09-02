# AcheNaki? Engineering Guide

## Project

**AcheNaki?** is a crowdsourced real-time electricity and household gas availability platform for Dhaka.

Crowdsourced reports are observations, not utility-provider ground truth. Public wording and system behavior must preserve uncertainty.

## Planned stack

- Frontend: Next.js, React, TypeScript, Tailwind CSS, and shadcn/ui where appropriate
- Backend: Laravel REST API
- Database: Supabase PostgreSQL
- Later hosting: Vercel for the frontend, Render for the backend, and Supabase for the database

Exact framework versions must be verified and selected before scaffolding.

## Architecture principles

- Use a modular monorepo with separate `frontend/` and `backend/` applications.
- Begin as a modular monolith, not microservices.
- Laravel is the authoritative backend and database writer.
- The browser must not write raw utility reports directly to Supabase.
- Require server-side validation; never trust client-supplied IDs, statuses, timestamps, or aggregate state.
- Server-generated timestamps are authoritative.
- Keep reports, inferred events, live-status projections, and analytics as separate concepts.
- Live status must account for contradictory evidence.
- No reports means insufficient data, never automatically available.
- Never publicly expose exact household or private coordinates.
- Use normalized location IDs instead of user-entered location names in reports.
- Treat raw reports as append-only by default; represent moderation or exclusion explicitly.
- Derived status must be rebuildable from authoritative data.
- Keep business logic out of giant controllers and components.
- Avoid premature queues, microservices, machine learning, and unnecessary abstractions.

## Reporting UX

- Keep the normal reporting flow essentially free of text input.
- Use predefined major-area and dependent sub-area selections.
- Use structured utility statuses and time buckets.
- Aim for two or three meaningful taps for a returning user with a remembered locality.

Electricity statuses:

- `AVAILABLE`
- `UNAVAILABLE`
- `UNSTABLE`

Gas statuses:

- `NORMAL`
- `LOW`
- `VERY_LOW`
- `UNAVAILABLE`

Current-report time buckets should conceptually support:

- `NOW`
- `MIN_5` where relevant
- `MIN_15`
- `MIN_30`
- `HOUR_1`
- `HOUR_2`
- `OVER_2_HOURS`
- `UNKNOWN`

Never convert `OVER_2_HOURS` or `UNKNOWN` into a fabricated exact timestamp.

## Quality rules

- Use type-safe TypeScript and Laravel conventions where appropriate.
- Apply explicit API validation and database constraints.
- Keep secrets in secure environment variables and out of Git.
- Add automated tests for critical business logic, including important success and failure paths.
- Prefer readable code over clever code and avoid duplicated domain logic.
- Make minimal, maintainable, production-oriented changes.
- Do not touch unrelated files.
- Explain and justify any major architectural deviation before implementing it.

## Workflow rules

- Implement only the requested scope and inspect existing code first.
- Avoid destructive operations, unapproved dependency changes, and unapproved network actions.
- Do not commit unless explicitly requested.
- After meaningful implementation, summarize changed files and important decisions, run relevant validation or tests, and report failures honestly.
- Never claim that a test or validation passed unless it actually ran successfully.
