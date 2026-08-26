# VVR AI tax-deed CSV v51

VVR AI now accepts county tax-deed surplus CSV exports in addition to the existing Stannp format.

## Added mapping

- Sale Date to the Surplus case sale date
- Tax Deed # to the tax deed number
- Cert # to the certificate number
- Surplus Available to the Surplus amount
- Property ID # to the parcel identifier
- Claimant name and mailing fields to a reusable Surplus Contact

The workflow remains approval-based. It previews every row, calculates VVR's 12% projected fee, groups repeated claimant names into one Contact, checks for duplicate parcels and tax deed numbers, and transactionally creates the selected cases and research tasks. Claimant mailing addresses are not used as property addresses, and no Property records are created from this file alone.

## Deployment

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
```
