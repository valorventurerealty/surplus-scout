# VVR AI Surplus contact grouping — v48

This cumulative release groups Surplus CSV rows by exact first and last name. One contact is created or reused for each name, and every parcel's Surplus case is linked to that shared contact. The contact page then lists all associated cases and parcels without duplicate contact clutter.

If a name has multiple mailing addresses in the CSV, the approval review displays a warning and the first selected row supplies the address for a newly created contact.

## Deployment

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan optimize
```

No database migration or new dependency is required.
