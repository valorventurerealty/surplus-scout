# Command Center Template Links & Attachments — V95

## What changed

Armory email templates now support the same guided hyperlink workflow as the Email composer. Template managers can add visible link text and an HTTP or HTTPS destination without hand-writing the Markdown syntax.

Templates can also retain reusable private attachments. When an active template is selected in Email Compose and the draft is saved, the template files are copied into that draft. The review screen continues to show the exact files that will be sent.

## Attachment controls

- Five files per template by default, governed by the existing `VVR_EMAIL_MAX_ATTACHMENTS` setting.
- 10 MB per file by default, governed by `VVR_EMAIL_ATTACHMENT_MAX_KB`.
- PDF, Word, Excel, CSV, text, JPG, and PNG formats.
- Private local-disk storage and permission-controlled download.
- Hash-based duplicate suppression.
- Template-derived draft files synchronize when the selected template changes.
- Files already copied into an outbound draft remain part of that governed draft if the source template is later archived or an attachment is removed.

## Deployment

This release includes a database migration. Deploy the update at the Laravel project root and run:

```bash
php artisan migrate --force
php artisan optimize:clear
```

No frontend build is required.
