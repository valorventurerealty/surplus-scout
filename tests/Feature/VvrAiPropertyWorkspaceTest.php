<?php

namespace Tests\Feature;

use App\Contracts\PropertyDocumentExtractionInterface;
use App\Data\PropertyExtractionResult;
use App\Enums\UserRole;
use App\Models\AiConversation;
use App\Models\Property;
use App\Models\PropertyIntakeFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VvrAiPropertyWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['ai.provider' => 'gemini', 'ai.api_key' => 'test-key']);
    }

    public function test_manual_property_entry_remains_separate_from_ai_intake(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($user)->get(route('properties.create'))
            ->assertOk()
            ->assertSee('Add property manually')
            ->assertSee('Open VVR AI')
            ->assertDontSee('Autofill from a property document');
    }

    public function test_user_can_prompt_ai_review_and_approve_property_creation(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->mock(PropertyDocumentExtractionInterface::class)
            ->shouldReceive('extract')->once()->andReturn($this->extraction());

        $response = $this->actingAs($user)->post(route('vvr-ai.intakes.store'), [
            'prompt' => 'I bought this property. Prepare it from the facts in this request.',
            'acknowledge_external_processing' => '1',
        ]);

        $conversation = AiConversation::query()->sole();
        $intake = PropertyIntakeFile::query()->sole();
        $response->assertRedirect(route('vvr-ai.conversations.show', $conversation));
        $this->assertSame('awaiting_approval', $conversation->status);
        $this->assertSame('prompt', $intake->source_mode);
        $this->assertSame($conversation->id, $intake->ai_conversation_id);
        Storage::disk('local')->assertExists($intake->path);

        $this->get(route('vvr-ai.conversations.show', $conversation))
            ->assertOk()
            ->assertSee('Proposed property plan')
            ->assertSee('120 Bayberry Rd')
            ->assertSee('Approval required');

        $this->post(route('properties.store'), $this->propertyData([
            'intake_token' => $intake->token,
            'approve_extracted_data' => '1',
        ]))->assertRedirect(route('vvr-ai.conversations.show', $conversation));

        $property = Property::query()->sole();
        $this->assertSame('completed', $conversation->fresh()->status);
        $this->assertSame($property->id, data_get($conversation->fresh()->result_json, 'property_id'));
        $this->assertDatabaseHas('property_intake_files', [
            'id' => $intake->id,
            'property_id' => $property->id,
            'status' => 'attached',
        ]);
    }

    public function test_ai_property_write_requires_explicit_approval(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
        $intake = PropertyIntakeFile::factory()->create([
            'user_id' => $user->id,
            'ai_conversation_id' => $conversation->id,
        ]);

        $this->actingAs($user)->post(route('properties.store'), $this->propertyData([
            'intake_token' => $intake->token,
        ]))->assertSessionHasErrors('approve_extracted_data');

        $this->assertDatabaseCount('properties', 0);
        $this->assertSame('ready', $intake->fresh()->status);
    }

    public function test_ai_conversations_are_private_and_restricted_by_role(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $otherOwner = User::factory()->create(['role' => UserRole::Owner]);
        $conversation = AiConversation::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($otherOwner)->get(route('vvr-ai.conversations.show', $conversation))->assertForbidden();

        foreach ([UserRole::VirtualAssistant, UserRole::Marketing, UserRole::ReadOnly] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('vvr-ai.index'))->assertOk();
        }

        foreach ([UserRole::Marketing, UserRole::ReadOnly] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->post(route('vvr-ai.intakes.store'), [
                'prompt' => 'Create a property',
                'acknowledge_external_processing' => '1',
            ])->assertForbidden();
        }
    }

    private function extraction(): PropertyExtractionResult
    {
        return PropertyExtractionResult::fromArray([
            'fields' => [
                ['field' => 'parcel_id', 'value' => '31-12', 'confidence' => .99, 'page' => null, 'source_excerpt' => 'Parcel 31-12', 'verification_status' => 'extracted'],
                ['field' => 'address', 'value' => '120 Bayberry Rd', 'confidence' => .98, 'page' => null, 'source_excerpt' => '120 Bayberry Rd', 'verification_status' => 'extracted'],
                ['field' => 'city', 'value' => 'Georgetown', 'confidence' => .95, 'page' => null, 'source_excerpt' => 'Georgetown, FL', 'verification_status' => 'extracted'],
                ['field' => 'county', 'value' => 'Putnam', 'confidence' => .95, 'page' => null, 'source_excerpt' => 'Putnam County', 'verification_status' => 'extracted'],
                ['field' => 'state', 'value' => 'FL', 'confidence' => .99, 'page' => null, 'source_excerpt' => 'FL 32139', 'verification_status' => 'extracted'],
                ['field' => 'property_type', 'value' => 'land', 'confidence' => .8, 'page' => null, 'source_excerpt' => 'vacant land', 'verification_status' => 'extracted'],
            ],
            'missing_fields' => ['zoning', 'flood_zone'],
            'warnings' => ['Zoning was not supplied.'],
        ], 'gemini-test-response', 100, 50);
    }

    private function propertyData(array $overrides = []): array
    {
        return array_merge([
            'parcel_id' => '31-12', 'county' => 'Putnam', 'address' => '120 Bayberry Rd',
            'city' => 'Georgetown', 'state' => 'FL', 'postal_code' => '32139',
            'property_type' => 'land', 'status' => 'research', 'wetlands_status' => 'unknown',
        ], $overrides);
    }
}
