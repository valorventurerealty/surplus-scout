<?php

namespace Tests\Feature;

use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_displays_every_status_in_operating_order(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        $property = Property::factory()->create([
            'address' => '120 Bayberry Road',
            'status' => PropertyStatus::UnderContract,
        ]);

        $response = $this->actingAs($user)->get(route('pipeline.index'));

        $response->assertOk()->assertSee('120 Bayberry Road');
        $html = $response->getContent();
        $positions = array_map(fn (PropertyStatus $status): int|false => strpos($html, 'stage-'.$status->value), PropertyStatus::cases());
        $sortedPositions = $positions;
        sort($sortedPositions);

        $this->assertNotContains(false, $positions);
        $this->assertSame($positions, $sortedPositions);
        $this->assertSame(PropertyStatus::UnderContract, $property->fresh()->status);
    }

    public function test_pipeline_uses_stacked_sections_without_horizontal_board_scrolling(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);

        $this->actingAs($user)->get(route('pipeline.index'))
            ->assertOk()
            ->assertSee('data-pipeline-layout="stacked"', false)
            ->assertDontSee('grid-flow-col', false)
            ->assertDontSee('auto-cols-[310px]', false)
            ->assertDontSee('min-w-max', false);
    }

    public function test_authorized_user_can_move_property_to_under_contract(): void
    {
        $user = User::factory()->create(['role' => UserRole::DispositionManager]);
        $property = Property::factory()->create(['status' => PropertyStatus::Marketing]);

        $this->actingAs($user)->patch(route('pipeline.properties.update', $property), [
            'status' => PropertyStatus::UnderContract->value,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(PropertyStatus::UnderContract, $property->fresh()->status);
        $this->assertSame($user->id, $property->fresh()->updated_by);
        $this->assertDatabaseHas(AuditLog::class, [
            'event' => 'updated',
            'auditable_type' => $property->getMorphClass(),
            'auditable_id' => $property->id,
        ]);
    }

    public function test_read_only_user_can_view_pipeline_but_cannot_move_property(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        $property = Property::factory()->create(['status' => PropertyStatus::Research]);

        $this->actingAs($user)->get(route('pipeline.index'))->assertOk();
        $this->actingAs($user)->patch(route('pipeline.properties.update', $property), [
            'status' => PropertyStatus::Bidding->value,
        ])->assertForbidden();

        $this->assertSame(PropertyStatus::Research, $property->fresh()->status);
    }

    public function test_pipeline_filters_properties(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        Property::factory()->create(['address' => '120 Bayberry Road', 'state' => 'FL']);
        Property::factory()->create(['address' => '88 Hidden Trail', 'state' => 'GA']);

        $this->actingAs($user)->get(route('pipeline.index', ['search' => 'Bayberry', 'state' => 'FL']))
            ->assertOk()
            ->assertSee('120 Bayberry Road')
            ->assertDontSee('88 Hidden Trail');
    }

    public function test_financial_pipeline_value_is_hidden_without_permission(): void
    {
        $property = Property::factory()->create([
            'status' => PropertyStatus::Owned,
            'expected_sales_price' => 987654.32,
        ]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $virtualAssistant = User::factory()->create(['role' => UserRole::VirtualAssistant]);

        $this->actingAs($owner)->get(route('pipeline.index'))
            ->assertOk()
            ->assertSee('Portfolio value')
            ->assertSee('$987,654.32');

        $this->actingAs($virtualAssistant)->get(route('pipeline.index'))
            ->assertOk()
            ->assertDontSee('Portfolio value')
            ->assertDontSee('$987,654.32');
    }

    public function test_invalid_pipeline_status_is_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $property = Property::factory()->create(['status' => PropertyStatus::Research]);

        $this->actingAs($user)->patch(route('pipeline.properties.update', $property), [
            'status' => 'not-a-real-stage',
        ])->assertSessionHasErrors('status');

        $this->assertSame(PropertyStatus::Research, $property->fresh()->status);
    }
}
