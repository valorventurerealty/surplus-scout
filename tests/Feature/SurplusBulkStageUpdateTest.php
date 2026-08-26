<?php

namespace Tests\Feature;

use App\Enums\SurplusCaseStatus;
use App\Enums\UserRole;
use App\Models\SurplusCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurplusBulkStageUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_change_the_stage_of_selected_cases_only(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AcquisitionManager]);
        $selected = SurplusCase::factory()->count(2)->create(['status' => SurplusCaseStatus::Research]);
        $unselected = SurplusCase::factory()->create(['status' => SurplusCaseStatus::Research]);

        $this->actingAs($manager)->patch(route('surplus.bulk-stage'), [
            'operation' => 'stage',
            'case_ids' => $selected->modelKeys(),
            'status' => SurplusCaseStatus::MailerSent->value,
        ])->assertRedirect()->assertSessionHas('success');

        foreach ($selected as $case) {
            $this->assertSame(SurplusCaseStatus::MailerSent, $case->fresh()->status);
            $this->assertDatabaseHas('audit_logs', [
                'user_id' => $manager->id,
                'event' => 'updated',
                'auditable_type' => $case->getMorphClass(),
                'auditable_id' => $case->id,
            ]);
        }

        $this->assertSame(SurplusCaseStatus::Research, $unselected->fresh()->status);
    }

    public function test_bulk_paid_stage_sets_a_missing_paid_date(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $case = SurplusCase::factory()->create([
            'status' => SurplusCaseStatus::Approved,
            'paid_at' => null,
        ]);

        $this->actingAs($owner)->patch(route('surplus.bulk-stage'), [
            'operation' => 'stage',
            'case_ids' => [$case->id],
            'status' => SurplusCaseStatus::Paid->value,
        ])->assertRedirect();

        $case->refresh();
        $this->assertSame(SurplusCaseStatus::Paid, $case->status);
        $this->assertSame(today()->toDateString(), $case->paid_at->toDateString());
    }

    public function test_read_only_user_cannot_bulk_change_surplus_stages(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        $case = SurplusCase::factory()->create(['status' => SurplusCaseStatus::Research]);

        $this->actingAs($user)->patch(route('surplus.bulk-stage'), [
            'operation' => 'stage',
            'case_ids' => [$case->id],
            'status' => SurplusCaseStatus::Closed->value,
        ])->assertForbidden();

        $this->assertSame(SurplusCaseStatus::Research, $case->fresh()->status);
    }

    public function test_bulk_stage_requires_a_valid_selection_and_stage(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)->from(route('surplus.index'))->patch(route('surplus.bulk-stage'), [
            'operation' => 'stage',
            'case_ids' => [],
            'status' => 'not-a-stage',
        ])->assertRedirect(route('surplus.index'))
            ->assertSessionHasErrors(['case_ids', 'status']);
    }

    public function test_manager_can_change_the_county_of_selected_cases_only(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AcquisitionManager]);
        $selected = SurplusCase::factory()->count(2)->create([
            'state' => 'FL',
            'county' => 'Orange',
            'tax_deed_number' => null,
            'foreclosure_case_number' => null,
        ]);
        $unselected = SurplusCase::factory()->create(['state' => 'FL', 'county' => 'Orange']);

        $this->actingAs($manager)->patch(route('surplus.bulk-stage'), [
            'operation' => 'county',
            'case_ids' => $selected->modelKeys(),
            'county' => '  Osceola County  ',
        ])->assertRedirect()->assertSessionHas('success');

        foreach ($selected as $case) {
            $this->assertSame('Osceola', $case->fresh()->county);
            $this->assertDatabaseHas('audit_logs', [
                'user_id' => $manager->id,
                'event' => 'updated',
                'auditable_type' => $case->getMorphClass(),
                'auditable_id' => $case->id,
            ]);
        }

        $this->assertSame('Orange', $unselected->fresh()->county);
    }

    public function test_bulk_controls_bind_stage_and_county_to_reactive_state(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AcquisitionManager]);
        SurplusCase::factory()->create(['county' => 'Orange']);

        $this->actingAs($manager)->get(route('surplus.index'))
            ->assertOk()
            ->assertSee('x-model="bulkStage"', false)
            ->assertSee('x-model="bulkCounty"', false)
            ->assertSee("!bulkCounty.trim()", false);
    }

    public function test_bulk_county_change_rejects_duplicate_case_identifiers_atomically(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        SurplusCase::factory()->create([
            'state' => 'FL',
            'county' => 'Osceola',
            'tax_deed_number' => 'TD-100',
        ]);
        $selected = SurplusCase::factory()->create([
            'state' => 'FL',
            'county' => 'Orange',
            'tax_deed_number' => 'TD-100',
        ]);

        $this->actingAs($owner)->from(route('surplus.index'))->patch(route('surplus.bulk-stage'), [
            'operation' => 'county',
            'case_ids' => [$selected->id],
            'county' => 'Osceola',
        ])->assertRedirect(route('surplus.index'))->assertSessionHasErrors('county');

        $this->assertSame('Orange', $selected->fresh()->county);
    }
}
