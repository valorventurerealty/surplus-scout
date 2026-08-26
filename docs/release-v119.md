# V119 Surplus Scout Workspace

Adds a secured Surplus Scout foundation workspace at `/surplus-scout` and places it under Management / Tools.

Access follows the existing Surplus case view policy. The workspace does not yet process AI requests, upload files, research records, or modify CRM data. It is intentionally ready for the first user-supplied Surplus Scout component.

No migration is required.

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan route:list --name=surplus-scout
php artisan view:cache
php artisan optimize
```
