# Property checklist update

Properties now include a structured acquisition checklist for:

- Max bid
- Property card
- Acquisition deed
- Paid receipt
- Quiet title / final judgment

Each checklist item stores its completion status, completion time, completing user, and an optional HTTPS link to the completed document or supporting item. Links open safely in a new tab and follow the existing private source-document permission boundary.

Existing properties are backfilled by `PropertyChecklistSeeder`. New properties receive all checklist items transactionally through `PropertyService`. Updates use backend authorization, strict allowed checklist keys, HTTPS-only URL validation, row locking, and audit logging.
