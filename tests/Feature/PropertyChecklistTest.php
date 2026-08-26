<?php

namespace Tests\Feature;

use App\Enums\PropertyChecklistKey;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\UserRole;
use App\Enums\WetlandsStatus;
use App\Models\Property;
use App\Models\User;
use App\Services\PropertyChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_property_receives_all_required_checklist_items(): void
    {
        $user = User::factory()->create(['role' => UserRole::AcquisitionManager]);

        $this->actingAs($user)->post(route('properties.store'), $this->propertyData())->assertRedirect();

        $property = Property::query()->firstOrFail();
        $this->assertSame(count(PropertyChecklistKey::cases()), $property->checklistItems()->count());
        $this->actingAs($user)->get(route('properties.show', $property))
            ->assertOk()
            ->assertSee('Max bid')
            ->assertSee('Quiet title / final judgment');
    }

    public function test_authorized_user_can_complete_items_and_save_https_evidence_links(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $property = Property::factory()->create();
        app(PropertyChecklistService::class)->initialize($property, $user);
        $items = $this->checklistData();
        $items[PropertyChecklistKey::MaxBid->value] = [
            'completed' => 1,
            'evidence_url' => 'https://drive.google.com/file/d/max-bid-document',
        ];

        $this->actingAs($user)->put(route('properties.checklist.update', $property), [
            'items' => $items,
        ])->assertRedirect(route('properties.show', $property).'#checklist');

        $item = $property->checklistItems()->where('item_key', PropertyChecklistKey::MaxBid->value)->firstOrFail();
        $this->assertTrue($item->is_completed);
        $this->assertSame('https://drive.google.com/file/d/max-bid-document', $item->evidence_url);
        $this->assertSame($user->id, $item->completed_by);
        $this->assertNotNull($item->completed_at);
    }

    public function test_checklist_evidence_link_must_use_https(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $property = Property::factory()->create();
        $items = $this->checklistData();
        $items[PropertyChecklistKey::AcquisitionDeed->value]['evidence_url'] = 'http://example.test/deed';

        $this->actingAs($user)->put(route('properties.checklist.update', $property), [
            'items' => $items,
        ])->assertSessionHasErrors('items.acquisition_deed.evidence_url');
    }

    public function test_user_without_document_permission_can_update_status_without_viewing_or_replacing_link(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $va = User::factory()->create(['role' => UserRole::VirtualAssistant]);
        $property = Property::factory()->create();
        app(PropertyChecklistService::class)->initialize($property, $owner);
        $item = $property->checklistItems()->where('item_key', PropertyChecklistKey::PropertyCard->value)->firstOrFail();
        $item->update(['evidence_url' => 'https://drive.google.com/file/d/private-card']);

        $this->actingAs($va)->get(route('properties.show', $property))
            ->assertOk()
            ->assertDontSee('https://drive.google.com/file/d/private-card');

        $malicious = $this->checklistData();
        $malicious[PropertyChecklistKey::PropertyCard->value]['evidence_url'] = 'https://example.test/replacement';
        $this->actingAs($va)->put(route('properties.checklist.update', $property), ['items' => $malicious])
            ->assertSessionHasErrors('items.property_card.evidence_url');

        $statusOnly = $this->checklistData(false);
        $statusOnly[PropertyChecklistKey::PropertyCard->value]['completed'] = 1;
        $this->actingAs($va)->put(route('properties.checklist.update', $property), ['items' => $statusOnly])
            ->assertRedirect();

        $item->refresh();
        $this->assertTrue($item->is_completed);
        $this->assertSame('https://drive.google.com/file/d/private-card', $item->evidence_url);
    }

    public function test_read_only_user_cannot_update_property_checklist(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        $property = Property::factory()->create();

        $this->actingAs($user)->put(route('properties.checklist.update', $property), [
            'items' => $this->checklistData(false),
        ])->assertForbidden();
    }

    private function checklistData(bool $includeLinks = true): array
    {
        return collect(PropertyChecklistKey::cases())->mapWithKeys(fn (PropertyChecklistKey $key): array => [
            $key->value => array_filter([
                'completed' => 0,
                'evidence_url' => $includeLinks ? null : false,
            ], fn ($value, $field): bool => $field !== 'evidence_url' || $includeLinks, ARRAY_FILTER_USE_BOTH),
        ])->all();
    }

    private function propertyData(): array
    {
        return [
            'parcel_id' => 'CHECKLIST-001',
            'county' => 'Putnam',
            'address' => '555 Checklist Lane',
            'city' => 'Palatka',
            'state' => 'FL',
            'postal_code' => '32177',
            'property_type' => PropertyType::Land->value,
            'status' => PropertyStatus::Research->value,
            'wetlands_status' => WetlandsStatus::Unknown->value,
        ];
    }
}
