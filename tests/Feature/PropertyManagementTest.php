<?php

namespace Tests\Feature;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\UserRole;
use App\Enums\WetlandsStatus;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_properties(): void
    {
        $this->get(route('properties.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_create_normalized_property_with_owner_and_audit_entry(): void
    {
        $user = User::factory()->create(['role' => UserRole::AcquisitionManager]);
        $owner = Contact::factory()->create();

        $response = $this->actingAs($user)->post(route('properties.store'), $this->validData([
            'parcel_id' => '12-123-456-A',
            'county' => 'Putnam County',
            'state' => 'fl',
            'owner_contact_id' => $owner->id,
            'gis_links_text' => "https://example.test/gis/1\nhttps://example.test/gis/1",
        ]));

        $property = Property::query()->firstOrFail();
        $response->assertRedirect(route('properties.show', $property));
        $this->assertSame('12123456A', $property->normalized_parcel_id);
        $this->assertSame('PUTNAM', $property->normalized_county);
        $this->assertSame('FL', $property->state);
        $this->assertSame($owner->id, $property->owner_contact_id);
        $this->assertSame(['https://example.test/gis/1'], $property->gis_links);
        $this->assertSame($user->id, $property->created_by);
        $this->assertDatabaseHas(AuditLog::class, [
            'event' => 'created',
            'auditable_type' => $property->getMorphClass(),
            'auditable_id' => $property->id,
        ]);
    }

    public function test_duplicate_parcel_in_same_jurisdiction_is_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        Property::factory()->create([
            'parcel_id' => '18-01-24-0000-0010-0000',
            'normalized_parcel_id' => '180124000000100000',
            'county' => 'Putnam',
            'normalized_county' => 'PUTNAM',
            'state' => 'FL',
        ]);

        $this->actingAs($user)->post(route('properties.store'), $this->validData([
            'parcel_id' => '18 01 24 0000 0010 0000',
            'county' => 'Putnam County',
            'state' => 'FL',
            'address' => '999 Different Road',
        ]))->assertSessionHasErrors('parcel_id');

        $this->assertSame(1, Property::query()->count());
    }

    public function test_duplicate_normalized_address_is_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        Property::factory()->create([
            'parcel_id' => 'ORIGINAL-1',
            'normalized_parcel_id' => 'ORIGINAL1',
            'address' => '120 Bayberry Rd.',
            'city' => 'Palatka',
            'state' => 'FL',
            'postal_code' => '32177',
            'normalized_address' => '120 BAYBERRY RD PALATKA FL 32177',
        ]);

        $this->actingAs($user)->post(route('properties.store'), $this->validData([
            'parcel_id' => 'DIFFERENT-2',
            'address' => '120 Bayberry Rd',
            'city' => 'Palatka',
            'state' => 'FL',
            'postal_code' => '32177',
        ]))->assertSessionHasErrors('address');
    }

    public function test_read_only_and_marketing_users_can_view_but_cannot_mutate_properties(): void
    {
        $property = Property::factory()->create();

        foreach ([UserRole::ReadOnly, UserRole::Marketing] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('properties.show', $property))->assertOk();
            $this->actingAs($user)->get(route('properties.edit', $property))->assertForbidden();
            $this->actingAs($user)->post(route('properties.store'), $this->validData())->assertForbidden();
        }
    }

    public function test_virtual_assistant_cannot_view_or_submit_financial_fields(): void
    {
        $user = User::factory()->create(['role' => UserRole::VirtualAssistant]);
        $property = Property::factory()->create([
            'purchase_price' => 14500,
            'investor_price' => 22000,
            'taxes' => 875,
            'attorney_fees' => 500,
            'realtor_fees' => 750,
            'other_costs' => 125,
            'all_in_amount' => 15375,
            'expected_sales_price' => 42000,
            'actual_sales_price' => 40000,
            'expected_profit' => 26625,
            'actual_profit' => 24625,
        ]);

        $this->actingAs($user)->get(route('properties.show', $property))
            ->assertOk()
            ->assertDontSee('$14,500.00')
            ->assertDontSee('$22,000.00')
            ->assertDontSee('Expected sales price')
            ->assertDontSee('Actual profit');

        $this->actingAs($user)->put(route('properties.update', $property), $this->validData([
            'purchase_price' => 1,
            'taxes' => 1,
            'attorney_fees' => 1,
            'realtor_fees' => 1,
            'other_costs' => 1,
            'all_in_amount' => 1,
            'expected_sales_price' => 1,
            'actual_sales_price' => 1,
        ]))->assertSessionHasErrors([
            'purchase_price', 'taxes', 'attorney_fees', 'realtor_fees', 'other_costs', 'all_in_amount', 'expected_sales_price', 'actual_sales_price',
        ]);

        $this->assertSame('14500.00', $property->fresh()->purchase_price);
    }

    public function test_authorized_user_can_store_complete_property_financials_including_a_loss(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $response = $this->actingAs($user)->post(route('properties.store'), $this->validData([
            'purchase_price' => 14500,
            'taxes' => 875.25,
            'attorney_fees' => 750,
            'realtor_fees' => 1200,
            'other_costs' => 300,
            'all_in_amount' => 1,
            'expected_sales_price' => 42000,
            'actual_sales_price' => 13000,
        ]));

        $property = Property::query()->firstOrFail();
        $response->assertRedirect(route('properties.show', $property));
        $this->assertSame('875.25', $property->taxes);
        $this->assertSame('750.00', $property->attorney_fees);
        $this->assertSame('1200.00', $property->realtor_fees);
        $this->assertSame('300.00', $property->other_costs);
        $this->assertSame('17625.25', $property->all_in_amount);
        $this->assertSame('42000.00', $property->expected_sales_price);
        $this->assertSame('13000.00', $property->actual_sales_price);
        $this->assertSame('24374.75', $property->expected_profit);
        $this->assertSame('-4625.25', $property->actual_profit);

        $this->actingAs($user)->get(route('properties.show', $property))
            ->assertOk()
            ->assertSee('Expected sales price')
            ->assertSee('Attorney fees')
            ->assertSee('Realtor fees')
            ->assertSee('Actual profit')
            ->assertSee('Deals')
            ->assertSee('No deals are linked to this property.');
    }

    public function test_owner_can_archive_property(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $property = Property::factory()->create();

        $this->actingAs($owner)->delete(route('properties.destroy', $property))
            ->assertRedirect(route('properties.index'));

        $this->assertSoftDeleted($property);
    }

    public function test_property_can_be_searched(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        Property::factory()->create(['address' => '44 Unique Needle Lane']);
        Property::factory()->create(['address' => '77 Ordinary Road']);

        $this->actingAs($user)->get(route('properties.index', ['search' => 'Unique Needle']))
            ->assertOk()->assertSee('44 Unique Needle Lane')->assertDontSee('77 Ordinary Road');
    }

    public function test_property_index_displays_all_in_instead_of_purchase_price(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        Property::factory()->create([
            'purchase_price' => 11111.11,
            'taxes' => 1000,
            'attorney_fees' => 500,
            'realtor_fees' => 250,
            'other_costs' => 125,
            'all_in_amount' => 12986.11,
            'expected_sales_price' => 20000,
            'expected_profit' => 7013.89,
        ]);

        $this->actingAs($user)->get(route('properties.index'))
            ->assertOk()
            ->assertSee('All-in / investor')
            ->assertSee('Expected sale / profit')
            ->assertSee('$12,986.11')
            ->assertSee('$20,000.00')
            ->assertSee('Profit: $7,013.89')
            ->assertDontSee('$11,111.11');
    }

    public function test_invalid_gis_link_is_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)->post(route('properties.store'), $this->validData([
            'gis_links_text' => 'javascript:alert(1)',
        ]))->assertSessionHasErrors('gis_links_text');
    }

    public function test_authorized_user_can_save_and_open_property_document_drive_folder(): void
    {
        $user = User::factory()->create(['role' => UserRole::AcquisitionManager]);
        $driveUrl = 'https://drive.google.com/drive/folders/property-documents-123';
        $closingUrl = 'https://drive.google.com/drive/folders/property-closing-456';

        $response = $this->actingAs($user)->post(route('properties.store'), $this->validData([
            'document_drive_url' => "  {$driveUrl}  ",
            'closing_documents_url' => "  {$closingUrl}  ",
        ]));

        $property = Property::query()->firstOrFail();
        $response->assertRedirect(route('properties.show', $property));
        $this->assertSame($driveUrl, $property->document_drive_url);
        $this->assertSame($closingUrl, $property->closing_documents_url);

        $this->actingAs($user)->get(route('properties.show', $property))
            ->assertOk()
            ->assertSee('Open Drive Folder')
            ->assertSee('Open Closing Documents')
            ->assertSee($driveUrl);
    }

    public function test_property_document_drive_folder_must_use_https(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)->post(route('properties.store'), $this->validData([
            'document_drive_url' => 'http://drive.google.com/drive/folders/insecure',
        ]))->assertSessionHasErrors('document_drive_url');

        $this->assertSame(0, Property::query()->count());
    }

    public function test_closing_documents_link_must_use_https(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)->post(route('properties.store'), $this->validData([
            'closing_documents_url' => 'http://example.test/closing-documents',
        ]))->assertSessionHasErrors('closing_documents_url');
    }

    public function test_hidden_document_links_are_preserved_when_virtual_assistant_updates_other_fields(): void
    {
        $user = User::factory()->create(['role' => UserRole::VirtualAssistant]);
        $property = Property::factory()->create([
            'document_drive_url' => 'https://drive.google.com/drive/folders/private-property',
            'closing_documents_url' => 'https://drive.google.com/drive/folders/private-closing',
        ]);
        $data = $this->validData(['address' => $property->address, 'parcel_id' => $property->parcel_id]);
        unset($data['purchase_price']);

        $this->actingAs($user)->put(route('properties.update', $property), $data)->assertRedirect();

        $property->refresh();
        $this->assertSame('https://drive.google.com/drive/folders/private-property', $property->document_drive_url);
        $this->assertSame('https://drive.google.com/drive/folders/private-closing', $property->closing_documents_url);
    }

    public function test_property_status_workflow_contains_only_the_requested_values(): void
    {
        $this->assertSame([
            'research', 'bidding', 'owned', 'actively_working', 'marketing', 'under_contract', 'sold', 'archived',
        ], array_map(fn (PropertyStatus $status): string => $status->value, PropertyStatus::cases()));

        $this->assertSame([
            'owned', 'actively_working', 'marketing', 'under_contract',
        ], PropertyStatus::portfolioValueStatuses());
        $this->assertSame([
            'owned', 'actively_working', 'marketing', 'under_contract', 'sold',
        ], PropertyStatus::financialActualStatuses());
    }

    public function test_user_without_source_document_permission_cannot_view_or_change_drive_folder(): void
    {
        $user = User::factory()->create(['role' => UserRole::VirtualAssistant]);
        $originalUrl = 'https://drive.google.com/drive/folders/private-original';
        $property = Property::factory()->create(['document_drive_url' => $originalUrl]);

        $this->actingAs($user)->get(route('properties.show', $property))
            ->assertOk()
            ->assertDontSee('Open Drive Folder')
            ->assertDontSee($originalUrl);

        $this->actingAs($user)->put(route('properties.update', $property), $this->validData([
            'document_drive_url' => 'https://drive.google.com/drive/folders/unauthorized-change',
        ]))->assertSessionHasErrors('document_drive_url');

        $this->assertSame($originalUrl, $property->fresh()->document_drive_url);
    }

    public function test_legacy_coordinates_are_not_accepted_or_saved(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)->post(route('properties.store'), $this->validData([
            'latitude' => '29.6486',
            'longitude' => '-81.6376',
        ]))->assertRedirect();

        $property = Property::query()->firstOrFail();
        $this->assertNull($property->getRawOriginal('latitude'));
        $this->assertNull($property->getRawOriginal('longitude'));
    }

    private function validData(array $overrides = []): array
    {
        return array_replace([
            'parcel_id' => '22-333-444',
            'county' => 'Putnam',
            'address' => '120 Bayberry Road',
            'city' => 'Palatka',
            'state' => 'FL',
            'postal_code' => '32177',
            'property_type' => PropertyType::Land->value,
            'status' => PropertyStatus::Research->value,
            'wetlands_status' => WetlandsStatus::Unknown->value,
            'purchase_price' => 14500,
        ], $overrides);
    }
}
