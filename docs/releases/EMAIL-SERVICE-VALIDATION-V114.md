# V114 Email Service Validation Hotfix

## Outcome

Validation errors raised while the email service parses recipients, resolves CRM context, checks sender configuration, or processes attachments now return to the correct Compose/Edit page with all non-file input preserved.

## Root cause

`OutboundEmailRequest` validation and its redirect were functioning, but later validation inside `OutboundEmailService` occurred after the FormRequest completed. Laravel therefore used the session's previous URL (`/email`), stranding the error bag on the Email index and making Save draft appear to navigate without doing anything.

## Changes

- The controller catches service-level `ValidationException` instances for create and update.
- New drafts return to `/email/create`; edits return to their own edit page.
- Error messages and non-file input are preserved.
- Added regression coverage for service-level recipient validation.
- No email is queued or sent by this handling.

## Deployment

Extract into `/home/valoljta/vvr-command-center`, overwrite the included files, and run:

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan view:cache
php artisan optimize
```

No migration is required.
