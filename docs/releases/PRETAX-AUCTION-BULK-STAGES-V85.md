# V85 — PreTax Auction Bulk Stages

## Delivered

- Select individual PreTax Auction files or every file on the current page.
- Choose a pipeline stage and move all selected files after an explicit confirmation.
- Limit each request to 200 distinct, active files.
- Reauthorize every selected file and update all files within one database transaction.
- Record the actor and an audit entry for every changed file.
- Hide bulk controls from read-only users and other roles without management permission.

Stage changes do not automatically populate deed, auction, claim, or payment dates. Those fields remain explicit business facts entered through the existing file form.

## Namecheap deployment

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan migrate --force
php -l app/Http/Requests/BulkUpdatePreAuctionStageRequest.php
php -l app/Http/Controllers/PreAuctionAcquisitionController.php
php -l app/Services/PreAuctionAcquisitionService.php
php artisan route:list --name=pre-auction.bulk-stage
if [ -f vendor/bin/phpunit ]; then php vendor/bin/phpunit --filter=PreAuctionBulkStageUpdateTest; fi
php artisan optimize
```

This release has no migration, dependency, queue, or cron changes.
