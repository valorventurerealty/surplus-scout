# Armory Email Template Save Hardening — V86

## Purpose

V86 fixes new Armory email templates returning to the form without appearing in the library when optional metadata was omitted or a human-readable version label contained spaces.

## Behavior

- Missing category defaults to **Other**.
- Missing status defaults to **Draft**.
- Missing version defaults to **1.0**.
- Version labels such as `Version 1 - Approved` are accepted.
- Template names and subjects are trimmed before validation and storage.
- Validation failures are displayed together in a prominent error summary.
- The save control explicitly submits the form.
- Successful creation opens the saved template, and the template remains visible in the Email Template Library.

No database migration or frontend asset build is required.

## Deployment

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan optimize
```

## Verification

Create an email template, save it, then return to **Armory → Email Templates** and confirm the new template appears at the top of the library.
