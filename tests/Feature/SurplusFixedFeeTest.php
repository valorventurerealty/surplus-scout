<?php

namespace Tests\Feature;

use App\Enums\SurplusCaseStatus;
use App\Enums\UserRole;
use App\Models\SurplusCase;
use App\Models\User;
use App\Services\SurplusCaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurplusFixedFeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_always_uses_twelve_percent_and_recalculates_expected_fee(): void
    {
        $user = User::factory()->create();
        $service = app(SurplusCaseService::class);

        $case = $service->create([
            'surplus_amount' => 5315.97,
            'agreed_fee_percentage' => 3,
        ], $user);

        $this->assertSame('12.00', $case->agreed_fee_percentage);
        $this->assertSame('637.92', $case->expected_fee);

        $case = $service->update($case, [
            'surplus_amount' => 6000,
            'agreed_fee_percentage' => 5,
        ], $user);

        $this->assertSame('12.00', $case->agreed_fee_percentage);
        $this->assertSame('720.00', $case->expected_fee);
    }

    public function test_model_prevents_direct_eloquent_writes_from_using_another_percentage(): void
    {
        $case = SurplusCase::factory()->create([
            'surplus_amount' => 1000,
            'agreed_fee_percentage' => 1,
            'expected_fee' => 10,
        ]);

        $this->assertSame('12.00', $case->agreed_fee_percentage);
        $this->assertSame('120.00', $case->expected_fee);
    }

    public function test_surplus_form_displays_fixed_fee_without_editable_percentage_input(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($user);

        $view = $this->view('surplus._form', [
            'contacts' => collect(),
            'properties' => collect(),
            'assignees' => collect(),
            'statuses' => SurplusCaseStatus::cases(),
        ]);

        $view->assertSee('12.00%')
            ->assertSee('Fixed')
            ->assertDontSee('name="agreed_fee_percentage"', false);
    }
}
