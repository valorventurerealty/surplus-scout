# Contacts visibility and associated tasks update

This additive release changes the Contacts table to show Name, Company, Email, Associated Tasks, and Next Follow-up as separate operational columns.

It also introduces the contact-scoped foundation of the Tasks domain:

- polymorphic task ownership for reuse by later Properties, Leads, and Deals modules;
- pending, in-progress, completed, and cancelled statuses;
- low, normal, high, and urgent priorities;
- assignee, due date, completion timestamp, audit fields, soft deletion, and query indexes;
- permission-controlled task creation, completion, and archival on a contact;
- scoped route binding that prevents cross-contact task mutations;
- open-task summaries on the Contacts list and paginated task history on each contact.

## Production deployment

Back up the database, enable maintenance mode, extract the update at the private application root, run the new migration, rebuild frontend assets, copy the generated public build to the document root, optimize Laravel, and disable maintenance mode. Do not run development seeders in production.
