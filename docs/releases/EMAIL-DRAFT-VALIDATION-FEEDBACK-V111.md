# V111 Email Draft Validation Feedback

## Outcome

When an email draft cannot be saved, the compose screen now displays a prominent `Draft not saved` panel containing every validation error. Previously, Laravel returned the user to the compose screen with field errors that were easy to miss, making the action appear to navigate backward without saving.

## Changes

- Added an accessible validation summary above the compose form.
- Preserved the submitted form values after validation failure through Laravel's existing old-input behavior.
- Added missing validation feedback for the selected signature.
- Made the Save draft control an explicit submit button.

## Deployment

Extract the update into `/home/valoljta/vvr-command-center`, overwrite the included views, and run:

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan view:cache
php artisan optimize
```

No migration is required.
