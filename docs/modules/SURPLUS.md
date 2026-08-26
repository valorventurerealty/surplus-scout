# Surplus workspace

The Surplus workspace manages claimant recovery cases from research through payment. It is a dedicated operational aggregate linked to the existing Contacts, Properties, Tasks, audit, and VVR AI systems; it does not duplicate those records.

## Pipeline and records

The business-stage order is Research, Locate Owner, Mailer Sent, Contact, Agreement, Submit Claim, Approved, Paid, and Closed. Each case has an opaque UUID route token and a generated `SUR-YYYY-000001` case number. It can link one claimant contact, one property, one assigned user, and any number of tasks.

The case stores source and jurisdiction, parcel and foreclosure identifiers, sale and claim dates, agreement/submission/approval/payment milestones, private notes, and an HTTPS document folder. Claim economics include surplus amount, agreed fee percentage, server-calculated expected fee, recovered amount, and actual fee. The application never accepts expected fee as authoritative user input. The agreed percentage and recorded actual fee are capped at 12% in browser validation, Laravel validation, the transaction service, and VVR AI tool validation. Deployment migration `2026_08_27_000030` corrects any existing higher percentage to 12%, recalculates expected fees, and caps recorded actual fees against the recovered amount (or listed surplus when recovery is blank).

The create and edit forms show a live **VVR projected fee pay** estimate as the surplus amount or fee percentage changes. The VVR AI Surplus review also previews the 12% projected fee beside the confirmed surplus amount. These browser values are estimates only; the transaction service recalculates the authoritative expected fee when the case is saved.

Paid Surplus work is recognized outside the property portfolio. Moving a case to Paid records the paid date when one is not supplied. The Financials workspace counts only **Actual fee received** on cases with a paid date as VVR realized profit. `Recovered amount` remains claimant money and is displayed separately; it never increases portfolio value, property sales, or VVR profit. Paid cases missing an actual fee appear as reconciliation warnings.

Claimant contact is optional during Research and Locate Owner, then required for every later stage. The same state, county, and foreclosure case number cannot be used by two active cases.

## Bulk stage changes

Authorized users can select individual cases or all cases visible on the current Surplus page, then choose a new pipeline stage or enter a county and confirm one bulk change. The endpoint accepts no more than 200 distinct active case IDs, validates the selected operation and value, rechecks the update policy for every case, and applies every selected update in one database transaction. A failure rolls back the complete batch. Each changed case produces its normal audit entry, and moving cases to Paid fills a missing paid date without overwriting an existing one.

Bulk county entry is trimmed, whitespace-normalized, and stored without a redundant trailing “County” label. Before writing, the service checks selected and existing active cases for conflicting state/county/tax-deed or foreclosure identifiers. A conflict rejects the full batch instead of producing ambiguous case records.

## Permissions and privacy

Owner, Partner, Acquisition Manager, Virtual Assistant, and Admin may create and update cases. Disposition Manager and Read Only may view operational details. Marketing has no Surplus access. Only Owner, Partner, Acquisition Manager, and Admin can view or submit financial fields. Private document access follows the existing source-document permission. Only Owner and Admin may archive cases.

These boundaries apply in controllers, policies, validation, linked Tasks, navigation, and VVR AI. Users without Surplus access cannot discover case-linked tasks. Financial values and document URLs are excluded from rendered pages and AI results for unauthorized users.

## AI document intake

The VVR AI workspace accepts prior-year tax notices, TRIM notices, and property cards as supporting Surplus intake evidence. It extracts candidate owner, mailing address, property, acreage, assessment, and annual tax-history fields, then pauses for review and explicit approval. Florida vacant-land TRIM **Units** are treated as acreage per VVR's confirmed business rule. Annual tax values are stored in `property_tax_records`, never in the acquisition-cost `properties.taxes` field. The workflow does not infer a surplus amount from a tax notice.

Approved execution creates or links the property, Surplus contact, case, tax history, private source, and four non-duplicating research tasks in one transaction. See `docs/modules/VVR-AI-SURPLUS-DOCUMENT-INTAKE.md` for extraction, duplicates, privacy, retention, and deployment details.

## VVR AI tools

`search_surplus_cases` and `get_surplus_case` are Level 0 read tools. `update_surplus_case` is a Level 2 CRM write and always pauses for explicit approval. The executor rechecks the logged-in user's policy, allowlists and validates fields, checks duplicate case identifiers, applies financial/document boundaries, executes through the transaction service, and returns a direct record link. The model never writes to the database directly.

## Deployment

After extracting the cumulative release on Namecheap, run:

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
```

The module adds no new Composer or npm dependency and requires no Redis, Docker, daemon, or external service. Standard database-queue cron execution remains sufficient.
