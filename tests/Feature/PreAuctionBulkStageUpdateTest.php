<?php

namespace Tests\Feature;

use App\Enums\PreAuctionAcquisitionStatus;
use App\Enums\UserRole;
use App\Models\PreAuctionAcquisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreAuctionBulkStageUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_change_the_stage_of_selected_files_only(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AcquisitionManager]);
        $selected = PreAuctionAcquisition::factory()->count(2)->create([
            'status' => PreAuctionAcquisitionStatus::Research,
        ]);
        $unselected = PreAuctionAcquisition::factory()->create([
            'status' => PreAuctionAcquisitionStatus::Research,
        ]);

        $this->actingAs($manager)->patch(route('pre-auction.bulk-stage'), [
            'case_ids' => $selected->modelKeys(),
            'status' => PreAuctionAcquisitionStatus::Outreach->value,
        ])->assertRedirect()->assertSessionHas('success');

        foreach ($selected as $case) {
            $this->assertSame(PreAuctionAcquisitionStatus::Outreach, $case->fresh()->status);
            $this->assertDatabaseHas('audit_logs', [
                'user_id' => $manager->id,
                'event' => 'updated',
                'auditable_type' => $case->getMorphClass(),
                'auditable_id' => $case->id,
            ]);
        }

        $this->assertSame(PreAuctionAcquisitionStatus::Research, $unselected->fresh()->status);
    }

    public function test_read_only_user_cannot_bulk_change_pre_auction_stages(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        $case = PreAuctionAcquisition::factory()->create([
            'status' => PreAuctionAcquisitionStatus::Research,
        ]);

        $this->actingAs($user)->patch(route('pre-auction.bulk-stage'), [
            'case_ids' => [$case->id],
            'status' => PreAuctionAcquisitionStatus::Closed->value,
        ])->assertForbidden();

        $this->assertSame(PreAuctionAcquisitionStatus::Research, $case->fresh()->status);
    }

    public function test_bulk_stage_requires_a_valid_selection_and_stage(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)->from(route('pre-auction.index'))->patch(route('pre-auction.bulk-stage'), [
            'case_ids' => [],
            'status' => 'not-a-stage',
        ])->assertRedirect(route('pre-auction.index'))
            ->assertSessionHasErrors(['case_ids', 'status']);
    }

    public function test_manager_sees_bulk_stage_controls_on_the_workspace(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AcquisitionManager]);
        PreAuctionAcquisition::factory()->create();

        $this->actingAs($manager)->get(route('pre-auction.index'))
            ->assertOk()
            ->assertSee('x-model="bulkStage"', false)
            ->assertSee('Select all PreTax Auction files on this page')
            ->assertSee('Change stages');
    }

    public function test_read_only_user_does_not_see_bulk_stage_controls(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        PreAuctionAcquisition::factory()->create();

        $this->actingAs($user)->get(route('pre-auction.index'))
            ->assertOk()
            ->assertDontSee('x-model="bulkStage"', false)
            ->assertDontSee('Select all PreTax Auction files on this page')
            ->assertDontSee('Change stages');
    }
}
