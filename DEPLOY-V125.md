# Deploy V125 - Osceola Owner Research

This ZIP is an additive update for the current VVR Command Center. It is not a full application archive.

## 1. Upload and extract

Upload the ZIP to `/home/valoljta/vvr-command-center` and extract it there. Confirm the archive's `app`, `config`, `database`, `docs`, `resources`, `routes`, and `tests` folders merge into the existing application.

## 2. Clear caches and migrate

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan migrate --force
```

The migration adds owner-research fields to existing `surplus_cases` and creates batch, attempt, and event audit tables. It does not alter or duplicate existing case identifiers.

## 3. Optional configuration

Defaults are production-ready for the 2026 workflow. Add these only when changing defaults:

```env
OSCEOLA_PROPERTY_APPRAISER_URL=https://search.property-appraiser.org
OSCEOLA_PRIMARY_TRIM_YEAR=2025
OSCEOLA_FALLBACK_TRIM_YEAR=2024
OSCEOLA_OWNER_RESEARCH_TIMEOUT=15
OSCEOLA_OWNER_RESEARCH_RETRIES=2
OSCEOLA_OWNER_RESEARCH_INTERVAL_MS=1500
OSCEOLA_TRIM_MAX_FILE_BYTES=10485760
```

The Property Appraiser URL is host-allowlisted in code and must remain the official HTTPS host.

## 4. Verify configuration and routes

```bash
php artisan route:list --name=surplus-scout.osceola.owner-research
php artisan tinker --execute='dump(config("surplus_research.owner_research"));'
php artisan schedule:list
```

The existing cron must continue running `queue:work --stop-when-empty --tries=3 --timeout=90` every five minutes.

## 5. Run automated tests

This project does not expose `php artisan test` on the server installation. Use PHPUnit directly:

```bash
php vendor/bin/phpunit --filter='OwnerNameAndClassificationTest|OsceolaTrimNoticeExtractorTest|OsceolaOwnerResearchWorkflowTest'
```

No test performs a live county request.

## 6. Test one case

1. Open **Surplus Scout -> Osceola**.
2. Confirm an existing Osceola Clerk case has a parcel and Pending Owner Research.
3. Click **Research Owner** on that case.
4. Run `php artisan queue:work --stop-when-empty --tries=3 --timeout=90` once, or wait for cron.
5. Open the owner-research batch.
6. Confirm the exact parcel, current owner, TRIM year, historical owner, classification, event timeline, and final status.
7. Open the linked Surplus case and confirm its ID/case number did not change and no duplicate case appeared.

## 7. Test a batch of ten

1. Return to **Surplus Scout -> Osceola**.
2. Click **Research Next 10**.
3. Let the database worker process the jobs sequentially.
4. Follow live progress on the batch page.
5. Confirm processed totals and the Ready, Business, Estate, Trust, Manual Review, and Error counts equal the case outcomes.

## 8. Rebuild caches

```bash
php artisan optimize
```

Do not proceed to Sunbiz, skip tracing, heir research, or outreach until one case and one batch have been reviewed successfully.
