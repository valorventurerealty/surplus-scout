# VVR AI Surplus document intake

VVR AI can turn a prior-year tax notice, TRIM notice, or property card into an approval-gated Surplus intake plan. The upload remains private and all extraction occurs through the configured backend Gemini provider. Uploaded content is treated as untrusted data, never as instructions.

## Workflow

In **VVR AI**, use a prompt such as `Create a Surplus case from this prior-year tax notice` and attach a supported PDF, DOCX, TXT, CSV, JPG, JPEG, or PNG file. VVR AI extracts candidate ownership, mailing-address, property, assessment, and tax-history facts with page, excerpt, confidence, and an `extracted` verification status.

The review screen separates four proposed records:

1. Property, including parcel, site address, legal description, and acreage.
2. Surplus contact, including a mailing address distinct from the property site.
3. Annual property tax history.
4. Surplus case and research tasks.

VVR's Florida vacant-land TRIM notices report acreage in the assessment row's **Units** column. That candidate is mapped to property acreage but remains extracted until the user approves it. Annual tax values are never copied to `properties.taxes`; that field remains an acquisition-cost component used by all-in calculations.

The extractor does not infer a surplus amount, foreclosure case, certificate, sale date, or claim deadline from a tax notice. Missing items remain blank and can create research tasks.

## Duplicate handling and approval

Before approval, the service checks exact state/county/parcel identity, normalized property address, owner name plus mailing address, existing parcel-linked Surplus cases, and SHA-256 document hashes. The reviewer can use a matching record or propose a new record. Exact property uniqueness constraints are rechecked during execution.

Approval is server-side. Permissions and input validation run again immediately before execution. The property/contact/case links, tax history, private document attachment, and non-duplicating research tasks execute inside one database transaction. Any required failure rolls back the complete write set. An attached intake cannot be approved again.

## Privacy and retention

Only users who can manage Surplus cases, properties, contacts, and private source documents can run this intake. Marketing cannot access Surplus contacts or claimant details. Tax-history values follow property-financial visibility. Original files are served only through an authorized controller; no public storage URL is created.

Unapproved files expire after `SURPLUS_INTAKE_EXPIRATION_HOURS` (24 by default). The existing Namecheap scheduler invokes `surplus:prune-intakes` daily. Approved source files are retained with their Surplus case.

## Namecheap deployment

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan migrate --force
php artisan test
php artisan optimize
```

The feature uses MySQL, private Laravel storage, scheduled cron, and synchronous Gemini extraction. It adds no Redis, Docker, Python service, npm package, or continuously running worker requirement.
