# V123 — VVR Sales Copilot MVP

1. Back up the application files and database.
2. Extract this update into `/home/valoljta/vvr-command-center` and allow it to merge/overwrite the listed application files.
3. Run:

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=SalesCopilotSeeder --force
export PATH=/opt/alt/alt-nodejs22/root/usr/bin:$PATH
npm run build
php artisan optimize
php artisan route:list --name=sales-copilot
```

4. Open `https://valorventure.business/sales-copilot` while signed in.
5. Test “This sounds like a scam,” “I need to think about the 12%,” a legal statute question, and “Stop calling me.”

Rollback code by restoring the pre-V123 file backup. The migration down operation removes all Sales Copilot data, so do not roll back the database after live use without first exporting that data.
