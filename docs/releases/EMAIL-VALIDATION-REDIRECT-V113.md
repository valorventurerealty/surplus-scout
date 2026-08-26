# V113 Email Validation Redirect Hotfix

## Outcome

Rejected new drafts return to `/email/create`, and rejected draft edits return to their edit page. Validation messages are no longer stranded on the Email index.

## Root cause

The email form submitted correctly, but Laravel's default validation redirect resolved to `/email` on the deployed shared-hosting session. The Email index did not display the validation error bag, so the failed save appeared to act like navigation and provided no explanation.

## Changes

- Added deterministic validation redirect destinations to `OutboundEmailRequest`.
- Added a reusable draft-validation summary to create, edit, and index pages.
- Retained explicit compose-form binding and all existing authorization, CSRF, recipient, attachment, and sender validation.

## Deployment

Extract into `/home/valoljta/vvr-command-center`, overwrite the included files, and run:

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan view:cache
php artisan optimize
```

No migration is required.
