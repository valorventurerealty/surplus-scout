# VVR AI Surplus case matching v55

This release prevents skip-trace imports from creating a second Surplus case when the parcel is already represented.

- Parcel identifiers are compared without punctuation.
- Both the case parcel and linked Property parcel are searched.
- `County` suffix and capitalization differences no longer prevent a match.
- Exact parcel matches take precedence over jurisdiction formatting.
- A conservative claimant fallback supports older parcel-less cases without guessing between multiple cases.
- Matched cases are updated and receive owner/relative contact links; they are not recreated.

The release also adds `surplus:merge-duplicate-cases`. Its default mode is read-only. With `--execute`, it merges only cases sharing state, normalized parcel, and claimant; retains the original case; transfers related records; and soft-archives the duplicate.
