# Property document autofill update

This additive release adds private document upload and reviewable property autofill to the existing Properties module. It does not add autonomous CRM actions and does not replace the future VVR AI milestone.

## Namecheap deployment

1. Back up the database and `.env`.
2. Upload and extract the update in `/home/valoljta/vvr-command-center`, not `public_html`.
3. Add `OPENAI_API_KEY` and the documented AI settings to the private `.env`.
4. Run:

```bash
cd /home/valoljta/vvr-command-center
php artisan down --retry=60
php artisan migrate --force
export PATH=/opt/alt/alt-nodejs22/root/usr/bin:$PATH
npm install --no-audit --no-fund
npm run build
cp -a public/build/. /home/valoljta/public_html/build/
php artisan optimize
php artisan up
```

5. Verify `/up`, `/login`, `/properties/create`, a fictional document extraction, reviewed property creation, private source download, `php artisan schedule:list`, and `storage/logs/laravel.log`.

If any command fails while maintenance mode is active, diagnose it and always run `php artisan up` when it is safe to restore service. No Composer dependency was added.
