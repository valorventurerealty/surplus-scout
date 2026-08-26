# V107 — Cumulative Calendar and Projections Recovery

The Website Chats full build was based on an earlier application snapshot. Extracting it added the new chat module but also overwrote files introduced by later Calendar, Google synchronization, and Projections releases.

V107 reapplies the complete post-chat cumulative layer while preserving Website Chats:

- Calendar meetings and generalized events.
- Google Calendar outbound and inbound synchronization.
- PHP 8.4 Google import compatibility.
- Projections workspace, permissions, calculations, editor hotfix, and annual totals.
- Compact two-column navigation.
- Merged Website Chats, Projections, and Google inbound route registrations.

The deployment runs existing idempotent migrations. It does not delete or replace CRM data.
