# Deploy to Namecheap Stellar

Production URL: `https://valorventure.business`  
cPanel home: `/home/valoljta`  
private application: `/home/valoljta/vvr-command-center`  
public document root: `/home/valoljta/public_html`

## Required hosting configuration

In cPanel, select PHP 8.2 or newer and enable `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, and `xml`. Enable SSH access. Create a MySQL database and least-privilege database user. Confirm SSL is active for the domain.

## Directory rule

Never upload the full Laravel repository into `public_html`. Put the repository, `.env`, `vendor`, `storage`, and source under `/home/valoljta/vvr-command-center`. Copy only the contents of Laravel's `public/` directory into `/home/valoljta/public_html`, then replace its `index.php` with `deploy/namecheap/public-index.php`. Keep Laravel's public `.htaccess` in `public_html`.

Expected layout:

```text
/home/valoljta/
├── vvr-command-center/       # private application and .env
│   ├── app/
│   ├── bootstrap/
│   ├── storage/
│   └── vendor/
└── public_html/              # web-accessible files only
    ├── build/
    ├── .htaccess
    ├── favicon.ico
    └── index.php             # supplied hosting-specific front controller
```

## First release

Build assets before uploading (`npm ci && npm run build`). On the server, from `/home/valoljta/vvr-command-center`, install production PHP dependencies with `composer install --no-dev --prefer-dist --optimize-autoloader`. Copy `.env.example` to `.env` and set:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://valorventure.business
DB_CONNECTION=mysql
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
INITIAL_ADMIN_NAME="VVR Owner"
INITIAL_ADMIN_EMAIL="owner@your-company-domain"
INITIAL_ADMIN_PASSWORD="a unique high-entropy bootstrap password"
```

Then run `php artisan key:generate`, `php artisan migrate --force`, `php artisan db:seed --force`, and `php artisan optimize`. Remove `INITIAL_ADMIN_PASSWORD` from `.env` after the first owner is created. Ensure `storage/` and `bootstrap/cache/` are writable by the account PHP process, but do not make them world-writable.

## Cron and queues

Stellar permits five-minute cron cadence. Add one cPanel cron entry (adjust the PHP binary path shown by cPanel if necessary):

```cron
*/5 * * * * cd /home/valoljta/vvr-command-center && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

The application scheduler drains database queue jobs with `--stop-when-empty`. When moving to DigitalOcean, AWS, or Azure, remove that scheduled queue drain and run persistent workers under Supervisor/systemd or the platform's worker service.

## Release procedure

Back up the MySQL database and `.env`; enable maintenance mode; upload the new source and built assets; run Composer; run migrations; clear and rebuild Laravel caches; restore writable permissions; disable maintenance mode; then verify `/up`, login, dashboard, contacts, password reset delivery, scheduled tasks, and `storage/logs/laravel.log`.

Do not store cPanel, database, SMTP, or OpenAI credentials in GitHub. Use cPanel `.env` now and GitHub/environment secrets for automated deployments later.
