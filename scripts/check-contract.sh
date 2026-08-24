#!/usr/bin/env bash
#
# Constitution Principle V gate: the runtime OpenAPI document served by the
# backend must match the canonical contract in
# specs/001-solo-ttrpg-assistant/contracts/openapi.yaml.
#
# Compares:
#   A) paths (+ HTTP methods per path), and
#   B) component schemas by property-set coverage — a contract schema passes
#      when some runtime schema carries at least its properties (API Platform
#      decorates resources with input/jsonld variants and extra bookkeeping
#      fields, so exact name equality is not meaningful).
#
# Documented normalizations / intentional exceptions:
#   - Runtime path keys carry the `/api` server prefix; the contract is written
#     relative to `servers: - url: /api`. The prefix is stripped before diffing.
#   - API Platform leaks the json_login check route under a non-path key
#     (`api_auth_login`); keys that do not start with "/" are ignored.
#   - /auth/login is served by the lexik json_login firewall listener rather
#     than an API Platform operation, so docs.json cannot carry it as a path.
#   - SheetStructure is the authoring-side model for the EasyAdmin editor; the
#     player API exposes sheet metadata inline on character views instead.
#   - DiceNotationProblem / IllegalTransitionProblem / SheetValidationProblem
#     document controller-emitted RFC 7807 extensions; API Platform only emits
#     generic Error/ConstraintViolation components for problem responses.
#
# Requirements: python3 with PyYAML. Override targets via env:
#   BACKEND_BASE_URL (default http://localhost:8080)
#   CONTRACT_FILE    (default specs/001-solo-ttrpg-assistant/contracts/openapi.yaml)
#
# Usage: scripts/check-contract.sh   # exits non-zero on drift

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONTRACT="${CONTRACT_FILE:-$ROOT/specs/001-solo-ttrpg-assistant/contracts/openapi.yaml}"
BASE_URL="${BACKEND_BASE_URL:-http://localhost:8080}"

command -v python3 >/dev/null 2>&1 || {
    echo "check-contract: python3 is required." >&2
    exit 2
}

exec python3 - "$CONTRACT" "$BASE_URL" <<'PY'
import json
import sys
import urllib.request

import yaml

contract_file, base_url = sys.argv[1], sys.argv[2]
drift: list[str] = []


def fail(message: str) -> None:
    drift.append(message)


try:
    with open(contract_file, encoding="utf-8") as handle:
        contract = yaml.safe_load(handle)
except OSError as error:
    print(f"check-contract: cannot read contract: {error}", file=sys.stderr)
    sys.exit(2)

docs_url = f"{base_url.rstrip('/')}/api/docs.json"

SKIP_PATHS = {
    # json_login firewall endpoint — implemented outside API Platform metadata.
    "/auth/login",
}

try:
    with urllib.request.urlopen(docs_url, timeout=15) as response:
        runtime = json.loads(response.read().decode("utf-8"))
except (OSError, ValueError) as error:
    print(
        f"check-contract: cannot fetch {docs_url} ({error}).\n"
        "Boot the stack first: docker compose up -d, then retry.",
        file=sys.stderr,
    )
    sys.exit(2)


# --- Gate A: paths + methods -------------------------------------------------

runtime_paths: dict[str, set[str]] = {}
ignored_keys: list[str] = []
for key, operations in (runtime.get("paths") or {}).items():
    if not key.startswith("/"):
        ignored_keys.append(key)
        continue
    normalized = key[4:] if key.startswith("/api/") or key == "/api" else key
    runtime_paths[normalized] = {
        method.upper()
        for method in (operations or {})
        if method.lower() in {"get", "post", "patch", "put", "delete"}
    }

contract_paths: dict[str, set[str]] = {}
for key, operations in (contract.get("paths") or {}).items():
    if key in SKIP_PATHS:
        continue
    contract_paths[key] = {
        method.upper()
        for method in (operations or {})
        if method.lower() in {"get", "post", "patch", "put", "delete"}
    }

missing_at_runtime = sorted(set(contract_paths) - set(runtime_paths))
unknown_at_runtime = sorted(set(runtime_paths) - set(contract_paths))
if missing_at_runtime:
    fail(f"paths missing from runtime docs.json: {missing_at_runtime}")
if unknown_at_runtime:
    fail(f"paths undocumented in the contract: {unknown_at_runtime}")

for path in sorted(set(contract_paths) & set(runtime_paths)):
    if contract_paths[path] != runtime_paths[path]:
        fail(
            f"methods differ for {path}: "
            f"contract={sorted(contract_paths[path])} runtime={sorted(runtime_paths[path])}"
        )

if ignored_keys:
    print(f"note: ignoring non-path runtime keys {sorted(ignored_keys)} (json_login artifacts)")


# --- Gate B: schema coverage --------------------------------------------------

SKIP_COMPONENTS = {
    # Authoring-side model; player API exposes structureFields inline instead.
    "SheetStructure",
    # Controller-emitted RFC 7807 extensions (see header comment).
    "DiceNotationProblem",
    "IllegalTransitionProblem",
    "SheetValidationProblem",
}


def effective_properties(schemas: dict, name: str, seen: set[str] | None = None) -> set[str]:
    """Direct properties plus properties inherited through allOf $ref members."""
    seen = seen if seen is not None else set()
    if name in seen:
        return set()
    seen.add(name)

    schema = schemas.get(name) or {}
    properties: set[str] = set(schema.get("properties") or {})
    for member in schema.get("allOf") or []:
        ref = (member or {}).get("$ref") or ""
        if ref.startswith("#/components/schemas/"):
            properties |= effective_properties(schemas, ref.rsplit("/", 1)[1], seen)
        else:
            properties |= set((member or {}).get("properties") or {})

    return properties


contract_schemas: dict = (contract.get("components") or {}).get("schemas") or {}
runtime_schemas: dict = (runtime.get("components") or {}).get("schemas") or {}
runtime_property_sets: list[set[str]] = [
    effective_properties(runtime_schemas, name) for name in runtime_schemas
]

for name in sorted(contract_schemas):
    if name in SKIP_COMPONENTS:
        continue
    wanted = effective_properties(contract_schemas, name)
    if not wanted:
        continue  # envelope-only schema (e.g. pure arrays) — covered by Gate A
    covered = any(wanted <= candidate for candidate in runtime_property_sets)
    if not covered:
        fail(
            f"schema {name} has no runtime counterpart exposing properties "
            f"{sorted(wanted)}"
        )


# --- Verdict -------------------------------------------------------------------

if drift:
    print("CONTRACT DRIFT DETECTED (Constitution V):")
    for message in drift:
        print(f"  ✗ {message}")
    print(
        "\nFix the backend resources or amend contracts/openapi.yaml in the "
        "same change set, then re-run."
    )
    sys.exit(1)

print(
    f"Contract OK: {len(contract_paths)} paths and "
    f"{len(contract_schemas)} schemas match {docs_url} (within documented normalizations)."
)
PY
