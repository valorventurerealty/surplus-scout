# V106 — Route Merge Recovery

The Website Chats release was built from an earlier route file. Deploying it retained the newer module files on disk but removed their route registrations, including Projections and Google Calendar inbound synchronization.

V106 uses the current cumulative route file and merges in:

- The public, secret-protected Website Chats webhook.
- Authenticated Website Chats index, detail, and update routes.
- Projections routes.
- Google Calendar inbound synchronization routes.
- All existing VVR CRM routes.

No database migration is required.
