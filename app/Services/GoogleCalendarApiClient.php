<?php

namespace App\Services;

use App\Contracts\GoogleCalendarGatewayInterface;
use App\Exceptions\GoogleCalendarException;
use App\Exceptions\GoogleCalendarSyncTokenExpiredException;
use App\Models\GoogleCalendarConnection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GoogleCalendarApiClient implements GoogleCalendarGatewayInterface
{
    public function authorizationUrl(string $state): string
    {
        $this->ensureConfigured();

        return rtrim((string) config('services.google_calendar.auth_url'), '?').'?'.http_build_query([
            'client_id' => config('services.google_calendar.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', config('services.google_calendar.scopes', [])),
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(string $code): array
    {
        $this->ensureConfigured();
        $response = Http::asForm()->acceptJson()->timeout($this->timeout())->post(
            (string) config('services.google_calendar.token_url'),
            [
                'code' => $code,
                'client_id' => config('services.google_calendar.client_id'),
                'client_secret' => config('services.google_calendar.client_secret'),
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
            ],
        );

        if (! $response->successful() || blank($response->json('access_token'))) {
            throw new GoogleCalendarException('Google did not accept the authorization response. Reconnect the calendar and try again.');
        }

        return $response->json();
    }

    public function writableCalendars(GoogleCalendarConnection $connection): array
    {
        $response = $this->request($connection, 'GET', '/users/me/calendarList', [
            'query' => ['minAccessRole' => 'writer', 'showHidden' => 'false'],
        ]);

        return collect($response->json('items', []))
            ->filter(fn (array $calendar): bool => in_array($calendar['accessRole'] ?? null, ['owner', 'writer'], true))
            ->map(fn (array $calendar): array => [
                'id' => (string) ($calendar['id'] ?? ''),
                'name' => (string) ($calendar['summary'] ?? $calendar['id'] ?? 'Google Calendar'),
                'primary' => (bool) ($calendar['primary'] ?? false),
                'access_role' => (string) ($calendar['accessRole'] ?? ''),
            ])
            ->filter(fn (array $calendar): bool => filled($calendar['id']))
            ->values()
            ->all();
    }

    public function createEvent(GoogleCalendarConnection $connection, string $calendarId, array $payload): array
    {
        $response = $this->request($connection, 'POST', $this->eventCollectionPath($calendarId), [
            'json' => $payload,
        ], [409]);

        if ($response->status() === 409) {
            return $this->updateEvent($connection, $calendarId, (string) $payload['id'], $payload);
        }

        return $response->json();
    }

    public function updateEvent(GoogleCalendarConnection $connection, string $calendarId, string $eventId, array $payload): array
    {
        $response = $this->request(
            $connection,
            'PATCH',
            $this->eventCollectionPath($calendarId).'/'.rawurlencode($eventId),
            ['json' => Arr::except($payload, ['id'])],
            [404, 410],
        );

        if (in_array($response->status(), [404, 410], true)) {
            $payload['id'] = 'vvr'.hash('sha256', (string) ($payload['id'] ?? $eventId).'|recovered');

            return $this->createEvent($connection, $calendarId, $payload);
        }

        return $response->json();
    }

    public function deleteEvent(GoogleCalendarConnection $connection, string $calendarId, string $eventId): void
    {
        $this->request(
            $connection,
            'DELETE',
            $this->eventCollectionPath($calendarId).'/'.rawurlencode($eventId),
            [],
            [404, 410],
        );
    }

    public function listEvents(
        GoogleCalendarConnection $connection,
        string $calendarId,
        ?string $syncToken = null,
        ?string $timeMin = null,
        ?string $pageToken = null,
    ): array {
        $query = [
            'showDeleted' => 'true',
            'singleEvents' => 'true',
            'maxResults' => max(1, min(2500, (int) config('services.google_calendar.inbound_page_size', 2500))),
        ];

        if ($syncToken) {
            $query['syncToken'] = $syncToken;
        } else {
            $query['timeMin'] = $timeMin ?: now()->toIso8601String();
            $query['orderBy'] = 'startTime';
        }
        if ($pageToken) {
            $query['pageToken'] = $pageToken;
        }

        $response = $this->request($connection, 'GET', $this->eventCollectionPath($calendarId), [
            'query' => $query,
        ], [410]);

        if ($response->status() === 410) {
            throw new GoogleCalendarSyncTokenExpiredException('Google Calendar requested a fresh synchronization checkpoint.');
        }

        return [
            'items' => collect($response->json('items', []))
                ->filter(fn (mixed $item): bool => is_array($item))
                ->values()
                ->all(),
            'next_page_token' => filled($response->json('nextPageToken')) ? (string) $response->json('nextPageToken') : null,
            'next_sync_token' => filled($response->json('nextSyncToken')) ? (string) $response->json('nextSyncToken') : null,
        ];
    }

    public function revoke(GoogleCalendarConnection $connection): void
    {
        $token = $connection->refresh_token ?: $connection->access_token;
        if (blank($token)) {
            return;
        }

        $response = Http::asForm()->acceptJson()->timeout($this->timeout())->post(
            (string) config('services.google_calendar.revoke_url'),
            ['token' => $token],
        );
        if (! $response->successful()) {
            throw new GoogleCalendarException('Google could not confirm remote authorization revocation. Local credentials were removed.');
        }
    }

    private function request(
        GoogleCalendarConnection $connection,
        string $method,
        string $path,
        array $options = [],
        array $acceptedStatuses = [],
    ): Response {
        $response = $this->send($connection, $method, $path, $options);

        if ($response->status() === 401) {
            $this->accessToken($connection, true);
            $response = $this->send($connection->refresh(), $method, $path, $options);
        }

        if (! $response->successful() && ! in_array($response->status(), $acceptedStatuses, true)) {
            $reason = match ($response->status()) {
                401 => 'Google authorization expired. Reconnect Google Calendar.',
                403 => 'Google denied access to the selected calendar. Confirm the account still has write permission.',
                404 => 'The Google Calendar or event could not be found.',
                429 => 'Google Calendar is temporarily rate limiting requests. The event will be retried.',
                default => 'Google Calendar returned an unexpected error (HTTP '.$response->status().').',
            };

            throw new GoogleCalendarException($reason);
        }

        return $response;
    }

    private function send(GoogleCalendarConnection $connection, string $method, string $path, array $options): Response
    {
        return Http::acceptJson()
            ->withToken($this->accessToken($connection))
            ->timeout($this->timeout())
            ->send($method, rtrim((string) config('services.google_calendar.api_url'), '/').$path, $options);
    }

    private function accessToken(GoogleCalendarConnection $connection, bool $force = false): string
    {
        if (! $force && filled($connection->access_token) && $connection->token_expires_at?->isAfter(now()->addMinute())) {
            return (string) $connection->access_token;
        }

        return DB::transaction(function () use ($connection, $force): string {
            $locked = GoogleCalendarConnection::query()->lockForUpdate()->findOrFail($connection->id);
            if (! $force && filled($locked->access_token) && $locked->token_expires_at?->isAfter(now()->addMinute())) {
                return (string) $locked->access_token;
            }
            if (blank($locked->refresh_token)) {
                throw new GoogleCalendarException('Google Calendar is disconnected. Connect it again from Calendar settings.');
            }

            $response = Http::asForm()->acceptJson()->timeout($this->timeout())->post(
                (string) config('services.google_calendar.token_url'),
                [
                    'client_id' => config('services.google_calendar.client_id'),
                    'client_secret' => config('services.google_calendar.client_secret'),
                    'refresh_token' => $locked->refresh_token,
                    'grant_type' => 'refresh_token',
                ],
            );
            if (! $response->successful() || blank($response->json('access_token'))) {
                throw new GoogleCalendarException('Google Calendar authorization could not be refreshed. Reconnect the calendar.');
            }

            $locked->updateQuietly([
                'access_token' => $response->json('access_token'),
                'refresh_token' => $response->json('refresh_token', $locked->refresh_token),
                'token_expires_at' => now()->addSeconds(max(60, (int) $response->json('expires_in', 3600))),
                'last_error' => null,
            ]);

            return (string) $locked->access_token;
        });
    }

    private function eventCollectionPath(string $calendarId): string
    {
        return '/calendars/'.rawurlencode($calendarId).'/events';
    }

    private function redirectUri(): string
    {
        return (string) (config('services.google_calendar.redirect_uri') ?: route('google-calendar.callback'));
    }

    private function timeout(): int
    {
        return max(5, (int) config('services.google_calendar.timeout', 20));
    }

    private function ensureConfigured(): void
    {
        if (blank(config('services.google_calendar.client_id')) || blank(config('services.google_calendar.client_secret'))) {
            throw new GoogleCalendarException('Google Calendar credentials have not been configured on the server.');
        }
    }
}
