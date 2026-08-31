# 15 · Make the contract gate inspect response payloads

Wave 5 · after `04-fix-logged-roll` · branch `contract-gate-payloads` · ~half a day

<context>
Lone Wolf is a multi-system solo-TTRPG assistant. Constitution Principle V makes the OpenAPI
contract at `specs/001-solo-ttrpg-assistant/contracts/openapi.yaml` the single source of truth for
integration: the Next.js frontend consumes a generated client and the Symfony backend must match
the contract. `scripts/check-contract.sh` is the gate that enforces it.

Read before changing anything:
- `scripts/check-contract.sh` in full — including its documented header of intentional exceptions
- `.specify/memory/constitution.md` — Principle V
- `docs/audit/02-specs.md` §2.2.6 — the analysis this prompt comes from
- `docs/audit/spec-compliance.md` §6 finding A5 — the defect that passed this gate
</context>

<preconditions>
The stack must be running and seeded, because the gate calls the live API:

    docker compose up -d --build
    docker compose exec php bin/console doctrine:migrations:migrate -n
    docker compose exec php bin/console app:seed:demo

`python3` with PyYAML must be available — the existing script already requires it.

Ideally run after `04-fix-logged-roll.md`, so the endpoint that motivated this is already correct
and your new gate passes on a clean tree. If it has not run, your new Gate C should **fail** on
`/campaigns/{id}/rolls` — which is a good demonstration, but do not then "fix" the gate to make it
pass.

Confirm `make lint` and `make test` are green before you start.
</preconditions>

<problem>
**The contract gate cannot see the defect class it exists to catch.**

`scripts/check-contract.sh` runs two gates against the runtime `/api/docs.json`:

- **Gate A** compares path keys and HTTP methods.
- **Gate B** compares component schemas by *property-set coverage* — a contract schema passes when
  some runtime schema carries at least its properties.

Neither ever fetches a response body. So an endpoint can declare its schema perfectly and
serialise something else entirely, and the gate stays green.

That is not hypothetical. `POST /api/campaigns/{campaignId}/rolls` returned

    {"roll":"/api/.well-known/genid/b3984bd9e95e94a4c185",
     "journalEntry":"/api/campaigns/4a224ef4-.../journal"}

where `openapi.yaml:382-383` requires embedded `DiceRollResult` and `JournalEntry` objects. The
declared schema was correct; API Platform serialised the nested resources as IRIs. The gate passed
— `Contract OK: 13 paths and 19 schemas match` — while the player app crashed on that response.

Gate B has a second weakness: matching against *any* runtime schema with a superset of the
properties means a contract schema can be satisfied by an unrelated one that happens to share
field names.

Constitution V says the API must match the contract. Today the gate only checks that it *claims*
to.
</problem>

<pattern>
Extend the existing script; do not replace it. Its shape is deliberate and worth preserving: a
bash wrapper around an embedded `python3` heredoc, a documented header listing every intentional
exception by name, `exit 1` for drift and `exit 2` for operational failure, and drift reported as
a bulleted list under `CONTRACT DRIFT DETECTED (Constitution V)`.

The existing `SKIP_PATHS` and `SKIP_COMPONENTS` sets are the model for how exceptions are
recorded here: named in code *and* explained in the header comment. Keep that discipline for
anything Gate C cannot cover.

`scripts/check-journal-performance.sh` is the model for driving the live API from a shell script:
it registers or logs in a player over HTTP, extracts the token with `jq`, and calls endpoints with
`curl`. Reuse that approach rather than inventing a new fixture mechanism.
</pattern>

<instructions>
1. Read `scripts/check-contract.sh` end to end and confirm Gates A and B still behave as described.
   Run it and record the current output.

2. Add **Gate C: response-payload conformance.** It must:
   - register a player over the API and start a campaign on a seeded system
     (`app:seed:demo` provides "Scene-Sequel Demo", "Act Ladder", "Freeform Sandbox")
   - call a representative set of endpoints: `GET /systems`, `POST /campaigns`,
     `GET /campaigns/{id}`, `POST /campaigns/{id}/advance`, `GET`+`POST /campaigns/{id}/journal`,
     `GET /campaigns/{id}/oracles`, the oracle consult, `POST /dice/roll`,
     `POST /campaigns/{id}/rolls`, `GET`+`POST /campaigns/{id}/characters`
   - validate each actual response against the contract's schema for that operation: required
     properties present, types correct, and — the case that motivated this — **a property declared
     as a `$ref` to an object must not come back as a string**
   - report drift in the existing format and exit 1

   Send `Accept: application/json` on every call. Content negotiation defaults to JSON-LD, so
   without it you will validate Hydra envelopes against plain schemas and get false drift.

3. Cover the error contracts too, since they are the project's most distinctive design decision
   and currently sit in `SKIP_COMPONENTS`. Assert the RFC 7807 shapes:
   - an illegal advance returns 422 with `legalAlternatives[]`
   - `0d6` returns 422 with a typed `reason`
   - a non-conforming character returns 422 with `violations[{field,message}]`

   Then narrow `SKIP_COMPONENTS` to only what genuinely remains uncoverable, and update the header
   comment to match.

4. Tighten Gate B to anchor on schema **name** rather than "any runtime schema with a superset of
   these properties", keeping the documented normalisations as an explicit named allowlist. If
   this surfaces real drift, **report it — do not widen the allowlist to make it pass.**

5. Keep the script runnable standalone and idempotent: it must be safe to run repeatedly against a
   seeded stack without leaving a growing pile of fixture data, or must clean up after itself. Say
   which approach you took.

6. Wire it into CI if `.github/workflows/ci.yml` exists (prompt `01-ci-pipeline.md`); the gate is
   already listed in `AGENTS.md`'s merge gates, so update that entry to say it now checks payloads
   (Constitution VI).
</instructions>

<constraints>
- Do not change `openapi.yaml`. If the contract is wrong, report it — a contract change needs a
  versioned migration path under Principle V.
- Do not change application code to make the gate pass. Drift the gate finds is a finding to
  report, not something to fix in this PR.
- Do not replace the script with a general-purpose OpenAPI validation library. New runtime
  dependencies for CI plumbing need their own justification, and the existing script's
  self-documenting exception list is the feature worth keeping.
- Do not remove the existing documented exceptions without checking each one. `/auth/login` in
  particular is genuinely unrepresentable — it is served by the `json_login` firewall listener and
  never reaches API Platform's metadata. Note it in your report as a standing gap.
- Keep it fast enough to run on every PR.
</constraints>

<acceptance_criteria>
    bash scripts/check-contract.sh
    # expected: exit 0, reporting paths, schemas AND payloads checked

    # Gate C actually catches the defect class it was built for:
    # temporarily revert the fix from prompt 04 (or, if 04 has not run, run the gate as-is)
    # expected: exit 1, naming /campaigns/{campaignId}/rolls and the roll/journalEntry properties
    # then restore.

    make lint && make test
    # expected: unchanged and green

Run the script twice in a row against the same stack; the second run passes too.

The header comment lists every remaining intentional exception, and every entry in `SKIP_PATHS`
and `SKIP_COMPONENTS` is explained there.
</acceptance_criteria>

<completion>
Branch `contract-gate-payloads` off an updated `master`. Commit atomically with short imperative
subjects.

Before finishing, run and report `make lint`, `make test` and `scripts/check-contract.sh`.

If a gate fails, report its output verbatim and stop. Never weaken a gate to make it pass — that
is the specific failure this prompt exists to prevent. Do not create or push git remotes.

Report: what Gate C covers, what it found, which exceptions remain and why, the result of the
deliberate-regression check in the acceptance criteria, and any drift you found but did not fix.
</completion>
