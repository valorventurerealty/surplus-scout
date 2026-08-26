# Milestone 1 — foundation and contacts

## Delivered

- Internal session authentication: login, logout, forgot/reset password, inactive-user rejection, and login throttling.
- Eight enum-backed roles: Owner, Partner, Acquisition Manager, Disposition Manager, Virtual Assistant, Marketing, Admin, and Read Only.
- Responsive dashboard shell with mobile navigation, persistent dark/light preference, Livewire metrics, and explicit future-module states.
- Contacts CRUD with type/status, assignment, next follow-up, notes, search/filter, pagination, authorization, validation, and soft archive.
- Audit records for contact create, update, delete, and restore lifecycle events.
- Safe development and initial-production seeders.
- Authentication, authorization, validation, audit, search, and role unit tests.
- GitHub Actions checks for frontend build, Pint formatting, and PHPUnit.

## Acceptance checks

1. An unauthenticated request to `/dashboard` or `/contacts` redirects to `/login`.
2. An inactive account cannot sign in.
3. Every active role can view contacts; Read Only cannot create, edit, or archive one.
4. A contact mutation persists the acting user's ID and an audit record.
5. Dashboard metrics are derived from stored contacts and refresh every 60 seconds.
6. Public registration does not exist.
7. Production seeding fails unless initial owner credentials are supplied.

## Not in scope

Properties, leads, deals, tasks, pipelines, documents, reports, automations, and AI operations are architecture-only in this milestone. Their navigation labels are not interactive so users cannot mistake unfinished behavior for working modules.

## Post-milestone increments

Contact-associated Tasks and the core Properties module were delivered after Milestone 1 as separately tested, additive releases. This section preserves the original milestone boundary; current application scope is documented in `README.md` and the module guides.
