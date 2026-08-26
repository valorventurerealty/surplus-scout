<?php

namespace Tests\Feature;

use App\Enums\NegotiationPlanStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\NegotiationPlan;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArmoryNegotiationWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_negotiations(): void
    {
        $this->get(route('armory.negotiations.index'))->assertRedirect(route('login'));
    }

    public function test_owner_can_create_bayberry_plan_and_view_exact_ladder(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $property = Property::factory()->create([
            'address' => '120 Bayberry Road',
            'all_in_amount' => 14100,
            'expected_sales_price' => 22000,
        ]);
        $buyer = Contact::factory()->create(['first_name' => 'Bayberry', 'last_name' => 'Buyer']);

        $response = $this->actingAs($owner)->post(route('armory.negotiations.store'), [
            'name' => '120 Bayberry Negotiation',
            'property_id' => $property->id,
            'buyer_contact_id' => $buyer->id,
            'status' => NegotiationPlanStatus::Active->value,
            'asking_price' => 22000,
            'all_in_amount' => 14100,
            'buyer_offer' => 16900,
            'counter_percent' => 77.5,
            'vvr_percentage' => 99,
            'notes' => 'Protect the core price ladder.',
        ]);

        $plan = NegotiationPlan::query()->firstOrFail();
        $response->assertRedirect(route('armory.negotiations.show', $plan));
        $this->assertSame('20.00', $plan->vvr_percentage);
        $this->assertSame('40.00', $plan->investor_one_percentage);
        $this->assertSame('40.00', $plan->investor_two_percentage);
        $this->assertTrue($plan->sync_property_financials);
        $this->assertNotNull($plan->financials_synced_at);
        $this->assertDatabaseHas(AuditLog::class, [
            'event' => 'created',
            'auditable_type' => $plan->getMorphClass(),
            'auditable_id' => $plan->id,
        ]);

        $this->actingAs($owner)->get(route('armory.negotiations.show', $plan))
            ->assertOk()
            ->assertSee('Core Price Ladder')
            ->assertSee('$22,000.00')
            ->assertSee('$14,100.00')
            ->assertSee('$7,900.00')
            ->assertSee('$1,580.00')
            ->assertSee('$3,160.00')
            ->assertSee('$16,900.00')
            ->assertSee('76.8%')
            ->assertSee('Counter Offer')
            ->assertSee('$17,050.00')
            ->assertSee('Counter');
    }

    public function test_manager_can_update_buyer_offer(): void
    {
        $manager = User::factory()->create(['role' => UserRole::DispositionManager]);
        $plan = NegotiationPlan::factory()->create(['buyer_offer' => null]);

        $this->actingAs($manager)->put(route('armory.negotiations.update', $plan), [
            'name' => $plan->name,
            'property_id' => $plan->property_id,
            'buyer_contact_id' => null,
            'status' => NegotiationPlanStatus::Active->value,
            'asking_price' => 22000,
            'all_in_amount' => 14100,
            'buyer_offer' => 16900,
            'counter_percent' => 80.0,
            'notes' => null,
        ])->assertRedirect(route('armory.negotiations.show', $plan));

        $plan->refresh();
        $this->assertSame('16900.00', $plan->buyer_offer);
        $this->assertSame('80.00', $plan->counter_percent);
    }

    public function test_manager_can_disable_property_sync_for_a_custom_scenario(): void
    {
        $manager = User::factory()->create(['role' => UserRole::DispositionManager]);
        $property = Property::factory()->create(['expected_sales_price' => 22000, 'all_in_amount' => 14100]);
        $plan = NegotiationPlan::factory()->create(['property_id' => $property->id, 'sync_property_financials' => true]);

        $this->actingAs($manager)->put(route('armory.negotiations.update', $plan), [
            'name' => $plan->name,
            'property_id' => $property->id,
            'sync_property_financials' => 0,
            'buyer_contact_id' => null,
            'status' => NegotiationPlanStatus::Active->value,
            'asking_price' => 25000,
            'all_in_amount' => 15000,
            'buyer_offer' => null,
            'counter_percent' => null,
            'notes' => null,
        ])->assertRedirect(route('armory.negotiations.show', $plan));

        $plan->refresh();
        $this->assertFalse($plan->sync_property_financials);
        $this->assertSame('25000.00', $plan->asking_price);
        $this->assertSame('15000.00', $plan->all_in_amount);
        $this->assertNull($plan->financials_synced_at);
    }

    public function test_virtual_assistant_and_read_only_user_cannot_manage_negotiations(): void
    {
        $plan = NegotiationPlan::factory()->create();

        foreach ([UserRole::VirtualAssistant, UserRole::ReadOnly] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('armory.negotiations.show', $plan))->assertOk();
            $this->actingAs($user)->get(route('armory.negotiations.create'))->assertForbidden();
            $this->actingAs($user)->put(route('armory.negotiations.update', $plan), [])->assertForbidden();
            $this->actingAs($user)->delete(route('armory.negotiations.destroy', $plan))->assertForbidden();
        }

        $this->assertNotSoftDeleted($plan);
    }

    public function test_invalid_property_or_buyer_ids_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('armory.negotiations.store'), [
            'name' => 'Invalid links',
            'property_id' => 999999,
            'buyer_contact_id' => 999999,
            'status' => NegotiationPlanStatus::Draft->value,
            'asking_price' => 22000,
            'all_in_amount' => 14100,
        ])->assertSessionHasErrors(['property_id', 'buyer_contact_id']);

        $this->assertDatabaseCount('negotiation_plans', 0);
    }

    public function test_counter_percentage_must_be_a_core_ladder_percentage(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('armory.negotiations.store'), [
            'name' => 'Invalid counter percentage',
            'status' => NegotiationPlanStatus::Draft->value,
            'asking_price' => 22000,
            'all_in_amount' => 14100,
            'counter_percent' => 76.8,
        ])->assertSessionHasErrors('counter_percent');

        $this->assertDatabaseCount('negotiation_plans', 0);
    }
}
