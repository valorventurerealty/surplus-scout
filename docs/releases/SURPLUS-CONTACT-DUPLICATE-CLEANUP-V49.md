# Surplus contact duplicate cleanup — v49

This cumulative release adds `contacts:merge-surplus-duplicates` to consolidate exact-name Surplus contacts created before CSV contact grouping was enabled.

The command is preview-only by default. `--execute` keeps the oldest contact, transfers all supported associations, writes an audit record, and soft-archives the redundant contacts in one transaction.

## Deployment and cleanup

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan migrate --force
php artisan contacts:merge-surplus-duplicates
php artisan contacts:merge-surplus-duplicates --execute
php artisan optimize
```

Review the preview output before running the execute command.
