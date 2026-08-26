<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurplusScoutWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_open_surplus_scout(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)
            ->get(route('surplus-scout.index'))
            ->assertOk()
            ->assertSee('Surplus Scout')
            ->assertSee('Foundation ready');
    }

    public function test_user_without_surplus_access_cannot_open_surplus_scout(): void
    {
        $marketing = User::factory()->create(['role' => UserRole::Marketing]);

        $this->actingAs($marketing)
            ->get(route('surplus-scout.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('surplus-scout.index'))
            ->assertRedirect(route('login'));
    }
}
