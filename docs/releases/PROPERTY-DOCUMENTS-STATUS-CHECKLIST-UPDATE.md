# Property documents, status, and checklist update

This release removes Paid Receipt from the property checklist and adds a dedicated private Closing Documents link alongside the general Google Drive property folder.

The property workflow now contains exactly these statuses: Research, Bidding, Owned, Actively Working, Marketing, Sold, and Archived. Existing `active` and `under_contract` records are migrated to `actively_working`; all other existing status values remain equivalent.

Both document links require HTTPS and use the existing private source-document permission. Hidden links are preserved when users without document permission update other property fields.
