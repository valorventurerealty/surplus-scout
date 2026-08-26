# Beside Phone Activity Integration — V84

## Delivered

- Secure Beside-to-Zapier-to-VVR webhook
- Dedicated, searchable Phone Calls workspace
- Calls, leads, voicemails, messages, captures, and voice notes
- Summary, transcript, action items, duration, inbox, and recording metadata
- Exact normalized-phone matching without automatic contact creation
- Unmatched and conflicting review states
- Authorized manual contact linking
- Recent phone activity on contact records
- Idempotent provider-event handling and compact audit receipts
- Privacy filtering for Surplus and PreTax Auction contacts

## Deployment

Deploy `vvr-command-center-v84.zip`, preserve the production `.env`, add a 64-character `BESIDE_WEBHOOK_SECRET`, and run:

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
php artisan route:list --name=integrations.beside.events
php vendor/bin/phpunit --filter=BesideIntegrationTest
```

No Node build, queue worker, Redis service, or cron change is required.

## Rollback

Application rollback can restore the preceding archive. Preserve the `phone_interactions` table if activity has already arrived. The migration's `down()` removes that table and its stored transcripts, so do not run `migrate:rollback` after production data exists without a verified backup.

## Zapier

Follow `docs/beside-integration.md`. Start with the Beside **Call** trigger and one fictional call. Confirm HTTP 201, verify the Phone Calls record and contact match, and only then publish the Zap.
