# Financials split workspace update

This cumulative release promotes Financials from a milestone shell to a working secured module.

## Included

- Nullable all-in amount on properties.
- One audited financial split configuration per property.
- Fixed 20% VVR / 40% Contact 1 / 40% Contact 2 structure.
- Two independently assigned CRM contacts.
- Deterministic cent-based profit and distribution calculation.
- Zero distributions on a loss.
- Portfolio financial totals and property table.
- Property financial editor with live Alpine preview and backend-authoritative persistence.
- Expected and actual calculations.
- Policies, Form Request authorization, transactional service, factory, tests, and documentation.

## Namecheap deployment

1. Back up the database and `/home/valoljta/vvr-command-center/.env`.
2. Upload and extract the cumulative update into `/home/valoljta/vvr-command-center`, never `public_html`.
3. Run:

```bash
cd /home/valoljta/vvr-command-center
php artisan down --retry=60
php artisan optimize:clear
php artisan migrate --force
export PATH=/opt/alt/alt-nodejs22/root/usr/bin:$PATH
npm install --no-audit --no-fund
npm run build
cp -a public/build/. /home/valoljta/public_html/build/
php artisan optimize
php artisan up
```

4. Verify `/up`, `/financials`, the 120 Bayberry example, recipient assignment, loss behavior, Property details, and restricted-role access.

No Composer dependency is added. Both migrations are additive. Existing profit values remain unchanged until an authorized user saves that property's authoritative all-in and sales amounts.
