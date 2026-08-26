# Surplus realized profit — v50

This cumulative release routes collected Surplus fees directly into Financials without adding Surplus cases to the property portfolio.

- Property actual profit remains separate.
- Surplus realized profit is the sum of Actual fee received for cases with a Paid date.
- Combined actual profit equals property actual profit plus Surplus realized profit.
- Recovered claimant money is tracked separately and never treated as VVR revenue.
- Paid cases missing an actual fee are flagged for reconciliation.
- Moving a case to Paid automatically records today's Paid date when it is blank.

## Deployment

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan optimize
```

No database migration or new dependency is required.
