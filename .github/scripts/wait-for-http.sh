#!/usr/bin/env bash
#
# Polls a URL until it answers. docker compose --wait only proves a container
# is running/healthy; php-fpm behind nginx and the Next.js dev server still
# need a moment to compile.
#
# Usage: wait-for-http.sh <url> [timeout-seconds]
set -euo pipefail

URL="$1"
TIMEOUT="${2:-120}"
DEADLINE=$(( SECONDS + TIMEOUT ))

until curl --silent --fail --output /dev/null "$URL"; do
    if [ "$SECONDS" -ge "$DEADLINE" ]; then
        echo "wait-for-http: $URL did not answer within ${TIMEOUT}s." >&2
        exit 1
    fi
    sleep 2
done

echo "wait-for-http: $URL is up."
