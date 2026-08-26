# Email Workspace

The Email workspace provides controlled outbound CRM communication through the server-side SMTP connection. Browser code never receives mailbox credentials.

PreTax Auction acquisition files are available as related CRM context to authorized users. Their case identifiers supply `{{parcel_id}}`, `{{county}}`, and `{{case_number}}`, and their owner relationship supplies the contact merge fields. Users without PreTax Auction access cannot select or view that context.

## Workflow

1. An authorized user composes manually or applies an active Armory email template.
2. The message is saved as a private draft. Saving never sends.
3. Attachments are stored on Laravel's private `local` disk and downloaded only through an authorized controller.
4. The review screen displays recipients, subject, message, signature, and attachments.
5. The review is cryptographically bound to those exact recipients, merged content, signature, and attachments; any subsequent change requires a fresh review.
6. The user checks the confirmation and queues the message.
7. The scheduled database queue worker sends it and records `sent` or `failed` accurately.

Messages can be linked to a contact, property, deal, or surplus case. The context resolver rechecks the record's policy before context is loaded and again before sending. Marketing cannot use restricted surplus records, and Read Only users cannot compose or send.

## Merge fields

The composer and Armory template editor share one allowlisted registry: `{{first_name}}`, `{{last_name}}`, `{{contact_name}}`, `{{property_address}}`, `{{parcel_id}}`, `{{county}}`, `{{surplus_amount}}`, `{{case_number}}`, and `{{sender_name}}`. The composer provides click-to-insert controls. Values are resolved only from authorized CRM context and shown before approval. A field without a value remains unresolved and blocks delivery.

For a Surplus-linked message, `{{parcel_id}}` and `{{county}}` always come from the Surplus case's **Case Identifiers**. Those case values remain authoritative even when the linked Property has different identifiers. Property- and Deal-linked messages use the Property record.

Compose and Edit include a **Related CRM record** selector. The user must choose the exact Property, Deal, or Surplus case that supplies merge values. A contact is not automatically converted into a case context because a contact may be associated with multiple parcels.

## Safety decisions

- From address is copied from backend mail configuration, never accepted from a form.
- Draft, queue, sending, sent, failed, and cancelled are distinct states.
- One explicit approval is required for each draft.
- Unknown merge fields block delivery.
- The sending job rechecks that the sender is active and still has permission.
- Automatic retry is disabled to reduce duplicate-delivery risk after uncertain SMTP failures. A visible manual retry is available for confirmed failures.
- An author may delete only an unsent draft. Deletion is recoverable for the configured retention period; a scheduled command then removes private attachments and permanently purges the record.
- Recipient and hourly limits are enforced server-side.
- Message body snapshots are excluded from general audit payloads; the dedicated email record is the communication history.

## Stellar / Namecheap

Use the existing five-minute cPanel cron that calls Laravel's scheduler. `routes/console.php` drains database jobs with `queue:work --stop-when-empty`, so no persistent worker or Redis is required. Delivery can take up to five minutes after approval.

The scheduler also runs `email:prune-deleted-drafts` daily at 4:15 AM. The default retention period is 30 days and can be changed with `VVR_EMAIL_DELETED_DRAFT_RETENTION_DAYS`.

Required production mail settings are `MAIL_MAILER`, `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, and `MAIL_FROM_NAME`. Keep the mailbox password only in `.env`.

## Current boundaries

This milestone sends individual outbound messages. It does not synchronize the inbox, capture replies, track opens/clicks, or run bulk campaigns. A job that has already entered `sending` cannot be automatically retried because SMTP delivery may be uncertain; an administrator should inspect the mailbox before taking further action.
