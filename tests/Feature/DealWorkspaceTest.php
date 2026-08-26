<?php

namespace Tests\Feature;

use App\Enums\DealStatus;
use App\Enums\DealType;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Property;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_a_linked_deal_with_a_generated_number(): void
    {
        $user = User::factory()->create(['role' => UserRole::AcquisitionManager]);
        $property = Property::factory()->create();
        $contact = Contact::factory()->create();

        $this->actingAs($user)->post(route('deals.store'), $this->validData([
            'property_id' => $property->id,
            'primary_contact_id' => $contact->id,
        ]))->assertRedirect();

        $deal = Deal::query()->sole();
        $this->assertSame('VVR-'.now()->format('Y').'-'.str_pad((string) $deal->id, 6, '0', STR_PAD_LEFT), $deal->deal_number);
        $this->assertSame($property->id, $deal->property_id);
        $this->assertSame($contact->id, $deal->primary_contact_id);
        $this->assertSame($user->id, $deal->created_by);
    }

    public function test_read_only_user_can_view_but_cannot_change_deals(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        $deal = Deal::factory()->create();

        $this->actingAs($user)->get(route('deals.show', $deal))->assertOk()->assertDontSee('Deal economics');
        $this->actingAs($user)->get(route('deals.create'))->assertForbidden();
        $this->actingAs($user)->put(route('deals.update', $deal), $this->validData())->assertForbidden();
        $this->actingAs($user)->delete(route('deals.destroy', $deal))->assertForbidden();
    }

    public function test_closed_deal_requires_an_actual_close_date(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)->post(route('deals.store'), $this->validData([
            'status' => DealStatus::Closed->value,
            'actual_close_date' => null,
        ]))->assertSessionHasErrors('actual_close_date');

        $this->assertDatabaseCount('deals', 0);
    }

    public function test_authorized_user_can_assign_multiple_contact_roles_without_duplicates(): void
    {
        $user = User::factory()->create(['role' => UserRole::DispositionManager]);
        $deal = Deal::factory()->create();
        $contact = Contact::factory()->create();

        $seller = ['contact_id' => $contact->id, 'role' => 'seller'];
        $this->actingAs($user)->post(route('deals.contacts.store', $deal), $seller)->assertRedirect();
        $this->actingAs($user)->post(route('deals.contacts.store', $deal), $seller)->assertRedirect();
        $this->actingAs($user)->post(route('deals.contacts.store', $deal), ['contact_id' => $contact->id, 'role' => 'realtor'])->assertRedirect();

        $this->assertDatabaseCount('contact_deal', 2);
    }

    public function test_task_can_be_linked_to_a_deal(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $deal = Deal::factory()->create();

        $this->actingAs($user)->post(route('tasks.store'), [
            'title' => 'Confirm closing package',
            'status' => 'pending',
            'priority' => 'high',
            'subject' => 'deal:'.$deal->id,
            'recurrence_interval' => 1,
        ])->assertRedirect();

        $task = Task::query()->sole();
        $this->assertTrue($task->taskable->is($deal));
    }

    public function test_property_and_contact_pages_show_linked_deals(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $property = Property::factory()->create();
        $contact = Contact::factory()->create();
        $deal = Deal::factory()->create(['property_id' => $property->id, 'primary_contact_id' => $contact->id, 'title' => 'Bayberry acquisition']);

        $this->actingAs($user)->get(route('properties.show', $property))->assertOk()->assertSee($deal->title);
        $this->actingAs($user)->get(route('contacts.show', $contact))->assertOk()->assertSee($deal->title);
    }

    public function test_only_owner_or_admin_can_archive_a_deal(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AcquisitionManager]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $deal = Deal::factory()->create();

        $this->actingAs($manager)->delete(route('deals.destroy', $deal))->assertForbidden();
        $this->actingAs($owner)->delete(route('deals.destroy', $deal))->assertRedirect(route('deals.index'));
        $this->assertSoftDeleted($deal);
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'title' => '120 Bayberry acquisition',
            'type' => DealType::Acquisition->value,
            'status' => DealStatus::UnderContract->value,
            'contract_date' => now()->toDateString(),
            'projected_close_date' => now()->addDays(30)->toDateString(),
            'offer_amount' => 14000,
            'contract_amount' => 14500,
            'earnest_money' => 500,
            'projected_revenue' => 7500,
            'notes' => 'Test transaction.',
        ], $overrides);
    }
}
