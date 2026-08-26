# PreTax Auctions Classification — V75

## Outcome

PreTax Auctions is now a first-class operational classification throughout the VVR Command Center. It is available for SOP departments, Armory scripts, Armory email-template outreach, contacts, and deals. Tasks and outbound email can link to the department's authorized records. The navigation and primary acquisition screens use the same business-facing name.

## Compatibility

This release does not rename database tables, models, URLs, route names, or existing Pre-Auction acquisition records. The `/pre-auction` workspace and its integrations continue to work unchanged. No migration is required because the affected classifications are stored in existing string columns.

## Access control

The dedicated PreTax Auctions contact and deal types inherit the existing Pre-Auction department permissions. Users who cannot view Pre-Auction acquisitions cannot list, filter, open, create, export, or select these contacts and deals through adjacent workspaces. Related Tasks and Email drafts use the same policy checks. VVR AI CSV intake assigns newly created owners the dedicated PreTax Auctions contact type.

## Deployment

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan optimize
```

No database migration, Composer install, or npm build is required.
