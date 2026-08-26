# V127 - Surplus Scout External Worker Package

This handoff moves execution of Osceola Surplus Scout queue jobs to an always-on Windows computer while keeping VVR Command Center and its production database as the source of truth.

Security decisions:

- The archive contains no production `.env` or credentials.
- Scout jobs use the dedicated `surplus-research` queue.
- The external worker listens only to that queue.
- Email, calendar, AI, and general CRM jobs remain on their existing queue.
- The external computer exposes no inbound web service.
- Database migrations remain a Namecheap responsibility.

No database migration is included in V127.
