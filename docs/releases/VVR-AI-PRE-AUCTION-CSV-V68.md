# VVR AI Pre-Auction CSV — V68

## Deployment

1. Extract the cumulative V68 release over `/home/valoljta/vvr-command-center`.
2. Run `php artisan optimize:clear`.
3. Run `php artisan migrate --force`.
4. Enable the installed Node.js 22 path and run `npm install --no-audit --no-fund` followed by `npm run build`.
5. Run `php artisan view:cache`.
6. Run `php artisan optimize`.
7. Confirm the new routes with `php artisan route:list --name=vvr-ai.pre-auction-csv-imports`.

## Acceptance check

Open VVR AI, use **Import upcoming tax deed auctions**, upload the example CSV, and confirm the review shows one valid Osceola row for parcel `2125291900000d0090`. Approve it and verify one contact, one Pre-Auction file, and one research task are returned. No Property, Surplus case, or Calendar event should be created from this file.
