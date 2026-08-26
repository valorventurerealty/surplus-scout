# V110 Phone Contact Plain-POST Hotfix

## Outcome

The Phone Calls contact-link form uses a conventional HTTP `POST` request that is compatible with Namecheap/LiteSpeed shared hosting.

## Changes

- Removed the `PATCH` method-override field from the contact-link form.
- Retained CSRF protection, authorization, validation, audit logging, and the existing controller action.
- Added an explicit `type="submit"` button.
- Corrected and expanded the contact option markup so attributes are parsed consistently by browsers.
- Kept the route compatible with both `POST` and `PATCH` requests.

## Deployment

Extract this update into `/home/valoljta/vvr-command-center`, overwriting the included files, then run:

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan view:cache
php artisan optimize
```

No migration is required.
