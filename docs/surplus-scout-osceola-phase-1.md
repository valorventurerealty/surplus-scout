# Surplus Scout — Osceola Clerk Phase 1

## Scope

This phase retrieves the current Osceola County Clerk Tax Deed Surplus Funds report, validates and parses it, normalizes identifiers and money, synchronizes the existing `surplus_cases` table, and preserves run and amount-change history. It does not perform Property Appraiser searches, TRIM retrieval, owner classification, skip tracing, mailers, calls, or outreach.

## Architecture

`OsceolaClerkSource` downloads the configured HTTPS Clerk PDF. `SafePdfTextExtractor` converts it to text using `smalot/pdfparser`, with a configured `pdftotext` binary as an optional fallback. `OsceolaPdfParser` validates the report structure and isolates county-specific parsing. `OsceolaRecordNormalizer` preserves the raw Parcel ID and creates the existing VVR normalized Parcel ID. `SurplusDuplicateService` resolves the Clerk logical key against the existing Surplus CRM. `SurplusImportService` applies all case writes in one database transaction. `ResearchRunService` and `RunOsceolaSurplusResearchJob` keep the shared-hosting workflow resumable and auditable.

The future county extension point is `CountySurplusSourceInterface` → county parser → normalizer → shared import engine.

## Data behavior

- Clerk key: `OSCEOLA|{NORMALIZED_PARCEL}|{CLERK_TAX_DEED}`.
- `parcel_id_raw` and the compatible existing `parcel_id` retain the government value; `normalized_parcel_id` is comparison-only.
- Existing `surplus_amount DECIMAL(14,2)` remains the single current surplus value. `surplus_amount_histories` preserves previous and new amounts with the run ID.
- New cases enter the existing `research` pipeline stage and receive `research_status=pending_owner_research`.
- Ambiguous but safely identifiable rows receive `research_status=manual_review` and a note.
- A missing row is marked `surplus_availability=no_longer_listed`; this does not mean claimed or paid.
- Removal detection only runs after complete download, extraction, validation, and parsing.

## PDF extraction requirement

Install the pure-PHP parser during deployment:

```bash
php -d memory_limit=512M composer.phar require smalot/pdfparser:^2.12.5 --update-no-dev --optimize-autoloader
```

Alternatively set `PDF_TO_TEXT_BINARY` to an executable absolute path for Poppler `pdftotext`. The application fails safely when neither extractor is available.

## Namecheap deployment

From `/home/valoljta/vvr-command-center` after uploading the update:

```bash
php artisan optimize:clear
php -d memory_limit=512M composer.phar require smalot/pdfparser:^2.12.5 --update-no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan route:list --name=surplus-scout.osceola
php artisan schedule:list
```

The existing five-minute cron and `queue:work --stop-when-empty --tries=3 --timeout=90` schedule process research jobs. The job itself uses one attempt and an 80-second timeout so a failed source cannot be partially retried inside a run.

## Testing

Standard tests use fake PDF text and mocked HTTP; they never call the live Clerk endpoint.

```bash
vendor/bin/phpunit --filter=Osceola
```

If the project includes Laravel's `artisan test` command, this is equivalent:

```bash
php artisan test --filter=Osceola
```

After deployment, open **Surplus Scout → Osceola Research**, run one import, wait for the next queue cron, and verify the run counts and Clerk report hash.

## Known Clerk structure and assumptions

The inspected report dated August 24, 2026 is an eight-page, text-based PDF. A Sale Date appears as a group heading and applies to the record lines beneath it. At least one Clerk Tax Deed value appears as `1662025` rather than the common `number-year` pattern. VVR preserves that exact value and flags it instead of inserting a guessed hyphen. Owner names are not required or imported in this phase. State is stored as `FL` because the existing VVR schema uses a two-character state column; the UI meaning remains Florida.
