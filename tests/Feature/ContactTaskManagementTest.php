<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTaskManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_create_contact_task(): void
    {
        $user = User::factory()->create(['role' => UserRole::AcquisitionManager]);
        $contact = Contact::factory()->create();

        $this->actingAs($user)->post(route('contacts.tasks.store', $contact), [
            'title' => 'Call seller about revised offer',
            'priority' => TaskPriority::High->value,
            'assigned_user_id' => $user->id,
            'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $task = $contact->tasks()->firstOrFail();
        $this->assertSame(TaskStatus::Pending, $task->status);
        $this->assertSame($user->id, $task->created_by);
        $this->assertDatabaseHas(AuditLog::class, ['event' => 'created', 'auditable_id' => $task->id]);
    }

    public function test_read_only_user_cannot_create_or_complete_task(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        $contact = Contact::factory()->create();
        $task = Task::factory()->for($contact, 'taskable')->create();

        $this->actingAs($user)->post(route('contacts.tasks.store', $contact), [
            'title' => 'Unauthorized task', 'priority' => TaskPriority::Normal->value,
        ])->assertForbidden();
        $this->actingAs($user)->patch(route('contacts.tasks.complete', [$contact, $task]))->assertForbidden();
    }

    public function test_task_can_be_completed_from_contact(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $contact = Contact::factory()->create();
        $task = Task::factory()->for($contact, 'taskable')->create();

        $this->actingAs($user)->patch(route('contacts.tasks.complete', [$contact, $task]))->assertRedirect();

        $task->refresh();
        $this->assertSame(TaskStatus::Completed, $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_task_from_another_contact_cannot_be_mutated_through_contact_route(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $contact = Contact::factory()->create();
        $otherContact = Contact::factory()->create();
        $task = Task::factory()->for($otherContact, 'taskable')->create();

        $this->actingAs($user)->patch(route('contacts.tasks.complete', [$contact, $task]))->assertNotFound();
    }

    public function test_contacts_index_displays_associated_open_task_and_requested_columns(): void
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create([
            'company' => 'Valor Test Holdings',
            'email' => 'contact@valor.test',
            'next_follow_up_at' => now()->addDay(),
        ]);
        Task::factory()->for($contact, 'taskable')->create(['title' => 'Review seller documents']);

        $this->actingAs($user)->get(route('contacts.index'))
            ->assertOk()
            ->assertSeeTextInOrder(['Name', 'Company', 'Email', 'Associated tasks', 'Next follow-up'])
            ->assertSee('Valor Test Holdings')
            ->assertSee('contact@valor.test')
            ->assertSee('Review seller documents');
    }
}
