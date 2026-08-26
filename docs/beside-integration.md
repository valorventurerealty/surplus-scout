# Beside phone integration

## Architecture

Beside sends a completed event to Zapier. Zapier maps the event into VVR's canonical JSON format and posts it to:

`POST https://valorventure.business/integrations/beside/events`

The request must include:

`X-VVR-Beside-Secret: <private 64-character secret>`

VVR validates the secret, throttles the endpoint, validates every field, detects a replayed provider event, normalizes the external party's phone number, and searches the CRM. Exactly one phone match is linked. No match is marked `unmatched`; multiple matches are marked `conflicting`. Neither condition creates a contact automatically.

The resulting activity is available at `/phone-calls` and on the linked contact page. An authorized user can review and manually link an unmatched or conflicting activity.

## Canonical payload

```json
{
  "event_id": "stable Beside call or event ID",
  "event_type": "call",
  "direction": "inbound",
  "occurred_at": "2026-08-16T10:30:00-04:00",
  "phone_number": "+1 407 555 0100",
  "caller_name": "Jane Owner",
  "caller_email": "jane@example.com",
  "caller_company": null,
  "inbox": "Main Line",
  "duration_seconds": 185,
  "summary": "Caller asked about the property.",
  "transcript": "Full transcript from Beside.",
  "recording_url": "https://authorized-provider.example/recording",
  "action_items": ["Return the call"],
  "provider_payload": {}
}
```

Required fields are `event_id`, `event_type`, and `occurred_at`. Supported event types are `call`, `lead`, `voicemail`, `message`, `capture`, and `voice_note`. Supported directions are `inbound`, `outbound`, and `unknown`.

Use Beside's stable event identifier for `event_id`; never use the current time or a random value. It is the idempotency key that prevents Zapier retries from creating duplicates. For `phone_number`, map the external caller or recipient—not VVR's own inbox number.

## Zapier setup

1. Create a Zap and choose **Beside** as the trigger app.
2. Select the **Call** trigger first. Connect the VVR Beside account and complete one fictional test call so Zapier receives sample fields.
3. Add **Webhooks by Zapier** as the action. Choose a POST or Custom Request action that supports JSON and custom headers.
4. Enter `https://valorventure.business/integrations/beside/events` as the URL.
5. Set the payload type to JSON and add `Content-Type: application/json`.
6. Add the `X-VVR-Beside-Secret` header using the same private value stored in VVR's `.env`.
7. Map Beside's sample fields into the canonical payload. Field labels can vary, so inspect the actual sample rather than guessing.
8. Test the action. A new event returns HTTP 201 with `accepted: true`; a safe replay returns HTTP 200 with `created: false`.
9. Verify the activity in **Phone Calls** and on the matched contact page, then publish the Zap.
10. Clone the Zap for any additional Beside triggers desired and change only `event_type` plus the relevant field mappings.

Do not place the webhook secret in the JSON body, a browser URL, a contact note, or a Zap name. Limit access to the Zapier workspace and rotate the secret if it is exposed.

## Namecheap deployment

Generate a secret on the server:

```bash
cd /home/valoljta/vvr-command-center
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Copy the output into the private `.env` file:

```dotenv
BESIDE_WEBHOOK_SECRET="paste-the-64-character-value-here"
```

Then deploy and refresh Laravel:

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
php artisan route:list --name=integrations.beside.events
```

If a Zapier test receives an HTML security-verification page instead of JSON, Namecheap/LiteSpeed is intercepting the webhook before Laravel. Do not disable all site security. Ask Namecheap to allowlist the exact path `/integrations/beside/events`, or use the narrowest endpoint-specific security exception available, then repeat the signed test.

## Security and privacy

- The API secret stays server-side and is compared using constant-time comparison.
- The endpoint is CSRF-exempt because Zapier cannot hold a Laravel browser session; the signed secret and rate limit replace browser CSRF protection for this endpoint.
- Payloads are strictly validated and do not invoke CRM actions.
- Transcripts and provider payloads are not copied into generic audit records. The audit log stores only a compact receipt.
- Marketing users cannot see unmatched activity or activity linked to Surplus or PreTax Auction contacts.
- Linked-record authorization is rechecked when viewing details or manually changing a contact link.
- Recording URLs are only accepted over HTTP or HTTPS and open in a separate protected tab.

## Operational boundaries

This milestone tracks activity. It does not let transcripts autonomously create contacts, tasks, deals, properties, or send messages. Those actions require a later reviewed automation milestone with explicit tool permissions and approvals.

Beside currently routes custom integrations through Zapier; the Command Center integration does not depend on Beside's private-beta Apps installation link.
