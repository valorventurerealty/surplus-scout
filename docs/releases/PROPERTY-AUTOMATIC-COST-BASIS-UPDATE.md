# Property automatic cost-basis update

Properties now store Attorney Fees, Realtor Fees, and Other Costs. All-in amount is calculated automatically:

```text
purchase price + taxes + attorney fees + realtor fees + other costs
```

Expected and actual profit are recalculated from this result on every save. Both the Property form and the secured Financials editor provide immediate Alpine.js previews, while backend services independently repeat the calculation in cents so browser tampering cannot alter authoritative values.

To avoid changing existing property economics during deployment, the migration places any positive difference between the existing All-in Amount and the existing Purchase Price plus Taxes into Other Costs.
