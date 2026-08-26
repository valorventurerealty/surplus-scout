# Google Calendar Integration — V92

## Delivered

- Owner/Admin OAuth connection and writable-calendar selection
- Encrypted access and refresh token storage
- Backend-only Google Calendar REST client
- Automatic queueing from manual and VVR AI auction creation paths
- Idempotent Google event creation and same-event updates
- Google event removal when a synchronized VVR auction is archived
- Per-event sync status, Google link, safe error, and manual retry
- Bulk queueing of existing upcoming unsynced events
- Bounded database-queue retries compatible with Namecheap Stellar
- OAuth state validation, least-privilege scopes, token revocation, auditing, and mocked tests

## Deployment

Preserve `.env`, upload the cumulative V92 archive over `/home/valoljta/vvr-command-center`, and run:

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
php artisan route:list --name=google-calendar
php artisan schedule:list
```

No Composer dependency or Node build is added by this release.

Add the Google Client ID and secret to the private `.env` only after deployment. Follow the secure terminal steps in the release handoff; never paste the secret into a browser-visible field, chat, Git repository, or `public_html`.

## Verification

1. Sign in as Owner or Admin.
2. Open Calendar and select **Google Calendar**.
3. Connect the authorized Google account.
4. Select the destination calendar.
5. Create a fictional future auction event.
6. Run the existing scheduled cron or wait for its next five-minute execution.
7. Confirm the event shows **Synced** and opens the matching Google event.
8. Edit the time and confirm the same Google event changes rather than duplicating.

## Rollback

Application rollback can restore V91. Do not run `migrate:rollback` after connections or sync metadata exist without a verified database backup. Disconnect Google Calendar first if authorization must be revoked. Google events already created remain in Google Calendar unless explicitly removed.
