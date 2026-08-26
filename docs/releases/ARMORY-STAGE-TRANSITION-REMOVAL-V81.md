# Armory Stage Transition Removal — V81

V81 removes the cross-script next-stage feature at the owner's request.

## Removed

- Default next-stage and destination-step controls.
- Response-branch transitions to other Armory scripts.
- The next-stage write route and controller action.
- Cross-script transition runtime behavior and UI labels.
- Cross-stage configuration columns and legacy transition-only events.

## Retained

- Armory scripts and private source files.
- Guided sessions and resumable session records.
- Ordered steps within each script.
- Response branches that move to another step in the same script.
- Notes, outcomes, context, audit controls, and session deletion retention.

Sessions that had already moved to another script remain attached to the script they currently reference. The removal migration deletes only transition-specific configuration and `stage_transitioned` event rows; it does not delete scripts, contacts, properties, normal session events, or guided sessions.

## Deployment

Run `php artisan migrate --force`, then rebuild Laravel's optimization caches. No frontend asset build is required.
