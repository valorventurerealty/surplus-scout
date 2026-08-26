#!/usr/bin/env bash

set -euo pipefail

project_dir=/home/tumeg/VVR-AI/surplus-scout
drive_mount=/media/tumeg/1TB
storage_root=${drive_mount}/VVR-AI/surplus-scout

for attempt in $(seq 1 120); do
    if mountpoint -q "$drive_mount" \
        && [ -r "$storage_root/ssh/id_ed25519" ] \
        && [ -w "$storage_root/data" ] \
        && [ -w "$storage_root/logs" ]; then
        cd "$project_dir"
        docker compose --env-file .env.scout -f compose.scout.yaml up -d
        logger -t vvr-surplus-scout 'Worker and SSH tunnel started successfully.'
        exit 0
    fi

    sleep 5
done

logger -t vvr-surplus-scout 'Startup timed out waiting for the 1TB drive.'
exit 1
