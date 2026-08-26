# Tasks workspace update

This release activates the Tasks navigation workspace and centralizes existing contact tasks with standalone and property-linked tasks.

Delivered features include filters, assignments, priorities, due dates, in-app reminders, recurring tasks, safe completion, task templates, audit logging, dashboard task metrics, and links to associated CRM records. Reminder processing uses the existing five-minute Laravel scheduler and database storage, making it safe for Namecheap Stellar shared hosting.

The release adds task recurrence/reminder columns, Laravel's database notifications table, and `task_templates`. After migration, seed the standard due-diligence templates with `php artisan db:seed --class=TaskTemplateSeeder --force`.
