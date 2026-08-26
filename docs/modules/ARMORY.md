# Armory workspace

## Current scope

Armory is VVR's authenticated internal library and interactive runner for scripts, talk tracks, and playbooks.

Users can browse, filter, search, sort, read, and download scripts. Script, category, version, status, source, and updated-date columns are sortable while preserving active filters. Authorized content managers can create scripts from a private file, pasted text, both, or metadata only when guided steps will be built afterward; update metadata and text; set category, status, and version; build guided steps; create response branches; and archive obsolete records. `PreTax Auctions` is available as a dedicated operational script category.

Armory also contains a working Negotiations area. Negotiation plans may be linked to a Property and buyer Contact, store the authoritative asking price, all-in amount, and current buyer offer, and generate a deterministic Core Price Ladder from 100% through 50% in 2.5% increments.

Supported private uploads are PDF, DOC, DOCX, TXT, Markdown, and RTF up to 10 MB. Files use generated server-side paths on Laravel's private `local` disk. Original names are metadata only and are used when an authorized user downloads a file. Uploaded files are never copied to `public_html`.

## Data and lifecycle

`armory_scripts` contains governed metadata, optional searchable plain text, private-storage metadata, SHA-256, audit actors, timestamps, and soft deletion. Status is one of Draft, Active, or Retired. The version label is intentionally human-controlled during this increment.

Identical uploaded bytes are detected by SHA-256 across active and archived records. Archive preserves the private file for audit and recovery; there is no permanent-delete interface.

TXT and Markdown content is copied into `content_text` when the author does not supply text. Other formats remain downloadable files until a future deterministic parsing increment. Script text is escaped on output and never rendered as HTML or executable Markdown.

## Permissions

Every active authenticated role may read and download Armory content. Owner, Partner, Acquisition Manager, Disposition Manager, Marketing, and Admin may create, edit, and archive scripts. Virtual Assistant and Read Only roles cannot mutate Armory.

Authorization is enforced by `ArmoryScriptPolicy`, Form Requests, controllers, and navigation actions. The model is audited; full script text is excluded from audit snapshots to avoid duplicating sensitive long-form content in the audit table.

## Interactive playbooks

Each script contains ordered guided steps. A step has words to say, optional private coaching guidance, and zero or more caller-response branches. Each branch can show a suggested reply, move to another step within the same script, or finish the session with an outcome. Guided sessions do not transition automatically between separate Armory scripts.

Steps without branches advance to the next sequence automatically. After the final step, the session completes. Sessions are stored in the database and can be resumed after navigation or logout. A session may be linked to a Contact and Property and records its user, current step, selected branches, notes, status, outcome, timestamps, and append-only event path.

Approved variables are `{{contact_name}}`, `{{property_address}}`, `{{user_name}}`, and `{{caller_name}}`. Replacement is deterministic server-side string substitution. Script content and replacement values remain escaped on output; no template expressions, HTML, JavaScript, or arbitrary code are evaluated.

All active authenticated users can run an active playbook. Guided Sessions includes a script selector so a new session can be launched without first opening a script. Draft playbooks may only be previewed by Armory managers. Users may resume and update only their own open sessions; Armory managers can review all recorded sessions. Playbook configuration still requires the existing Armory management permission.

Users may delete their own guided sessions; Armory managers may delete any session they can review. Deletion is a recoverable soft delete, is audit logged without duplicating session notes, and removes the session from normal queries immediately. `armory:prune-deleted-sessions` permanently purges records after `VVR_ARMORY_DELETED_SESSION_RETENTION_DAYS` (30 days by default). The scheduler runs this command daily at 4:30 AM.

Future increments may add immutable published revisions, analytics dashboards, call-transcript integrations, and explicit publishing approval. Existing sessions should remain pinned to their original revision when versioning is introduced.

## Negotiation calculations

The Core Price Ladder uses cent-based application calculations:

```text
ladder asking price = full asking price × ladder percentage
projected profit = ladder asking price − all-in amount
VVR projection = projected profit × 20%
Investor 1 projection = projected profit × 40%
Investor 2 projection = remaining projected profit
buyer offer percentage = buyer offer ÷ full asking price
counter offer = full asking price × user-selected ladder percentage
```

Investor 2 receives any cent-level rounding remainder so the three split projections add exactly to projected profit. Unlike recorded Financials distributions, Negotiation projections remain signed below break-even. Negative values are warnings about the proposed price and never create a payment or alter Property Financials.

The initial ladder is intentionally fixed at 100% to 50% in 2.5% steps. Percentages and split structure cannot be overridden by request input during this increment.

The user selects a counter percentage from the same fixed 100%–50% ladder. Armory calculates the exact counter price, projected profit, and complete split before the user responds externally. No percentage is selected automatically, and uncontrolled percentages outside the Core Price Ladder are rejected.
