# Deals workspace

The Deals workspace is the transaction system for acquisitions, dispositions, surplus recoveries, PreTax Auction acquisitions, and rentals. A deal is a separate aggregate from a property: the property status drives the visual Pipeline, while the deal status tracks the lifecycle of a specific transaction.

## Records and relationships

Each deal has a permanent UUID route token and a generated human-readable number in the form `VVR-YYYY-000001`. It can reference one property, one primary contact, one assigned user, and any number of additional contacts with explicit roles such as seller, buyer, investor, attorney, realtor, or title company. Tasks use Laravel's existing polymorphic association and can be assigned directly to a deal.

Deal statuses are Draft, Active, Under Contract, Due Diligence, Closing, Closed, and Cancelled. Deal classifications are Acquisition, Disposition, Surplus Recovery, PreTax Auction Acquisition, and Rental. Closing a deal requires an actual close date. Deal status changes do not automatically change property status in this milestone.

## Security

All active users may view general transaction records except department-restricted PreTax Auction deals. Owner, Partner, Acquisition Manager, Disposition Manager, and Admin may create or update deals. Only Owner and Admin may archive one. Financial amounts and private document links are enforced independently by policy and validation; Virtual Assistant, Marketing, and Read Only users cannot receive or submit those fields.

All creates and updates use the service layer, database transactions, model validation, audit actor fields, Laravel policies, CSRF protection, and non-sequential route tokens. Archived deals use soft deletion.

## Financial boundaries

Offer, contract, earnest-money, and revenue fields describe the transaction. They do not overwrite Property Financials, which remains authoritative for purchase cost, taxes, fees, all-in amount, sales price, profit, and payment splits.

## Deployment

Run `php artisan migrate --force` after extracting the release. Clear old framework caches before migration, then rebuild frontend assets and run `php artisan optimize`. No Redis, daemon worker, Docker service, or additional PHP extension is required.
