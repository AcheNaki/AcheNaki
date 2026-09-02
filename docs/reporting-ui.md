# Reporting Interface

The AcheNaki? Next.js `/report` route provides the mobile-first interface for submitting individual electricity and household gas observations. It does not display or calculate area-wide live status, confidence, inferred events, analytics, or historical reports.

## Flow

First-time visitors choose a canonical major area and dependent sub-area before utility controls appear:

```text
Major area → Sub-area → Utility → Status → Time → Optional gas cookability → Submit
```

Returning visitors with a verified saved locality start at utility selection. The current locality remains visible with a **Change** action. Report choices use large cards or structured chips; the normal reporting flow has no free-text status, locality, comment, or time input. Search fields only filter canonical API options and cannot create locations.

## Saved locality

The browser stores `areaId`, `subAreaId`, and their minimal display names under a versioned local-storage key. On return, the UI fetches active areas and the selected area's active sub-areas, then verifies both IDs and their relationship before using the saved locality. Corrupted, missing, inactive, or mismatched values are removed and the location flow is shown again.

Changing locality updates this record but does not remove or replace the separate anonymous reporter token. Clearing browser storage removes both conveniences and does not delete previously accepted reports.

## Anonymous reporting

The page quietly reuses or obtains the backend-issued anonymous reporter token through the centralized API client. The token is never rendered or logged. If report validation identifies an invalid token, the client removes it, obtains one replacement, and retries once. Other failures do not trigger automatic submission retries.

## Feedback

- HTTP 201 shows **Report submitted** and describes the submission as one observation.
- HTTP 200 with `meta.duplicate: true` shows **Already received** as a successful idempotent outcome.
- HTTP 429 asks the resident to wait before reporting again.
- Location validation asks the resident to reselect an active locality.
- Network/server errors remain retryable and do not expose raw backend responses.
- **Report Another Update** retains locality but clears utility, status, time, and gas cookability.

## Current limitations

- No aggregate or live locality status is available yet.
- The anonymous token and saved locality are browser-local and can be cleared or copied; they are not accounts or proof of identity.
- Search matches canonical English display names only; aliases and reviewed Bangla names are not yet available.
- Component behavior is covered through shared domain/client tests and live integration smoke testing. A future cross-browser E2E phase should automate the complete visual flow and browser storage restoration.
