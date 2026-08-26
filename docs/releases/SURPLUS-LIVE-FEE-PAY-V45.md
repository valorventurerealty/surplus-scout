# Surplus live fee pay — v45

This cumulative release improves the Surplus financial workflow without changing the database schema.

## Changes

- The Surplus case form provides dedicated inputs for surplus amount and VVR fee percentage.
- A live **VVR projected fee pay** value updates immediately from `surplus amount × fee percentage`.
- The fee percentage remains limited to 12%.
- The VVR AI Surplus intake review previews the projected fee at 12% when a surplus amount is entered or extracted.
- The backend remains authoritative and recalculates the saved expected fee during the transaction.

## Deployment

Extract this cumulative release over the application directory, preserving the private `.env`, then run:

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan optimize
```

No migration or new Composer/npm package is required for this release.
