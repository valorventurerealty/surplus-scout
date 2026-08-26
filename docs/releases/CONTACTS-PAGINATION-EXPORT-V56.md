# Contacts pagination and export v56

The Contacts workspace now supports selectable page sizes of 20, 50, 100, and 250 records.

Users can select individual contacts or all contacts on the current page and export the selection as CSV. A separate Export filtered action exports every contact matching the current search, contact type, and status filters across all result pages.

Exports include contact details, mailing information, assignment, next follow-up, associated properties, visible Surplus cases, open tasks, and notes. Contact visibility rules are applied again on the server before export, including the Marketing role's Surplus restriction. Export operations are rate limited and audit logged. CSV values beginning with spreadsheet formula characters are escaped to mitigate formula injection.

No database migration is required for this release.
