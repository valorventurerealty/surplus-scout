# Release Notes

## V108 — Contact Follow-up Purpose

- Added an optional Follow-up purpose field alongside each contact's next follow-up date.
- Displayed the purpose on contact detail and directory views and included it in contact CSV exports.
- Required a date whenever a purpose is entered, retained the value during governed duplicate merges, and added feature coverage.

## V107 — Cumulative Calendar and Projections Recovery

- Restored Meeting as a supported Calendar event type after the Website Chats full build reverted the enum.
- Restored Google Calendar inbound synchronization services, settings, jobs, models, routes, and scheduler registration.
- Restored Projections roles, User permissions, policy, models, services, forms, views, migrations, tests, and annual totals.
- Preserved Website Chats configuration and routes alongside the later Calendar and Projections features.
- Preserved the compact two-column navigation and refreshed production assets.

## V106 — Route Merge Recovery

- Restored Projections and Google Calendar inbound-sync routes removed when the Website Chats release replaced the newer route file.
- Merged the Website Chats webhook and authenticated workspace routes into the current cumulative route set.
- Preserved the compact navigation and all existing CRM routes.

## V105 — Compact Navigation Merge Recovery

- Restored the compact two-column workspace launcher after the Website Chats update replaced it with the older single-column sidebar.
- Preserved Website Chats under Communication when that module's route is installed.
- Preserved Projections and all other permission-aware internal and external workspace destinations.
- Included refreshed production assets so compact-navigation styling is available after deployment.

## V104 — Annual Projection Totals

- Added live projected dollar totals to every month in the projection editor.
- Added a bottom-of-year total row showing category units, category value, and the complete annual projected profit pool.
- Added the same annual-total row to each saved projection's monthly-detail table.

## V103 — Projection Editor Hotfix

- Corrected the projection editor's category-map initialization so PHP arrays are converted to Laravel Collections before calling `mapWithKeys()`.
- Restored the scenario edit page without changing projection data or calculations.

## V102 — Projections Workspace

- Added a governed Projections workspace under Management / Tools.
- Added database-backed scenarios, monthly operating plans, average net-profit assumptions, optional assigned contacts, status, default-scenario selection, audit logging, and soft archival.
- Imported the supplied 2026–2030 land flip, property flip, rental, and surplus assumptions through an idempotent production seeder.
- Standardized every projected payment to 20% VVR / 40% Assigned Contact 1 / 40% Assigned Contact 2.
- Added live browser calculations while editing and authoritative server-side cent calculations after saving.
- Added annual, category, monthly, and five-year summaries with permission-aware financial access.
- Added policy, validation, factory, service, automated test, and deployment documentation coverage.

## V101 — Google Import PHP 8.4 Compatibility

- Replaced callable-string collection filters with single-argument closures in the Google event and attendee import paths.
- Restored compatibility with PHP 8.4, where `is_array()` rejects Laravel's additional collection-key argument.

## V100 — Google Booking Import

- Added an opt-in Google Calendar booking import that mirrors future Google-created events into VVR as Meeting records.
- Added incremental five-minute reconciliation through the existing database queue and Namecheap scheduler; no persistent worker or Redis is required.
- Added idempotent Google event keys so repeated imports and reschedules update one VVR meeting instead of creating duplicates.
- Added Google cancellation handling that archives the corresponding imported VVR meeting.
- Marked imported meetings as Google-managed and blocked local edits, deletion, and outbound re-synchronization loops.
- Added bounded attendee, organizer, meeting-link, end-time, source, sync-checkpoint, and error metadata.
- Added administrator controls to enable or disable booking import and queue an immediate import from Google Calendar settings.
- Added automated coverage for authorization, imports, reschedules, cancellations, loop prevention, expired checkpoints, and outbound/edit guards.

## V99 — Calendar Meetings

- Added Meeting as a first-class Calendar event type.
- Renamed the Calendar action from **+ Add auction** to **+ Add Event** and generalized create, edit, detail, and upcoming-event language.
- Added a required meeting title while keeping parcel, county, property address, event link, and max bid auction-specific or optional for meetings.
- Added meeting-safe Google Calendar synchronization without invented parcel or county values.
- Preserved auction duplicate protection, financial permissions, audit behavior, database queues, and Google idempotency.

## V98 — Compact Workspace Navigation

- Preserved the Daily Command, Communication, Revenue / Operations, and Management / Tools organization.
- Arranged the four workspace groups in a compact two-column launcher so active destinations fit in the sidebar without routine vertical scrolling.
- Reduced navigation row height while retaining readable labels, active-page highlighting, permission-aware links, dark mode, and safe external-link indicators.
- Retained vertical overflow as a fallback for unusually short screens and browser zoom settings.

## V97 — Clickable Signature Links

- Added the guided hyperlink control to Email Signature editing.
- Rendered labeled Markdown links and pasted bare URLs as clickable signature links.
- Reused the governed safe-email renderer so raw HTML is stripped and unsafe link schemes remain blocked.
- Updated signature previews to display the exact HTML used in delivered messages.

## V96 — Template Attachment Migration Recovery

- Replaced overlong MySQL-generated foreign-key identifiers with explicit short names.
- Made the V95 attachment migration safe to resume after the initial table was partially created.
- Preserved any existing attachment rows while completing missing columns and constraints.

## V95 — Reusable Template Links and Attachments

- Added the guided hyperlink control to Armory email template creation and editing.
- Added private reusable attachments to Armory email templates with governed upload, download, removal, type, count, and size controls.
- Automatically copies the selected template’s attachments into an outbound draft and synchronizes them when the selected template changes.
- Added clickable safe-HTML template previews while stripping raw HTML and unsafe link schemes.
- Added regression coverage for template attachment storage, hyperlink rendering, and draft attachment copying.

## V94 — Clickable Email Links and Attachment-Ready Composer

- Added a guided hyperlink insertion control to the Command Center email composer.
- Rendered HTTP/HTTPS Markdown links as clickable HTML in review and delivered email while blocking unsafe link schemes and raw HTML.
- Clarified attachment formats, limits, private storage behavior, and existing draft attachments.
- Added regression coverage for link rendering, unsafe-link rejection, and attachment controls.

## V93 — Command Center Navigation Reorganization

- Reorganized the sidebar into Daily Command, Communication, Revenue / Operations, and Management / Tools.
- Applied the requested operating order while preserving role-based visibility and existing destinations.
- Kept Mailers and Drive as secure external links with new-tab indicators.
- Removed the unrelated Coming Milestones block from the primary operating navigation.
- Added regression coverage for group labels and full Owner navigation order.

## V92 — Google Calendar Integration

- Added secure Owner/Admin OAuth connection and writable Google Calendar selection.
- Added encrypted token storage and a backend-only Google Calendar REST client.
- Queued idempotent create, update, and cancellation synchronization from the authoritative VVR Calendar service.
- Added per-event status, safe failures, Google links, manual retries, and bulk sync of existing upcoming events.
- Kept the integration Namecheap-compatible through the existing database queue and five-minute scheduler.
- Added least-privilege scopes, OAuth state validation, token revocation, bounded jobs, mocked tests, and deployment documentation.

## V91 — Sortable Armory Email Templates

- Added sortable Template, Category, Subject, Version, Status, and Updated columns.
- Preserved search, category, and status filters while changing sort order or page.
- Applied controlled business ordering for template categories and statuses.
- Allowlisted sort fields and directions and added regression coverage for ordering and injection rejection.

## V90 — Guided Armory Save and Continue 405 Fix

- Replaced every Playbook Builder referrer-based redirect with an explicit redirect to the builder screen.
- Changed all Playbook Builder and guided-session write forms to same-origin relative actions.
- Added explicit submit button types across step, branch, session, completion, abandonment, and deletion actions.
- Added regression coverage for exact builder redirects, guided-session redirects, and relative form actions.

## V89 — Shared-Hosting Email Template POST Preservation

- Changed new email-template submission to a same-origin relative URL so HTTP-to-HTTPS redirects cannot convert POST into GET.
- Forced the Save button to use POST and the dedicated save endpoint independently of form parsing.
- Added rendered-form regression coverage for the relative action and explicit button method.

## V88 — Dedicated Armory Email Template Save Route

- Added a dedicated POST-only email-template save endpoint ahead of all dynamic Armory routes.
- Explicitly bound the save button to the email-template form.
- Successful creation now returns to the library with the new template visible and a named confirmation banner.
- Added route, redirect, persistence, and rendered-form regression coverage.

## V87 — Armory Email Template Browser Submission Fix

- Defaulted new email templates to the Other category in the rendered form.
- Moved validation feedback to Laravel so browser-native required-field checks cannot silently block submission.
- Added a named, explicit save action and regression coverage for the rendered creation form.

## V86 — Armory Email Template Save Hardening

- Added safe defaults for omitted email-template category, status, and version metadata.
- Accepted human-readable version labels instead of silently rejecting spaces.
- Added a prominent validation summary and an explicit save submission control.
- Added regression coverage proving newly saved templates appear in the Email Template Library.

## V85 — PreTax Auction Bulk Stage Management

- Added page-level selection controls to the PreTax Auctions workspace.
- Authorized users can move up to 200 selected files to a new stage in one transactional action.
- Every updated file is permission-checked and recorded in the audit log.

## V84 — Beside Phone Activity Integration

- Adds a signed, rate-limited Zapier webhook for Beside calls, leads, voicemails, messages, captures, and voice notes.
- Stores transcripts, summaries, recordings, and action items in a dedicated Phone Calls workspace.
- Matches exactly one existing CRM contact by normalized phone number; unmatched and ambiguous calls require review.
- Adds manual contact linking, contact-page phone history, idempotent event handling, compact audit receipts, privacy filtering, and automated tests.

## V83 — Armory Script Save Hardening

- Added safe defaults for omitted script category, status, and version metadata.
- Accepted human-readable version labels such as `Version 1` instead of rejecting spaces silently.
- Validated private source uploads by approved file extension, avoiding unreliable shared-host MIME detection while retaining private storage and size limits.
- Displayed every validation error in a prominent summary and made the save button an explicit form submission control.
- Added regression coverage for minimal script creation and human-readable version labels.

## V82 — Armory Metadata-Only Script Fix

- Allowed new Armory scripts to be saved before script text, a source file, or guided steps are added.
- Clarified that the private file is optional and guided steps may be built after creation.
- Added a prominent validation-error summary and changed the create action label to **Save script**.
- Added feature coverage for the metadata-first guided-script workflow.

## V81 — Armory Stage Transition Removal

- Removed the Armory default next-stage and destination-step feature at the owner's request.
- Restored guided sessions to single-script execution while retaining within-script step branches.
- Removed transition routes, controls, runtime behavior, model relationships, and transition-only events.
- Added a cleanup migration that drops the deployed cross-stage configuration columns without deleting scripts or sessions.

## V80 — Armory Next-Stage Redirect Hotfix

- Replaced the next-stage save action's referrer-based success and validation redirects with explicit redirects to the Interactive Playbook Builder.
- Prevented successful POST requests from landing on the write-only endpoint as GET and producing a 405 response.
- Added regression coverage for the exact post-save redirect destination.

## V79 — Armory Next-Stage Save Hotfix

- Fixed the Interactive Playbook Builder's 405 response by using a native POST submission for the next-stage form.
- Retained PATCH compatibility while preserving CSRF, authorization, and target-stage/step validation.
- Added regression coverage for saving a selected next stage and starting step through the browser's request method.

## V78 — SOP Sequencing

- Added an assignable next-SOP relationship to create ordered operating-procedure workflows.
- Added a bottom-of-SOP continuation card with a direct link to the assigned procedure.
- Added server-side protection against self-references, circular chains, and archived targets.
- Added focused feature coverage for assignment, navigation, and invalid targets.

## V77 — Armory Stage Step Routing

- Added optional exact-step selection for default and response-based cross-stage guided-session transitions.
- Added server-side destination-stage/step ownership validation and transactional runtime revalidation.
- Added destination-step details to guided-session transition history.
- Added feature coverage for default routing, response routing, and mismatched-stage rejection.

## V76 — Armory Multi-Stage Guided Sessions

- Added default next-stage transitions between interactive Armory scripts so a session can continue automatically after the final unbranched step.
- Added per-response next-stage overrides alongside existing within-stage next-step branches, with validation preventing both targets from being selected together.
- Preserved the original starting stage while tracking the current stage, contact, property, notes, and complete session path across transitions.
- Added active-stage checks for non-manager users, transactional transitions, stage-transition history, and focused automated coverage.

## V75 — PreTax Auctions Classification

- Added **PreTax Auctions** as a controlled SOP department, Armory script category, contact type, and dedicated deal classification, plus **PreTax Auction Outreach** for Armory email templates.
- Updated the authenticated workspace labels to use the PreTax Auctions department name while preserving all existing `pre-auction` routes and stored acquisition records.
- Applied existing Pre-Auction permissions to the new contact and deal classifications so restricted users cannot discover them through Contacts, Deals, Tasks, exports, or Email recipient selection.
- Added PreTax Auction acquisition files as an authorized Email related-record context, using the file's parcel ID, county, case number, and owner for merge fields.
- New VVR AI PreTax Auction CSV contacts now receive the dedicated contact classification instead of the generic Seller type.

## V74 — Bulk County Control Hotfix

- Connected the Surplus bulk stage and county inputs to reactive Alpine state so their action buttons enable immediately after a valid selection is entered.

## V73 — Bulk Surplus County Updates

- Extended Surplus bulk selection so authorized users can assign a validated county to multiple selected cases in one audited transaction.
- County changes remove a redundant “County” suffix and reject identifier conflicts before any selected case is changed.

## V72 — Mailers Workspace Shortcut

- Added a secure Mailers item to authenticated workspace navigation that opens VVR's configured Stannp application in a new browser tab.

## V71 — Completed Task Default Visibility

- Completed tasks are now excluded from the default Tasks list and remain available through the explicit Completed status filter and a direct “View completed tasks” link.

## V70 — Bulk Task Status Updates

- Added page-level task selection, select-all, clear-selection, and bulk status changes to the Tasks workspace.
- Bulk changes validate selected tasks and status, re-check each task policy, execute atomically, retain per-task audit history, maintain completion timestamps, and advance recurring tasks through the existing recurrence workflow.

## V69 — Bulk Surplus Stage Updates

- Added page-level case selection, select-all, clear-selection, and a bulk stage control to the Surplus workspace.
- Bulk changes validate the stage and selected cases, re-check update permission for every case, execute in one database transaction, preserve per-case audit logs, and automatically record the paid date when moving a case to Paid.

## V68 — VVR AI Pre-Auction CSV Intake

- Added private, deterministic Pre-Auction CSV mapping with duplicate detection, review and approval, transactional contact/case execution, source links, assessor context, idempotent research tasks, audit logging, and no invented Property or Calendar data.

## V67 — Pre-Auction View Hotfix

- Fixed the Pre-Auction list-page Blade compilation error that caused an HTTP 500 response when rendering the property fallback column.

## V66 — Pre-Auction Migration Recovery

- Shortened the Pre-Auction composite index name and made the migration safely resumable after MySQL partially creates the main table.

## V65 — Pre-Auction Tax Deed Acquisitions

- Added a separate Florida pre-auction acquisition department with its own pipeline, auction and deed controls, entitlement review, financial calculations, contacts, properties, tasks, policies, tests, and documentation.

## V64 — Armory Sorting and Guided Session Management

- Added sortable Armory script columns, a new-session launcher on Guided Sessions, and audited recoverable deletion for guided sessions.

## V63 — Recoverable Email Draft Deletion

- Added author-only soft deletion for unsent drafts and scheduled permanent pruning of expired drafts and private attachments.

## V62 — Email Related Record Selector

- Added a server-validated Property, Deal, and Surplus case selector to email Compose/Edit so users can explicitly supply the correct merge-field context.

## V61 — Authoritative Surplus Merge Identifiers

- Made Surplus Case Identifiers authoritative for `{{parcel_id}}` and `{{county}}` in Surplus-linked email, even when linked Property values differ.

## V60 — Surplus Identifier Merge Fix

- Fixed `{{parcel_id}}` and `{{county}}` so populated Surplus Case Identifiers are used when the linked Property fields are blank.

## V59 — Email Merge Fields

- Added a centralized allowlist and click-to-insert merge-field reference to the Email composer; updated Armory guidance for the live sending workflow.

## V58 — Email Workspace

- Added private drafts, CRM-linked composition, Armory templates, attachments, signatures, explicit send approval, queued SMTP delivery, status tracking, policies, tests, and Stellar deployment documentation.

## [Unreleased](https://github.com/laravel/laravel/compare/v12.12.2...12.x)

## [v12.12.2](https://github.com/laravel/laravel/compare/v12.12.1...v12.12.2) - 2026-03-14

* [12.x] Add `APP_NAME` fallback in Slack log channel username by [@hamedelasma](https://github.com/hamedelasma) in https://github.com/laravel/laravel/pull/6762

## [v12.12.1](https://github.com/laravel/laravel/compare/v12.12.0...v12.12.1) - 2026-03-10

* [12.x] Makes imports consistent by [@nunomaduro](https://github.com/nunomaduro) in https://github.com/laravel/laravel/pull/6760

## [v12.12.0](https://github.com/laravel/laravel/compare/v12.11.2...v12.12.0) - 2026-03-09

* Update phpunit version to ^11.5.50 to address CVE by [@PerryvanderMeer](https://github.com/PerryvanderMeer) in https://github.com/laravel/laravel/pull/6746
* [12.x] Add `APP_NAME` fallback in mail config by [@apoorvdarshan](https://github.com/apoorvdarshan) in https://github.com/laravel/laravel/pull/6755
* [12.x] Neutralize DB_URL in default phpunit.xml by [@Husseinadq](https://github.com/Husseinadq) in https://github.com/laravel/laravel/pull/6761

## [v12.11.2](https://github.com/laravel/laravel/compare/v12.11.1...v12.11.2) - 2026-01-19

* [12.x] Update composer dev script to ensure no timeout by [@jackbayliss](https://github.com/jackbayliss) in https://github.com/laravel/laravel/pull/6735
* [12.x] Update jobs/cache migrations by [@jackbayliss](https://github.com/jackbayliss) in https://github.com/laravel/laravel/pull/6736
* [12.x] Remove failed jobs indexes by [@jackbayliss](https://github.com/jackbayliss) in https://github.com/laravel/laravel/pull/6739
* [12.x] Add `APP_URL` fallback in filesystems config by [@KentarouTakeda](https://github.com/KentarouTakeda) in https://github.com/laravel/laravel/pull/6742
* chore: Update outdated GitHub Actions version by [@pgoslatara](https://github.com/pgoslatara) in https://github.com/laravel/laravel/pull/6743

## [v12.11.1](https://github.com/laravel/laravel/compare/v12.11.0...v12.11.1) - 2025-12-23

* Use environment variable for `DB_SSLMODE` - Postgres by [@robsontenorio](https://github.com/robsontenorio) in https://github.com/laravel/laravel/pull/6727
* fix: ensure APP_URL does not have trailing slash in filesystem by [@msamgan](https://github.com/msamgan) in https://github.com/laravel/laravel/pull/6728

## [v12.11.0](https://github.com/laravel/laravel/compare/v12.10.1...v12.11.0) - 2025-11-25

* fix: cookies are not available for subdomains by default by [@joostdebruijn](https://github.com/joostdebruijn) in https://github.com/laravel/laravel/pull/6705
* Fix PHP 8.5 PDO Driver Specific Constant Deprecation by [@RyanSchaefer](https://github.com/RyanSchaefer) in https://github.com/laravel/laravel/pull/6710
* Ignore Laravel compiled views for Vite  by [@QistiAmal1212](https://github.com/QistiAmal1212) in https://github.com/laravel/laravel/pull/6714

## [v12.10.1](https://github.com/laravel/laravel/compare/v12.10.0...v12.10.1) - 2025-11-06

* Update schema URL in package.json by [@robinmiau](https://github.com/robinmiau) in https://github.com/laravel/laravel/pull/6701

## [v12.10.0](https://github.com/laravel/laravel/compare/v12.9.1...v12.10.0) - 2025-11-04

* Add background driver by [@barryvdh](https://github.com/barryvdh) in https://github.com/laravel/laravel/pull/6699

## [v12.9.1](https://github.com/laravel/laravel/compare/v12.9.0...v12.9.1) - 2025-10-23

* [12.x] Replace Bootcamp with Laravel Learn by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6692
* [12.x] Comment out CLI workers for fresh applications by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/laravel/pull/6693

## [v12.9.0](https://github.com/laravel/laravel/compare/v12.8.0...v12.9.0) - 2025-10-21

**Full Changelog**: https://github.com/laravel/laravel/compare/v12.8.0...v12.9.0

## [v12.8.0](https://github.com/laravel/laravel/compare/v12.7.1...v12.8.0) - 2025-10-20

* [12.x] Makes test suite using broadcast's `null` driver by [@nunomaduro](https://github.com/nunomaduro) in https://github.com/laravel/laravel/pull/6691

## [v12.7.1](https://github.com/laravel/laravel/compare/v12.7.0...v12.7.1) - 2025-10-15

* Added `failover` driver to the `queue` config comment.  by [@sajjadhossainshohag](https://github.com/sajjadhossainshohag) in https://github.com/laravel/laravel/pull/6688

## [v12.7.0](https://github.com/laravel/laravel/compare/v12.6.0...v12.7.0) - 2025-10-14

**Full Changelog**: https://github.com/laravel/laravel/compare/v12.6.0...v12.7.0

## [v12.6.0](https://github.com/laravel/laravel/compare/v12.5.0...v12.6.0) - 2025-10-02

* Fix setup script by [@goldmont](https://github.com/goldmont) in https://github.com/laravel/laravel/pull/6682

## [v12.5.0](https://github.com/laravel/laravel/compare/v12.4.0...v12.5.0) - 2025-09-30

* [12.x] Fix type casting for environment variables in config files by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6670
* Fix CVEs affecting vite by [@faissaloux](https://github.com/faissaloux) in https://github.com/laravel/laravel/pull/6672
* Update .editorconfig to target compose.yaml by [@fredikaputra](https://github.com/fredikaputra) in https://github.com/laravel/laravel/pull/6679
* Add pre-package-uninstall script to composer.json by [@cosmastech](https://github.com/cosmastech) in https://github.com/laravel/laravel/pull/6681

## [v12.4.0](https://github.com/laravel/laravel/compare/v12.3.1...v12.4.0) - 2025-08-29

* [12.x] Add default Redis retry configuration by [@mateusjatenee](https://github.com/mateusjatenee) in https://github.com/laravel/laravel/pull/6666

## [v12.3.1](https://github.com/laravel/laravel/compare/v12.3.0...v12.3.1) - 2025-08-21

* [12.x] Bump Pint version by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6653
* [12.x] Making sure all related processed are closed when terminating the currently command by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6654
* [12.x] Use application name from configuration by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6655
* Bring back postAutoloadDump script by [@jasonvarga](https://github.com/jasonvarga) in https://github.com/laravel/laravel/pull/6662

## [v12.3.0](https://github.com/laravel/laravel/compare/v12.2.0...v12.3.0) - 2025-08-03

* Fix Critical Security Vulnerability in form-data Dependency by [@izzygld](https://github.com/izzygld) in https://github.com/laravel/laravel/pull/6645
* Revert "fix" by [@RobertBoes](https://github.com/RobertBoes) in https://github.com/laravel/laravel/pull/6646
* Change composer post-autoload-dump script to Artisan command by [@lmjhs](https://github.com/lmjhs) in https://github.com/laravel/laravel/pull/6647

## [v12.2.0](https://github.com/laravel/laravel/compare/v12.1.0...v12.2.0) - 2025-07-11

* Add Vite 7 support by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/laravel/pull/6639

## [v12.1.0](https://github.com/laravel/laravel/compare/v12.0.11...v12.1.0) - 2025-07-03

* [12.x] Disable nightwatch in testing by [@laserhybiz](https://github.com/laserhybiz) in https://github.com/laravel/laravel/pull/6632
* [12.x] Reorder environment variables in phpunit.xml for logical grouping by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6634
* Change to hyphenate prefixes and cookie names by [@u01jmg3](https://github.com/u01jmg3) in https://github.com/laravel/laravel/pull/6636
* [12.x] Fix type casting for environment variables in config files by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6637

## [v12.0.11](https://github.com/laravel/laravel/compare/v12.0.10...v12.0.11) - 2025-06-10

**Full Changelog**: https://github.com/laravel/laravel/compare/v12.0.10...v12.0.11

## [v12.0.10](https://github.com/laravel/laravel/compare/v12.0.9...v12.0.10) - 2025-06-09

* fix alphabetical order by [@Khuthaily](https://github.com/Khuthaily) in https://github.com/laravel/laravel/pull/6627
* [12.x] Reduce redundancy and keeps the .gitignore file cleaner by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6629
* [12.x] Fix: Add void return type to satisfy Rector analysis by [@Aluisio-Pires](https://github.com/Aluisio-Pires) in https://github.com/laravel/laravel/pull/6628

## [v12.0.9](https://github.com/laravel/laravel/compare/v12.0.8...v12.0.9) - 2025-05-26

* [12.x] Remove apc by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6611
* [12.x] Add JSON Schema to package.json by [@martinbean](https://github.com/martinbean) in https://github.com/laravel/laravel/pull/6613
* Minor language update by [@woganmay](https://github.com/woganmay) in https://github.com/laravel/laravel/pull/6615
* Enhance .gitignore to exclude common OS and log files by [@mohammadRezaei1380](https://github.com/mohammadRezaei1380) in https://github.com/laravel/laravel/pull/6619

## [v12.0.8](https://github.com/laravel/laravel/compare/v12.0.7...v12.0.8) - 2025-05-12

* [12.x] Clean up URL formatting in README by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6601

## [v12.0.7](https://github.com/laravel/laravel/compare/v12.0.6...v12.0.7) - 2025-04-15

* Add `composer run test` command by [@crynobone](https://github.com/crynobone) in https://github.com/laravel/laravel/pull/6598
* Partner Directory Changes in ReadME by [@joshcirre](https://github.com/joshcirre) in https://github.com/laravel/laravel/pull/6599

## [v12.0.6](https://github.com/laravel/laravel/compare/v12.0.5...v12.0.6) - 2025-04-08

**Full Changelog**: https://github.com/laravel/laravel/compare/v12.0.5...v12.0.6

## [v12.0.5](https://github.com/laravel/laravel/compare/v12.0.4...v12.0.5) - 2025-04-02

* [12.x] Update `config/mail.php` to match the latest core configuration by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6594

## [v12.0.4](https://github.com/laravel/laravel/compare/v12.0.3...v12.0.4) - 2025-03-31

* Bump vite from 6.0.11 to 6.2.3 - Vulnerability patch by [@abdel-aouby](https://github.com/abdel-aouby) in https://github.com/laravel/laravel/pull/6586
* Bump vite from 6.2.3 to 6.2.4 by [@thinkverse](https://github.com/thinkverse) in https://github.com/laravel/laravel/pull/6590

## [v12.0.3](https://github.com/laravel/laravel/compare/v12.0.2...v12.0.3) - 2025-03-17

* Remove reverted change from CHANGELOG.md by [@AJenbo](https://github.com/AJenbo) in https://github.com/laravel/laravel/pull/6565
* Improves clarity in app.css file by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6569
* [12.x] Refactor: Structural improvement for clarity by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6574
* Bump axios from 1.7.9 to 1.8.2 - Vulnerability patch by [@abdel-aouby](https://github.com/abdel-aouby) in https://github.com/laravel/laravel/pull/6572
* [12.x] Remove Unnecessarily [@source](https://github.com/source) by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6584

## [v12.0.2](https://github.com/laravel/laravel/compare/v12.0.1...v12.0.2) - 2025-03-04

* Make the github test action run out of the box independent of the choice of testing framework by [@ndeblauw](https://github.com/ndeblauw) in https://github.com/laravel/laravel/pull/6555

## [v12.0.1](https://github.com/laravel/laravel/compare/v12.0.0...v12.0.1) - 2025-02-24

* [12.x] prefer stable stability by [@pataar](https://github.com/pataar) in https://github.com/laravel/laravel/pull/6548

## [v12.0.0 (2025-??-??)](https://github.com/laravel/laravel/compare/v11.0.2...v12.0.0)

Laravel 12 includes a variety of changes to the application skeleton. Please consult the diff to see what's new.
