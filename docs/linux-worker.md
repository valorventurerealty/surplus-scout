# Surplus Scout Linux worker

This deployment runs only the Laravel `surplus-research` queue. It does not expose a web port and does not run database migrations.

## Prerequisites

- Docker Engine with Docker Compose
- PHP 8.4 is supplied by the container to match the committed dependency lockfile
- A writable persistent directory on the 1TB data drive
- A worker-specific production `.env.scout`
- Production database access restricted to this machine
- The configured Osceola secure relay when direct Clerk downloads are unavailable

## Configure

1. Copy `.env.scout.example` to `.env.scout`.
2. Add the production `APP_KEY`, database connection, and relay values locally.
3. Confirm `VVR_SCOUT_DATA_PATH` and `VVR_SCOUT_LOG_PATH` are writable directories on the 1TB drive.
4. Never run migrations from this worker.

## Validate without production access

```bash
docker build --target development -t vvr-surplus-scout:test .
docker run --rm vvr-surplus-scout:test php artisan test --filter=Osceola
```

The tests use SQLite in memory and mocked HTTP; they do not contact the Clerk or production database.

## Validate configuration

```bash
docker compose --env-file .env.scout -f compose.scout.yaml config --quiet
docker compose --env-file .env.scout -f compose.scout.yaml run --rm scout-worker php artisan about --only=environment
```

Do not continue unless the application boots and the database configuration has been independently confirmed.

## Run

```bash
docker compose --env-file .env.scout -f compose.scout.yaml up -d --build
docker compose --env-file .env.scout -f compose.scout.yaml logs --tail=100 scout-worker
```

The `unless-stopped` restart policy causes Docker to restart the worker after a reboot when the Docker service is enabled.

## Stop

```bash
docker compose --env-file .env.scout -f compose.scout.yaml down
```
