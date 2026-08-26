# Tasks module

## Purpose

Tasks is VVR's centralized workspace for assigning and tracking accountable work. Existing contact tasks and new standalone or property-linked tasks appear in one searchable queue.

## Capabilities

- Search and filter by status, priority, assignee, deadline, and associated record type.
- Keep completed work out of the default queue while retaining it through the explicit Completed status filter.
- Select individual tasks or all tasks on the current page and change their status together.
- Assign active users, set priorities and due dates, and link a task to a contact or property.
- Configure a private in-app reminder for the assigned user.
- Repeat work daily, weekly, or monthly at a configurable interval and optional end date.
- Complete, edit, cancel, or archive tasks according to policy.
- Create tasks from reusable templates. Owner and Admin manage templates; all active roles can use active templates.
- View open, overdue, due-today, and assigned-to-me counts.

## Recurrence and idempotency

Bulk status changes accept up to 200 distinct active tasks, validate the requested status, recheck each task's update policy, and execute the complete batch in one database transaction. If one selected task is restricted, nothing changes. Each task retains its normal audit entry. Bulk completion uses the same recurrence workflow as individual completion; moving a task away from Completed clears its completion timestamp and resets reminder-delivery state.

Completing a recurring task creates the next occurrence. The next deadline is calculated deterministically in application code. Each generated occurrence receives a unique SHA-256 recurrence key derived from the recurrence root and next deadline, so repeated completion requests cannot create duplicate tasks. Monthly recurrence uses no-overflow calendar arithmetic; a January 31 task advances to the last valid day of February.

## Reminders

`tasks:send-reminders` runs every five minutes through the existing Laravel scheduler. It locks eligible task rows, writes one database notification, and sets `reminder_sent_at` in the same transaction. It requires no Redis server or permanent worker and is compatible with the existing Namecheap cron configuration.

## Permissions

- Every active authenticated user can view tasks and active templates.
- Every active role except Read Only can create, update, complete, cancel, and archive tasks.
- Only Owner and Admin can create, edit, or deactivate task templates.
- Form Requests and policies enforce these permissions on the backend.

## Audit and deletion

Task and template mutations use the existing audit system. Tasks are soft-deleted when archived. Templates are deactivated instead of deleted so historical business standards remain traceable.
