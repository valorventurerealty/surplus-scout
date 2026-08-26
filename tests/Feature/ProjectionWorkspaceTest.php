<?php

namespace Tests\Feature;

use App\Enums\ProjectionCategory;
use App\Enums\ProjectionScenarioStatus;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\ProjectionScenario;
use App\Models\User;
use App\Services\ProjectionCalculator;
use App\Services\ProjectionScenarioService;
use Database\Seeders\ProjectionScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectionWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_workbook_scenario_reconciles_to_the_original_five_year_profit_pool_and_fixed_split(): void
    {
        User::factory()->create(['role' => UserRole::Owner]);
        $this->seed(ProjectionScenarioSeeder::class);
        $scenario = ProjectionScenario::query()->firstOrFail();

        $summary = app(ProjectionCalculator::class)->summarize($scenario);

        $this->assertSame(287120000, $summary['grand']['total']);
        $this->assertSame(57424000, $summary['grand']['vvr']);
        $this->assertSame(114848000, $summary['grand']['contact_one']);
        $this->assertSame(114848000, $summary['grand']['contact_two']);
        $this->assertSame(7720000, $summary['years'][2026]['total']);
        $this->assertSame(117600000, $summary['years'][2030]['total']);
    }

    public function test_financially_restricted_user_cannot_view_projections(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);

        $this->actingAs($user)->get(route('projections.index'))->assertForbidden();
    }

    public function test_owner_can_update_monthly_units_and_all_splits_recalculate(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $scenario = app(ProjectionScenarioService::class)->create([
            'name' => 'One-year plan',
            'status' => ProjectionScenarioStatus::Active->value,
            'start_year' => 2026,
            'end_year' => 2026,
            'contact_one_id' => null,
            'contact_two_id' => null,
            'notes' => null,
        ], $owner);
        $payload = $this->payload($scenario);
        $payload['entries']['land_flip'][2026][1] = 2;

        $this->actingAs($owner)->put(route('projections.update', $scenario), $payload)
            ->assertRedirect(route('projections.index', ['scenario' => $scenario->token]));

        $summary = app(ProjectionCalculator::class)->summarize($scenario->refresh());
        $this->assertSame(2000000, $summary['grand']['total']);
        $this->assertSame(400000, $summary['grand']['vvr']);
        $this->assertSame(800000, $summary['grand']['contact_one']);
        $this->assertSame(800000, $summary['grand']['contact_two']);
    }

    public function test_projection_views_show_a_total_at_the_bottom_of_each_year(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $scenario = app(ProjectionScenarioService::class)->create([
            'name' => 'Annual totals plan',
            'status' => ProjectionScenarioStatus::Active->value,
            'start_year' => 2026,
            'end_year' => 2027,
            'contact_one_id' => null,
            'contact_two_id' => null,
            'notes' => null,
        ], $owner);

        $this->actingAs($owner)
            ->get(route('projections.edit', $scenario))
            ->assertOk()
            ->assertSee('2026 total')
            ->assertSee('2027 total')
            ->assertSee('Projected total');

        $this->actingAs($owner)
            ->get(route('projections.index', ['scenario' => $scenario->token]))
            ->assertOk()
            ->assertSee('2026 total')
            ->assertSee('2027 total');
    }

    public function test_same_contact_cannot_receive_both_projected_forty_percent_shares(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $contact = Contact::factory()->create();
        $scenario = app(ProjectionScenarioService::class)->create([
            'name' => 'Contact validation',
            'status' => ProjectionScenarioStatus::Draft->value,
            'start_year' => 2026,
            'end_year' => 2026,
            'contact_one_id' => null,
            'contact_two_id' => null,
            'notes' => null,
        ], $owner);
        $payload = $this->payload($scenario);
        $payload['contact_one_id'] = $contact->id;
        $payload['contact_two_id'] = $contact->id;

        $this->actingAs($owner)->from(route('projections.edit', $scenario))
            ->put(route('projections.update', $scenario), $payload)
            ->assertRedirect(route('projections.edit', $scenario))
            ->assertSessionHasErrors(['contact_one_id', 'contact_two_id']);
    }

    /** @return array<string, mixed> */
    private function payload(ProjectionScenario $scenario): array
    {
        $entries = [];
        $assumptions = [];
        foreach (ProjectionCategory::cases() as $category) {
            $assumptions[$category->value] = $category->defaultAverageNetProfit();
            foreach ($scenario->years() as $year) {
                foreach (range(1, 12) as $month) {
                    $entries[$category->value][$year][$month] = 0;
                }
            }
        }

        return [
            'name' => $scenario->name,
            'status' => $scenario->status->value,
            'start_year' => $scenario->start_year,
            'end_year' => $scenario->end_year,
            'contact_one_id' => null,
            'contact_two_id' => null,
            'notes' => null,
            'assumptions' => $assumptions,
            'entries' => $entries,
        ];
    }
}
