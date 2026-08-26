<?php

namespace Tests\Feature;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\TaskPriority;
use App\Enums\TaskRecurrence;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Enums\WetlandsStatus;
use App\Models\Property;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_tasks_are_hidden_by_default_and_visible_when_filtered(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        Task::factory()->create(['title' => 'Visible pending title', 'status' => TaskStatus::Pending]);
        Task::factory()->create(['title' => 'Hidden completed title', 'status' => TaskStatus::Completed, 'completed_at' => now()]);

        $this->actingAs($user)->get(route('tasks.index'))
            ->assertOk()
            ->assertSeeText('Visible pending title')
            ->assertDontSeeText('Hidden completed title')
            ->assertSeeText('Completed tasks are hidden from the default view.');

        $this->actingAs($user)->get(route('tasks.index', ['status' => TaskStatus::Completed->value]))
            ->assertOk()
            ->assertSeeText('Hidden completed title')
            ->assertDontSeeText('Visible pending title');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_authorized_user_can_create_property_task_with_schedule_reminder_and_recurrence(): void
    {
        Carbon::setTestNow('2026-08-16 09:00:00');
        $user = User::factory()->create(['role' => UserRole::AcquisitionManager]);
        $property = Property::factory()->create();

        $response = $this->actingAs($user)->post(route('tasks.store'), [
            'title' => 'Review title commitment',
            'description' => 'Confirm exceptions and ownership vesting.',
            'status' => TaskStatus::Pending->value,
            'priority' => TaskPriority::High->value,
            'assigned_user_id' => $user->id,
            'subject' => 'property:'.$property->id,
            'due_at' => '2026-08-20 17:00:00',
            'reminder_at' => '2026-08-19 17:00:00',
            'recurrence_frequency' => TaskRecurrence::Weekly->value,
            'recurrence_interval' => 2,
            'recurrence_ends_at' => '2026-12-31 17:00:00',
        ]);

        $task = Task::query()->firstOrFail();
        $response->assertRedirect(route('tasks.show', $task));
        $this->assertTrue($task->taskable->is($property));
        $this->assertSame(TaskPriority::High, $task->priority);
        $this->assertSame(TaskRecurrence::Weekly, $task->recurrence_frequency);
        $this->assertSame(2, $task->recurrence_interval);
        $this->assertSame($user->id, $task->created_by);
    }

    public function test_completing_recurring_task_creates_one_idempotent_next_occurrence(): void
    {
        Carbon::setTestNow('2026-08-16 09:00:00');
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $task = Task::factory()->create([
            'status' => TaskStatus::Pending,
            'assigned_user_id' => $user->id,
            'due_at' => '2026-08-18 10:00:00',
            'reminder_at' => '2026-08-18 09:00:00',
            'recurrence_frequency' => TaskRecurrence::Weekly,
            'recurrence_interval' => 1,
        ]);

        $this->actingAs($user)->patch(route('tasks.complete', $task))->assertRedirect();
        $this->actingAs($user)->patch(route('tasks.complete', $task))->assertRedirect();

        $this->assertSame(2, Task::query()->count());
        $next = Task::query()->whereKeyNot($task->id)->firstOrFail();
        $this->assertSame('2026-08-25 10:00:00', $next->due_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-25 09:00:00', $next->reminder_at->format('Y-m-d H:i:s'));
        $this->assertSame($task->id, $next->recurrence_parent_id);
    }

    public function test_due_reminder_creates_one_private_notification(): void
    {
        Carbon::setTestNow('2026-08-16 09:00:00');
        $user = User::factory()->create(['role' => UserRole::VirtualAssistant]);
        $task = Task::factory()->create([
            'assigned_user_id' => $user->id,
            'status' => TaskStatus::Pending,
            'reminder_at' => '2026-08-16 08:55:00',
            'reminder_sent_at' => null,
        ]);

        $this->artisan('tasks:send-reminders')->assertSuccessful();
        $this->artisan('tasks:send-reminders')->assertSuccessful();

        $this->assertSame(1, $user->fresh()->notifications()->count());
        $this->assertNotNull($task->fresh()->reminder_sent_at);
    }

    public function test_read_only_user_can_view_tasks_but_cannot_mutate_them(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        $task = Task::factory()->create();

        $this->actingAs($user)->get(route('tasks.index'))->assertOk();
        $this->actingAs($user)->get(route('tasks.show', $task))->assertOk();
        $this->actingAs($user)->post(route('tasks.store'), $this->validData())->assertForbidden();
        $this->actingAs($user)->get(route('tasks.edit', $task))->assertForbidden();
        $this->actingAs($user)->delete(route('tasks.destroy', $task))->assertForbidden();
    }

    public function test_invalid_or_missing_associated_record_is_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)->post(route('tasks.store'), $this->validData([
            'subject' => 'property:999999',
        ]))->assertSessionHasErrors('subject');

        $this->assertSame(0, Task::query()->count());
    }

    public function test_only_owner_or_admin_can_manage_task_templates(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $va = User::factory()->create(['role' => UserRole::VirtualAssistant]);

        $this->actingAs($owner)->post(route('task-templates.store'), [
            'name' => 'Seller follow-up',
            'title' => 'Follow up with seller',
            'priority' => TaskPriority::High->value,
            'due_in_days' => 2,
            'reminder_lead_minutes' => 1440,
            'recurrence_interval' => 1,
            'is_active' => 1,
        ])->assertRedirect(route('task-templates.index'));

        $template = TaskTemplate::query()->firstOrFail();
        $this->actingAs($va)->get(route('task-templates.index'))->assertOk();
        $this->actingAs($va)->get(route('task-templates.edit', $template))->assertForbidden();
    }

    private function validData(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Call seller',
            'status' => TaskStatus::Pending->value,
            'priority' => TaskPriority::Normal->value,
            'recurrence_interval' => 1,
        ], $overrides);
    }
}
