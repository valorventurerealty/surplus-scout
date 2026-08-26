# V66 — Pre-Auction Migration Recovery

The initial V65 deployment reached MySQL's 64-character identifier limit while adding the Pre-Auction composite index. MySQL had already committed the main table, but Laravel correctly left the migration unrecorded.

V66 uses the explicit index name `pre_auction_status_user_auction_idx`. The migration detects the partially created main table, adds only the missing indexes, creates the missing contact association table, and then completes normally. It does not drop or overwrite the partial table.

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
```
