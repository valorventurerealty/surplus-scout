# Properties core update

This additive release introduces the production core of the Properties module. It adds a normalized MySQL schema, model/factory/seeder, policy and role capabilities, validated transactional service, searchable responsive table, complete create/edit/detail screens, dashboard count, navigation, audits, tests, and module documentation.

## Production deployment on Namecheap Stellar

1. Back up the database and `/home/valoljta/vvr-command-center/.env`.
2. From `/home/valoljta/vvr-command-center`, run `php artisan down --retry=60`.
3. Extract the update into `/home/valoljta/vvr-command-center`, preserving its directory structure and overwriting matching application files. Do not extract the private source into `public_html`.
4. Run `php artisan migrate --force`.
5. Select Node 22 for the SSH session: `export PATH=/opt/alt/alt-nodejs22/root/usr/bin:$PATH`.
6. Run `npm install --no-audit --no-fund` and `npm run build`.
7. Copy the contents of `/home/valoljta/vvr-command-center/public/build/` into `/home/valoljta/public_html/build/`.
8. Run `php artisan optimize` and then `php artisan up`.
9. Verify `/up`, `/login`, `/dashboard`, `/properties`, property creation, financial visibility for an authorized role, and `storage/logs/laravel.log`.

No Composer package was added. Do not run `db:seed` in production for this release; `PropertySeeder` is restricted to local/testing environments.

## Rollback

Before production data is entered, code may be restored from the prior release and the migration rolled back with `php artisan migrate:rollback --step=1 --force`. Once property data exists, restore from the pre-release database backup instead of dropping the table blindly.
