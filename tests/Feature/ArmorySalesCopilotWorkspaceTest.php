<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArmorySalesCopilotWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('armory.sales-copilot.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_armory_user_can_open_sales_copilot(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner, 'is_active' => true]);

        $this->actingAs($user)->get(route('armory.sales-copilot.index'))
            ->assertOk()
            ->assertSee('VVR Sales Copilot')
            ->assertSee('What did they say?')
            ->assertSee('Knowledge setup next')
            ->assertSee('Not processing yet');
    }

    public function test_inactive_user_cannot_open_sales_copilot(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner, 'is_active' => false]);

        $this->actingAs($user)->get(route('armory.sales-copilot.index'))->assertForbidden();
    }

    public function test_armory_navigation_contains_sales_copilot_link(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner, 'is_active' => true]);

        $this->actingAs($user)->get(route('armory.index'))
            ->assertOk()
            ->assertSee(route('armory.sales-copilot.index'), false)
            ->assertSee('VVR Sales Copilot');
    }
}
