# V115 Email Signature Plain-POST Hotfix

## Outcome

Email signature updates no longer return HTTP 405 on Namecheap/LiteSpeed.

## Root cause

The signature form relied on Laravel's `PUT` method override. The shared-hosting request path rejected the update before it reached `EmailSignatureController`.

## Changes

- The signature update route accepts both `POST` and `PUT`.
- The browser form sends a conventional `POST` with CSRF protection and no method override.
- The Save signature button is an explicit submit control.
- Existing authorization, validation, safe link rendering, default-signature behavior, and active status handling remain unchanged.
- Regression coverage now exercises the shared-hosting-compatible `POST` path.

## Deployment

Extract into `/home/valoljta/vvr-command-center`, overwrite the included files, and run:

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan route:list --name=email.signatures.update
php artisan view:cache
php artisan optimize
```

The route list should show `POST|PUT`. No migration is required.
