<?php

namespace Tests\Feature;

use App\Enums\AuctionEventType;
use App\Enums\CalendarEventSource;
use App\Enums\UserRole;
use App\Jobs\ImportGoogleCalendarEventsJob;
use App\Models\CalendarEvent;
use App\Models\GoogleCalendarConnection;
use App\Models\User;
use App\Services\GoogleCalendarInboundSyncService;
use App\Services\GoogleCalendarSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GoogleCalendarInboundSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('i', 32)),
            'services.google_calendar.client_id' => 'test-client.apps.googleusercontent.com',
            'services.google_calendar.client_secret' => 'test-secret',
        ]);
    }

    public function test_owner_can_enable_future_booking_import_and_job_is_queued(): void
    {
        Queue::fake();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $connection = $this->connection($owner, false);

        $this->actingAs($owner)->put(route('google-calendar.inbound-sync.update'), ['enabled' => true])
            ->assertRedirect(route('google-calendar.index'));

        $connection->refresh();
        $this->assertTrue($connection->inbound_sync_enabled);
        $this->assertNotNull($connection->inbound_sync_started_at);
        $this->assertNull($connection->inbound_sync_token);
        Queue::assertPushed(ImportGoogleCalendarEventsJob::class);
    }

    public function test_non_admin_cannot_manage_booking_import(): void
    {
        $readOnly = User::factory()->create(['role' => UserRole::ReadOnly]);
        $this->connection(User::factory()->create(['role' => UserRole::Owner]));

        $this->actingAs($readOnly)->put(route('google-calendar.inbound-sync.update'), ['enabled' => true])
            ->assertForbidden();
    }

    public function test_future_google_booking_is_imported_as_google_managed_meeting(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://www.googleapis.com/calendar/v3/calendars/*/events*' => Http::response([
            'items' => [$this->googleEvent()],
            'nextSyncToken' => 'sync-token-1',
        ])]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $connection = $this->connection($owner);

        $summary = app(GoogleCalendarInboundSyncService::class)->import($connection);

        $event = CalendarEvent::query()->firstOrFail();
        $this->assertSame(1, $summary['created']);
        $this->assertSame(CalendarEventSource::Google, $event->source);
        $this->assertSame(AuctionEventType::Meeting, $event->event_type);
        $this->assertSame('Seller consultation', $event->title);
        $this->assertSame('https://meet.google.com/abc-defg-hij', $event->auction_url);
        $this->assertSame('seller@example.com', $event->google_attendees[0]['email']);
        $this->assertSame('sync-token-1', $connection->refresh()->inbound_sync_token);
        Http::assertSent(fn (Request $request): bool => filled($request->data()['timeMin'] ?? null));
    }

    public function test_rescheduled_booking_updates_one_existing_meeting_without_duplication(): void
    {
        Http::preventStrayRequests();
        Http::fakeSequence()
            ->push(['items' => [$this->googleEvent()], 'nextSyncToken' => 'sync-token-1'])
            ->push(['items' => [$this->googleEvent([
                'etag' => 'etag-2',
                'start' => ['dateTime' => '2026-10-20T15:00:00-04:00'],
                'end' => ['dateTime' => '2026-10-20T15:30:00-04:00'],
            ])], 'nextSyncToken' => 'sync-token-2']);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $connection = $this->connection($owner);
        $sync = app(GoogleCalendarInboundSyncService::class);

        $sync->import($connection);
        $summary = $sync->import($connection->refresh());

        $this->assertSame(1, CalendarEvent::query()->count());
        $this->assertSame(1, $summary['updated']);
        $this->assertSame('2026-10-20 15:00:00', CalendarEvent::query()->firstOrFail()->starts_at->format('Y-m-d H:i:s'));
    }

    public function test_google_cancellation_archives_imported_meeting(): void
    {
        Http::preventStrayRequests();
        Http::fakeSequence()
            ->push(['items' => [$this->googleEvent()], 'nextSyncToken' => 'sync-token-1'])
            ->push(['items' => [[
                'id' => 'google-booking-1',
                'status' => 'cancelled',
                'etag' => 'etag-cancelled',
            ]], 'nextSyncToken' => 'sync-token-2']);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $connection = $this->connection($owner);
        $sync = app(GoogleCalendarInboundSyncService::class);

        $sync->import($connection);
        $summary = $sync->import($connection->refresh());

        $this->assertSame(1, $summary['cancelled']);
        $event = CalendarEvent::withTrashed()->firstOrFail();
        $this->assertTrue($event->trashed());
        $this->assertNotNull($event->google_cancelled_at);
    }

    public function test_vvr_originated_google_event_is_not_imported_back_into_vvr(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://www.googleapis.com/calendar/v3/calendars/*/events*' => Http::response([
            'items' => [$this->googleEvent([
                'id' => 'vvr-generated-event',
                'extendedProperties' => ['private' => ['vvr_calendar_event_id' => '42']],
            ])],
            'nextSyncToken' => 'sync-token-1',
        ])]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $connection = $this->connection($owner);

        $summary = app(GoogleCalendarInboundSyncService::class)->import($connection);

        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(0, CalendarEvent::query()->count());
    }

    public function test_expired_sync_token_recovers_with_bounded_fresh_checkpoint(): void
    {
        Http::preventStrayRequests();
        Http::fakeSequence()
            ->push([], 410)
            ->push(['items' => [$this->googleEvent()], 'nextSyncToken' => 'fresh-token']);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $connection = $this->connection($owner);
        $connection->updateQuietly(['inbound_sync_token' => 'expired-token']);

        app(GoogleCalendarInboundSyncService::class)->import($connection->refresh());

        $this->assertSame('fresh-token', $connection->refresh()->inbound_sync_token);
        Http::assertSentCount(2);
    }

    public function test_google_managed_meeting_cannot_enter_outbound_queue_or_be_edited_locally(): void
    {
        Queue::fake();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $event = CalendarEvent::factory()->create([
            'source' => CalendarEventSource::Google,
            'event_type' => AuctionEventType::Meeting,
            'title' => 'Google booking',
        ]);

        $this->assertFalse(app(GoogleCalendarSyncService::class)->queue($event));
        $this->actingAs($owner)->get(route('calendar.edit', $event))->assertForbidden();
        Queue::assertNothingPushed();
    }

    private function connection(User $owner, bool $enabled = true): GoogleCalendarConnection
    {
        return GoogleCalendarConnection::factory()->for($owner, 'user')->create([
            'calendar_id' => 'primary',
            'inbound_sync_enabled' => $enabled,
            'inbound_sync_started_at' => $enabled ? now() : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function googleEvent(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'google-booking-1',
            'status' => 'confirmed',
            'eventType' => 'default',
            'etag' => 'etag-1',
            'summary' => 'Seller consultation',
            'description' => 'Discuss the property and next steps.',
            'location' => 'Google Meet',
            'htmlLink' => 'https://calendar.google.com/calendar/event?eid=booking',
            'hangoutLink' => 'https://meet.google.com/abc-defg-hij',
            'start' => ['dateTime' => '2026-10-20T14:00:00-04:00'],
            'end' => ['dateTime' => '2026-10-20T14:30:00-04:00'],
            'updated' => '2026-09-20T12:00:00Z',
            'organizer' => ['email' => 'valorventurerealty@gmail.com'],
            'attendees' => [[
                'displayName' => 'Seller Example',
                'email' => 'seller@example.com',
                'responseStatus' => 'accepted',
            ]],
        ], $overrides);
    }
}
