# V100 — Google Calendar Inbound Bookings

## Outcome

Appointments booked directly on the authorized Google Calendar can appear automatically in VVR Command Center as Meeting events.

## Data flow

1. An Owner or Admin enables booking import under **Calendar → Google Calendar**.
2. VVR queues the initial import and records its start boundary.
3. The Namecheap scheduler polls Google every five minutes through a resumable database-queue job.
4. Future standard Google events are created as Google-managed VVR Meetings.
5. Google reschedules update the same VVR record; Google cancellations archive it.
6. VVR-generated Google events are excluded to prevent duplicates and synchronization loops.

## Controls and security

- Only Owner and Admin roles can enable, disable, or manually queue imports.
- OAuth access and refresh tokens remain encrypted at rest.
- Google descriptions and attendee data are treated as untrusted content and escaped in the interface.
- Only valid HTTPS event and meeting links are stored.
- Imported meetings cannot be edited or archived locally.
- The import is page-bounded, retryable, idempotent, and protected by a unique-job lock.
- Sync checkpoints advance only after a complete successful page run.

## Deployment

Deploy the V100 update, run migrations, copy the built frontend assets into Namecheap's actual web root, rebuild Laravel caches, and confirm the scheduler. No Google OAuth reconnection is necessary when the existing authorization already includes `calendar.events`.

After deployment, open **Calendar → Google Calendar**, enable **Google booking import**, and select **Import now**. The queued job will be processed on the next five-minute scheduler cycle.
