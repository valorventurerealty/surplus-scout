# V103 — Projection Editor Hotfix

The initial Projections editor attempted to call Laravel's `mapWithKeys()` directly on the PHP array returned by an enum's `cases()` method. V103 explicitly converts that array to a Collection before preparing Alpine's live-calculation assumptions.

This is a view-only hotfix. It requires no migration, seeding, or frontend build.
