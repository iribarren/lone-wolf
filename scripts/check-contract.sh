#!/usr/bin/env bash
#
# Constitution Principle V gate: the API served by the backend must match the
# canonical contract in
# specs/001-solo-ttrpg-assistant/contracts/openapi.yaml — both what it *declares*
# and what it actually *sends*.
#
# Three gates:
#   A) paths (+ HTTP methods per path) against the runtime /api/docs.json.
#   B) component schemas, anchored by NAME through the explicit
#      RUNTIME_SCHEMA_NAMES map below: the contract schema must be carried by
#      the one runtime schema that models it, and that schema must expose at
#      least the contract's properties. (Anchoring by name replaces the old
#      "some runtime schema has a superset of these properties" rule, which any
#      unrelated schema sharing field names could satisfy.)
#   C) response payloads. Gate C registers/logs in a fixture player, runs a
#      whole play loop against the live API and validates every response body
#      against the contract schema for that operation: declared status code,
#      declared media type, required properties present, JSON types correct,
#      enums honoured, and — the case this gate was built for — a property
#      declared as a `$ref` to an object must not come back as an IRI string.
#      Gates A and B never fetch a body, so `POST /campaigns/{id}/rolls`
#      answering `{"roll":"/api/.well-known/genid/…"}` where the contract
#      requires an embedded DiceRollResult passed them cleanly (audit A5).
#
# Documented normalizations / intentional exceptions
# --------------------------------------------------
# Paths (Gate A):
#   - Runtime path keys carry the `/api` server prefix; the contract is written
#     relative to `servers: - url: /api`. The prefix is stripped before diffing.
#   - Non-path runtime keys (keys not starting with "/") are ignored. The Lexik
#     bundle used to leak the json_login check route under one — the route NAME
#     `api_auth_login` — but LoginPathFactory now documents that endpoint at its
#     real path, so nothing is expected to land here any more.
#   - /auth/login used to sit in SKIP_PATHS: it is served by the json_login
#     firewall listener rather than an API Platform operation, so docs.json
#     carried no path for it and Gate A could not check its declaration. That
#     exception is gone (audit C2). LoginPathFactory brings the endpoint into
#     the generated document without touching the firewall, so Gate A now
#     verifies the declaration and Gate C the payload, like every other path.
#
# Schemas (Gate B):
#   - RUNTIME_SCHEMA_NAMES maps each contract schema to the runtime schema that
#     models it. API Platform names schemas after its own resource classes
#     (`System`, `StageResource`, `DiceRoll.RollDiceInput`, …) and decorates
#     them with input/jsonld variants, so the names differ by design; the map is
#     the allowlist of those renames and is checked for stale entries.
#   - Runtime properties beyond the contract's (e.g. `Error.instance`,
#     `Character.validatedStructureVersion`) are not drift: the contract does
#     not close its objects.
#   - COVERED_BY_GATE_C = {DiceNotationProblem, IllegalTransitionProblem,
#     SheetValidationProblem}: controller-emitted RFC 7807 extensions. API
#     Platform only publishes generic Error/ConstraintViolation components for
#     problem responses, so these have no runtime schema to anchor to — but they
#     are no longer unchecked. Gate C asserts all three against live 422
#     payloads, and fails if it did not actually reach them.
#   - SKIP_COMPONENTS = {SheetStructure}: the only genuinely uncoverable schema
#     left. It is the authoring-side model for the EasyAdmin editor; the player
#     API never serves it, exposing structureFields/structureVersion inline on
#     character views instead.
#
# Payloads (Gate C):
#   - `format` (uuid, date-time, email) and `pattern`/`maxLength` are NOT
#     validated. Gate C checks structure and JSON types, which is what the A5
#     defect class turns on; string-format checking would only restate the
#     backend's own validators.
#   - Extra properties in a response are not drift, matching Gate B.
#   - Not exercised: `DELETE /campaigns/{id}` without ?confirm — the contract
#     declares a bare 400 with no schema, so there is nothing to conform to.
#   - Fixture data: one stable player account (CONTRACT_GATE_EMAIL) reused
#     across runs, and one campaign created and deleted per run. Re-running the
#     script leaves nothing behind but that account.
#   - If the seeded sheet structure changes, `POST /characters` will answer 422
#     and Gate C reports it as drift with the violations inline; update
#     `fixture_attributes` to match the new sheet.
#
# Requirements: python3 with PyYAML, and a booted, migrated, `app:seed:demo`-ed
# stack (Gate C calls the live API). Override targets via env:
#   BACKEND_BASE_URL      (default http://localhost:8080)
#   CONTRACT_FILE         (default specs/001-solo-ttrpg-assistant/contracts/openapi.yaml)
#   CONTRACT_GATE_EMAIL   (default contract-gate@example.test)
#   CONTRACT_GATE_PASSWORD
#   CONTRACT_GATE_SYSTEM  (default "Scene-Sequel Demo", from app:seed:demo)
#
# Exit codes: 0 clean, 1 contract drift, 2 operational failure (cannot reach the
# stack, missing fixtures, unreadable contract).
#
# Usage: scripts/check-contract.sh   # exits non-zero on drift

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONTRACT="${CONTRACT_FILE:-$ROOT/specs/001-solo-ttrpg-assistant/contracts/openapi.yaml}"
BASE_URL="${BACKEND_BASE_URL:-http://localhost:8080}"
GATE_EMAIL="${CONTRACT_GATE_EMAIL:-contract-gate@example.test}"
GATE_PASSWORD="${CONTRACT_GATE_PASSWORD:-contract-gate-fixture-password}"
GATE_SYSTEM="${CONTRACT_GATE_SYSTEM:-Scene-Sequel Demo}"

command -v python3 >/dev/null 2>&1 || {
    echo "check-contract: python3 is required." >&2
    exit 2
}

exec python3 - "$CONTRACT" "$BASE_URL" "$GATE_EMAIL" "$GATE_PASSWORD" "$GATE_SYSTEM" <<'PY'
import json
import sys
import urllib.error
import urllib.request

import yaml

contract_file, base_url, gate_email, gate_password, gate_system = sys.argv[1:6]
base_url = base_url.rstrip("/")
drift: list[str] = []


def fail(message: str) -> None:
    drift.append(message)


def operational(message: str) -> None:
    """Not drift: the gate could not run. Exit 2 so CI distinguishes the two."""
    print(f"check-contract: {message}", file=sys.stderr)
    sys.exit(2)


try:
    with open(contract_file, encoding="utf-8") as handle:
        contract = yaml.safe_load(handle)
except OSError as error:
    operational(f"cannot read contract: {error}")

docs_url = f"{base_url}/api/docs.json"

# No path-level exceptions: every contract path is expected in docs.json.
SKIP_PATHS: set[str] = set()

try:
    with urllib.request.urlopen(docs_url, timeout=15) as response:
        runtime = json.loads(response.read().decode("utf-8"))
except (OSError, ValueError) as error:
    operational(
        f"cannot fetch {docs_url} ({error}).\n"
        "Boot the stack first: docker compose up -d, then retry."
    )


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


# --- Gate B: schema coverage, anchored by name -------------------------------

# Contract schema -> the runtime schema that models it. API Platform names
# schemas after its resource classes, so a rename here is expected; an entry
# that stops matching a contract schema is a stale allowlist and fails.
RUNTIME_SCHEMA_NAMES = {
    "AuthToken": "AuthRegister.RegisterOutput",
    "BaseProblem": "Error",
    "CampaignState": "CampaignState",
    "CampaignSummary": "CampaignSummary",
    "CharacterView": "Character",
    "CharacterWrite": "Character.SaveCharacterInput",
    "ConsultationOutcome": "OracleSummary.ConsultationOutcomeResource",
    "DiceRollResult": "DiceRoll",
    "FieldDefinition": "SheetFieldEntryResource",
    "JournalEntry": "JournalEntry",
    "OracleSummary": "OracleSummary",
    "RollRequest": "DiceRoll.RollDiceInput",
    "StageView": "StageResource",
    "SuggestedAction": "StageActionResource",
    "SystemSummary": "System",
}

# Emitted by controllers, never published as runtime components — Gate C
# validates them against live problem+json payloads instead (see header).
COVERED_BY_GATE_C = {
    "DiceNotationProblem",
    "IllegalTransitionProblem",
    "SheetValidationProblem",
}

SKIP_COMPONENTS = {
    # Authoring-side model; the player API exposes structureFields inline.
    "SheetStructure",
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

for stale in sorted(set(RUNTIME_SCHEMA_NAMES) - set(contract_schemas)):
    fail(f"RUNTIME_SCHEMA_NAMES names {stale}, which is no longer a contract schema")

for name in sorted(contract_schemas):
    if name in SKIP_COMPONENTS or name in COVERED_BY_GATE_C:
        continue
    runtime_name = RUNTIME_SCHEMA_NAMES.get(name, name)
    if runtime_name not in runtime_schemas:
        fail(f"schema {name} has no runtime schema named {runtime_name}")
        continue
    wanted = effective_properties(contract_schemas, name)
    exposed = effective_properties(runtime_schemas, runtime_name)
    if not wanted <= exposed:
        fail(
            f"schema {name} -> runtime {runtime_name} is missing properties "
            f"{sorted(wanted - exposed)}"
        )


# --- Gate C: response payload conformance ------------------------------------

payload_checks = 0
reached_schemas: set[str] = set()


def excerpt(value: object, limit: int = 90) -> str:
    text = value if isinstance(value, str) else json.dumps(value, ensure_ascii=False)
    return text if len(text) <= limit else f"{text[:limit]}…"


def api(
    method: str,
    path: str,
    *,
    token: str | None = None,
    body: object | None = None,
) -> tuple[int, str, object, str]:
    """Call the live API. Always negotiates plain JSON: the default is JSON-LD,
    and Hydra envelopes do not match the contract's plain schemas."""
    request = urllib.request.Request(f"{base_url}/api{path}", method=method)
    request.add_header("Accept", "application/json")
    if token:
        request.add_header("Authorization", f"Bearer {token}")
    if body is not None:
        request.add_header("Content-Type", "application/json")
        request.data = json.dumps(body).encode("utf-8")

    try:
        with urllib.request.urlopen(request, timeout=20) as response:
            status, headers, raw = response.status, response.headers, response.read()
    except urllib.error.HTTPError as error:
        status, headers, raw = error.code, error.headers, error.read()
    except OSError as error:
        operational(f"{method} /api{path} failed: {error}")

    text = raw.decode("utf-8", "replace") if raw else ""
    media = (headers.get("Content-Type") or "").split(";")[0].strip()
    try:
        parsed = json.loads(text) if text else None
    except ValueError:
        parsed = None
    return status, media, parsed, text


def dereference(node: object) -> tuple[dict, str | None]:
    """Resolve a possibly-$ref contract node; returns (schema, component name)."""
    label: str | None = None
    seen: set[str] = set()
    while isinstance(node, dict) and "$ref" in node:
        ref = str(node["$ref"])
        if ref in seen or not ref.startswith("#/"):
            operational(f"cannot resolve contract $ref {ref}")
        seen.add(ref)
        target: object = contract
        for segment in ref[2:].split("/"):
            target = (target or {}).get(segment) if isinstance(target, dict) else None
        if target is None:
            operational(f"contract $ref {ref} points at nothing")
        if ref.startswith("#/components/schemas/"):
            label = ref.rsplit("/", 1)[1]
            reached_schemas.add(label)
        node = target
    return (node if isinstance(node, dict) else {}), label


def json_type(value: object) -> str:
    if value is None:
        return "null"
    if isinstance(value, bool):
        return "boolean"
    if isinstance(value, int):
        return "integer"
    if isinstance(value, float):
        return "number"
    if isinstance(value, str):
        return "string"
    if isinstance(value, list):
        return "array"
    if isinstance(value, dict):
        return "object"
    return type(value).__name__


def validate(value: object, node: object, where: str, pointer: str) -> None:
    schema, label = dereference(node)
    named = f" {label}" if label else ""
    at = pointer or "$"

    if value is None:
        if not schema.get("nullable"):
            fail(f"{where}: {at} is null; the contract{named} does not allow null")
        return

    for member in schema.get("allOf") or []:
        validate(value, member, where, pointer)

    declared = schema.get("type")
    if declared is None and schema.get("properties"):
        declared = "object"
    if declared is not None:
        actual = json_type(value)
        matches = actual in {"integer", "number"} if declared == "number" else actual == declared
        if not matches:
            fail(
                f"{where}: {at} — the contract declares {declared}{named}, "
                f"the runtime returned {actual} {excerpt(value)}"
            )
            return

    enum = schema.get("enum")
    if enum is not None and value not in enum:
        fail(f"{where}: {at} is {excerpt(value)}, not one of {enum}")

    if isinstance(value, dict):
        for name in schema.get("required") or []:
            if name not in value:
                fail(f"{where}: {at} is missing required property '{name}'{named}")
        for name, property_schema in (schema.get("properties") or {}).items():
            if name in value:
                validate(value[name], property_schema, where, f"{pointer}.{name}" if pointer else name)
    elif isinstance(value, list) and schema.get("items"):
        for index, item in enumerate(value):
            validate(item, schema["items"], where, f"{pointer}[{index}]")


def response_schema(method: str, contract_path: str, status: int, media: str) -> object | None:
    """The contract's schema for one operation/status/media, or None when the
    contract documents that response without a body."""
    operation = ((contract.get("paths") or {}).get(contract_path) or {}).get(method.lower())
    if operation is None:
        operational(f"the contract has no {method} {contract_path} operation")
    declared = (operation.get("responses") or {}).get(str(status))
    if declared is None:
        fail(
            f"{method} {contract_path} -> {status}: the contract documents only "
            f"{sorted(operation.get('responses') or {})} for this operation"
        )
        return None
    body, _ = dereference(declared)
    content = (body.get("content") or {}).get(media)
    # A documented body-less response (204, the bare 400 on delete) has nothing
    # to conform to.
    return None if content is None else (content.get("schema") or {})


def check(
    method: str,
    contract_path: str,
    url_path: str,
    expected_status: int,
    *,
    token: str | None = None,
    body: object | None = None,
    media: str = "application/json",
) -> object:
    """Call an endpoint and validate its body against the contract's schema for
    that operation, status and media type. Returns None when the call did not
    answer as documented — the caller stops rather than cascading."""
    global payload_checks
    where = f"{method} {contract_path} -> {expected_status}"
    status, actual_media, payload, text = api(method, url_path, token=token, body=body)
    payload_checks += 1

    if status != expected_status:
        fail(f"{where}: the runtime answered {status} {excerpt(text)}")
        return None

    schema = response_schema(method, contract_path, expected_status, media)
    if schema is None:
        return payload
    if actual_media != media:
        fail(f"{where}: Content-Type was {actual_media or '(none)'}, the contract declares {media}")
        return payload

    validate(payload, schema, where, "")
    return payload


PROBLEM = "application/problem+json"


def gate_c() -> None:
    """Play a whole campaign against the live API, validating every body.

    Returning early is not success: the drift is already recorded and the
    caller reports it. The campaign is deleted on every exit path.
    """
    # 1. Authenticate. The account is stable across runs; only the campaign
    #    below is created and deleted each time.
    global payload_checks
    status, _, payload, text = api(
        "POST", "/auth/register", body={"email": gate_email, "password": gate_password}
    )
    if status == 201:
        payload_checks += 1
        schema = response_schema("POST", "/auth/register", 201, "application/json")
        if schema is not None:
            validate(payload, schema, "POST /auth/register -> 201", "")
    elif status != 422:  # 422 = the fixture account already exists
        operational(
            f"cannot provision the fixture player: POST /auth/register -> {status} {excerpt(text)}"
        )

    # /auth/login is invisible to Gates A and B (json_login firewall), so this is
    # the only check the contract's login response ever gets.
    session = check(
        "POST", "/auth/login", "/auth/login", 200,
        body={"email": gate_email, "password": gate_password},
    )
    if not isinstance(session, dict) or not session.get("token"):
        operational("the fixture player could not log in; check CONTRACT_GATE_PASSWORD")
    token = str(session["token"])

    # 2. Pick the seeded system and open a campaign on it.
    systems = check("GET", "/systems", "/systems", 200, token=token)
    if systems is None:
        return
    if not isinstance(systems, list) or not systems:
        operational("GET /systems returned no systems; run `bin/console app:seed:demo`")
    chosen = next((s for s in systems if s.get("name") == gate_system), None)
    if chosen is None:
        operational(
            f"seeded system {gate_system!r} is absent (saw "
            f"{sorted(str(s.get('name')) for s in systems)}); run `bin/console app:seed:demo`"
        )

    campaign_id: str | None = None
    try:
        campaign = check(
            "POST", "/campaigns", "/campaigns", 201,
            token=token, body={"gameSystemId": chosen["systemId"]},
        )
        if not isinstance(campaign, dict) or not campaign.get("id"):
            return
        campaign_id = str(campaign["id"])
        campaign_path = f"/campaigns/{campaign_id}"

        check("GET", "/campaigns", "/campaigns", 200, token=token)
        check("GET", "/campaigns/{campaignId}", campaign_path, 200, token=token)

        # 3. Error contract: an illegal advance refuses with its legal alternatives.
        problem = check(
            "POST", "/campaigns/{campaignId}/advance", f"{campaign_path}/advance", 422,
            token=token, body={"toStageId": "00000000-0000-0000-0000-000000000000"},
            media=PROBLEM,
        )
        alternatives = problem.get("legalAlternatives") if isinstance(problem, dict) else None
        if problem is not None and (not isinstance(alternatives, list) or not alternatives):
            fail(
                "POST /campaigns/{campaignId}/advance -> 422: legalAlternatives is "
                f"{excerpt(alternatives)}; FR-016 requires the refusal to list the legal moves"
            )

        # 4. A legal advance, taken from the state the API itself suggested.
        actions = (campaign.get("currentStage") or {}).get("suggestedActions") or []
        if not actions:
            operational(f"the starting stage of {gate_system!r} suggests no advance to exercise")
        check(
            "POST", "/campaigns/{campaignId}/advance", f"{campaign_path}/advance", 201,
            token=token, body={"toStageId": actions[0].get("toStageId")},
        )

        # 5. Journal.
        check(
            "POST", "/campaigns/{campaignId}/journal", f"{campaign_path}/journal", 201,
            token=token, body={"narrative": "Contract gate payload probe."},
        )
        check("GET", "/campaigns/{campaignId}/journal", f"{campaign_path}/journal", 200, token=token)

        # 6. Oracles: list, consult, save.
        oracles = check(
            "GET", "/campaigns/{campaignId}/oracles", f"{campaign_path}/oracles", 200, token=token
        )
        if oracles is None:
            return
        stocked = [
            o for o in oracles if isinstance(o, dict) and (o.get("entryCount") or 0) > 0
        ]
        if not stocked:
            operational(
                "no oracle with entries is visible to the campaign; run `bin/console app:seed:demo`"
            )
        oracle_path = f"{campaign_path}/oracles/{stocked[0]['oracleId']}"
        outcome = check(
            "POST", "/campaigns/{campaignId}/oracles/{oracleId}/consult",
            f"{oracle_path}/consult", 200,
            token=token, body={"save": False},
        )
        consulted = (outcome or {}).get("entry") or {}
        check(
            "POST", "/campaigns/{campaignId}/oracles/{oracleId}/save", f"{oracle_path}/save", 201,
            token=token,
            body={
                "text": consulted.get("text") or "Contract gate saved result.",
                "interpretation": "Saved by the contract gate.",
            },
        )

        # 7. Dice — and the payload shape that motivated this whole gate. Both
        #    `roll` and `journalEntry` are $refs to objects; API Platform used to
        #    serialise them as IRI references (audit A5) and every gate stayed
        #    green.
        check("POST", "/dice/roll", "/dice/roll", 200, token=token, body={"notation": "2d6+1"})
        problem = check(
            "POST", "/dice/roll", "/dice/roll", 422,
            token=token, body={"notation": "0d6"}, media=PROBLEM,
        )
        if isinstance(problem, dict) and not problem.get("reason"):
            fail(
                "POST /dice/roll -> 422: no `reason` in the problem body; FR-027 "
                "requires the refusal to name a typed reason"
            )
        check(
            "POST", "/campaigns/{campaignId}/rolls", f"{campaign_path}/rolls", 201,
            token=token, body={"notation": "1d20+2"},
        )

        # 8. Characters, including the sheet-validation error contract. The
        #    attributes satisfy the seeded "Scene-Sequel Demo" PC sheet; if that
        #    sheet changes, the 201 below turns into a 422 reported with its
        #    violations inline, and this literal is what to update.
        check(
            "GET", "/campaigns/{campaignId}/characters", f"{campaign_path}/characters", 200,
            token=token,
        )
        fixture_attributes = {"hp": 10}
        character = check(
            "POST", "/campaigns/{campaignId}/characters", f"{campaign_path}/characters", 201,
            token=token,
            body={"kind": "pc", "name": "Contract Gate", "attributes": fixture_attributes},
        )
        if isinstance(character, dict) and character.get("id"):
            check(
                "PATCH", "/characters/{characterId}", f"/characters/{character['id']}", 200,
                token=token,
                body={
                    "kind": "pc",
                    "name": "Contract Gate II",
                    "attributes": fixture_attributes,
                },
            )
        # An NPC's sheet requires nothing of it, so this is the payload that used
        # to come back as `"attributes": []` — a JSON array where the contract
        # types an object. It is the only call that reaches the empty-map path.
        check(
            "POST", "/campaigns/{campaignId}/characters", f"{campaign_path}/characters", 201,
            token=token,
            body={"kind": "npc", "name": "Contract Gate NPC", "attributes": {}},
        )
        problem = check(
            "POST", "/campaigns/{campaignId}/characters", f"{campaign_path}/characters", 422,
            token=token,
            body={"kind": "pc", "name": "Nonconforming", "attributes": {"not_a_sheet_field": "x"}},
            media=PROBLEM,
        )
        violations = problem.get("violations") if isinstance(problem, dict) else None
        if problem is not None and (not isinstance(violations, list) or not violations):
            fail(
                "POST /campaigns/{campaignId}/characters -> 422: violations is "
                f"{excerpt(violations)}; FR-023 requires field-level violations"
            )
    finally:
        # 9. Leave the stack as we found it — and check the delete contract while
        #    we are at it.
        if campaign_id:
            check(
                "DELETE", "/campaigns/{campaignId}", f"/campaigns/{campaign_id}?confirm=true", 204,
                token=token,
            )


gate_c()


# The Gate B exemptions delegated to Gate C only hold while Gate C really reaches
# them; otherwise those schemas would be checked by nobody.
for name in sorted(COVERED_BY_GATE_C - reached_schemas):
    fail(
        f"schema {name} is exempted from Gate B as 'covered by Gate C', but no "
        "Gate C payload check reached it"
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
    f"Contract OK: {len(contract_paths)} paths, {len(contract_schemas)} schemas and "
    f"{payload_checks} response payloads match {base_url}/api "
    "(within documented normalizations)."
)
PY
