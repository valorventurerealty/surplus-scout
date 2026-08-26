<?php

namespace App\Contracts;

use App\Models\GoogleCalendarConnection;

interface GoogleCalendarGatewayInterface
{
    public function authorizationUrl(string $state): string;

    /** @return array<string, mixed> */
    public function exchangeAuthorizationCode(string $code): array;

    /** @return list<array{id: string, name: string, primary: bool, access_role: string}> */
    public function writableCalendars(GoogleCalendarConnection $connection): array;

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function createEvent(GoogleCalendarConnection $connection, string $calendarId, array $payload): array;

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function updateEvent(GoogleCalendarConnection $connection, string $calendarId, string $eventId, array $payload): array;

    public function deleteEvent(GoogleCalendarConnection $connection, string $calendarId, string $eventId): void;

    /** @return array{items: list<array<string, mixed>>, next_page_token: ?string, next_sync_token: ?string} */
    public function listEvents(
        GoogleCalendarConnection $connection,
        string $calendarId,
        ?string $syncToken = null,
        ?string $timeMin = null,
        ?string $pageToken = null,
    ): array;

    public function revoke(GoogleCalendarConnection $connection): void;
}
