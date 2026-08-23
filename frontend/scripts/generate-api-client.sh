#!/usr/bin/env bash
# Contract-first client pipeline (T024, Constitution V).
# Downloads the live OpenAPI document from the backend and emits a typed
# schema. The generated file is COMMITTED so builds never need a running API.
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
base_url="${API_BASE_URL:-http://localhost:8080}"
out="$root/src/lib/api/schema.gen.ts"

tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT

curl -fsSL "$base_url/api/docs.json" -o "$tmp"
echo "OpenAPI document: $base_url/api/docs.json ($(wc -c <"$tmp") bytes)"

cd "$root"
npx openapi-typescript "$tmp" -o "$out"

echo "Wrote $out"
