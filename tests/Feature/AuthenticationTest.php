<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_is_available(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_active_user_can_authenticate(): void
    {
        $user = User::factory()->create(['password' => 'Correct-Horse-42']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Correct-Horse-42'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        $user = User::factory()->create(['is_active' => false, 'password' => 'Correct-Horse-42']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Correct-Horse-42'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
