# Google Calendar integration

The integration has two controlled synchronization paths. VVR is authoritative for events created in VVR and sends those changes to Google. Google is authoritative for bookings created directly in the selected Google Calendar and imports them into VVR as Google-managed Meetings.

## Google booking import

An Owner or Admin enables booking import from **Calendar → Google Calendar**. The first import uses the enable time as its lower boundary, so past appointments are not backfilled. Future events that already exist in Google are eligible because their start time is after that boundary.

After the first successful import, VVR stores Google's opaque incremental synchronization token. Subsequent runs request only changes. If Google expires a token, VVR performs a bounded fresh synchronization beginning at the original import boundary.

Imported data includes the event title, start and end time, location, description, Google event link, HTTPS meeting link, organizer email, and up to 100 attendees. Google descriptions are untrusted data and are escaped when displayed. Special Google event types such as focus time, working location, and out-of-office are not imported.

The Google event identity is stored as a SHA-256 key scoped to the connection and calendar. Repeated imports are idempotent. Reschedules update the existing meeting and Google cancellations soft-delete it. Events originally exported by VVR are identified by their private extended property and are never imported back.

Google-managed meetings are read-only in VVR. Reschedule or cancel them in Google Calendar; the next inbound reconciliation updates or archives the VVR record.

## Workflow

1. An Owner or Admin connects a Google account through OAuth 2.0.
2. VVR requests only event-management and calendar-list read scopes.
3. The administrator selects one writable destination calendar.
4. Creating or editing a meeting or auction event records the VVR change first and queues synchronization after the database transaction commits.
5. The Namecheap scheduler drains the database queue within its normal five-minute cadence.
6. The event page displays `Not connected`, `Pending`, `Synced`, `Failed`, `Cancellation pending`, or `Removed from Google`.
7. Archiving an already-synchronized VVR event queues deletion of the matching Google event.

When booking import is disabled, VVR does not request Google event changes. Existing imported meetings are preserved.

## Shared-hosting execution

`ImportGoogleCalendarEventsJob` is scheduled every five minutes before the existing queue-drain command. It uses the database queue, a unique-job lock, bounded pages, request timeouts, and retries. Namecheap does not need Redis, Docker, Python, or a continuously running worker.

The integration page also offers **Import now**. That button queues the same governed job; the five-minute queue worker processes it.

The existing OAuth authorization scope `https://www.googleapis.com/auth/calendar.events` supports inbound event listing. Optional safety settings are:

```dotenv
GOOGLE_CALENDAR_INBOUND_PAGE_SIZE=2500
GOOGLE_CALENDAR_INBOUND_MAX_PAGES=10
```

Errors are recorded on the connection and shown to administrators. A failed request does not claim success and does not advance the synchronization checkpoint.

## Security

- Client credentials live only in the private server `.env` file.
- OAuth access and refresh tokens use Laravel's encrypted database casts and are excluded from model audit payloads.
- OAuth callbacks validate a single-use session state before exchanging a code.
- Only active Owner and Admin accounts can connect, select, or disconnect a Google Calendar.
- Normal Calendar policies still control event creation, updates, archive actions, and manual retries.
- Google credentials and tokens are never sent to frontend JavaScript.
- Disconnect attempts remote token revocation and always removes locally stored tokens.
- Max bid is deliberately excluded from the Google event payload.

OAuth connections are never seeded. The module includes a factory for automated tests, but production authorization can only be established through the authenticated OAuth callback.

## Idempotency and retries

Each VVR event receives a deterministic Google-compatible event ID derived from the application URL and VVR event ID. A repeated create request therefore updates the same Google event instead of creating a duplicate. The VVR record stores Google's event ID, link, ETag, destination calendar, synchronization version, timestamps, status, and a safe failure message.

Every queued write includes the current synchronization version. Stale jobs stop without writing. Jobs retry at bounded intervals and never loop indefinitely. An Owner, Admin, or other user allowed to edit Calendar may queue a manual retry from the event page.

## Google Cloud configuration

Enable Google Calendar API and create an OAuth 2.0 **Web application** client. Configure these scopes:

```text
https://www.googleapis.com/auth/calendar.events
https://www.googleapis.com/auth/calendar.calendarlist.readonly
```

Authorized origin:

```text
https://valorventure.business
```

Authorized redirect URI:

```text
https://valorventure.business/settings/integrations/google-calendar/callback
```

Add the connecting Google account as a test user while the OAuth app is in Testing. Google testing-mode refresh tokens can be time-limited; move the OAuth application to the appropriate production publishing status before relying on unattended long-term synchronization.

## Environment

```dotenv
GOOGLE_CALENDAR_CLIENT_ID=
GOOGLE_CALENDAR_CLIENT_SECRET=
GOOGLE_CALENDAR_REDIRECT_URI="https://valorventure.business/settings/integrations/google-calendar/callback"
GOOGLE_CALENDAR_REQUEST_TIMEOUT=20
GOOGLE_CALENDAR_DEFAULT_DURATION_MINUTES=60
```

Do not add a `VITE_` prefix to any secret.

## Namecheap operation

No Redis, Docker container, Python service, or persistent queue worker is required. The existing five-minute `schedule:run` cron launches `queue:work --stop-when-empty`. A newly saved event normally appears in Google Calendar during the next queue drain. The architecture can use a persistent worker later without changing the CRM or Google tool layer.

## Failure behavior

Google timeouts, rejected authorization, rate limits, missing calendars, expired tokens, and API errors never roll back the authoritative VVR event. The VVR event is marked failed with a safe explanation and can be retried. The interface never claims an event synchronized unless Google returned a successful result.
