# V126 - Fixed 12% Surplus Fee

This update makes the VVR surplus recovery fee an application invariant of 12%.

## What changes

- The fee percentage is displayed as a fixed, non-editable 12.00%.
- Expected fee is recalculated automatically whenever the surplus amount changes.
- Service and model safeguards override any attempted lower or different percentage.
- The migration sets every existing Surplus case to 12% and recalculates its expected fee.
- Actual fees remain limited to 12% of the recovered amount, or the listed surplus when no recovered amount exists.

For a surplus amount of `$5,315.97`, the expected fee is `$637.92`.

## Deploy on Namecheap

From `/home/valoljta/vvr-command-center` after uploading and extracting this update:

```bash
php artisan optimize:clear
php artisan migrate --force
php vendor/bin/phpunit --filter=SurplusFixedFeeTest
php artisan optimize
```

Then open an existing Surplus case and confirm Claim economics shows `Agreed fee 12.00%` and the recalculated expected fee.
