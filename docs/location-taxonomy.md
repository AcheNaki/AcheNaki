# Dhaka Location Taxonomy

## Purpose

AcheNaki? uses a predefined locality taxonomy so residents can report utility conditions consistently without typing arbitrary place names. The canonical application dataset is `backend/database/data/dhaka-locations.json`; Laravel's `LocationSeeder` validates and imports it into `areas` and `sub_areas`.

This is a **curated application taxonomy grounded in available official/public geographic sources**. It is not government-certified, a legal boundary dataset, or a replacement for DNCC/DSCC ward records.

## Model

The user-facing hierarchy is intentionally limited to:

```text
Dhaka
└── Major Area
    └── Sub-area / Locality
```

Residents commonly identify places as Mirpur 10, Gulshan 1, North Badda, or Banasree Block C rather than by ward number. Official ward and planning material therefore grounds corporation assignment and locality existence, while the grouping is optimized for a fast, understandable reporting selector. It does not add coordinates, polygons, wards, postal codes, feeders, or transformer boundaries.

The current canonical file contains 55 major-area groupings and 334 selectable sub-areas: 29 areas / 188 sub-areas in DNCC and 26 areas / 146 sub-areas in DSCC. Counts describe the current curated dataset, not completeness or official status.

## Research method and sources

The initial expansion was reviewed against these source categories, in priority order:

1. [DNCC ward/locality information](https://dncc.gov.bd/pages/static-pages/6922ded3933eb65569e1da8e) and the [DNCC location and area page](https://dncc.gov.bd/site/page/c0b6953f-16d3-405b-85e9-dece13bb98de/%E0%A6%B2%E0%A7%8B%E0%A6%95%E0%A7%87%E0%A6%B6%E0%A6%A8-%E0%A6%93-%E0%A6%86%E0%A7%9F%E0%A6%A4%E0%A6%A8Accessed) for northern corporation coverage and ward-grounded locality names.
2. [DSCC official portal](https://dscc.gov.bd/) and its ward/councillor material, including [reserved-ward representative addresses](https://dscc.gov.bd/pages/static-pages/6922def2933eb65569e1e9f6), for southern corporation coverage.
3. A [DSCC official planning terms-of-reference document](https://dscc.gov.bd/sites/default/files/files/dscc.portal.gov.bd/notices/ab08816b_3668_46f9_8d1f_5fa452f13889/ToR_DSCC%2020-3-2019.pdf), which identifies DSCC thana/locality families used to cross-check broad coverage.
4. [RAJUK Detailed Area Plan material](https://rajuk.gov.bd/pages/static-pages/6922dfc5933eb65569e23e6d), the [DAP overview](https://rajuk.gov.bd/site/page/68c8d4af-f493-43de-a54c-b0dc83d56bff/), and [RAJUK-approved private residential projects](https://rajuk.gov.bd/pages/static-pages/6922ddb2933eb65569e15ec7) for planning context and residential-area grounding.
5. [Bangladesh Bureau of Statistics geographic/Enumeration Area material](https://file.portal.gov.bd/uploads/92dcb631-3884-4525-8746-c8a79e72f029/663/0b5/bca/6630b5bca5a81907820597.pdf) as a government geographic cross-check.

Sources were accessed on 2026-09-01. Official records frequently organize places by wards, thanas, projects, or service jurisdiction rather than by a single resident-facing hierarchy. Consequently, sources support the taxonomy's grounding but do not certify every grouping or boundary.

## Grouping decisions and ambiguity

- Combined parents such as `Khilkhet–Nikunja` and `Kalabagan–Green Road` avoid creating dozens of very small parents while keeping the selectable child locality granular.
- Mirpur and Pallabi remain separate practical parents. Mirpur-number localities stay under Mirpur; Pallabi, Kalshi, and nearby named localities stay under Pallabi.
- Rampura is assigned to DNCC because DNCC's official locality material places East/West Rampura, Ulon, and Hajipara in DNCC wards. Banasree remains a separate DSCC-facing practical grouping.
- Aftab Nagar is grouped under Badda for selector usability, but its edges and its relationship with Banasree/Rampura are commonly described inconsistently. This needs boundary review before any polygon or jurisdictional use.
- Rayerbazar is grouped with Lalmatia under DNCC while Hazaribagh is grouped with Lalbagh under DSCC. Their adjoining edges need authoritative spatial verification.
- Hatirjheel is grouped with Moghbazar under DSCC for locality selection. The broader Hatirjheel area touches multiple adjacent neighborhoods and must not be interpreted as a precise administrative polygon.
- Bashundhara Residential Area and Banasree both use labels such as `Block A`. These are intentionally valid only within their parent; `(area_id, slug)` is the stable sub-area identity.
- Northern and peripheral localities, especially Bosila/Washpur and Kamrangirchar/Kholamora edges, require further municipality-boundary review. Inclusion is for practical locality coverage, not a legal boundary assertion.

No alias table is introduced yet. Alternate spellings and names should be collected from real search behavior, reviewed, and added through a deliberate design that cannot create ambiguous report targets.

## Limitations

- The dataset has no verified polygons, coordinates, ward mapping, or utility-service boundaries.
- English spellings vary across official records; Bangla names remain absent until verified.
- A locality's city-corporation value applies to its current practical parent grouping and must not be used for legal or billing decisions.
- Coverage should be tested with Dhaka residents and local reviewers. Missing or confusing localities are expected to be corrected over time.
- The taxonomy does not claim that two nearby localities share electricity feeders or gas-pressure conditions.

## Change governance

Changes should cite a reliable source or documented local review, explain moves or merges, and include dataset validation tests. Once reports exist:

- Keep numeric database IDs and slugs stable.
- Prefer renaming display text over changing a slug.
- Deactivate superseded records rather than deleting them.
- Treat moving a sub-area to another parent as a data migration requiring impact review.
- Review corporation changes explicitly and record the evidence.
- Never duplicate the canonical list in PHP, frontend code, tests, or documentation.

The seeder matches canonical records by slug, updates them in place, and deactivates records no longer present. Repeated runs therefore preserve IDs and do not create duplicates.
