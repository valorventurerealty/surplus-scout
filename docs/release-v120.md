# V120 — Surplus Scout Osceola Clerk Phase 1

## Included

- Authenticated Osceola research dashboard, run button, live queued/running refresh, results, and history.
- HTTPS Clerk PDF retrieval with PDF signature and file-size validation.
- Private source-file retention with SHA-256 traceability.
- County-specific deterministic PDF parser and raw/normalized Parcel ID handling.
- Shared transactional duplicate, amount-change, and removal synchronization against `surplus_cases`.
- Dedicated run and amount history tables.
- Database queue job compatible with the existing Namecheap cron worker.
- Unit and feature coverage for parsing, normalization, duplicate behavior, amount history, removal detection, permissions, double execution, and failure protection.

## Not included

Property Appraiser automation, TRIM automation, AI classification, owner lookup, skip tracing, mailers, calls, and outreach remain disabled and out of scope.

## Required deployment order

See `docs/surplus-scout-osceola-phase-1.md`. Install `smalot/pdfparser`, migrate, optimize, confirm the routes, then run the first import from Surplus Scout.
