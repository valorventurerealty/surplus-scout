# Armory Multi-Stage Guided Sessions — V76

## Outcome

An Armory guided session can now continue through multiple script stages without being closed and restarted. Every script can define a default next stage, and every caller-response branch can override that default with a specific next stage.

## Execution rules

- Normal steps continue by sequence within the current stage.
- A response branch may target one next step or one next stage, never both.
- A branch-level stage transition takes precedence over the script's default transition.
- The default next stage runs only after the final step when that step has no response branches.
- Entering a stage always opens its first sequenced guided step.
- With no valid next step or stage, the session completes using the existing outcome behavior.

Transitions execute in the existing database transaction. The session retains its user, contact, property, caller name, notes, and token. The starting stage remains recorded separately from the current stage, and the event timeline records each source-to-destination transition.

## Safety

Only Armory managers can configure stage transitions. A next stage must exist, differ from the current stage, and contain at least one guided step. Non-manager users can transition only into active stages. If a destination is later archived, deactivated, or emptied, the current session remains unchanged and displays an actionable validation error.

## Deployment

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
```

No new Composer dependency, npm dependency, Redis service, or permanent worker is required.
