<?php

namespace Tests\Feature;

use App\Enums\TaskRecurrence;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\SurplusCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskBulkStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_the_status_of_selected_tasks_only(): void
    {
        $user = User::factory()->create(['role' => UserRole::AcquisitionManager]);
        $selected = Task::factory()->count(2)->create(['status' => TaskStatus::Pending]);
        $unselected = Task::factory()->create(['status' => TaskStatus::Pending]);

        $this->actingAs($user)->patch(route('tasks.bulk-status'), [
            'task_ids' => $selected->modelKeys(),
            'status' => TaskStatus::InProgress->value,
        ])->assertRedirect()->assertSessionHas('success');

        foreach ($selected as $task) {
            $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);
            $this->assertDatabaseHas('audit_logs', [
                'user_id' => $user->id,
                'event' => 'updated',
                'auditable_type' => $task->getMorphClass(),
                'auditable_id' => $task->id,
            ]);
        }

        $this->assertSame(TaskStatus::Pending, $unselected->fresh()->status);
    }

    public function test_bulk_completion_sets_completion_time_and_advances_recurrence(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $task = Task::factory()->create([
            'status' => TaskStatus::Pending,
            'due_at' => now()->addDay(),
            'recurrence_frequency' => TaskRecurrence::Weekly,
            'recurrence_interval' => 1,
        ]);

        $this->actingAs($owner)->patch(route('tasks.bulk-status'), [
            'task_ids' => [$task->id],
            'status' => TaskStatus::Completed->value,
        ])->assertRedirect();

        $task->refresh();
        $this->assertSame(TaskStatus::Completed, $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertDatabaseCount('tasks', 2);
        $this->assertDatabaseHas('tasks', [
            'recurrence_parent_id' => $task->id,
            'status' => TaskStatus::Pending->value,
        ]);
    }

    public function test_read_only_user_cannot_bulk_change_task_statuses(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        $task = Task::factory()->create(['status' => TaskStatus::Pending]);

        $this->actingAs($user)->patch(route('tasks.bulk-status'), [
            'task_ids' => [$task->id],
            'status' => TaskStatus::Cancelled->value,
        ])->assertForbidden();

        $this->assertSame(TaskStatus::Pending, $task->fresh()->status);
    }

    public function test_batch_is_not_executed_when_one_task_is_restricted(): void
    {
        $marketing = User::factory()->create(['role' => UserRole::Marketing]);
        $visibleTask = Task::factory()->create([
            'taskable_type' => null,
            'taskable_id' => null,
            'status' => TaskStatus::Pending,
        ]);
        $surplus = SurplusCase::factory()->create();
        $restrictedTask = Task::factory()->create([
            'taskable_type' => $surplus->getMorphClass(),
            'taskable_id' => $surplus->id,
            'status' => TaskStatus::Pending,
        ]);

        $this->actingAs($marketing)->patch(route('tasks.bulk-status'), [
            'task_ids' => [$visibleTask->id, $restrictedTask->id],
            'status' => TaskStatus::Completed->value,
        ])->assertForbidden();

        $this->assertSame(TaskStatus::Pending, $visibleTask->fresh()->status);
        $this->assertSame(TaskStatus::Pending, $restrictedTask->fresh()->status);
    }

    public function test_bulk_status_requires_a_valid_selection_and_status(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)->from(route('tasks.index'))->patch(route('tasks.bulk-status'), [
            'task_ids' => [],
            'status' => 'not-a-status',
        ])->assertRedirect(route('tasks.index'))
            ->assertSessionHasErrors(['task_ids', 'status']);
    }
}
