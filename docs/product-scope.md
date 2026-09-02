# Product Scope

## First MVP goal

The first MVP will let Dhaka residents submit fast, structured electricity and household gas observations and view cautious, evidence-based status estimates for predefined localities. It will validate the core reporting and aggregation model before adding broader platform features.

## Included in the first MVP

### Location

- Dhaka as the initial city
- Predefined major areas
- Predefined dependent sub-areas or localities
- A remembered locality for faster returning-user reports
- IDs submitted to the API instead of arbitrary location names

### Reporting

- Anonymous reporting without mandatory authentication
- Electricity available, unavailable/loadshedding, and voltage-unstable observations
- Gas normal, low, very-low, and unavailable observations
- Structured relative time buckets
- Essentially no free-text input in the normal reporting flow

### Backend and data quality

- Server-authoritative report timestamps
- Server-side validation of locations, statuses, and time buckets
- Basic request throttling and duplicate-report prevention
- A simple, deterministic, explainable aggregation algorithm
- Support for contradictory observations
- Low, medium, and high confidence bands with evidence counts
- An explicit insufficient-recent-data state
- Separation between raw reports and rebuildable derived status

### Frontend

- Mobile-first reporting flow
- Searchable predefined major-area selection and dependent sub-area selection
- Public live-status dashboard
- Locality status/detail page
- Clear recent-report, recency, disagreement, and confidence information
- Accessible loading, empty, success, and error states

### Testing

- Backend validation tests for accepted and rejected payloads
- Tests for time-bucket interpretation
- Tests for duplicate and locality-parent validation
- Aggregation tests covering agreement, contradiction, staleness, and insufficient data
- Automated coverage of critical reporting flows

## Deferred from the first MVP

- Interactive Dhaka map (deferred pending verified canonical locality display centroids)
- Machine-learning prediction
- Mandatory authentication
- User reputation system
- Notifications
- Public comments
- Arbitrary free-text reports
- Advanced device fingerprinting
- Complex fraud scoring
- Historical backfill reporting
- Complex geospatial processing
- Full city-wide advanced analytics
- Native mobile applications

These capabilities are deferred to keep the first MVP focused and trustworthy; they are not permanently rejected. Each should be reconsidered after real usage, data quality, operational cost, and privacy implications can be evaluated.

## MVP guardrails

- One report must never be presented as certain locality-wide truth.
- Absence of reports must never be interpreted as utility availability.
- `OVER_2_HOURS` and `UNKNOWN` must preserve their uncertainty.
- Exact private residential coordinates are outside the reporting model.
- Historical statistics must distinguish observed or inferred coverage from unknown periods.
