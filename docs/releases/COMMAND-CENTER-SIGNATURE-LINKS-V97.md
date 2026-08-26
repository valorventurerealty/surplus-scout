# Command Center Signature Links — V97

Email Signatures now include the same guided **Insert a hyperlink** control used by Compose and Armory Email Templates. Signature managers can enter visible link text and an HTTP or HTTPS destination, then insert the link at the current cursor position.

Pasted bare HTTP and HTTPS URLs are also rendered as clickable links. Raw HTML is stripped, unsafe link schemes remain blocked, and the signature list displays the same governed HTML used at delivery time.

V97 is cumulative with the V96 recovery migration. Deploy the update and run:

```bash
php artisan migrate --force
php artisan optimize:clear
```

No additional database migration or frontend build is required beyond the included V96 recovery migration.
