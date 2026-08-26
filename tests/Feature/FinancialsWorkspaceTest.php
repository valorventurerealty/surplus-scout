<?php

namespace Tests\Feature;

use App\Enums\PropertyStatus;
use App\Enums\SurplusCaseStatus;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Property;
use App\Models\PropertyFinancialSplit;
use App\Models\SurplusCase;
use App\Models\NegotiationPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialsWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_financials_workspace(): void
    {
        $this->get(route('financials.index'))->assertRedirect(route('login'));
    }

    public function test_financial_roles_can_open_the_active_workspace(): void
    {
        foreach ([UserRole::Owner, UserRole::Partner, UserRole::AcquisitionManager, UserRole::DispositionManager, UserRole::Admin] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('financials.index'))
                ->assertOk()
                ->assertSee('Financial operations workspace')
                ->assertSee('Active')
                ->assertSeeInOrder(['Portfolio Value', 'Total all-in'])
                ->assertSee('20% to VVR', false);
        }
    }

    public function test_bayberry_example_is_calculated_and_split_20_40_40(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $firstContact = Contact::factory()->create(['first_name' => 'Investor', 'last_name' => 'One']);
        $secondContact = Contact::factory()->create(['first_name' => 'Investor', 'last_name' => 'Two']);
        $property = Property::factory()->create(['address' => '120 Bayberry Road']);

        $response = $this->actingAs($user)->put(route('financials.properties.update', $property), [
            'purchase_price' => 14500,
            'taxes' => 0,
            'attorney_fees' => 0,
            'realtor_fees' => 0,
            'other_costs' => 0,
            'all_in_amount' => 1,
            'expected_sales_price' => 22000,
            'actual_sales_price' => null,
            'contact_one_id' => $firstContact->id,
            'contact_two_id' => $secondContact->id,
        ]);

        $response->assertRedirect(route('financials.properties.edit', $property));
        $property->refresh();
        $this->assertSame('7500.00', $property->expected_profit);
        $this->assertNull($property->actual_profit);
        $this->assertDatabaseHas('property_financial_splits', [
            'property_id' => $property->id,
            'vvr_percentage' => 20,
            'contact_one_id' => $firstContact->id,
            'contact_one_percentage' => 40,
            'contact_two_id' => $secondContact->id,
            'contact_two_percentage' => 40,
        ]);

        $this->actingAs($user)->get(route('financials.index'))
            ->assertOk()
            ->assertSee('120 Bayberry Road')
            ->assertSee('$7,500.00')
            ->assertSee('$1,500.00')
            ->assertSee('$3,000.00')
            ->assertSee('Investor One')
            ->assertSee('Investor Two');
    }

    public function test_financial_editor_recalculates_all_in_and_profits_from_cost_components(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $property = Property::factory()->create();

        $this->actingAs($user)->put(route('financials.properties.update', $property), [
            'purchase_price' => 10000,
            'taxes' => 500,
            'attorney_fees' => 750,
            'realtor_fees' => 1000,
            'other_costs' => 250,
            'all_in_amount' => 1,
            'expected_sales_price' => 20000,
            'actual_sales_price' => 19000,
        ])->assertRedirect(route('financials.properties.edit', $property));

        $property->refresh();
        $this->assertSame('12500.00', $property->all_in_amount);
        $this->assertSame('7500.00', $property->expected_profit);
        $this->assertSame('6500.00', $property->actual_profit);
    }

    public function test_sales_price_only_update_recalculates_profit_and_syncs_linked_armory_plan(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $property = Property::factory()->create([
            'purchase_price' => 10000,
            'taxes' => 500,
            'attorney_fees' => 500,
            'realtor_fees' => 0,
            'other_costs' => 0,
            'all_in_amount' => 11000,
            'expected_sales_price' => 18000,
            'expected_profit' => 7000,
        ]);
        $plan = NegotiationPlan::factory()->create([
            'property_id' => $property->id,
            'sync_property_financials' => true,
            'asking_price' => 18000,
            'all_in_amount' => 11000,
        ]);

        $this->actingAs($user)->put(route('financials.properties.update', $property), [
            'expected_sales_price' => 22000,
        ])->assertRedirect(route('financials.properties.edit', $property));

        $property->refresh();
        $plan->refresh();
        $this->assertSame('11000.00', $property->all_in_amount);
        $this->assertSame('11000.00', $property->expected_profit);
        $this->assertSame('22000.00', $plan->asking_price);
        $this->assertSame('11000.00', $plan->all_in_amount);
        $this->assertNotNull($plan->financials_synced_at);
    }

    public function test_custom_armory_plan_is_not_changed_by_property_financial_update(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $property = Property::factory()->create([
            'purchase_price' => 10000,
            'all_in_amount' => 10000,
            'expected_sales_price' => 18000,
        ]);
        $plan = NegotiationPlan::factory()->create([
            'property_id' => $property->id,
            'sync_property_financials' => false,
            'asking_price' => 25000,
            'all_in_amount' => 12000,
        ]);

        $this->actingAs($user)->put(route('financials.properties.update', $property), [
            'expected_sales_price' => 22000,
        ])->assertRedirect(route('financials.properties.edit', $property));

        $plan->refresh();
        $this->assertSame('25000.00', $plan->asking_price);
        $this->assertSame('12000.00', $plan->all_in_amount);
    }

    public function test_projected_totals_only_include_owned_through_under_contract_stages(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $included = [
            PropertyStatus::Owned,
            PropertyStatus::ActivelyWorking,
            PropertyStatus::Marketing,
            PropertyStatus::UnderContract,
        ];
        $excluded = [
            PropertyStatus::Research,
            PropertyStatus::Bidding,
            PropertyStatus::Sold,
            PropertyStatus::Archived,
        ];

        foreach ($included as $status) {
            Property::factory()->create([
                'status' => $status,
                'expected_sales_price' => 250000,
                'all_in_amount' => 100000,
                'expected_profit' => 150000,
                'actual_sales_price' => 50000,
                'actual_profit' => 10000,
            ]);
        }
        foreach ($excluded as $status) {
            Property::factory()->create([
                'status' => $status,
                'expected_sales_price' => 999999,
                'all_in_amount' => 777777,
                'expected_profit' => 222222,
                'actual_sales_price' => 50000,
                'actual_profit' => 10000,
            ]);
        }

        $response = $this->actingAs($user)->get(route('financials.index'));

        $response->assertOk()->assertSee('Research, Bidding, and Archived properties are excluded from every property summary value.');
        $totals = $response->viewData('totals');
        $this->assertEquals(1000000, (float) $totals->expected_sales_price);
        $this->assertEquals(400000, (float) $totals->all_in_amount);
        $this->assertEquals(600000, (float) $totals->expected_profit);
        $this->assertEquals(250000, (float) $totals->actual_sales_price);
        $this->assertEquals(50000, (float) $totals->actual_profit);
    }

    public function test_paid_surplus_actual_fee_flows_to_combined_actual_profit_without_entering_portfolio(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        Property::factory()->create([
            'status' => PropertyStatus::Sold,
            'actual_sales_price' => 50000,
            'actual_profit' => 10000,
            'expected_sales_price' => 999999,
            'all_in_amount' => 777777,
            'expected_profit' => 222222,
        ]);
        $paid = SurplusCase::factory()->create([
            'status' => SurplusCaseStatus::Paid,
            'surplus_amount' => 25000,
            'recovered_amount' => 25000,
            'actual_fee' => 3000,
            'paid_at' => today(),
        ]);
        SurplusCase::factory()->create([
            'status' => SurplusCaseStatus::Approved,
            'surplus_amount' => 50000,
            'actual_fee' => 6000,
            'paid_at' => null,
        ]);

        $response = $this->actingAs($user)->get(route('financials.index'));

        $response->assertOk()
            ->assertSee('Property actual profit')->assertSee('$10,000.00')
            ->assertSee('Surplus realized profit')->assertSee('$3,000.00')
            ->assertSee('Combined actual profit')->assertSee('$13,000.00')
            ->assertSee('Claimant money recovered')->assertSee('$25,000.00')
            ->assertSee($paid->case_number);
        $this->assertEquals(0, (float) $response->viewData('totals')->expected_sales_price);
        $this->assertEquals(3000, $response->viewData('surplusRealizedProfit'));
        $this->assertEquals(13000, $response->viewData('combinedActualProfit'));
    }

    public function test_paid_surplus_without_actual_fee_is_flagged_for_reconciliation(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        SurplusCase::factory()->create([
            'status' => SurplusCaseStatus::Paid, 'paid_at' => today(), 'actual_fee' => null,
        ]);

        $this->actingAs($user)->get(route('financials.index'))->assertOk()
            ->assertSee('1 paid case missing actual fee')
            ->assertSee('Needs reconciliation');
    }

    public function test_financial_user_without_surplus_financial_permission_cannot_see_surplus_receipts(): void
    {
        $user = User::factory()->create(['role' => UserRole::DispositionManager]);
        $case = SurplusCase::factory()->create([
            'status' => SurplusCaseStatus::Paid, 'paid_at' => today(),
            'recovered_amount' => 43210.98, 'actual_fee' => 5185.32,
        ]);

        $this->actingAs($user)->get(route('financials.index'))->assertOk()
            ->assertSee('Actual profit')
            ->assertDontSee('Surplus realized profit')
            ->assertDontSee($case->case_number)
            ->assertDontSee('43,210.98')
            ->assertDontSee('5,185.32');
    }

    public function test_same_contact_cannot_receive_both_40_percent_shares(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $contact = Contact::factory()->create();
        $property = Property::factory()->create();

        $this->actingAs($user)->put(route('financials.properties.update', $property), [
            'all_in_amount' => 14500,
            'expected_sales_price' => 22000,
            'contact_one_id' => $contact->id,
            'contact_two_id' => $contact->id,
        ])->assertSessionHasErrors(['contact_one_id', 'contact_two_id']);

        $this->assertDatabaseCount('property_financial_splits', 0);
    }

    public function test_restricted_roles_cannot_see_open_or_update_financials(): void
    {
        $property = Property::factory()->create();

        foreach ([UserRole::VirtualAssistant, UserRole::Marketing, UserRole::ReadOnly] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee(route('financials.index'), false);
            $this->actingAs($user)->get(route('financials.index'))->assertForbidden();
            $this->actingAs($user)->put(route('financials.properties.update', $property), [
                'all_in_amount' => 1,
                'expected_sales_price' => 2,
            ])->assertForbidden();
        }

        $this->assertDatabaseCount(PropertyFinancialSplit::class, 0);
    }
}
