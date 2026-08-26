# Document autofill removal

This update removes OpenAI-assisted upload and autofill from the Contacts and Properties modules.

## Behavior

- Contact and property creation remain fully available through their normal validated forms.
- Autofill upload panels and extraction-review panels are removed.
- Contact and property extraction endpoints are no longer registered and return `404`.
- OpenAI extraction services are no longer bound into the application container.
- Intake tokens are no longer accepted during contact or property creation.
- Existing CRM records are unchanged.
- Existing attached private source documents remain permission-protected and downloadable.
- Existing unattached temporary uploads continue to be removed by the scheduled pruning commands.
- Existing intake tables and nullable data are retained to avoid destructive production changes.

No OpenAI API key is required.

## Namecheap deployment

1. Back up the database and `/home/valoljta/vvr-command-center/.env`.
2. Upload and extract the update in `/home/valoljta/vvr-command-center`, not `public_html`.
3. Run:

```bash
cd /home/valoljta/vvr-command-center
php artisan down --retry=60
php artisan optimize:clear
export PATH=/opt/alt/alt-nodejs22/root/usr/bin:$PATH
npm install --no-audit --no-fund
npm run build
cp -a public/build/. /home/valoljta/public_html/build/
php artisan optimize
php artisan up
```

4. Verify `/up`, `/contacts/create`, and `/properties/create`.

No migration or Composer install is required. If deployment stops after maintenance mode begins, run `php artisan up` once the application is safe to restore.
