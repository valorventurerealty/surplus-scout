# PreTax Auctions

## Purpose and boundary

PreTax Auctions is a separate sister department to Surplus Recovery. It tracks Florida properties already scheduled for tax deed auction where VVR is evaluating or acquiring the current owner's interest before the auction. Records use the `PAQ-YYYY-######` numbering sequence and never share Surplus pipeline stages or fee calculations. Existing internal `pre-auction` route names and database structures remain unchanged for backward compatibility.

The operational concept recorded by the workspace is: purchase and record title before the scheduled auction, document review of the non-redemption strategy, allow the independently scheduled auction to occur, and then conduct a separate human review of any potential surplus entitlement. The application does not redeem tax certificates, record deeds, determine legal eligibility, submit claims, or communicate externally.

## Workflow

The ordered pipeline is Research, Owner Located, Outreach, Negotiating, Purchase Agreement, Closing, Deed Recorded, Awaiting Auction, Auction Completed, Surplus Review, Claim Submitted, Paid, Closed, and Disqualified.

The list is sortable and filterable by stage, assignee, auction horizon, owner, property, and identifiers. Authorized managers can select individual files or every file on the current page and move up to 200 selected files to another pipeline stage. The bulk operation is validated and executed transactionally; it does not invent or backfill milestone dates. Auction date, Florida county, and parcel ID are required. Internal purchase, closing, and deed dates are validated against the auction date.

## Economics

Authorized financial users can record owner purchase price, closing costs, other costs, projected surplus, winning bid, generated surplus, and amount recovered. The server always recalculates:

```text
total acquisition cost = purchase price + closing costs + other costs
projected net = projected surplus - total acquisition cost
actual net = amount recovered - total acquisition cost
```

These are pre-auction department economics and are intentionally excluded from existing Property portfolio totals and Surplus Recovery fee totals.

## Entitlement controls

Entitlement status is Not Reviewed, Needs Counsel, Potentially Eligible, Eligible, or Ineligible. Any status beyond Not Reviewed requires a written review basis. The application records the reviewer and review date. Ownership or deed recording never automatically marks entitlement as verified or eligible.

## Permissions and audit

All active roles except Marketing may view the department. Owner, Partner, Acquisition Manager, Virtual Assistant, and Admin may create and update files. Only Owner and Admin may archive. Financial values are restricted to Owner, Partner, Acquisition Manager, and Admin. Private Drive links follow existing source-document permissions.

Case writes, bulk stage updates, and archives use Laravel policies, server validation, database transactions, normalized parcel IDs, duplicate tax-deed detection, audit logs, and soft deletion. Bulk controls are not rendered for users who cannot manage PreTax Auction files, and the server reauthorizes every selected file before writing.
