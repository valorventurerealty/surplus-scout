# Projections module

The Projections workspace converts VVR's operating goals into auditable monthly and annual financial forecasts. It is available under **Management / Tools** to users authorized to view property financials. Only Owner, Partner, and Admin roles may create or update scenarios; only Owner and Admin may archive them.

## Model

Each scenario stores a name, status, fixed planning horizon, optional assigned contacts, default-scenario flag, and planning notes. Four operating categories are supported initially:

- Land Flips
- Property Flips
- Rental Income
- Surplus Recovery

Each category has one editable average net-profit assumption. Every scenario month stores an integer operating-unit goal. Calculated values are not stored: the server derives the projected profit pool from monthly units multiplied by the category assumption.

## Payment split

Every positive projected dollar follows the governed VVR split:

- 20% Valor Venture Realty
- 40% Assigned Contact 1
- 40% Assigned Contact 2

The two contacts are optional planning labels but must be different when both are assigned. Server calculations use integer cents, and the second 40% share receives any rounding remainder so the components always reconcile exactly to the projected pool.

## Supplied workbook scenario

`ProjectionScenarioSeeder` imports the monthly 2026–2030 plan from `VVR Full Projections (1).xlsx`. The import is idempotent and never overwrites a scenario that already has the same name. Its assumptions are:

- Land flip average net profit: $10,000
- Property flip average net profit: $40,000
- Rental monthly net per projected property: $1,200
- Surplus average net fee: $1,200

The supplied five-year projected profit pool reconciles to $2,871,200: $574,240 to VVR and $1,148,480 to each 40% contact share.

Legacy category-specific 50/20, 70/30, and SERP-side calculations in the reference sheet are intentionally not carried into the application because the governing instruction is that all projected pay follows the usual 20/40/40 split.

## Editing and controls

The edit screen recalculates its displayed totals immediately as assumptions or monthly units change. The submitted values are validated again and recalculated on the backend. Scenario horizons are locked after creation to prevent silent loss of monthly planning data; create another scenario for a different period.

All scenario, assumption, and monthly-entry writes use Laravel transactions, policies, validation, and audit logging. Scenario archival uses soft deletion. Setting a default scenario is transactional and leaves only one current default.
