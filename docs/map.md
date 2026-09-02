# Deferred Map Geodata Foundation

The interactive Dhaka map is deferred from the current MVP because no verified canonical locality centroids could be acquired from this execution environment. This is a geodata-availability constraint, not a reporting, projection, dashboard, or realtime architecture failure. No public map route, marker, map API, or map UI exists in this build.

`backend/scripts/acquire-dhaka-geo.php` is a development-only, resumable acquisition tool. It reads the canonical taxonomy and queries OpenStreetMap Nominatim sequentially, at one request per second, with three bounded contextual query variants. Successful lookups are checkpointed to `backend/database/data/dhaka-location-geo.json`; unsuccessful or ambiguous candidates are checkpointed separately in `dhaka-location-geo-review.json`.

The conservative Greater Dhaka validation bounds are 23.60–23.95 latitude and 90.25–90.55 longitude. A candidate must be within those bounds, identify Bangladesh, and have address/display context matching the canonical parent or locality. Coordinates are approximate locality display centroids only—not user or household locations, legal boundaries, utility feeders, transformers, gas networks, or outage footprints.

Nominatim is a one-time development/maintenance fallback, never a runtime dependency. The tool uses a descriptive User-Agent, sequential requests, persisted checkpoints, and no parallelism. Only `VERIFIED` rows will be suitable for future public map rendering; unresolved rows remain fully usable in reporting, search, and locality pages.

At the latest acquisition attempt, the execution environment could not complete direct Nominatim HTTPS requests. The resulting review artifact contains only unresolved checkpoints, no coordinates, and must never be treated as production map data. The canonical verified geo file is intentionally empty until successful network acquisition and validation can run.
