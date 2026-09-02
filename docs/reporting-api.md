# Reporting API

The AcheNaki? reporting API accepts structured individual observations. A stored report is evidence from one anonymous browser token; it is not an area-wide utility status, inferred event, or confidence result.

## Base URL and format

Local API base URL: `http://127.0.0.1:8000/api/v1`

Requests and responses use JSON. Enum values are canonical uppercase strings. All authoritative timestamps are generated and stored in UTC and returned as ISO 8601 UTC values. A future frontend may display them in `Asia/Dhaka`.

## Anonymous identity

Call `POST /anonymous-session` before reporting. With no valid token, the endpoint returns a newly generated opaque token:

```json
{
  "data": {
    "token": "ar1_<43 random URL-safe characters>"
  }
}
```

The frontend stores this non-PII token in browser local storage and sends it in `X-Anonymous-Reporter`. Calling the endpoint with an already valid token in that header returns the same token. A missing or malformed token is replaced cleanly. This is a browser-level continuity signal, not proof of a unique person.

The database never stores the raw token. On first report use, Laravel stores only an HMAC-SHA-256 pseudonymous representation in an internal reporter row. Raw IP addresses, user agents, hardware fingerprints, coordinates, and addresses are not persisted. Removing browser storage creates a new anonymous identity. Changing the application key also changes token hashes, so key rotation requires an explicit identity-continuity decision. Reporter rows currently share the report retention lifecycle; a deletion/anonymization retention policy must be set before production data collection.

## Submit an observation

`POST /utility-reports` with `Accept: application/json`, `Content-Type: application/json`, and `X-Anonymous-Reporter`.

Electricity:

```json
{
  "area_id": 10,
  "sub_area_id": 103,
  "utility_type": "ELECTRICITY",
  "status": "UNAVAILABLE",
  "time_bucket": "MIN_15"
}
```

Electricity statuses are `AVAILABLE`, `UNAVAILABLE`, and `UNSTABLE`.

Gas:

```json
{
  "area_id": 10,
  "sub_area_id": 103,
  "utility_type": "GAS",
  "status": "VERY_LOW",
  "time_bucket": "MIN_30",
  "can_cook": false
}
```

Gas statuses are `NORMAL`, `LOW`, `VERY_LOW`, and `UNAVAILABLE`. `can_cook` may be `true`, `false`, `null`, or omitted. It is rejected for electricity. Subjective combinations such as `NORMAL` with `can_cook: false` are preserved rather than over-constrained.

Both location records must exist and be active, and the sub-area must belong to the submitted area. The server rejects cross-utility status values. Client-supplied `reported_at` has no authority and is ignored.

## Time buckets

| Bucket | Stored `estimated_started_at` |
| --- | --- |
| `NOW` | `reported_at` |
| `MIN_5` | 5 minutes before `reported_at` |
| `MIN_15` | 15 minutes before `reported_at` |
| `MIN_30` | 30 minutes before `reported_at` |
| `HOUR_1` | 1 hour before `reported_at` |
| `HOUR_2` | 2 hours before `reported_at` |
| `OVER_2_HOURS` | `null`; no exact time is fabricated |
| `UNKNOWN` | `null`; no exact time is fabricated |

The bucket remains stored even when the estimated timestamp is null, preserving the submitted degree of temporal knowledge.

## Success and duplicates

A newly accepted report returns HTTP 201:

```json
{
  "data": {
    "id": 123,
    "utility_type": "ELECTRICITY",
    "status": "UNAVAILABLE",
    "area_id": 10,
    "sub_area_id": 103,
    "reported_at": "2026-09-01T09:30:00.000000Z",
    "time_bucket": "MIN_15",
    "estimated_started_at": "2026-09-01T09:15:00.000000Z"
  },
  "meta": { "duplicate": false }
}
```

An identical observation from the same reporter for the same sub-area, utility, and status within 180 seconds returns the existing report with HTTP 200 and `meta.duplicate: true`. It does not insert another row. A status transition, different utility, or different sub-area remains accepted. The duplicate window is configured centrally in `config/reporting.php`.

A newly created report also triggers synchronous recalculation of the affected sub-area and utility live projection, followed by stabilized utility-specific event/interval reconciliation. The submission response remains limited to the individual report; it does not present a projection or inferred event as a consequence of one observation. Duplicate responses do not add evidence or recalculate. See `docs/live-status.md` and `docs/utility-events-and-analytics.md` for the separate derived models and read APIs.

Duplicate lookup and insertion run in one transaction while locking the reporter row. PostgreSQL therefore serializes concurrent submissions for one reporter without Redis or a distributed lock. This was verified against PostgreSQL 17: six OS processes submitting the identical observation at the same instant produced exactly one stored report, one reporter row, and five idempotent duplicate responses.

## Validation errors and throttling

Invalid submissions return HTTP 422:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "The submitted data was invalid.",
    "details": {
      "status": ["The selected status is invalid."]
    }
  }
}
```

Report creation is limited to 12 attempts per minute per anonymous token. The anonymous-session endpoint is limited to 20 requests per minute per request IP as an ephemeral network-level protection; IPs are not persisted. Limits are central configuration values. Exceeding a limit returns HTTP 429 with standard rate-limit headers.

## CORS

Local CORS permits `http://localhost:3000`, the required JSON headers, and `X-Anonymous-Reporter`. Credentials are disabled because identity is header-based rather than cookie-based. Production and preview Vercel origins must be explicitly comma-separated in `CORS_ALLOWED_ORIGINS`; wildcard origins are not used.
