# V102 — Projections Workspace

## Deployment

After deploying the V102 files:

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=ProjectionScenarioSeeder --force
php artisan optimize
```

Copy the current Vite build into `/home/valoljta/public_html/build` whenever the release package includes a new frontend manifest.

The dedicated seeder is safe to rerun. It imports the supplied VVR scenario only when a scenario with that exact name has never existed, including soft-deleted scenarios.
