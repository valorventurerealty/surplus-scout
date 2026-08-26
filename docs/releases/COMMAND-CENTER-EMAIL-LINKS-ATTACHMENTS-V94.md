# Command Center Email Links & Attachments — V94

## What changed

The Email workspace now includes an **Insert a hyperlink** panel below the message editor. Enter the visible link text and a complete `http://` or `https://` destination, place the cursor in the message, and select **Insert link**. The composer stores the link using `[label](https://example.com)` syntax and renders it as a clickable link on the review screen and in the delivered HTML email.

Attachments remain private on the local application disk until delivery. A draft accepts up to the configured attachment count and size limits (five files and 10 MB per file by default) across PDF, Word, Excel, CSV, text, JPG, and PNG formats.

## Security behavior

- Raw HTML in the message is stripped.
- Unsafe link schemes such as `javascript:` are not rendered as links.
- Review fingerprints include both the final text and final HTML, so a message must be reviewed again if either representation changes.
- Attachments remain covered by the existing private-storage, authorization, hash, review-fingerprint, and delivery controls.

## Deployment

No database migration or frontend build is required. Deploy the update at the Laravel project root and run:

```bash
php artisan optimize:clear
```
