# V116 Email Save Endpoint Hotfix

This update replaces the email workspace's ambiguous REST write URLs with dedicated plain-POST save endpoints that are compatible with the Namecheap/LiteSpeed deployment.

## Routes

- New draft: `POST /email/compose/save`
- Update draft: `POST /email/{outboundEmail}/save`
- Update signature: `POST /email/signatures/{signature}/save`

The existing route names are preserved, so the Blade forms generate the new URLs without controller or navigation changes. Generic resource `store` and `update` routes are excluded to prevent duplicate route names and method ambiguity.

The V114 validation handling is included. If draft creation fails validation, the user remains on the Compose page and sees the actual errors instead of being returned silently to the email index.

## Deployment

Extract this archive into `/home/valoljta/vvr-command-center`, preserving directories, then run:

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan route:list --name=email.store
php artisan route:list --name=email.update
php artisan route:list --name=email.signatures.update
php artisan optimize
```

The three write routes must display `POST` and paths ending in `/save`.
