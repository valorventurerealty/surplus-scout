<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\PropertyStatus;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Property;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SortableCrmTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_columns_are_sortable_in_both_directions(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        Property::factory()->create(['address' => '900 Expensive Sorting Way', 'all_in_amount' => 50000]);
        Property::factory()->create(['address' => '100 Affordable Sorting Way', 'all_in_amount' => 10000]);

        $ascending = $this->actingAs($user)->get(route('properties.index', [
            'sort' => 'all_in_investor',
            'direction' => 'asc',
        ]));
        $ascending->assertOk()
            ->assertSeeInOrder(['100 Affordable Sorting Way', '900 Expensive Sorting Way'])
            ->assertSee('direction=desc', false);

        $this->get(route('properties.index', [
            'sort' => 'all_in_investor',
            'direction' => 'desc',
        ]))->assertOk()->assertSeeInOrder(['900 Expensive Sorting Way', '100 Affordable Sorting Way']);
    }

    public function test_properties_sort_status_in_pipeline_order_instead_of_alphabetically(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $expected = [
            PropertyStatus::Research,
            PropertyStatus::Bidding,
            PropertyStatus::Owned,
            PropertyStatus::ActivelyWorking,
            PropertyStatus::Marketing,
            PropertyStatus::UnderContract,
            PropertyStatus::Sold,
            PropertyStatus::Archived,
        ];

        foreach (array_reverse($expected) as $position => $status) {
            Property::factory()->create([
                'address' => str_pad((string) $position, 2, '0', STR_PAD_LEFT).' '.$status->label().' Status Property',
                'status' => $status,
            ]);
        }

        $response = $this->actingAs($user)->get(route('properties.index', [
            'sort' => 'status',
            'direction' => 'asc',
        ]));

        $response->assertOk()->assertSeeInOrder(array_map(
            fn (PropertyStatus $status): string => $status->label().' Status Property',
            $expected,
        ));

        $this->get(route('properties.index', [
            'sort' => 'status',
            'direction' => 'desc',
        ]))->assertOk()->assertSeeInOrder(array_map(
            fn (PropertyStatus $status): string => $status->label().' Status Property',
            array_reverse($expected),
        ));
    }

    public function test_deals_can_be_sorted_by_related_property(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $alpha = Property::factory()->create(['address' => '100 Alpha Property']);
        $zulu = Property::factory()->create(['address' => '900 Zulu Property']);
        Deal::factory()->create(['title' => 'Zulu Deal Row', 'property_id' => $zulu->id]);
        Deal::factory()->create(['title' => 'Alpha Deal Row', 'property_id' => $alpha->id]);

        $this->actingAs($user)->get(route('deals.index', [
            'sort' => 'property',
            'direction' => 'asc',
        ]))->assertOk()->assertSeeInOrder(['Alpha Deal Row', 'Zulu Deal Row']);
    }

    public function test_contacts_can_be_sorted_by_open_task_count(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $oneTask = Contact::factory()->create(['first_name' => 'One', 'last_name' => 'Task Contact']);
        $threeTasks = Contact::factory()->create(['first_name' => 'Three', 'last_name' => 'Task Contact']);
        Task::factory()->create(['taskable_type' => Contact::class, 'taskable_id' => $oneTask->id]);
        Task::factory()->count(3)->create(['taskable_type' => Contact::class, 'taskable_id' => $threeTasks->id]);

        $this->actingAs($user)->get(route('contacts.index', [
            'sort' => 'associated_tasks',
            'direction' => 'asc',
        ]))->assertOk()->assertSeeInOrder(['One Task Contact', 'Three Task Contact']);
    }

    public function test_every_requested_heading_has_a_sort_link(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($user)->get(route('properties.index'))->assertOk()
            ->assertSee('sort=property', false)->assertSee('sort=parcel_county', false)
            ->assertSee('sort=owner', false)->assertSee('sort=type', false)
            ->assertSee('sort=acreage', false)->assertSee('sort=all_in_investor', false)
            ->assertSee('sort=expected_sale_profit', false)->assertSee('sort=status', false);

        $this->get(route('deals.index'))->assertOk()
            ->assertSee('sort=deal', false)->assertSee('sort=property', false)
            ->assertSee('sort=primary_contact', false)->assertSee('sort=assigned', false)
            ->assertSee('sort=close_date', false)->assertSee('sort=contract_projected', false)
            ->assertSee('sort=status', false);

        $this->get(route('contacts.index'))->assertOk()
            ->assertSee('sort=name', false)->assertSee('sort=company', false)
            ->assertSee('sort=email', false)->assertSee('sort=associated_tasks', false)
            ->assertSee('sort=next_follow_up', false);

        $this->get(route('tasks.index', ['status' => TaskStatus::Pending->value]))->assertOk()
            ->assertSee('sort=task', false)->assertSee('sort=associated_record', false)
            ->assertSee('sort=assigned_to', false)->assertSee('sort=due', false)
            ->assertSee('sort=priority', false)->assertSee('sort=status', false)
            ->assertSee('status=pending', false);
    }

    public function test_tasks_use_business_priority_sorting(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        Task::factory()->create(['title' => 'Low Priority Sorting Task', 'priority' => TaskPriority::Low]);
        Task::factory()->create(['title' => 'Urgent Priority Sorting Task', 'priority' => TaskPriority::Urgent]);

        $this->actingAs($user)->get(route('tasks.index', [
            'sort' => 'priority',
            'direction' => 'desc',
        ]))->assertOk()->assertSeeInOrder(['Urgent Priority Sorting Task', 'Low Priority Sorting Task']);
    }

    public function test_tasks_can_be_sorted_by_associated_property_address(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $alpha = Property::factory()->create(['address' => '100 Alpha Task Property']);
        $zulu = Property::factory()->create(['address' => '900 Zulu Task Property']);
        Task::factory()->create(['title' => 'Zulu Associated Task', 'taskable_type' => Property::class, 'taskable_id' => $zulu->id]);
        Task::factory()->create(['title' => 'Alpha Associated Task', 'taskable_type' => Property::class, 'taskable_id' => $alpha->id]);

        $this->actingAs($user)->get(route('tasks.index', [
            'sort' => 'associated_record',
            'direction' => 'asc',
        ]))->assertOk()->assertSeeInOrder(['Alpha Associated Task', 'Zulu Associated Task']);
    }

    public function test_sorting_is_allowlisted_and_financial_sorting_respects_permissions(): void
    {
        $readOnly = User::factory()->create(['role' => UserRole::ReadOnly]);

        $this->actingAs($readOnly)->get(route('properties.index', [
            'sort' => 'all_in_investor',
            'direction' => 'asc',
        ]))->assertSessionHasErrors('sort');

        $this->get(route('contacts.index', [
            'sort' => 'created_at desc; drop table contacts',
            'direction' => 'asc',
        ]))->assertSessionHasErrors('sort');

        $this->get(route('tasks.index', [
            'sort' => 'tasks.updated_at',
            'direction' => 'desc',
        ]))->assertSessionHasErrors('sort');
    }
}
