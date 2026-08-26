<?php

namespace Tests\Feature;

use App\Enums\AuctionCounty;
use App\Enums\AuctionEventType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CalendarEvent;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_create_normalized_property_linked_auction(): void
    {
        $user = User::factory()->create(['role' => UserRole::AcquisitionManager]);
        $property = Property::factory()->create();
        $response = $this->actingAs($user)->post(route('calendar.store'), $this->validData([
            'property_id' => $property->id,
            'max_bid' => 14500,
        ]));

        $event = CalendarEvent::query()->firstOrFail();
        $response->assertRedirect(route('calendar.show', $event));
        $this->assertSame('311227722700110120', $event->normalized_parcel_number);
        $this->assertSame($property->id, $event->property_id);
        $this->assertSame('14500.00', $event->max_bid);
        $this->assertSame('2026-08-20 09:00:00', $event->starts_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas(AuditLog::class, ['event' => 'created', 'auditable_type' => $event->getMorphClass(), 'auditable_id' => $event->id]);
    }

    public function test_normalized_duplicate_auction_is_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($user)->post(route('calendar.store'), $this->validData())->assertRedirect();
        $this->actingAs($user)->post(route('calendar.store'), $this->validData([
            'parcel_number' => '31 12 27 7227 0011 0120',
        ]))->assertSessionHasErrors('date');
        $this->assertSame(1, CalendarEvent::query()->count());
    }

    public function test_authorized_user_can_create_a_meeting_without_auction_fields(): void
    {
        $user = User::factory()->create(['role' => UserRole::AcquisitionManager]);

        $response = $this->actingAs($user)->post(route('calendar.store'), [
            'title' => 'Seller follow-up meeting',
            'event_type' => AuctionEventType::Meeting->value,
            'date' => '2026-08-20',
            'time' => '14:30',
            'notes' => 'Review the proposed purchase terms.',
        ]);

        $event = CalendarEvent::query()->firstOrFail();
        $response->assertRedirect(route('calendar.show', $event));
        $this->assertSame(AuctionEventType::Meeting, $event->event_type);
        $this->assertSame('Seller follow-up meeting', $event->displayTitle());
        $this->assertNull($event->parcel_number);
        $this->assertNull($event->normalized_parcel_number);
        $this->assertNull($event->auction_url);
        $this->assertNull($event->county);
    }

    public function test_calendar_create_screen_uses_general_event_language(): void
    {
        $user = User::factory()->create(['role' => UserRole::AcquisitionManager]);

        $this->actingAs($user)->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('+ Add Event');

        $this->actingAs($user)->get(route('calendar.create'))
            ->assertOk()
            ->assertSee('Meeting')
            ->assertSee('Event title');
    }

    public function test_calendar_month_and_county_filters_show_matching_events(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        CalendarEvent::factory()->create(['starts_at' => '2026-08-20 09:00:00', 'county' => AuctionCounty::Putnam, 'property_address' => '120 Bayberry Rd']);
        CalendarEvent::factory()->create(['starts_at' => '2026-08-21 09:00:00', 'county' => AuctionCounty::Orange, 'property_address' => '500 Orange Ave']);

        $this->actingAs($user)->get(route('calendar.index', ['month' => '2026-08', 'county' => 'putnam']))
            ->assertOk()->assertSee('120 Bayberry Rd')->assertDontSee('500 Orange Ave');
    }

    public function test_virtual_assistant_cannot_view_or_submit_max_bid(): void
    {
        $user = User::factory()->create(['role' => UserRole::VirtualAssistant]);
        $event = CalendarEvent::factory()->create(['max_bid' => 14500]);
        $this->actingAs($user)->get(route('calendar.show', $event))->assertOk()->assertDontSee('$14,500.00')->assertDontSee('Max bid');
        $this->actingAs($user)->post(route('calendar.store'), $this->validData(['parcel_number' => 'VA-NEW-PARCEL', 'max_bid' => 1]))
            ->assertSessionHasErrors('max_bid');

        $update = $this->validData(['parcel_number' => $event->parcel_number]);
        unset($update['max_bid']);
        $this->actingAs($user)->put(route('calendar.update', $event), $update)->assertRedirect();
        $this->assertSame('14500.00', $event->fresh()->max_bid);
    }

    public function test_read_only_user_can_view_but_cannot_mutate_calendar(): void
    {
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);
        $event = CalendarEvent::factory()->create();
        $this->actingAs($user)->get(route('calendar.index'))->assertOk();
        $this->actingAs($user)->get(route('calendar.show', $event))->assertOk();
        $this->actingAs($user)->get(route('calendar.create'))->assertForbidden();
        $this->actingAs($user)->delete(route('calendar.destroy', $event))->assertForbidden();
    }

    public function test_auction_link_must_use_https_and_county_is_allowlisted(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($user)->post(route('calendar.store'), $this->validData([
            'auction_url' => 'http://example.test/insecure',
            'county' => 'duval',
        ]))->assertSessionHasErrors(['auction_url', 'county']);
    }

    private function validData(array $overrides = []): array
    {
        return array_replace([
            'parcel_number' => '31-12-27-7227-0011-0120',
            'event_type' => AuctionEventType::TaxDeedAuction->value,
            'date' => '2026-08-20',
            'time' => '09:00',
            'auction_url' => 'https://example.test/auction/putnam-120',
            'property_address' => '120 Bayberry Rd',
            'county' => AuctionCounty::Putnam->value,
            'max_bid' => 14500,
            'notes' => 'Research complete.',
        ], $overrides);
    }
}
