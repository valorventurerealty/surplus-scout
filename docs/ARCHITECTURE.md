# VVR Command Center architecture

## Architectural direction

VVR Command Center is a modular monolith. Laravel remains the single deployable application and transactional boundary while business capabilities are separated by domain. This is the best fit for Stellar today and preserves clean seams for queues, object storage, search, or independently scaled services later.

The browser layer uses Blade for server-rendered pages, Livewire for stateful application surfaces, AlpineJS for small local interactions, and Tailwind CSS for the design system. Controllers stay thin. Form Requests own input validation and authorization. Policies own record permissions. Domain services will own workflows. Jobs own slow or retryable work. Events represent committed business facts, and listeners invoke automations without coupling pipeline code to notifications or documents.

## Module boundaries

| Module | Owns | Integrates through |
|---|---|---|
| Identity | users, roles, sessions, permissions | policies and authenticated actor IDs |
| CRM | contacts, activities, communication history | contact IDs and domain events |
| Properties | parcels, research, comps, media, GIS links | property IDs and storage contracts |
| Leads | lead source/type, motivation, follow-up, timeline | contacts, properties, assignments |
| Deals | economics, offers, contracts, due diligence | leads, properties, pipeline events |
| Pipelines | stage definitions, transitions, history | guarded transition service and events |
| Tasks | recurrence, templates, reminders, assignments | polymorphic subject and jobs |
| Buyers | criteria and property matching | query/service contracts |
| Documents | templates, generated artifacts, signatures | private storage abstraction and jobs |
| Automations | trigger/action definitions and execution logs | queued event subscribers |
| AI Operations | conversations, memory, knowledge, tool approvals | permission-scoped application tools |
| Reporting | read-optimized metrics and exports | query objects and scheduled snapshots |

The delivered application implements Identity, the CRM contact aggregate, the centralized Tasks workspace, the core Property aggregate, Financials, the private Armory script library, audit logging, and the dashboard/navigation shell. Incomplete navigation entries remain inactive and clearly labelled; no fake module behavior is shipped.

## Security model

- Authentication uses Laravel's session guard, CSRF protection, session regeneration, inactive-account rejection, and keyed login throttling.
- Public registration is disabled. Owners/admins will provision accounts in Settings.
- `UserRole` is stored as a constrained enum cast. Policies are the authorization boundary for web, Livewire, API, and AI tools.
- Contacts, tasks, and properties are soft-deleted and every mutation writes an audit record with actor, changed values, IP, and user agent.
- Property financials are both hidden in server-rendered views and prohibited by backend validation for roles without financial access.
- Production secrets remain only in `.env`; the initial owner seed requires injected credentials and refuses unsafe production defaults.
- Documents will use a private disk and signed/authorized downloads. API keys will use Laravel encrypted casts.
- The future REST API will be versioned under `/api/v1`, authenticated with Sanctum, rate-limited, and backed by the same policies and services as the UI.
- AI read operations will inherit the acting user's permissions. Mutating tools will produce a typed action preview and require confirmation before dispatch, with request/result audit records.

## Data and scale

MySQL is the production source of truth. Foreign keys protect ownership links, composite indexes cover common filters, and pagination is mandatory for collection screens. Laravel filesystem, cache, queue, mail, and notification contracts prevent hosting-specific code. Stellar uses database-backed sessions/cache/queues and a five-minute cron-driven queue drain. A VPS/cloud deployment can switch environment variables to Redis, S3-compatible object storage, and persistent workers without changing domain code.

## Milestone roadmap

1. Identity, roles, shell, contacts, navigation, tests (implemented).
2. Properties and leads, including research data and lead timelines (core Properties delivered; media, comparable sales, documents, and Leads remain).
3. Acquisition/disposition/surplus pipelines and guarded transitions.
4. Calendar and the broader automation engine (Tasks, recurring work, and private reminders are delivered).
5. Buyers/investors, criteria matching, deals, and dispositions.
6. Private documents, templates, PDF generation, and signature adapter.
7. Reporting, marketing, activities, and communication adapters.
8. AI command center, RAG knowledge base, memory, permission-aware tools, and approval workflow.

Each milestone must ship migrations, models, factories, seeders, controllers or Livewire components, policies, validation, tests, and module documentation before the next starts.
