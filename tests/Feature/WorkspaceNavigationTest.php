<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_secure_vvr_drive_link(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Drive')
            ->assertSee(config('vvr.drive_url'))
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_invalid_or_non_https_drive_configuration_is_not_rendered(): void
    {
        config(['vvr.drive_url' => 'javascript:alert(1)']);
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('javascript:alert(1)', false);
    }

    public function test_authenticated_user_sees_secure_mailers_workspace_link(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mailers')
            ->assertSee(config('vvr.mailers_url'))
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_invalid_or_non_https_mailers_configuration_is_not_rendered(): void
    {
        config(['vvr.mailers_url' => 'javascript:alert(1)']);
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('javascript:alert(1)', false);
    }

    public function test_pre_auction_navigation_respects_department_permissions(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $marketing = User::factory()->create(['role' => UserRole::Marketing]);

        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertSee('PreTax Auctions');
        $this->actingAs($marketing)->get(route('dashboard'))->assertOk()->assertDontSee('PreTax Auctions');
    }

    public function test_owner_navigation_is_grouped_in_operating_order(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-compact-workspace-navigation', false)
            ->assertSeeInOrder([
                'Daily Command',
                'Dashboard',
                'Tasks',
                'Calendar',
                'Pipeline',
                'Communication',
                'Contacts',
                'Phone Calls',
                'Email',
                'Mailers',
                'Revenue / Operations',
                'Surplus',
                'PreTax Auctions',
                'Properties',
                'Deals',
                'Management / Tools',
                'Financials',
                'VVR AI',
                'SOPs',
                'Armory',
                'Drive',
            ])
            ->assertDontSee('Coming milestones');
    }
}
