# Armory Stage Step Routing — V77

V77 extends multi-stage guided sessions so an Armory manager can choose both the destination stage and the exact step where the session should continue.

## Behavior

- The default stage transition can target a specific destination step.
- Each response branch can target a specific destination step.
- Leaving the destination step blank starts at the first sequenced step.
- A destination step is rejected unless it belongs to the selected destination stage.
- Runtime transitions recheck stage availability, permissions, and step ownership inside the session transaction.
- Transition history records both the destination stage and destination step.

## Deployment

Run `php artisan migrate --force` to add the two nullable destination-step foreign keys, then rebuild Laravel's optimization caches.
