# Command Center Template Attachment Migration Recovery — V96

V95 could leave `armory_email_template_attachments` partially created on MySQL because the framework-generated foreign-key identifier exceeded MySQL's 64-character limit. V96 uses explicit short constraint names and checks the existing schema before creating tables, columns, or constraints.

The corrected migration can run directly against the partial V95 state. Do not manually drop the partial table.

```bash
php artisan migrate --force
php artisan optimize:clear
```
