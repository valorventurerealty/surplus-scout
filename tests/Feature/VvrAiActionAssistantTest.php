<?php

namespace Tests\Feature;

use App\Contracts\AiProviderInterface;
use App\Data\AiDocumentInput;
use App\Data\AiStructuredResponse;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\AiActionPlan;
use App\Models\AiConversation;
use App\Models\Property;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VvrAiActionAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.provider' => 'gemini', 'ai.api_key' => 'test-key', 'ai.approval_expiration_minutes' => 60]);
    }

    public function test_write_plan_requires_approval_then_creates_task_and_audit_records(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $this->fakePlan([
            'intent' => 'create_follow_up_tasks',
            'summary' => 'Create one high-priority follow-up task.',
            'missing_information' => [], 'warnings' => [],
            'tool_calls' => [[
                'name' => 'create_task', 'summary' => 'Create seller follow-up task.',
                'arguments_json' => json_encode(['title' => 'Call seller about title', 'priority' => 'high', 'assigned_user_id' => $user->id]),
            ]],
        ]);

        $response = $this->actingAs($user)->post(route('vvr-ai.intakes.store'), [
            'prompt' => 'Create a high priority task for me to call the seller about title.',
            'acknowledge_external_processing' => '1',
        ]);

        $conversation = AiConversation::query()->sole();
        $plan = AiActionPlan::query()->sole();
        $response->assertRedirect(route('vvr-ai.conversations.show', $conversation));
        $this->assertSame('pending_approval', $plan->status);
        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseHas('ai_audit_logs', ['action_plan_id' => $plan->id, 'event' => 'plan_created']);

        $this->post(route('vvr-ai.plans.approve', [$conversation, $plan]))
            ->assertRedirect(route('vvr-ai.conversations.show', $conversation));

        $task = Task::query()->sole();
        $this->assertSame('Call seller about title', $task->title);
        $this->assertSame('completed', $plan->fresh()->status);
        $this->assertDatabaseHas('ai_tool_calls', ['action_plan_id' => $plan->id, 'tool_name' => 'create_task', 'status' => 'completed']);
        $this->assertDatabaseHas('ai_audit_logs', ['action_plan_id' => $plan->id, 'event' => 'plan_completed']);
        $this->assertDatabaseHas('ai_usage_records', ['conversation_id' => $conversation->id, 'operation' => 'action_planning', 'successful' => true]);
    }

    public function test_rejected_plan_makes_no_changes(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->fakePlan([
            'intent' => 'create_follow_up_tasks', 'summary' => 'Create a task.', 'missing_information' => [], 'warnings' => [],
            'tool_calls' => [['name' => 'create_task', 'summary' => 'Create task.', 'arguments_json' => '{"title":"Do not create"}']],
        ]);
        $this->actingAs($user)->post(route('vvr-ai.intakes.store'), ['prompt' => 'Make a task', 'acknowledge_external_processing' => '1']);
        $conversation = AiConversation::query()->sole();
        $plan = AiActionPlan::query()->sole();

        $this->post(route('vvr-ai.plans.reject', [$conversation, $plan]), ['rejection_reason' => 'Wrong action'])
            ->assertRedirect(route('vvr-ai.conversations.show', $conversation));

        $this->assertSame('rejected', $plan->fresh()->status);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_read_only_user_can_search_but_cannot_receive_write_tools(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        Property::factory()->create(['address' => '120 Bayberry Road', 'status' => PropertyStatus::Owned]);
        $this->fakePlan([
            'intent' => 'general_question', 'summary' => 'Find owned properties.', 'missing_information' => [], 'warnings' => [],
            'tool_calls' => [['name' => 'get_properties', 'summary' => 'Search owned properties.', 'arguments_json' => '{"status":"owned"}']],
        ]);

        $this->actingAs($user)->post(route('vvr-ai.intakes.store'), ['prompt' => 'Show owned properties', 'acknowledge_external_processing' => '1']);

        $plan = AiActionPlan::query()->sole();
        $this->assertSame('completed', $plan->status);
        $this->assertStringContainsString('120 Bayberry Road', json_encode($plan->result_json));
        $this->assertStringNotContainsString('expected_sales_price', json_encode($plan->result_json));
        $this->assertFalse(collect(app(\App\Contracts\ToolRegistryInterface::class)->forUser($user))->contains('name', 'create_task'));
    }

    public function test_approval_expiration_prevents_execution(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
        $plan = AiActionPlan::query()->create([
            'token' => fake()->uuid(), 'conversation_id' => $conversation->id, 'user_id' => $user->id,
            'intent' => 'create_follow_up_tasks', 'summary' => 'Expired task plan', 'risk_level' => 2,
            'status' => 'pending_approval', 'expires_at' => now()->subMinute(),
        ]);
        $plan->toolCalls()->create([
            'sequence' => 1, 'tool_name' => 'create_task', 'action_summary' => 'Create expired task',
            'risk_level' => 2, 'requires_approval' => true, 'arguments_json' => ['title' => 'Expired task'],
            'status' => 'proposed', 'idempotency_key' => hash('sha256', fake()->uuid()),
        ]);

        $this->actingAs($user)->post(route('vvr-ai.plans.approve', [$conversation, $plan]))
            ->assertSessionHasErrors('approval');

        $this->assertSame('expired', $plan->fresh()->status);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_required_tool_failure_rolls_back_prior_writes(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
        $plan = AiActionPlan::query()->create([
            'token' => fake()->uuid(), 'conversation_id' => $conversation->id, 'user_id' => $user->id,
            'intent' => 'multi_action', 'summary' => 'Task then invalid pipeline move', 'risk_level' => 2,
            'status' => 'pending_approval', 'expires_at' => now()->addHour(),
        ]);
        foreach ([
            ['create_task', ['title' => 'Must roll back']],
            ['change_pipeline_stage', ['property_id' => 999999, 'status' => 'marketing']],
        ] as $index => [$tool, $arguments]) {
            $plan->toolCalls()->create([
                'sequence' => $index + 1, 'tool_name' => $tool, 'action_summary' => $tool,
                'risk_level' => 2, 'requires_approval' => true, 'arguments_json' => $arguments,
                'status' => 'proposed', 'idempotency_key' => hash('sha256', $plan->id.'|'.$index),
            ]);
        }

        $this->actingAs($user)->post(route('vvr-ai.plans.approve', [$conversation, $plan]))
            ->assertSessionHasErrors('approval');

        $this->assertDatabaseCount('tasks', 0);
        $this->assertSame('failed', $plan->fresh()->status);
        $this->assertDatabaseHas('ai_audit_logs', ['action_plan_id' => $plan->id, 'event' => 'plan_failed']);
    }

    public function test_reapproving_completed_plan_is_idempotent(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $this->fakePlan([
            'intent' => 'create_follow_up_tasks', 'summary' => 'Create once.', 'missing_information' => [], 'warnings' => [],
            'tool_calls' => [['name' => 'create_task', 'summary' => 'Create one task.', 'arguments_json' => '{"title":"Only once"}']],
        ]);
        $this->actingAs($user)->post(route('vvr-ai.intakes.store'), ['prompt' => 'Create one task only', 'acknowledge_external_processing' => '1']);
        $conversation = AiConversation::query()->sole();
        $plan = AiActionPlan::query()->sole();

        $this->post(route('vvr-ai.plans.approve', [$conversation, $plan]))->assertRedirect();
        $this->post(route('vvr-ai.plans.approve', [$conversation, $plan]))->assertRedirect();

        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_model_cannot_request_a_tool_outside_the_users_allowlist(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        $this->fakePlan([
            'intent' => 'create_follow_up_tasks', 'summary' => 'Unauthorized write.', 'missing_information' => [], 'warnings' => [],
            'tool_calls' => [['name' => 'create_task', 'summary' => 'Bypass permissions.', 'arguments_json' => '{"title":"Forbidden"}']],
        ]);

        $this->actingAs($user)->post(route('vvr-ai.intakes.store'), ['prompt' => 'Create a task', 'acknowledge_external_processing' => '1'])
            ->assertSessionHasErrors('prompt');

        $this->assertDatabaseCount('ai_action_plans', 0);
        $this->assertDatabaseCount('tasks', 0);
        $this->assertSame('failed', AiConversation::query()->sole()->status);
    }

    private function fakePlan(array $plan): void
    {
        $this->app->instance(AiProviderInterface::class, new class($plan) implements AiProviderInterface
        {
            public function __construct(private readonly array $plan) {}
            public function isConfigured(): bool { return true; }
            public function generateStructured(array $schema, string $systemPrompt, string $userPrompt): AiStructuredResponse
            {
                return new AiStructuredResponse($this->plan, 'fake-plan', 20, 10);
            }
            public function generateStructuredFromDocument(AiDocumentInput $document, array $schema, string $systemPrompt, string $userPrompt): AiStructuredResponse
            {
                throw new \LogicException('Document extraction is not used in these tests.');
            }
        });
    }
}
