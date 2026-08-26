# VVR AI Surplus CSV import

The VVR AI workspace accepts both county tax-deed surplus exports and the earlier Stannp-compatible mailing lists. Header matching is case-insensitive and ignores spaces and punctuation.

The preferred tax-deed format is:

`Sale Date`, `Tax Deed #`, `Cert #`, `Surplus Available`, `Property ID #`, `firstname`, `lastname`, `address1`, `city`, `State`, `country`, `postcode`.

The earlier Stannp format remains supported:

`firstname`, `lastname`, `address1`, `city`, `State`, `country`, `postcode`, `parcel_number`, `Surplus`.

Skip-trace exports may also include the owner's `Phone 1 number` and `Email 1`, plus repeating groups such as `RELATIVE 1: First Name`, `RELATIVE 1: Possible Type`, mailing fields, five phone/type pairs, and five emails. The importer supports up to ten numbered relative groups.

## Data mapping

- Rows with the same normalized first and last name map to one Contact with type **Surplus**. All of that person's parcel cases appear under the single contact.
- `Property ID #` or `parcel_number` maps to the Surplus case parcel identifier.
- `Surplus Available` or `Surplus` maps to the Surplus amount. The application calculates the projected VVR fee at the enforced 12% rate.
- `Sale Date`, `Tax Deed #`, and `Cert #` map to the case sale date, tax deed number, and certificate number.
- The CSV `State` value is the claimant's mailing state. It is never treated as the parcel's state.
- The CSV does not contain a property address. The workflow therefore does not create a Property record or copy the mailing address into a property field. It creates a research task for each new case.
- Skip-traced relatives are matched by email, phone, or normalized name and mailing address. New people become Surplus contacts and are linked to the matching case as Relative; the reported relationship, age, alternate phones, and emails are retained in internal notes.

## Workflow

The server parses and validates the structured CSV without sending it to Gemini. It limits imports to `SURPLUS_CSV_MAX_ROWS` (500 by default), stores the original privately, hashes the source, and shows every row before approval. The review screen requires confirmation of the parcel state and county, displays reusable contacts and existing parcel cases, shows invalid rows, and lets the user select the rows to execute.

After explicit approval, permissions and duplicates are rechecked. All selected rows execute inside one transaction. An existing exact-name owner contact is reused; otherwise the first selected row for that name creates the shared contact. Blank contact fields may be enriched, but populated CRM values are not silently overwritten. When rows for the same name contain different mailing addresses, the review warns the user and the first selected row supplies the new contact's address. A parcel or tax deed number that already has a case in the confirmed state and county is updated rather than duplicated: its surplus and projected 12% fee are refreshed, blank deed/certificate/sale-date fields are filled, and approved relatives are linked. Every new case starts in Research and gets one property-research task. The audit log stores the source hash and affected record IDs.

Case matching removes parcel punctuation and checks both the Surplus case parcel and its linked Property parcel. County values such as `Osceola` and `Osceola County` are equivalent. An exact parcel match is reused even when an older case has inconsistent county formatting. Older parcel-less cases may be matched only when the claimant resolves to exactly one plausible case; the importer does not guess between multiple cases.

If an earlier release already created duplicate cases, use the preview-first cleanup command:

```bash
php artisan surplus:merge-duplicate-cases
php artisan surplus:merge-duplicate-cases --execute
```

Only cases with the same state, normalized parcel, and claimant are eligible. Execution keeps the original case, transfers contact links, documents, AI import references, and tasks, preserves the newest financial values, archives redundant tasks, and soft-archives the duplicate case.

Only users who can create Contacts and Surplus cases and view Surplus financial data may use or approve this workflow.

Unapproved CSV plans expire after the configured Surplus intake period and the existing daily `surplus:prune-intakes` schedule removes their private files and database staging rows. Completed imports retain their source hash and private source for audit.

## Cleaning up imports created before contact grouping

Release v48 prevents new exact-name Surplus contact duplicates. Existing duplicates can be consolidated with the recoverable, audit-logged command below. It only considers active contacts whose type is Surplus and whose trimmed first and last names match case-insensitively.

```bash
php artisan contacts:merge-surplus-duplicates
php artisan contacts:merge-surplus-duplicates --execute
```

The first command is a read-only preview. Execution keeps the oldest contact, moves cases, tasks, documents, property ownership/associations, financial splits, negotiation sessions, deals, and intake history to it, and soft-archives the redundant contacts. The merge runs in one database transaction.
