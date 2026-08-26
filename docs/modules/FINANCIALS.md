# Financials workspace

## Current scope

The secured `/financials` workspace is the authoritative property-economics and payment-split interface.

It provides:

- Portfolio totals headed by Portfolio Value, followed by Total All-in, Expected Profit, Actual Sales, and Actual Profit. Portfolio Value, Total All-in, and Expected Profit include only properties in Owned, Actively Working, Marketing, or Under Contract status. Actual Sales and Actual Profit include those statuses plus Sold. Research, Bidding, and Archived are excluded from every Financials summary value.
- Per-property financial configuration.
- Deterministic expected and actual net-profit calculation.
- A fixed 20% VVR / 40% Contact 1 / 40% Contact 2 payment structure.
- Two assignable CRM contact recipients.
- Projected and actual distribution previews.
- Direct links between Financials and Property records.

Deals, closing-cost line items, payments, reconciliations, rental cash flow, and exports remain later increments and are not simulated.

## Authoritative calculations

```text
all-in amount = purchase price + taxes + attorney fees + realtor fees + other costs
net profit = sales price - all-in amount
distributable profit = maximum(net profit, 0)
VVR payment = 20% of distributable profit
Contact 1 payment = 40% of distributable profit
Contact 2 payment = remaining distributable profit
```

The second contact receives the rounding remainder so all three payments always add exactly to distributable profit. Negative profit is stored as a loss and produces zero distributions. Missing inputs produce missing results, never fabricated zero-profit claims.

Example for 120 Bayberry Road:

- All-in amount: $14,500.00
- Expected sales price: $22,000.00
- Expected net profit: $7,500.00
- VVR at 20%: $1,500.00
- Contact 1 at 40%: $3,000.00
- Contact 2 at 40%: $3,000.00

## Data model

`properties.all_in_amount` stores the automatically calculated complete cost basis. Expected and actual profit remain on the Property aggregate but are written only from deterministic application calculations.

Attorney fees, realtor fees, and other costs are stored as separate decimal components. All-in is read-only in the interface and recalculated server-side on every save. The migration preserves an existing all-in amount by placing any positive difference not already explained by purchase price and taxes into Other Costs.

`property_financial_splits` stores one split configuration per property, the fixed percentages, two optional contact IDs, and audit actors. Contact deletion nulls the applicable recipient without deleting the property or financial history.

## Permissions and audit

Only active Owner, Partner, Acquisition Manager, Disposition Manager, and Admin users can see or update Financials. Enforcement occurs in navigation, controllers, Form Request authorization, model policies, and result presentation.

Property and split changes use a database transaction. Existing Property and `PropertyFinancialSplit` audit hooks record changes. No development seeder is registered because production financial allocations must never be fabricated.
