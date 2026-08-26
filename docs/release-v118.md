# V118 Beside Call Enrichment

V118 lets a later Beside/Zapier delivery enrich an existing VVR phone-call record with post-call information. The existing record is matched by the stable Beside call ID and updated instead of duplicated.

Supported fields include:

- `summary` (aliases: `call_summary`, `ai_summary`, `notes`, `call_notes`)
- `transcript` (alias: `call_transcript`)
- `recording_url` (aliases: `recording_link`, `recording`)
- `action_items`
- `duration_seconds`
- caller name, email, company, inbox, and phone number

The identifier may be supplied as `event_id`, `call_id`, or `provider_call_id`. Zapier must send the same Beside call ID used when the call was first created.

Blank follow-up values never erase existing call content. Confidential summary and transcript text is not copied into the audit log; the audit entry records only which fields changed.

## Zapier mapping

Map the Webhooks by Zapier JSON body as follows:

```text
event_id       = Beside Call ID
event_type     = call
direction      = Beside direction
occurred_at    = Beside call date/time
summary        = Beside summary or notes
transcript     = Beside transcript
recording_url  = Beside recording URL
action_items   = Beside action items
```

The existing `X-VVR-Beside-Secret` header remains required.

## Deployment

Extract the release into `/home/valoljta/vvr-command-center`, overwrite existing files, then run:

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan optimize
```

No migration is required.
