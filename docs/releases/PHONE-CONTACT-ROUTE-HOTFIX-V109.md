# V109 Phone Contact Route Hotfix

## Outcome

Linking or changing the CRM contact on a Phone Calls record now works on Namecheap/LiteSpeed without returning HTTP 405.

## Change

The authenticated `phone-interactions.contact.update` route accepts both `POST` and `PATCH`. Both methods execute the existing `PhoneInteractionController::linkContact` action, including its authorization, validation, audit logging, and redirect behavior.

The Blade form retains Laravel's `PATCH` method override. Direct `POST` support is a shared-hosting compatibility fallback.

## Deployment

Extract the update into `/home/valoljta/vvr-command-center`, then run:

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan route:list --name=phone-interactions.contact.update
php artisan optimize
```

The route list should show `POST|PATCH` for `phone-calls/{phoneInteraction}/contact`.
