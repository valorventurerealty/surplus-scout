# V61 — Authoritative Surplus Merge Identifiers

For email linked to a Surplus case, `{{parcel_id}}` and `{{county}}` now always resolve from the Surplus case's Case Identifiers.

- Surplus context: use `surplus_cases.parcel_id` and `surplus_cases.county`.
- Property or Deal context: use the linked Property fields.
- A missing Surplus case identifier remains unresolved and blocks sending; the engine does not silently substitute a potentially different Property identifier.
- Merge-field descriptions now explain the context-specific source.

The regression test uses conflicting Property and Surplus identifiers and confirms that the Surplus values win.

No database migration or frontend build is required.
