# Financial data synchronization

## Source of truth

The `properties` table is authoritative for property ownership economics:

- purchase price
- taxes
- attorney fees
- realtor fees
- other costs
- all-in amount
- expected and actual sales prices
- expected and actual profit

`all_in_amount`, `expected_profit`, and `actual_profit` are computed server-side. Browser calculations are previews only. `PropertyFinancialCalculator` is the single calculation path used by both the Property editor and the Financials workspace.

## Workspace behavior

- Properties, Financials, Pipeline, Dashboard, and Reports must read these values directly from the property record.
- A linked Deal displays live property financials separately from deal-specific offer, contract, earnest-money, and revenue fields. Those fields are not interchangeable and are never silently copied.
- A linked Armory negotiation plan follows the property's expected sales price and all-in amount by default. Users may disable **Keep financials synced** to preserve a custom scenario.
- Only roles with property-financial permission may receive or display these values.

## Transaction and audit behavior

Property recalculation and dependent Armory updates occur in the same database transaction. A failure rolls the operation back. Property and negotiation model changes continue through the existing audit-log system.

Future AI write tools must call `PropertyService` or `PropertyFinancialService`; they must not write calculated columns directly.
