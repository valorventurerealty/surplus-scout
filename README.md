# VVR Command Center

VVR Command Center includes a controlled Email workspace for CRM-linked drafts, Armory templates, private attachments, signatures, explicit approval, and database-queued delivery through the configured server-side SMTP account. See `docs/modules/EMAIL-WORKSPACE.md`.

The auction Calendar optionally synchronizes VVR-created events to a connected Google Calendar through encrypted server-side OAuth credentials and database-queued, idempotent jobs. See `docs/modules/GOOGLE-CALENDAR-INTEGRATION.md`.

Valor Venture Realty's private operating system for off-market real estate operations.

This repository contains the Milestone 1 foundation plus the delivered Contacts, Properties, Deals, Surplus, Pre-Auction Tax Deed Acquisitions, Pipeline, Tasks, Calendar, Financials, Armory, SOPs, and VVR AI increments. VVR AI provides backend-only Gemini property intake, read-only SOP retrieval, and a registered CRM action engine for authorized searches, property and Surplus updates, pipeline movement, checklist updates, tasks, buyer searches, reporting, and auction events. Every write pauses for explicit approval and is revalidated, authorized, executed transactionally, audited, and reported with direct record links. Manual workflows remain fully available without invoking AI.

## Requirements

- PHP 8.2–8.5 with Laravel-required extensions
- Composer 2
- MySQL 8+ (SQLite may be used by automated tests)
- Node.js 22 and npm

## Local installation

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
php artisan migrate --seed
php artisan serve
```

For local sample data, leave `APP_ENV=local`. To create a specific initial owner, set `INITIAL_ADMIN_NAME`, `INITIAL_ADMIN_EMAIL`, and `INITIAL_ADMIN_PASSWORD` before seeding. Never commit `.env`.

## Quality checks

```bash
vendor/bin/pint --test
php artisan test
npm run build
```

See [architecture](docs/ARCHITECTURE.md), [VVR AI workspace](docs/modules/VVR-AI-WORKSPACE.md), [Surplus module](docs/modules/SURPLUS.md), [SOPs module](docs/modules/SOPS.md), [Properties module](docs/modules/PROPERTIES.md), [Tasks module](docs/modules/TASKS.md), [Armory module](docs/modules/ARMORY.md), and [Namecheap deployment](deploy/namecheap/DEPLOYMENT.md).

## Production boundary

Only `public/` is web-accessible. Source code, `.env`, `vendor/`, and `storage/` must remain outside the document root. Public registration is deliberately unavailable; an initial Owner account is provisioned through the guarded production seeder.
