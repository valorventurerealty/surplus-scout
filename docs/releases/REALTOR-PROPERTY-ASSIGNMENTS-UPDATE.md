# Realtor and contact-property assignments update

This additive release adds the Realtor contact type and transactional many-to-many assignments between contacts and properties.

## Included

- `Realtor` contact type.
- `contact_property` table with unique contact/property pairs, relationship type, creator, and timestamps.
- Contact and Property Eloquent relationships.
- Multi-property assignment on contact create and edit.
- Associated properties on contact details.
- Associated contacts on property details.
- Separation between general associations and the existing authoritative owner field.
- Validation preventing duplicate, nonexistent, or archived-property assignments.
- Dedicated audit entries for assignment changes.
- Feature coverage for Realtor creation, attaching, detaching, reverse navigation, auditing, and archived-property rejection.

## Namecheap deployment

1. Back up the database and `/home/valoljta/vvr-command-center/.env`.
2. Upload and extract the cumulative update into `/home/valoljta/vvr-command-center`, not `public_html`.
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

4. Verify `/up`, Realtor creation, assigning multiple properties, removing an assignment, and navigation in both directions.

No Composer dependency is added. The migration is additive and does not change existing ownership or contact data.
