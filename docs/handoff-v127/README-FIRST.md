# VVR Surplus Scout - External Computer Handoff

This package contains a complete, secret-free VVR Command Center source tree plus the files needed to run only Surplus Scout jobs on an always-on Windows computer.

## Package layout

- `vvr-command-center-source/` - cumulative Laravel source through V126, including Surplus Scout Phase 1 and Osceola owner research.
- `server-update/` - the small update that must be deployed to Namecheap so Scout jobs use their own queue.
- `external-worker/` - environment template, worker launcher, health check, and Windows startup-task scripts.

No `.env` file or production secret is included.

## How it works

1. An authorized user starts research at `https://valorventure.business/surplus-scout/osceola`.
2. Namecheap writes the job to the `surplus-research` database queue.
3. The external computer reads only that queue.
4. The worker downloads and validates the Clerk report, performs owner research when requested, and writes results to the existing VVR database.
5. Results remain visible in the normal VVR Command Center UI.

No inbound port or public website is required on the external computer.

## Requirements for the external computer

- Windows 10 or 11 with automatic sleep disabled.
- PHP 8.2 or newer (PHP 8.4 recommended) with `curl`, `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`, and `zip` extensions.
- Composer 2.
- Poppler for Windows, including `pdftotext.exe`.
- A stable internet connection and preferably a stable public IP address.
- Outbound HTTPS access and outbound MySQL access to the Namecheap database host.

Node.js is not required for a queue-only worker because production frontend assets are already built.

## Step 1 - Update Namecheap

Deploy the contents of `server-update/` following `server-update/DEPLOY-TO-NAMECHEAP.md`.

This must happen first. It moves only Scout jobs to the isolated queue.

## Step 2 - Configure Remote MySQL safely

In Namecheap/cPanel, authorize only the external computer's public IP under Remote MySQL. Create a dedicated database user for the worker when available. It needs read/write access to the VVR database but does not need permission to alter schemas; migrations continue to run only on Namecheap.

Do not use `localhost` as `DB_HOST` on the external computer. Use the remote MySQL hostname shown by Namecheap.

If Namecheap does not permit a remote connection from this computer, stop. Do not expose MySQL publicly. The next appropriate implementation is a signed HTTPS worker API.

## Step 3 - Install the source

Copy this package to a private location such as:

```text
C:\VVR\SurplusScout
```

Open PowerShell in `vvr-command-center-source` and run:

```powershell
composer install --no-dev --optimize-autoloader
Copy-Item ..\external-worker\.env.external-worker.example .env
```

Do not run `php artisan key:generate` and do not run migrations from the external computer.

## Step 4 - Enter secrets locally

Edit the new `.env` on the external computer. Enter these values through a secure channel, not email or chat:

- The same production `APP_KEY` used by VVR.
- The remote Namecheap database hostname.
- A dedicated database username and password.
- The Cloudflare Worker download URL and relay token already used for Osceola Clerk retrieval.
- The exact local path to `pdftotext.exe`.

Never copy the complete production `.env`. The worker template intentionally disables mail delivery and omits Gemini, Google Calendar, Beside, and SMTP secrets.

After saving `.env`, run:

```powershell
php artisan optimize:clear
php artisan config:cache
..\external-worker\check-surplus-worker.ps1
```

The health check must report a working database connection.

## Step 5 - Test in the foreground

Run:

```powershell
..\external-worker\start-surplus-worker.ps1
```

Leave that window open, start one Osceola research run from VVR, and confirm the run completes. Press `Ctrl+C` to stop the foreground test.

## Step 6 - Start automatically at Windows logon

Run PowerShell as the Windows account that will operate the worker:

```powershell
..\external-worker\install-startup-task.ps1
```

The task restarts the Laravel worker if it exits. Confirm it is running in Windows Task Scheduler under `VVR Surplus Scout Worker`.

To stop it temporarily:

```powershell
..\external-worker\stop-startup-task.ps1
```

## Operating rules

- Keep Windows, PHP, Composer, and Poppler patched.
- Enable device encryption and use a password-protected Windows account.
- Never share or upload the worker's `.env`.
- Do not run migrations from the external machine.
- Review `storage/logs/laravel.log` when a run fails.
- Run `external-worker/check-surplus-worker.ps1` after credential, network, or PHP changes.
- If the public IP changes, update the Remote MySQL allowlist immediately.

## Current capabilities and limitations

Included:

- Osceola Clerk report download through the configured secure relay.
- PDF extraction and report validation.
- Normalization, duplicate detection, persistence, amount-change history, and removal detection.
- Osceola Property Appraiser and TRIM owner research workflows already present in VVR.
- Dedicated queue isolation and restartable Windows worker.

Not included:

- A public endpoint on the external computer.
- Automatic web scraping outside the existing authorized county adapters.
- A signed HTTPS worker API. Remote MySQL is required for this package.
- Secrets or production data.
