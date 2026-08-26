# VVR AI Pre-Auction CSV Intake

## Purpose

This workflow imports upcoming Florida tax deed auction lists into the separate Pre-Auction Tax Deed Acquisitions department. It never creates Surplus Recovery cases.

## Supported source columns

The importer accepts the supplied format and common normalized aliases for:

- `firstname`, `lastname`
- `address1`, `city`, `State`, `country`, `postcode`
- `Listing Type`
- `Assessor Market Value`
- `v` (the supplied export's auction-date column), `Auction Date`, or `Sale Date`
- `Parcel Number`
- `Appraiser Link`
- `County`
- `Owner 1 Name`
- `Property Details Link`

`Listing Type` must identify a Tax Deed auction. Counties must be enabled in the VVR auction county enum.

## Data boundaries

- Contact address fields are treated as owner mailing data only.
- The importer does not create a Property record without a verified property address.
- Assessor Market Value is research context, not projected surplus, purchase price, portfolio value, or acquisition cost.
- The importer does not create a Calendar event without an auction URL and verified property address.
- The `v` header is accepted only as the source-specific auction-date alias and is disclosed on the review screen.
- No CSV data is transmitted to Gemini. Parsing and normalization occur on the Laravel server.

## Duplicate detection and idempotency

Pre-Auction files match on Florida state, normalized county, and normalized parcel ID. Contacts match on normalized name and mailing address. Re-approval or re-import reuses the existing records. Research tasks use a stable parcel-based title and are created only when absent.

Existing Pre-Auction fields are not silently overwritten when populated. A conflicting auction date is displayed before approval and the existing date is preserved.

## Approval and transaction behavior

The review screen shows valid and invalid rows, contact matches, case matches, warnings, conflicts, proposed actions, source links, and row-selection controls. Approval is checked server-side. All selected CRM writes run in one database transaction; a failure rolls back contacts, cases, links, tasks, import state, messages, and audit metadata.

## Execution results

Approved rows can:

- create or reuse a Seller contact;
- create or update a Pre-Auction acquisition file;
- link the contact as the primary owner;
- save assessor market value and research-source links;
- create one high-priority research task per parcel;
- return direct links to affected contacts and Pre-Auction files.

## Shared-hosting operations

Uploads use Laravel's private `local` disk. Pending uploads expire with the existing `surplus:prune-intakes` scheduled command. Database queues, Redis, Python, Docker, and a continuously running worker are not required for this deterministic CSV workflow.
