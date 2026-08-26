# V60 — Surplus Identifier Merge Fix

The Email merge engine now falls back to the Surplus case's Case Identifiers when a linked Property record exists but its parcel ID or county is blank.

Resolution order for `{{parcel_id}}` and `{{county}}`:

1. A non-blank value on the linked Property.
2. The value stored on the linked Surplus case.
3. Unresolved, which safely blocks delivery.

This release includes a regression test using a Surplus case with an empty linked Property identifier and a populated Case Identifiers section.

No migration or frontend build is required.
