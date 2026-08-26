# Pipeline workspace

## Purpose

Pipeline is the visual operating board for VVR properties. The existing `properties.status` field is its single source of truth, so a status changed from a property form immediately appears in Pipeline and a Pipeline move immediately updates the property.

## Stages

The board uses this fixed order:

1. Research
2. Bidding
3. Owned
4. Actively Working
5. Marketing
6. Under Contract
7. Sold
8. Archived

The board intentionally does not create a separate stage table. This prevents a property status and a Pipeline stage from disagreeing.

## Features

- Horizontally scrollable, responsive board with a column and property count for every status.
- Search by parcel, address, city, or county; state and property-type filters.
- Direct links from cards to the complete property record.
- Owner, parcel, location, type, and permission-controlled portfolio value on each card. The Portfolio Value total includes only Owned, Actively Working, Marketing, and Under Contract properties.
- Authorized stage movement backed by enum validation, Laravel policies, a database transaction, and the existing property audit log.
- Read-only presentation for roles that may view but not update properties.

## Permissions and financial privacy

All active authenticated roles may view Pipeline. Only roles allowed to update a property can move it. Portfolio value is calculated from `expected_sales_price` and is never loaded into the view for users without property-financial permission.

## Deployment

No Pipeline migration is required because the database already stores property status as a string. The new `under_contract` value is accepted by the application enum after this release. Clear Laravel caches after deployment so routes and compiled views use the new workspace.
