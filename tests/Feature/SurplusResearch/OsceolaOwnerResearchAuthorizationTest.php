<?php

namespace Tests\Feature\SurplusResearch;

use App\Enums\SurplusOwnerResearchStatus;
use App\Enums\UserRole;
use App\Jobs\ResearchOsceolaSurplusOwnerJob;
use App\Models\SurplusCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OsceolaOwnerResearchAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_only_user_cannot_queue_owner_research(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly, 'is_active' => true]);
        $this->actingAs($user)->post(route('surplus-scout.osceola.owner-research.store'), ['mode' => 'next_10'])
            ->assertForbidden();
    }

    public function test_next_ten_queues_existing_cases_as_sequential_database_jobs(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => UserRole::Owner, 'is_active' => true]);
        SurplusCase::factory()->count(2)->create([
            'claimant_contact_id' => null, 'property_id' => null, 'assigned_user_id' => null,
            'source_name' => 'Osceola County Clerk', 'county' => 'Osceola',
            'research_status' => SurplusOwnerResearchStatus::Pending->value,
        ]);

        $this->actingAs($user)->post(route('surplus-scout.osceola.owner-research.store'), ['mode' => 'next_10'])
            ->assertRedirect();

        $this->assertDatabaseHas('surplus_owner_research_batches', ['mode' => 'next_10', 'total_cases' => 2]);
        Queue::assertPushed(ResearchOsceolaSurplusOwnerJob::class, 2);
    }
}
