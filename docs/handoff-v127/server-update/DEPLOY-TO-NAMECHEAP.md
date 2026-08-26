# Namecheap update required before starting the external worker

Upload the contents of this `server-update` directory into:

`/home/valoljta/vvr-command-center`

The two Scout jobs are assigned to a dedicated database queue named `surplus-research`. This prevents the external computer from processing email, calendar, or other VVR jobs.

Run on Namecheap:

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php vendor/bin/phpunit --filter=SurplusResearchQueueIsolationTest
php artisan optimize
```

Do not start the external worker before applying this update. Once applied, Scout jobs will wait in the database until the external worker is available.
