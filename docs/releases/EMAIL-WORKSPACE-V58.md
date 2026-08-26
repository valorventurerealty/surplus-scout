# V58 — Email Workspace

This release adds a production outbound-email workspace using the verified Namecheap Private Email SMTP configuration.

## Included

- Private drafts and attachments
- Manual composition and active Armory template selection
- CRM contact selection and record context for contacts, properties, deals, and surplus cases
- Allowlisted merge fields
- Mark Lewis default signature
- Explicit review and send approval
- Database-queued delivery compatible with Stellar shared hosting
- Sent, failed, cancelled, and retry tracking
- Per-user visibility with Owner, Partner, and Admin oversight
- Rate limits, audit integration, and automated tests with no live SMTP calls

## Deploy

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
php artisan schedule:list
```

The migration creates the email tables and the approved default signature. Existing production SMTP `.env` values remain unchanged. No npm build is required for this release.
