# V64 — Armory Sorting and Guided Session Management

## Delivered

- Sort scripts by Script, Category, Version, Status, Source, or Updated.
- Preserve active Armory filters when changing sort order.
- Start a runnable guided script directly from the Guided Sessions workspace.
- Restrict non-managers to active scripts while allowing authorized managers to preview configured drafts and retired scripts.
- Delete guided sessions from the list or detail screen.
- Soft-delete sessions for 30 days, audit the deletion without copying notes, and permanently prune expired records through Laravel Scheduler.

## Deployment

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
php artisan schedule:list
```

No new Composer or npm dependency is required. The existing five-minute Namecheap cron remains unchanged. After deployment, `schedule:list` should include `armory:prune-deleted-sessions` at 4:30 AM.
