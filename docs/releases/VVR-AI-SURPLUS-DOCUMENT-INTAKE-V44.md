# VVR AI Surplus Document Intake — v44

This cumulative update adds approval-gated Surplus intake from prior-year tax notices and property cards. It introduces private hashed uploads, owner mailing-address fields, separate annual property tax history, duplicate review, transaction-safe property/contact/case linking, idempotent research tasks, claimant privacy enforcement, private downloads, and scheduled expired-upload cleanup.

TRIM vacant-land Units are proposed as acreage. Annual tax history remains completely separate from property acquisition-cost taxes and all-in calculations. Missing surplus amounts and legal case facts remain blank.

Database migration: `2026_08_28_000031_create_surplus_document_intake_tables.php`.

Deploy with `php artisan optimize:clear`, `php artisan migrate --force`, `php artisan test`, and `php artisan optimize` from `/home/valoljta/vvr-command-center`.
