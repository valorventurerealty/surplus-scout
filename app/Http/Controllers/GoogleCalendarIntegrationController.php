<?php

namespace App\Http\Controllers;

use App\Contracts\GoogleCalendarGatewayInterface;
use App\Exceptions\GoogleCalendarException;
use App\Models\CalendarEvent;
use App\Models\GoogleCalendarConnection;
use App\Enums\CalendarEventSource;
use App\Jobs\ImportGoogleCalendarEventsJob;
use App\Services\GoogleCalendarSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class GoogleCalendarIntegrationController extends Controller
{
    public function index(GoogleCalendarGatewayInterface $gateway): View
    {
        Gate::authorize('manageGoogleCalendarIntegration');
        $connection = GoogleCalendarConnection::active();
        $calendars = [];
        if ($connection) {
            try {
                $calendars = $gateway->writableCalendars($connection);
                $connection->updateQuietly(['last_error' => null]);
            } catch (Throwable $exception) {
                $connection->updateQuietly(['last_error' => Str::limit($exception->getMessage(), 1000)]);
            }
        }

        return view('settings.integrations.google-calendar', [
            'connection' => $connection?->fresh(),
            'calendars' => $calendars,
            'configured' => filled(config('services.google_calendar.client_id'))
                && filled(config('services.google_calendar.client_secret')),
            'pendingCount' => CalendarEvent::query()->whereIn('google_sync_status', [
                'not_configured', 'pending', 'failed',
            ])->where('source', CalendarEventSource::Vvr)->where('starts_at', '>=', now()->startOfDay())->count(),
            'importedCount' => CalendarEvent::withTrashed()->where('source', CalendarEventSource::Google)->count(),
        ]);
    }

    public function connect(Request $request, GoogleCalendarGatewayInterface $gateway): RedirectResponse
    {
        Gate::authorize('manageGoogleCalendarIntegration');
        $state = Str::random(64);
        $request->session()->put('google_calendar_oauth_state', $state);

        try {
            return redirect()->away($gateway->authorizationUrl($state));
        } catch (GoogleCalendarException $exception) {
            return redirect()->route('google-calendar.index')->withErrors(['google_calendar' => $exception->getMessage()]);
        }
    }

    public function callback(Request $request, GoogleCalendarGatewayInterface $gateway): RedirectResponse
    {
        Gate::authorize('manageGoogleCalendarIntegration');
        if ($request->filled('error')) {
            return redirect()->route('google-calendar.index')->withErrors([
                'google_calendar' => 'Google Calendar authorization was cancelled or denied.',
            ]);
        }

        $expectedState = (string) $request->session()->pull('google_calendar_oauth_state', '');
        $state = (string) $request->query('state', '');
        if (blank($expectedState) || blank($state) || ! hash_equals($expectedState, $state)) {
            abort(419, 'Google Calendar authorization session expired.');
        }
        $validated = $request->validate(['code' => ['required', 'string', 'max:4096']]);

        try {
            $tokens = $gateway->exchangeAuthorizationCode($validated['code']);
            $existing = GoogleCalendarConnection::query()->where('user_id', $request->user()->id)->first();
            $refreshToken = $tokens['refresh_token'] ?? $existing?->refresh_token;
            if (blank($refreshToken)) {
                throw new GoogleCalendarException('Google did not provide offline access. Remove VVR Command Center from your Google account permissions and reconnect.');
            }

            $connection = DB::transaction(function () use ($request, $tokens, $refreshToken): GoogleCalendarConnection {
                GoogleCalendarConnection::query()->where('is_active', true)->update(['is_active' => false]);

                return GoogleCalendarConnection::query()->updateOrCreate(
                    ['user_id' => $request->user()->id],
                    [
                        'access_token' => $tokens['access_token'],
                        'refresh_token' => $refreshToken,
                        'token_expires_at' => now()->addSeconds(max(60, (int) ($tokens['expires_in'] ?? 3600))),
                        'scopes' => preg_split('/\s+/', trim((string) ($tokens['scope'] ?? '')), -1, PREG_SPLIT_NO_EMPTY),
                        'is_active' => true,
                        'connected_at' => now(),
                        'revoked_at' => null,
                        'last_error' => null,
                    ],
                );
            });

            $calendars = $gateway->writableCalendars($connection);
            $selected = collect($calendars)->firstWhere('primary', true) ?? $calendars[0] ?? null;
            if (! $selected) {
                throw new GoogleCalendarException('The connected Google account has no writable calendars.');
            }
            $calendarChanged = $connection->calendar_id !== $selected['id'];
            $connection->update([
                'google_account_email' => $selected['primary'] ? $selected['id'] : null,
                'calendar_id' => $selected['id'],
                'calendar_name' => $selected['name'],
                ...($calendarChanged && $connection->inbound_sync_enabled ? [
                    'inbound_sync_started_at' => now(),
                    'inbound_sync_token' => null,
                    'inbound_sync_error' => null,
                ] : []),
            ]);

            return redirect()->route('google-calendar.index')->with('success', 'Google Calendar connected. Select the destination calendar and sync upcoming events.');
        } catch (Throwable $exception) {
            return redirect()->route('google-calendar.index')->withErrors([
                'google_calendar' => $exception instanceof GoogleCalendarException
                    ? $exception->getMessage()
                    : 'Google Calendar connection could not be completed. Review the Google configuration and reconnect.',
            ]);
        }
    }

    public function update(Request $request, GoogleCalendarGatewayInterface $gateway): RedirectResponse
    {
        Gate::authorize('manageGoogleCalendarIntegration');
        $connection = GoogleCalendarConnection::active();
        if (! $connection) {
            throw ValidationException::withMessages(['calendar_id' => 'Connect Google Calendar first.']);
        }
        $validated = $request->validate(['calendar_id' => ['required', 'string', 'max:255']]);
        try {
            $calendar = collect($gateway->writableCalendars($connection))->firstWhere('id', $validated['calendar_id']);
        } catch (GoogleCalendarException $exception) {
            return redirect()->route('google-calendar.index')->withErrors(['calendar_id' => $exception->getMessage()]);
        }
        if (! $calendar) {
            throw ValidationException::withMessages(['calendar_id' => 'Select a writable Google Calendar from the authorized list.']);
        }
        $calendarChanged = $connection->calendar_id !== $calendar['id'];
        $connection->update([
            'calendar_id' => $calendar['id'],
            'calendar_name' => $calendar['name'],
            'last_error' => null,
            ...($calendarChanged && $connection->inbound_sync_enabled ? [
                'inbound_sync_started_at' => now(),
                'inbound_sync_token' => null,
                'last_inbound_sync_at' => null,
                'inbound_sync_error' => null,
            ] : []),
        ]);

        return redirect()->route('google-calendar.index')->with('success', 'Google Calendar destination updated.');
    }

    public function updateInboundSync(Request $request): RedirectResponse
    {
        Gate::authorize('manageGoogleCalendarIntegration');
        $connection = GoogleCalendarConnection::active();
        if (! $connection) {
            throw ValidationException::withMessages(['google_calendar' => 'Connect Google Calendar first.']);
        }

        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        $enabled = (bool) $validated['enabled'];
        $enabling = $enabled && ! $connection->inbound_sync_enabled;
        $connection->update([
            'inbound_sync_enabled' => $enabled,
            'inbound_sync_token' => $enabling || ! $enabled ? null : $connection->inbound_sync_token,
            'inbound_sync_started_at' => $enabling ? now() : $connection->inbound_sync_started_at,
            'last_inbound_sync_at' => $enabling ? null : $connection->last_inbound_sync_at,
            'inbound_sync_error' => null,
        ]);

        if ($enabled) {
            ImportGoogleCalendarEventsJob::dispatch();
        }

        return redirect()->route('google-calendar.index')->with('success', $enabled
            ? 'Google booking import enabled. Future Google events will appear as VVR meetings.'
            : 'Google booking import disabled. Existing imported meetings were preserved.');
    }

    public function runInboundSync(): RedirectResponse
    {
        Gate::authorize('manageGoogleCalendarIntegration');
        $connection = GoogleCalendarConnection::active();
        if (! $connection?->inbound_sync_enabled) {
            throw ValidationException::withMessages(['google_calendar' => 'Enable Google booking import first.']);
        }

        ImportGoogleCalendarEventsJob::dispatch();

        return redirect()->route('google-calendar.index')->with('success', 'Google booking import queued.');
    }

    public function syncUpcoming(GoogleCalendarSyncService $sync): RedirectResponse
    {
        Gate::authorize('manageGoogleCalendarIntegration');
        if (! GoogleCalendarConnection::active()) {
            throw ValidationException::withMessages(['google_calendar' => 'Connect Google Calendar first.']);
        }

        $queued = 0;
        CalendarEvent::query()
            ->where('source', CalendarEventSource::Vvr)
            ->where('starts_at', '>=', now()->startOfDay())
            ->whereIn('google_sync_status', ['not_configured', 'pending', 'failed'])
            ->orderBy('id')
            ->chunkById(100, function ($events) use ($sync, &$queued): void {
                foreach ($events as $event) {
                    $queued += $sync->queue($event) ? 1 : 0;
                }
            });

        return redirect()->route('google-calendar.index')->with('success', $queued.' upcoming event(s) queued for Google Calendar.');
    }

    public function retry(CalendarEvent $event, GoogleCalendarSyncService $sync): RedirectResponse
    {
        Gate::authorize('update', $event);
        if (! $sync->queue($event)) {
            return redirect()->route('calendar.show', $event)->withErrors(['google_calendar' => 'Connect Google Calendar before syncing this event.']);
        }

        return redirect()->route('calendar.show', $event)->with('success', 'Google Calendar synchronization queued.');
    }

    public function disconnect(GoogleCalendarGatewayInterface $gateway): RedirectResponse
    {
        Gate::authorize('manageGoogleCalendarIntegration');
        $connection = GoogleCalendarConnection::active();
        $warning = null;
        if ($connection) {
            try {
                $gateway->revoke($connection);
            } catch (GoogleCalendarException $exception) {
                $warning = $exception->getMessage();
            }
        }
        $connection?->update([
            'is_active' => false,
            'inbound_sync_enabled' => false,
            'inbound_sync_token' => null,
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'revoked_at' => now(),
        ]);

        $redirect = redirect()->route('google-calendar.index')->with('success', 'Google Calendar disconnected. Existing Google events were left unchanged.');

        return $warning ? $redirect->withErrors(['google_calendar' => $warning]) : $redirect;
    }
}
