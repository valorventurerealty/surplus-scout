# Contact creation 500 repair

This corrective cumulative release restores contact database compatibility omitted from the v6 overlay archive.

The v8 correction also includes the current `User` model and `UserRole` enum and makes `ContactPolicy::viewSourceDocuments` depend on the established financial-access capability. This resolves the production exception `Call to undefined method App\Models\User::canViewContactSourceDocuments()` when redirecting to a newly created contact.

## Cause

The deployed contact request and model can use canonical `normalized_email` and `normalized_phone` values for duplicate detection. Authorized contact detail pages can also preserve historical private source-document links. A server that did not previously receive migrations `000006` through `000008` can therefore query a missing column or table and return HTTP 500.

## Repair

This archive includes the original ordered, Laravel-managed migrations for:

- Contact normalized email and phone columns and indexes.
- Backfilling canonical values for existing contacts.
- The private historical contact source-document table.

It also includes the matching Contact model, normalizer, controller, policy, and private-file components. Laravel records migrations in the `migrations` table, so already-completed migrations are skipped automatically.

## Deployment

```bash
cd /home/valoljta/vvr-command-center
php artisan down --retry=60
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
php artisan up
```

Then create a fictional contact and open its detail page. If the error persists, inspect the newest entry in `storage/logs/laravel.log`; do not enable public debug output in production.
