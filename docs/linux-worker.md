# Surplus Scout Linux worker

This deployment runs only the Laravel `surplus-research` queue. It does not expose a web port and does not run database migrations.

## Prerequisites

- Docker Engine with Docker Compose
- PHP 8.4 is supplied by the container to match the committed dependency lockfile
- A writable persistent directory on the 1TB data drive
- A worker-specific production `.env.scout`
- Production database access restricted to this machine
- Namecheap SSH access with a dedicated authorized key for the database tunnel
- The configured Osceola secure relay when direct Clerk downloads are unavailable

## Configure

1. Copy `.env.scout.example` to `.env.scout`.
2. Add the production `APP_KEY`, database connection, and relay values locally.
3. Confirm `VVR_SCOUT_DATA_PATH` and `VVR_SCOUT_LOG_PATH` are writable directories on the 1TB drive.
4. Set `VVR_SCOUT_UID` and `VVR_SCOUT_GID` to the output of `id -u` and `id -g` so the unprivileged container user can write those directories.
5. Set the `VVR_SCOUT_SSH_*` values for the Namecheap account and set Laravel's `DB_HOST=ssh-tunnel` and `DB_PORT=5522`.
6. Never run migrations from this worker.

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

On Linux Mint, install `deploy/linux/start-scout-worker.sh` as a Startup Applications entry when the 1TB drive might mount after Docker starts. The launcher waits up to ten minutes for the drive, then recreates the tunnel and worker so their bind mounts always reference the mounted filesystem.

## Stop

```bash
docker compose --env-file .env.scout -f compose.scout.yaml down
```
