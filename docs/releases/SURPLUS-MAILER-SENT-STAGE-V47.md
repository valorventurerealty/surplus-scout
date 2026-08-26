# Surplus Mailer Sent stage — v47

This cumulative release adds **Mailer Sent** to the Surplus pipeline between **Locate Owner** and **Contact**.

The stage is available in Surplus create/edit forms, filters, business-order sorting, and VVR AI Surplus tool validation. A claimant remains required because Mailer Sent occurs after Locate Owner.

## Deployment

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan optimize
```

No database migration or new dependency is required for this release.
