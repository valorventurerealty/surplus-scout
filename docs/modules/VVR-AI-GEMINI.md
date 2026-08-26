# VVR AI — Gemini property intake

## Scope

This milestone restores property-document autofill using a provider-independent backend contract and a Gemini implementation. It is not an autonomous database agent. Gemini extracts candidate facts; Laravel owns authentication, private storage, normalization, duplicate detection, validation, approval, transactions, audit actors, and record creation.

## Data flow

1. An authorized user opens VVR AI, writes a business prompt, optionally uploads an allowed file, and explicitly acknowledges external processing.
2. Laravel validates the file, calculates its SHA-256 hash, and stores it on the private `local` disk.
3. Equivalent requests may reuse an existing structured extraction result. Attached document hashes are shown as possible duplicates before approval.
4. TXT, CSV, and DOCX text is extracted deterministically on the server. PDF and image content is sent as Gemini multimodal input.
5. Gemini must respond using the configured JSON schema. Document content is delimited as untrusted data and cannot change system rules.
6. Laravel validates field names and confidence values, normalizes supported values, and searches exact parcel and normalized-address duplicates.
7. The review screen shows source excerpts, pages, confidence, missing fields, warnings, and possible duplicates. Every model value is labeled `extracted`, not `verified`.
8. The user corrects the embedded Property proposal and checks the explicit approval box.
9. Property creation, checklist initialization, source attachment, conversation completion, and completion message execute in the property transaction.

The final Property request repeats permission, duplicate, enum, numeric, URL, and data-shape validation. Gemini never receives database credentials and never writes to MySQL.

## Permissions

Property intake requires both Property create authorization and private Property document access. Owner, Partner, Acquisition Manager, Disposition Manager, and Admin can use it. Virtual Assistant, Marketing, and Read Only roles cannot send or view source documents through this workflow.

## Configuration

Store the API key only in the server `.env`:

```dotenv
AI_PROVIDER=gemini
GEMINI_API_KEY=
GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1beta
GEMINI_DEFAULT_MODEL=gemini-3.6-flash
GEMINI_EXTRACTION_MODEL=gemini-3.6-flash
GEMINI_REQUEST_TIMEOUT=90
GEMINI_MAX_RETRIES=2
```

After changing it, run `php artisan optimize:clear` and then `php artisan optimize`. Never prefix the key with `VITE_`, put it in Blade, or call Gemini from browser JavaScript.

## Free-tier privacy

The interface warns users that files are transmitted to the configured Google Gemini API. Google states that free-tier content may be used to improve its products. Use fictional or explicitly approved non-sensitive documents until VVR adopts an approved processing arrangement or paid tier for real contracts, seller PII, and closing records.

## Tool registry

`VvrToolRegistry` defines names, descriptions, strict input schemas, allowed roles, risk levels, approval requirements, and enabled state. Read tools are Level 0. All CRM writes are Level 2 and require approval. Surplus tools are intentionally disabled because a Surplus Case source-of-truth module does not yet exist.

The registry is a security boundary and preparation for the conversational agent loop. This milestone does not allow a model to execute registered tools directly.

## Namecheap requirements

Gemini is accessed using Laravel's outbound HTTPS client, so no GPU, Python service, Redis, Docker, or persistent worker is required. PHP ZIP is needed only for DOCX text extraction. Existing intake-pruning scheduler entries remove expired unattached files.
