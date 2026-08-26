# Armory Script Save Hardening — V83

## Purpose

This release hardens creation of new Armory scripts after production submissions were returning to the form without inserting a record.

## Behavior

- A title is the only business field that must be supplied manually.
- A missing category defaults to `Other`.
- A missing status defaults to `Draft`.
- A missing version defaults to `1.0`.
- Human-readable versions such as `Version 1` are accepted.
- Private uploads remain limited to PDF, DOC, DOCX, TXT, Markdown, and RTF files no larger than 10 MB.
- A rejected submission displays the exact validation messages in a prominent error summary.

Uploaded files remain outside `public_html`, are hash checked for duplicates, and are never executed.

## Deployment

No migration or frontend build is required.

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan optimize
```

## Verification

Create a new script using only a title, then confirm that the application opens the saved script page and that it appears in the Script Library.
