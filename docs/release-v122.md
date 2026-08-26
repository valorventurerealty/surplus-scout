# V122 — Osceola Secure Relay Hotfix

Namecheap Stellar can reach general HTTPS endpoints but times out when connecting directly to the Osceola Clerk host. This release adds an optional authenticated Cloudflare Worker transport relay without changing the authoritative government source stored in VVR.

## Security and integrity

- The Worker can retrieve only the hard-coded Osceola Clerk PDF.
- A 64-character random shared token is required in the `X-VVR-Relay-Token` header.
- The token is stored as a Cloudflare secret and only in Laravel's private `.env`.
- The relay rejects non-GET requests, non-PDF responses, malformed PDF signatures, and files over 15 MB.
- Responses are not cached.
- Laravel independently repeats PDF signature, size, report-title, columns, county, and record validation.
- VVR stores the Clerk URL as `source_url`; the Worker URL is transport configuration only.
- Direct Clerk retrieval remains the default when no relay URL is configured.

## Configuration

After deploying `deploy/cloudflare/osceola-surplus-relay/worker.js`, configure:

```dotenv
OSCEOLA_SURPLUS_DOWNLOAD_URL="https://YOUR-WORKER.YOUR-SUBDOMAIN.workers.dev"
OSCEOLA_SURPLUS_RELAY_TOKEN="THE-SAME-64-CHARACTER-SECRET"
```

Then run `php artisan optimize:clear`, test the relay from the server, and run Osceola Research again. No database migration is required.
