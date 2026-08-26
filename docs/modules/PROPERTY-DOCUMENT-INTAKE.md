# Property document intake and autofill

## Workflow

1. An authorized user opens VVR AI, enters a prompt, and optionally uploads one PDF, DOCX, TXT, CSV, JPG, JPEG, or PNG file.
2. Laravel validates the type and size, hashes the bytes, and stores the original file on the private `local` disk.
3. The backend sends supported content to the configured Gemini extraction model. The API key never reaches the browser.
4. The provider must return strict JSON containing candidate fields, confidence, source excerpt, page, missing fields, and warnings.
5. Application code rejects unsupported fields and deterministically normalizes state, numeric, currency, property-type, and wetlands values.
6. The application checks extracted parcel/address values for possible existing properties.
7. The VVR AI conversation displays candidate values, missing fields, source evidence, duplicate warnings, and an editable property proposal.
8. Nothing is written to `properties` until the user reviews/corrects the proposal and explicitly approves the Level 2 write.
9. Property creation, checklist initialization, source attachment, and conversation completion occur transactionally.

Model-extracted information is never marked verified. Required property validation, duplicate checks, policies, and financial restrictions run again during final submission.

## Storage and lifecycle

- Files live under `storage/app/private/property-intakes/{user_id}` through Laravel's `local` disk.
- `property_intake_files.sha256` supports duplicate-upload detection.
- An attached file retains its original name, MIME type, size, hash, extraction metadata, model, response ID, and token usage.
- Unattached ready/failed files expire after 24 hours by default.
- `properties:prune-intakes` runs daily through Laravel Scheduler and removes expired bytes and rows.
- There is no public storage URL. Downloads pass through authentication, property policy, relationship checks, and a private streamed response.
- Source files are restricted to Owner, Partner, Acquisition Manager, Disposition Manager, and Admin because they may contain financial/legal information.

## Prompt-injection boundary

Uploaded files are untrusted data. The extraction system message explicitly rejects instructions contained in documents. The model has no CRM tools in this workflow and cannot create records, send communications, access secrets, or bypass approval. Only allowlisted structured fields are accepted.

## Configuration

Set these only in the private production `.env`:

```dotenv
AI_PROVIDER=gemini
GEMINI_API_KEY=your-private-api-key
GEMINI_DEFAULT_MODEL=gemini-3.6-flash
GEMINI_EXTRACTION_MODEL=gemini-3.6-flash
GEMINI_REQUEST_TIMEOUT=90
GEMINI_MAX_RETRIES=2
AI_FILE_UPLOAD_LIMIT_KB=10240
PROPERTY_INTAKE_EXPIRATION_HOURS=24
CONTACT_INTAKE_EXPIRATION_HOURS=24
```

After changing configuration, run `php artisan optimize:clear && php artisan optimize`. Never place the API key in Blade, Alpine, JavaScript, Git, cPanel screenshots, or support messages.

The cPanel PHP `upload_max_filesize` and `post_max_size` values must be at least 10 MB and 11 MB respectively; 16 MB for both provides practical overhead.

## Failure behavior

- Missing/invalid key, timeout, rate limit, API failure, refusal, missing output, or invalid structured JSON returns a visible upload error.
- Failed extraction never creates a property.
- A failed/private source file remains only until its configured expiration for operational diagnosis, then the scheduler prunes it.
- An expired or foreign intake token cannot be attached through a crafted form submission.
- Duplicate document hashes already attached to a property are shown as warnings and exact parcel/address duplicates remain blocked by final validation.

## Tests

Standard tests mock the provider or Gemini HTTP response. Coverage includes extraction/prefill, no browser key exposure, strict structured requests, reviewed transactional attachment, permission denial, duplicate upload reuse, protected download, and prompt-injection boundaries. Standard tests make no live Gemini calls.

A factory is provided for test fixtures. No intake-file seeder is registered because fabricated private uploads must never be introduced into production or ordinary development data.
