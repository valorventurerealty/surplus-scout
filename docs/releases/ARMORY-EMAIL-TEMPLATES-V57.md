# V57 — Armory Email Templates

## Added

- Dedicated Email Templates tab inside Armory.
- Search and filtering by text, category, and status.
- Create, read, update, and soft-archive workflow.
- Subject and plain-text body preview with copy controls.
- CRM-oriented merge-field reference.
- Policy enforcement, auditing, factory, validation, and feature tests.

## Deployment

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
```

No Node build is required for this release because it only uses the application's existing Tailwind and Alpine assets.

## Scope boundary

This release manages email content only. It does not send emails or automatically insert CRM data into merge fields.
