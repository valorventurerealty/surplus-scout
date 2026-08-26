# Contact tasks

Contact tasks are the first production slice of the Tasks domain. Users can add, assign, prioritize, complete, and archive work directly from a contact. The Contacts list displays up to three open tasks per contact plus an additional-count indicator.

Tasks use a polymorphic `taskable` relationship so future Property, Deal, Lead, and Document records can share the same task aggregate without schema changes. Status and priority are backed enums. Due dates and assignee/status indexes support operational queues; soft deletion preserves history; and every mutation uses the shared audit trail.

Contact-scoped routes use scoped model binding to prevent a task belonging to one contact from being changed through another contact's URL. Task policies apply the active-user and Read Only restrictions already established for Contacts.

The later full Tasks milestone will build on this schema with recurring schedules, templates, reminders, notifications, standalone task views, and calendar synchronization.
