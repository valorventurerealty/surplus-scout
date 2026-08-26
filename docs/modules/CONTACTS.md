# Contacts module

Contacts are shared relationship records for sellers, surplus claimants, PreTax Auctions owners, investors, buyers, builders, developers, agents, attorneys, vendors, and other counterparties.

## Record contract

Every contact requires first name, last name, type, and status. Company, normalized communication details, assignment, next follow-up, and notes are optional. Creator/updater IDs preserve accountability. Records are archived with soft deletion so downstream deal and communication references remain valid.

## Application flow

`ContactController` handles HTTP orchestration. `StoreContactRequest` and `UpdateContactRequest` provide validation plus policy authorization. `ContactPolicy` is the single permission boundary. `ContactService` owns transactional creation/update and source-file attachment. The `Contact` model owns casts, deterministic email/phone normalization, and relationships. Search covers name, company, email, and phone; type/status filters and indexed pagination keep list queries bounded.

The `Auditable` concern records lifecycle changes with actor, old/new values, IP address, and user agent. Audit records are append-only through normal application paths. A future audit screen must be restricted to Owner/Admin roles.

## Extension rules

- Phone and email canonicalization is owned by `ContactNormalizer`; communication integrations must reuse it.
- Lead-specific motivation, pipeline, property, and timeline fields belong in the Leads module, not on contacts.
- Buyer investment criteria belongs in the Buyers module and references a contact.
- Bulk import must reuse the same validation/service boundary and dispatch audit-safe jobs rather than bypassing models.

OpenAI-assisted document autofill is disabled. Contacts are created and maintained through the validated CRM form. Historical private source files, if any, remain protected and available to authorized users.

## Property assignments

Contacts can be assigned to multiple active properties through the `contact_property` relationship table. The assignment is separate from `properties.owner_contact_id`, which remains the authoritative owner relationship. This prevents Realtor, buyer, investor, builder, and other operational relationships from being mistaken for ownership.

The current assignment UI records the relationship type as `associated`. The schema stores a relationship type so a later milestone can introduce specialized roles without replacing the association model. Assignment changes run in the same database transaction as the contact write and create a dedicated audit-log event containing the previous and new property IDs.

`Realtor` is an available contact type in addition to the existing `Agent` type. `Surplus` classifies contacts involved in surplus-recovery outreach or claims. `PreTax Auctions` classifies owners and related contacts involved in the separate pre-auction acquisition department. These classifications inherit their department visibility rules; linking a contact to a case remains a separate, explicit relationship.
