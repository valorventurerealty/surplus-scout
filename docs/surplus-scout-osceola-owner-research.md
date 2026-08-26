# Surplus Scout - Osceola Owner Research

## Scope

This release researches existing Osceola County Clerk Surplus cases. It never creates a new Surplus case, contacts anyone, skip traces, researches heirs, or determines legal entitlement.

## Architecture

`SurplusOwnerResearchBatchService` selects eligible existing cases and queues one database job per case. The existing Namecheap queue cron processes those jobs sequentially. `SurplusOwnerResearchService` coordinates the workflow while `CountyOwnerResearchProviderInterface` isolates county-specific access.

The Osceola implementation uses the public Property Appraiser endpoints that power the official search page:

- Exact parcel: `GET /api/v1/parcelmarket?$filter=strap eq '{normalized parcel}'`
- Available TRIM attachments: `GET /api/v1/attachment` filtered by exact parcel, year, and `tp eq 'TR'`
- TRIM PDF: `GET /Search/GetAttachment/{official attachment id}`
- Human traceability: `/Search/MainSearch?pin={normalized parcel}`

The returned `strap` must normalize to exactly the stored case parcel. Zero results become Parcel Not Found. Multiple results or any mismatch stop the case; name and address are never used as substitute parcel identifiers.

## TRIM behavior

The tested 2025 and 2024 notices are two-page PDFs. Their first page contains machine-readable parcel, site-address, owner, and mailing-address text. `OsceolaTrimNoticeExtractor` validates the document title, year, and exact parcel before isolating the owner block. Ambiguous layouts go to Manual Review; missing values are not invented.

The configured years are:

```env
OSCEOLA_PRIMARY_TRIM_YEAR=2025
OSCEOLA_FALLBACK_TRIM_YEAR=2024
```

If the primary owner differs from the current owner, the primary TRIM is selected. If it matches, the fallback is checked. If both match, the case becomes Owner Match Unresolved.

## Status routing

- Individual / Multiple Individuals -> Ready for Skip Trace
- Business -> Business Research Needed
- Estate -> Estate / Heir Research Needed
- Trust -> Trust Research Needed
- Government / Association or Unknown -> Manual Review
- Missing exact parcel -> Parcel Not Found
- Missing configured notices -> TRIM Notice Not Found
- Temporary source failure or blocking -> Property Appraiser Error

The result is only a potential claimant or research subject. It does not establish entitlement, heirship, beneficiary status, current residence, or guaranteed payment.

## Audit and data safety

Every execution creates a new `surplus_owner_research_attempts` row and ordered `surplus_owner_research_events`. Prior attempts are retained. Selected TRIM PDFs are stored on Laravel's private local disk, outside `public_html`, with a SHA-256 hash and official attachment reference.

The workflow gathers and validates county data before transactionally updating the existing case. Failed research changes the research status and diagnostic note but does not erase previously stored owner fields. No screenshot is produced by the shared-hosting HTTP adapter; `diagnostic_reference` is retained and the adapter contract permits a future Playwright service to add screenshots.

## Batch processing

- Research Next 10 queues the ten oldest Pending Owner Research cases.
- Research All Pending queues all Pending Owner Research cases as separate database jobs.
- Research Selected accepts Pending or explicitly retryable cases.
- Research Owner / Retry Research queues one selected existing case.

Only one active Osceola owner-research batch is permitted. Jobs are queued individually and processed sequentially, avoiding unsafe browser/API concurrency. Requests are paced, time-limited, and retried. CAPTCHA or HTTP blocking stops the case; VVR does not bypass access controls.

## County-site reliability

The county's field names and attachment endpoints are not a published contract and may change. The implementation validates every response and fails closed. Namecheap must be able to make outbound HTTPS requests to `search.property-appraiser.org`. If it cannot, a separately authorized relay/provider can implement `CountyOwnerResearchProviderInterface` without changing the CRM update layer.

## Tests

Standard tests mock public-record providers and make no live county requests:

```bash
php vendor/bin/phpunit --filter='OwnerNameAndClassificationTest|OsceolaTrimNoticeExtractorTest|OsceolaOwnerResearchWorkflowTest'
```

The tests cover punctuation and suffix comparison, owner classifications, deterministic TRIM extraction, primary/fallback decisions, unresolved matches, same-case updates, no duplicates, audit history, and failure preservation.
