# VVR AI Surplus CSV import — v46

This cumulative release adds an approval-controlled Stannp CSV intake to VVR AI.

## Deployment

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
```

No new Composer or npm package is required.
