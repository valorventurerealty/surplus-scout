<?php

namespace Tests\Feature;

use App\Enums\PreAuctionAcquisitionStatus;
use App\Enums\PreAuctionEntitlementStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\PreAuctionAcquisition;
use App\Models\Property;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreAuctionAcquisitionWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_create_a_separate_pre_auction_file_with_deterministic_economics(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $owner = Contact::factory()->create();
        $property = Property::factory()->create();

        $response = $this->actingAs($user)->post(route('pre-auction.store'), $this->validData([
            'owner_contact_id' => $owner->id, 'property_id' => $property->id,
            'purchase_price' => 14500, 'closing_costs' => 750, 'other_costs' => 250,
            'projected_surplus' => 25000, 'amount_recovered' => 24000,
        ]));

        $case = PreAuctionAcquisition::query()->firstOrFail();
        $response->assertRedirect(route('pre-auction.show', $case));
        $this->assertStringStartsWith('PAQ-', $case->case_number);
        $this->assertSame('15500.00', $case->total_acquisition_cost);
        $this->assertSame('9500.00', $case->projected_net);
        $this->assertSame('8500.00', $case->actual_net);
        $this->assertDatabaseHas('contact_pre_auction_acquisition', ['pre_auction_acquisition_id' => $case->id, 'contact_id' => $owner->id, 'role' => 'owner']);
        $this->assertDatabaseHas(AuditLog::class, ['event' => 'created', 'auditable_type' => $case->getMorphClass(), 'auditable_id' => $case->id]);
    }

    public function test_pre_auction_file_is_limited_to_florida_and_requires_documented_entitlement_review(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($user)->post(route('pre-auction.store'), $this->validData([
            'state' => 'GA', 'entitlement_status' => PreAuctionEntitlementStatus::Eligible->value,
            'entitlement_notes' => null,
        ]))->assertSessionHasErrors(['state', 'entitlement_notes']);

        $this->assertSame(0, PreAuctionAcquisition::query()->count());
    }

    public function test_marketing_cannot_view_and_read_only_cannot_manage_pre_auction_files(): void
    {
        $case = PreAuctionAcquisition::factory()->create();
        $marketing = User::factory()->create(['role' => UserRole::Marketing]);
        $readOnly = User::factory()->create(['role' => UserRole::ReadOnly]);

        $this->actingAs($marketing)->get(route('pre-auction.index'))->assertForbidden();
        $this->actingAs($readOnly)->get(route('pre-auction.show', $case))->assertOk();
        $this->actingAs($readOnly)->get(route('pre-auction.edit', $case))->assertForbidden();
    }

    public function test_task_can_be_linked_to_a_pre_auction_file(): void
    {
        $user = User::factory()->create(['role' => UserRole::AcquisitionManager]);
        $case = PreAuctionAcquisition::factory()->create();

        $this->actingAs($user)->post(route('tasks.store'), [
            'title' => 'Verify deed recording', 'status' => 'pending', 'priority' => 'high',
            'subject' => 'pre_auction:'.$case->id, 'recurrence_interval' => 1,
        ])->assertRedirect();

        $task = Task::query()->firstOrFail();
        $this->assertInstanceOf(PreAuctionAcquisition::class, $task->taskable);
        $this->assertSame($case->id, $task->taskable_id);
    }

    public function test_duplicate_county_tax_deed_number_is_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        PreAuctionAcquisition::factory()->create(['county' => 'Orange', 'tax_deed_number' => '256-2026']);

        $this->actingAs($user)->post(route('pre-auction.store'), $this->validData([
            'county' => 'Orange', 'tax_deed_number' => '256-2026',
        ]))->assertSessionHasErrors('tax_deed_number');
    }

    private function validData(array $overrides = []): array
    {
        return array_replace([
            'status' => PreAuctionAcquisitionStatus::Research->value,
            'state' => 'FL', 'county' => 'Orange', 'parcel_id' => '11-22-33-4444-5555-6666',
            'tax_deed_number' => 'TD-2026-100', 'auction_at' => now()->addMonths(2)->format('Y-m-d\TH:i'),
            'purchase_deadline' => now()->addMonth()->format('Y-m-d'),
            'entitlement_status' => PreAuctionEntitlementStatus::NotReviewed->value,
        ], $overrides);
    }
}
