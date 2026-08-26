<?php

namespace Tests\Feature;

use App\Enums\AuctionEventType;
use App\Enums\GoogleCalendarSyncStatus;
use App\Enums\UserRole;
use App\Jobs\SyncCalendarEventToGoogleJob;
use App\Models\CalendarEvent;
use App\Models\GoogleCalendarConnection;
use App\Models\User;
use App\Services\GoogleCalendarEventMapper;
use App\Contracts\GoogleCalendarGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GoogleCalendarIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('v', 32)),
            'services.google_calendar.client_id' => 'test-client.apps.googleusercontent.com',
            'services.google_calendar.client_secret' => 'test-secret',
            'services.google_calendar.redirect_uri' => 'https://valorventure.business/settings/integrations/google-calendar/callback',
        ]);
    }

    public function test_only_owner_or_admin_can_manage_google_calendar_connection(): void
    {
        $readOnly = User::factory()->create(['role' => UserRole::ReadOnly]);
        $this->actingAs($readOnly)->get(route('google-calendar.index'))->assertForbidden();
        $this->actingAs($readOnly)->get(route('google-calendar.connect'))->assertForbidden();
    }

    public function test_connect_redirect_uses_oauth_state_and_offline_access(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $response = $this->actingAs($owner)->get(route('google-calendar.connect'));

        $response->assertRedirectContains('accounts.google.com/o/oauth2/v2/auth');
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('access_type=offline', $location);
        $this->assertStringContainsString('calendar.events', $location);
        $this->assertNotEmpty(session('google_calendar_oauth_state'));
    }

    public function test_callback_stores_tokens_encrypted_and_selects_primary_calendar(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token-value',
                'refresh_token' => 'refresh-token-value',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.calendarlist.readonly',
            ]),
            'https://www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response(['items' => [[
                'id' => 'valorventurerealty@gmail.com',
                'summary' => 'VVR Command Center',
                'primary' => true,
                'accessRole' => 'owner',
            ]]]),
        ]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $response = $this->actingAs($owner)
            ->withSession(['google_calendar_oauth_state' => 'known-state'])
            ->get(route('google-calendar.callback', ['state' => 'known-state', 'code' => 'authorization-code']));

        $response->assertRedirect(route('google-calendar.index'));
        $connection = GoogleCalendarConnection::query()->firstOrFail();
        $this->assertSame('access-token-value', $connection->access_token);
        $this->assertSame('refresh-token-value', $connection->refresh_token);
        $this->assertSame('valorventurerealty@gmail.com', $connection->calendar_id);
        $this->assertSame('VVR Command Center', $connection->calendar_name);
        $raw = DB::table('google_calendar_connections')->first();
        $this->assertNotSame('access-token-value', $raw->access_token);
        $this->assertNotSame('refresh-token-value', $raw->refresh_token);
    }

    public function test_new_calendar_event_queues_google_sync_when_connected(): void
    {
        Queue::fake();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $connection = $this->connection($owner);

        $this->actingAs($owner)->post(route('calendar.store'), $this->validCalendarData())->assertRedirect();

        $event = CalendarEvent::query()->firstOrFail();
        $this->assertSame(GoogleCalendarSyncStatus::Pending, $event->google_sync_status);
        $this->assertSame($connection->id, $event->google_calendar_connection_id);
        $this->assertSame('primary', $event->google_calendar_id);
        Queue::assertPushed(SyncCalendarEventToGoogleJob::class, fn ($job): bool =>
            $job->calendarEventId === $event->id && $job->syncVersion === 1
        );
    }

    public function test_sync_job_creates_idempotent_google_event_without_private_max_bid(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/*/events' => Http::response([
                'id' => 'vvr-google-event-id',
                'htmlLink' => 'https://calendar.google.com/calendar/event?eid=test',
                'etag' => 'etag-1',
            ]),
        ]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $connection = $this->connection($owner);
        $event = CalendarEvent::factory()->create([
            'max_bid' => 14500,
            'google_calendar_connection_id' => $connection->id,
            'google_calendar_id' => 'primary',
            'google_sync_status' => GoogleCalendarSyncStatus::Pending,
            'google_sync_version' => 1,
        ]);

        $job = new SyncCalendarEventToGoogleJob($event->id, 1);
        $job->handle(app(GoogleCalendarGatewayInterface::class), app(GoogleCalendarEventMapper::class));

        $event->refresh();
        $this->assertSame(GoogleCalendarSyncStatus::Synced, $event->google_sync_status);
        $this->assertSame('vvr-google-event-id', $event->google_event_id);
        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && str_contains($request->url(), '/calendars/primary/events')
                && isset($payload['extendedProperties']['private']['vvr_calendar_event_id'])
                && ! str_contains(json_encode($payload), '14500');
        });
    }

    public function test_meeting_maps_to_google_without_invented_auction_fields(): void
    {
        $event = CalendarEvent::factory()->create([
            'title' => 'Meeting with seller',
            'event_type' => AuctionEventType::Meeting,
            'parcel_number' => null,
            'normalized_parcel_number' => null,
            'auction_url' => null,
            'property_address' => null,
            'county' => null,
            'max_bid' => null,
        ]);

        $payload = app(GoogleCalendarEventMapper::class)->map($event);

        $this->assertSame('Meeting with seller', $payload['summary']);
        $this->assertArrayNotHasKey('location', $payload);
        $this->assertStringContainsString('VVR Command Center meeting', $payload['description']);
        $this->assertStringNotContainsString('Parcel:', $payload['description']);
        $this->assertStringNotContainsString('County:', $payload['description']);
        $this->assertArrayNotHasKey('vvr_normalized_parcel', $payload['extendedProperties']['private']);
    }

    public function test_archiving_synced_event_queues_google_deletion(): void
    {
        Queue::fake();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $connection = $this->connection($owner);
        $event = CalendarEvent::factory()->create([
            'google_calendar_connection_id' => $connection->id,
            'google_calendar_id' => 'primary',
            'google_event_id' => 'existing-google-id',
            'google_sync_status' => GoogleCalendarSyncStatus::Synced,
            'google_sync_version' => 1,
        ]);

        $this->actingAs($owner)->delete(route('calendar.destroy', $event))->assertRedirect(route('calendar.index'));

        $deleted = CalendarEvent::withTrashed()->findOrFail($event->id);
        $this->assertTrue($deleted->trashed());
        $this->assertSame(GoogleCalendarSyncStatus::DeletionPending, $deleted->google_sync_status);
        Queue::assertPushed(SyncCalendarEventToGoogleJob::class, fn ($job): bool =>
            $job->calendarEventId === $event->id && $job->syncVersion === 2
        );
    }

    public function test_existing_google_event_is_patched_instead_of_duplicated(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/*/events/*' => Http::response([
                'id' => 'existing-google-id',
                'htmlLink' => 'https://calendar.google.com/calendar/event?eid=existing',
                'etag' => 'etag-2',
            ]),
        ]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $connection = $this->connection($owner);
        $event = CalendarEvent::factory()->create([
            'google_calendar_connection_id' => $connection->id,
            'google_calendar_id' => 'primary',
            'google_event_id' => 'existing-google-id',
            'google_sync_status' => GoogleCalendarSyncStatus::Pending,
            'google_sync_version' => 2,
        ]);

        (new SyncCalendarEventToGoogleJob($event->id, 2))->handle(
            app(GoogleCalendarGatewayInterface::class),
            app(GoogleCalendarEventMapper::class),
        );

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool =>
            $request->method() === 'PATCH' && str_ends_with($request->url(), '/events/existing-google-id')
        );
        $this->assertSame(GoogleCalendarSyncStatus::Synced, $event->refresh()->google_sync_status);
    }

    public function test_expired_access_token_is_refreshed_server_side(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]),
            'https://www.googleapis.com/calendar/v3/calendars/*/events' => Http::response(['id' => 'new-google-id']),
        ]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $connection = $this->connection($owner);
        $connection->update(['token_expires_at' => now()->subMinute()]);
        $event = CalendarEvent::factory()->create([
            'google_calendar_connection_id' => $connection->id,
            'google_calendar_id' => 'primary',
            'google_sync_status' => GoogleCalendarSyncStatus::Pending,
            'google_sync_version' => 1,
        ]);

        (new SyncCalendarEventToGoogleJob($event->id, 1))->handle(
            app(GoogleCalendarGatewayInterface::class),
            app(GoogleCalendarEventMapper::class),
        );

        $this->assertSame('fresh-token', $connection->refresh()->access_token);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://oauth2.googleapis.com/token');
    }

    public function test_invalid_oauth_state_is_rejected_before_token_request(): void
    {
        Http::fake();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner)
            ->withSession(['google_calendar_oauth_state' => 'expected'])
            ->get(route('google-calendar.callback', ['state' => 'tampered', 'code' => 'code']))
            ->assertStatus(419);
        Http::assertNothingSent();
    }

    private function connection(User $owner): GoogleCalendarConnection
    {
        return GoogleCalendarConnection::query()->create([
            'user_id' => $owner->id,
            'google_account_email' => 'valorventurerealty@gmail.com',
            'calendar_id' => 'primary',
            'calendar_name' => 'VVR Command Center',
            'access_token' => 'valid-access-token',
            'refresh_token' => 'valid-refresh-token',
            'token_expires_at' => now()->addHour(),
            'scopes' => config('services.google_calendar.scopes'),
            'is_active' => true,
            'connected_at' => now(),
        ]);
    }

    private function validCalendarData(): array
    {
        return [
            'parcel_number' => '31-12-27-7227-0011-0120',
            'event_type' => 'tax_deed_auction',
            'date' => now()->addMonth()->format('Y-m-d'),
            'time' => '09:00',
            'auction_url' => 'https://example.test/auction/120-bayberry',
            'property_address' => '120 Bayberry Rd',
            'county' => 'putnam',
            'max_bid' => 14500,
            'notes' => 'Research complete.',
        ];
    }
}
