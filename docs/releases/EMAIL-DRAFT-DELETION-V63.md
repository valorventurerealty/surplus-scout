# V63 — Recoverable Email Draft Deletion

- Authors can delete their own unsent drafts from the review screen.
- Queued, sending, sent, failed, and cancelled messages cannot be deleted with this action.
- Deleted drafts are hidden immediately and retained for 30 days by default.
- The daily `email:prune-deleted-drafts` command removes private attachments before permanently purging expired drafts.
- Deletion remains audited and uses row locking to prevent a send/delete race.

This release requires one database migration. No frontend build is required.
