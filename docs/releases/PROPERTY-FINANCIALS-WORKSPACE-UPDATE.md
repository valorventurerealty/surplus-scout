# Property financials and workspace update

This additive release extends property financials and introduces the secured Financials milestone workspace.

## Included

- Nullable property fields for taxes, expected sales price, actual sales price, expected profit, and actual profit.
- Financial validation and decimal casting.
- Negative profit support for losses.
- Property create, edit, and detail presentation.
- Inactive Deal Financials placeholders.
- Permission-protected `/financials` milestone workspace and navigation.
- Feature tests for persistence, privacy, losses, navigation, and workspace authorization.

## Namecheap deployment

1. Back up the database and `/home/valoljta/vvr-command-center/.env`.
2. Upload and extract the update into `/home/valoljta/vvr-command-center`, not `public_html`.
3. Run:

```bash
cd /home/valoljta/vvr-command-center
php artisan down --retry=60
php artisan migrate --force
export PATH=/opt/alt/alt-nodejs22/root/usr/bin:$PATH
npm install --no-audit --no-fund
npm run build
cp -a public/build/. /home/valoljta/public_html/build/
php artisan optimize:clear
php artisan optimize
php artisan up
```

4. Verify `/up`, an authorized Property create/edit/detail workflow, `/financials`, and restricted-role financial access.

No Composer dependency is added. The migration is additive and does not modify existing property financial values. If deployment stops after maintenance mode begins, run `php artisan up` once the application is safe to restore.
