# Standard Operating Procedures workspace

The SOPs workspace is VVR's controlled internal procedure library. It is distinct from Armory: Armory contains scripts and interactive negotiation playbooks, while SOPs contains authoritative operating instructions, ownership, version labels, and review schedules.

## Records and sources

Each SOP has a private UUID route token, title, department, status, version label, procedure owner, summary, written procedure, effective date, and next-review date. Statuses are Draft, Active, and Retired. Departments cover general operations, acquisitions, dispositions, surplus recovery, PreTax Auctions, research, marketing, financials, administration, technology, and compliance.

An SOP must have at least one usable source: written procedure text, a private uploaded file, or an HTTPS Drive link. Supported private attachments are PDF, DOC, DOCX, TXT, MD, and RTF up to 15 MB. Files are stored below `storage/app/private/sops`, never in the public web root. TXT and Markdown uploads populate searchable procedure text when no text was entered.

Uploads receive a SHA-256 digest. Duplicate files are rejected, including matches against archived SOPs. Replacing or removing an attachment updates the database transactionally and removes the superseded private file only after the database update succeeds.

## Permissions and auditing

Every active authenticated user can read SOPs and download their private attachments. Owner, Partner, and Admin can create and update SOPs. Only Owner and Admin can archive them. Public registration remains unavailable, downloads are policy-gated, and responses include `X-Content-Type-Options: nosniff`.

Creates, updates, and archives are audited. Procedure body text is excluded from generic audit payloads to avoid copying potentially sensitive operational content into the audit log; document-control metadata remains auditable.

VVR AI exposes `search_sops` and `get_sop` as Level 0 read-only tools. Laravel retrieves only bounded procedure text after authenticating and authorizing the user. AI cannot create, edit, retire, archive, or replace SOP files in this milestone.

## Namecheap deployment

No new Composer or npm dependency is required. After extracting the cumulative release:

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
```

The existing `storage/app/private` directory must remain writable by the hosting account. No public storage link is required for SOP attachments.
## SOP sequencing

Each SOP may optionally reference one next SOP. Authorized SOP managers assign the continuation from the create or edit form. The selected SOP appears in a dedicated continuation card at the bottom of the current procedure so users can move directly into the next approved process.

Self-references, circular chains, and archived targets are rejected. The relationship is optional, and leaving it blank marks the current procedure as the end of that sequence. If a linked SOP is archived later, normal soft-delete scoping prevents it from being presented as the next procedure.
