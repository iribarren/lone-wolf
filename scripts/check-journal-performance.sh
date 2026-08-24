#!/usr/bin/env bash
#
# SC-008 performance evidence: with a 500-entry journal seeded on a campaign,
# the latest-view page (first keyset page, newest-first) must answer in < 2 s.
#
# Steps:
#   1. Seed the fixture inside the compose stack (app:seed:large-journal).
#   2. Log the fixture player in over HTTP.
#   3. Time GET /api/campaigns/{id}/journal and assert the total latency.
#
# Requirements: docker compose stack up, curl, jq.
# Overrides: BACKEND_BASE_URL, PERF_EMAIL, PERF_PASSWORD, PERF_ENTRIES,
#            PERF_MAX_SECONDS (default 2), COMPOSE (default "docker compose").
#
# Usage: scripts/check-journal-performance.sh   # exits non-zero on failure

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BASE_URL="${BACKEND_BASE_URL:-http://localhost:8080}"
EMAIL="${PERF_EMAIL:-perf@example.com}"
PASSWORD="${PERF_PASSWORD:-perf-player-password}"
ENTRIES="${PERF_ENTRIES:-500}"
MAX_SECONDS="${PERF_MAX_SECONDS:-2}"
COMPOSE="${COMPOSE:-docker compose}"

command -v curl >/dev/null 2>&1 || { echo "curl is required." >&2; exit 2; }
command -v jq >/dev/null 2>&1 || { echo "jq is required." >&2; exit 2; }

# curl reports %{time_total} with a locale-dependent decimal separator.
export LC_ALL=C

echo "== Seeding $ENTRIES journal entries for $EMAIL =="
seed_output=$($COMPOSE exec -T php bin/console app:seed:large-journal \
    --email="$EMAIL" --password="$PASSWORD" --entries="$ENTRIES")

campaign_id=$(printf '%s\n' "$seed_output" | sed -n 's/^perf_campaign=//p' | tail -n1)
if [[ -z "$campaign_id" ]]; then
    echo "Seeder did not report a campaign id; output was:" >&2
    printf '%s\n' "$seed_output" >&2
    exit 1
fi
echo "Campaign: $campaign_id"

echo "== Logging in =="
token=$(curl -fsS "$BASE_URL/api/auth/login" \
    -H 'Content-Type: application/json' \
    -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}" | jq -er '.token')
[[ -n "$token" ]] || { echo "Login returned no token." >&2; exit 1; }

echo "== Timing latest journal view =="
read -r http_code elapsed <<<"$(curl -sS -o /dev/null -w '%{http_code} %{time_total}' \
    -H "Authorization: Bearer $token" \
    "$BASE_URL/api/campaigns/$campaign_id/journal")"

printf 'GET /journal -> HTTP %s in %.3f s\n' "$http_code" "$elapsed"

if [[ "$http_code" != "200" ]]; then
    echo "FAIL: expected HTTP 200, got $http_code." >&2
    exit 1
fi

awk -v t="$elapsed" -v max="$MAX_SECONDS" 'BEGIN { exit !(t <= max) }' ||
    { echo "FAIL: latest view took ${elapsed}s (> ${MAX_SECONDS}s, SC-008)." >&2; exit 1; }

echo "PASS: latest view of a $ENTRIES-entry journal answered in ${elapsed}s (<= ${MAX_SECONDS}s, SC-008)."
