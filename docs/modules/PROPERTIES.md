# Properties module

## Purpose

Properties is the source of truth for VVR parcel and acquisition research. It provides searchable, paginated property records without coupling the core aggregate to future deals, documents, comparable sales, or media implementations.

## Stored data

- Parcel ID plus deterministic normalized parcel ID.
- County plus deterministic normalized county, street address, city, state, ZIP, and normalized full address.
- Property type, operational status, acreage, zoning, flood zone, wetlands status, and road access.

The property status workflow is: Research, Bidding, Owned, Actively Working, Marketing, Under Contract, Sold, and Archived. This status field is also the single source of truth for the Pipeline workspace; no duplicate pipeline-stage value is maintained.

The original nullable latitude and longitude database columns are retained only for non-destructive schema compatibility. They are not exposed, accepted, or populated by the current application.
- Structured electricity, water, sewer, and gas research.
- Up to ten validated HTTP/HTTPS GIS links.
- An optional private HTTPS link to the property's Google Drive document folder.
- A four-item acquisition checklist for max bid, property card, acquisition deed, and quiet title/final judgment. Each item stores completion metadata and an optional private HTTPS evidence link.
- Separate private HTTPS links for the general Google Drive property folder and the completed closing documents.
- Optional owner contact relationship.
- Purchase price, ARV, wholesale price, and investor price.
- Taxes, attorney fees, realtor fees, other costs, expected sales price, actual sales price, expected profit, and actual profit.
- Legal description and research notes.
- Creator/updater IDs, timestamps, soft deletion, and lifecycle audit logs.

Pictures, comparable sales, and documents intentionally remain separate future aggregates. They should reference `properties.id` and use private storage or dedicated relational tables rather than JSON blobs on the property row.

OpenAI-assisted document autofill is disabled. Properties are created and maintained through the validated CRM form. Authorized users can store a Drive folder link for relevant documents, and historical private source files remain protected. Drive files are not copied into VVR; access is also governed by the sharing permissions configured in Google Drive.

## Duplicate protection

The application normalizes parcel punctuation/casing and county suffixes. A database unique constraint protects `(state, normalized_county, normalized_parcel_id)`. Form validation provides a readable error before the constraint is reached and also blocks exact normalized-address duplicates. Updates exclude the current property from both checks.

## Permissions

- Every active authenticated role may list and view properties.
- Owner, Partner, Acquisition Manager, Disposition Manager, Virtual Assistant, and Admin may create or update them.
- Marketing and Read Only may not mutate them.
- Only Owner, Partner, Acquisition Manager, Disposition Manager, and Admin may view or submit financial fields.
- Only those financial-access roles may view, submit, or open property document Drive links and download source intake documents.
- All property-management roles may update checklist completion status. Checklist evidence links use the same restricted source-document permission as other private property documents.
- Only Owner and Admin may archive properties.
- Inactive users are rejected by the policy before any property action.

Financial restrictions are enforced in backend validation as well as the interface. A crafted request cannot use VVR forms to change hidden financial fields.

All-in amount is server-calculated as purchase price plus taxes, attorney fees, realtor fees, and other costs. Users cannot override the result with a crafted all-in value. Expected and actual profit are recalculated from the resulting all-in amount on every financial save and may be negative to represent a loss. Payment allocations are configured in the secured Financials workspace. Property screens retain a clearly inactive Deal Financials placeholder for later detailed deal economics.

Properties expose their generally associated contacts through the `contact_property` relationship while retaining `owner_contact_id` as a distinct authoritative ownership link.

The Properties index displays two secured financial pairs: All-in Amount with Investor Price, and Expected Sales Price with Expected Profit. Purchase Price is still retained and visible within the secured property detail and Financials editor. Negative expected profit is visibly identified as a loss.

## Service boundary

`PropertyService` owns normalized writes and wraps create/update operations in database transactions. Controllers authorize, validate, and delegate. Future API, Livewire, automation, and AI tools must call the same policy and service boundary rather than writing directly to the model.

## Tests

Automated coverage includes guest protection, authorized creation, normalization, owner linking, audit logging, parcel duplicates, address duplicates, role restrictions, financial-field protection, archival, search, GIS-link safety, private Drive-link validation and permissions, and normalizer behavior.
