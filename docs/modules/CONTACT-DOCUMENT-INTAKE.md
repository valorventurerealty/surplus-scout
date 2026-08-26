# Contact document intake and autofill

## Scope

The Add Contact screen accepts one source file and extracts one primary contact. Supported uploads are PDF, DOCX, TXT, CSV, JPG, JPEG, and PNG. Bulk CSV import is intentionally separate because bulk creation needs row-level validation, resumable jobs, per-row errors, and explicit duplicate resolution.

## Workflow

1. An authorized contact editor uploads a business card, seller document, contact sheet, or other supported file.
2. Laravel validates the file, calculates SHA-256, and stores it under `storage/app/private/contact-intakes/{user_id}`.
3. The backend sends the file to the configured OpenAI Responses API model with a strict contact extraction schema.
4. Only first name, last name, company, email, phone, type, and notes are accepted.
5. The review screen shows the candidate value, confidence, source excerpt/page, missing fields, warnings, and possible duplicates.
6. The existing Add Contact form is prefilled. The user must review required fields and submit it normally.
7. Email and phone are normalized deterministically and checked again for exact duplicates.
8. Contact creation and source-file attachment occur in one database transaction.

The model cannot create the contact, bypass policy, send communications, or choose an assignee/follow-up. Extracted values are candidates, never verified facts.

## Duplicate handling

- Email comparison uses trimmed lowercase canonical values.
- Phone comparison uses digits-only canonical values while preserving the user's display value.
- The review also warns about exact first-name, last-name, and company matches.
- Exact canonical email or phone duplicates are rejected during final validation, including crafted requests.
- Reusing a document hash already attached to a contact is rejected.

## Security and lifecycle

- The API key remains server-side in `.env`.
- Documents are untrusted data; document instructions cannot override the extraction schema or system prompt.
- No public file URL is generated.
- Downloads require authentication, relationship validation, and `ContactPolicy::viewSourceDocuments`.
- Source files are restricted to Owner, Partner, Acquisition Manager, Disposition Manager, and Admin because uploaded seller/contact files may include sensitive details.
- Unattached files expire after 24 hours by default and `contacts:prune-intakes` removes them daily.
- Attached files remain linked to the contact as intake provenance.

## Testing

Standard tests mock OpenAI. Coverage includes prefill, prompt-injection boundary, normalized duplicate prevention, duplicate warning, transactional attachment, Read Only denial, source download permissions, and expiration pruning. No standard test calls the live API.

A factory exists for test fixtures. No seeder creates fabricated private documents.
