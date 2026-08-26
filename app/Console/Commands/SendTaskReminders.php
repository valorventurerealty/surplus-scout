<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\TaskReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendTaskReminders extends Command
{
    protected $signature = 'tasks:send-reminders';

    protected $description = 'Create private in-app notifications for due task reminders';

    public function handle(): int
    {
        $sent = 0;

        Task::query()
            ->open()
            ->whereNotNull('assigned_user_id')
            ->whereNotNull('reminder_at')
            ->whereNull('reminder_sent_at')
            ->where('reminder_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($tasks) use (&$sent): void {
                foreach ($tasks as $candidate) {
                    DB::transaction(function () use ($candidate, &$sent): void {
                        $task = Task::query()->lockForUpdate()->with('assignedUser')->find($candidate->id);

                        if (! $task || $task->reminder_sent_at || ! $task->assignedUser?->is_active) {
                            return;
                        }

                        $task->assignedUser->notify(new TaskReminderNotification($task));
                        $task->updateQuietly(['reminder_sent_at' => now()]);
                        $sent++;
                    });
                }
            });

        $this->info("Sent {$sent} task reminder(s).");

        return self::SUCCESS;
    }
}
