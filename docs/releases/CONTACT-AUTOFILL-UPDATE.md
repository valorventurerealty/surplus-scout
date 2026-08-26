# Contact document autofill update

This additive release extends the private, review-first Responses API intake used by Properties to Add Contact. It also backfills and indexes canonical contact email/phone values for duplicate detection.

## Namecheap deployment

1. Back up the MySQL database and private `.env`.
2. Upload and extract the cumulative update in `/home/valoljta/vvr-command-center`, never `public_html`.
3. Ensure the private `.env` contains the OpenAI settings from `.env.example`, including `CONTACT_INTAKE_EXPIRATION_HOURS=24`.
4. In cPanel PHP settings, use at least `upload_max_filesize=16M` and `post_max_size=16M`.
5. Run:

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

6. Verify `/up`, `/contacts/create`, fictional contact extraction, duplicate warnings, reviewed contact creation, restricted source download, `php artisan schedule:list`, and `storage/logs/laravel.log`.

No Composer dependency was added. If deployment stops after `artisan down`, run `php artisan up` once the application is safe to restore.
