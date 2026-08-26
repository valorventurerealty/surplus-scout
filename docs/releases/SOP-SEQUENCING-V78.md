# SOP Sequencing — V78

V78 adds controlled workflow continuation between SOPs.

## Behavior

- SOP managers can assign an optional next SOP from the SOP form.
- Every user who can read the current SOP sees the assigned continuation at the bottom.
- The continuation card identifies the next procedure, department, status, and summary.
- Self-links, circular chains, and archived targets are rejected by server-side validation.
- Managers see a configuration prompt when no continuation is assigned.
- Existing SOPs remain valid and end normally until a next SOP is selected.

## Deployment

Run `php artisan migrate --force` to add the nullable self-referencing `next_sop_id`, then rebuild Laravel's optimization caches.
