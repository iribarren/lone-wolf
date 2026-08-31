# Fix Prompts

One prompt per pull request, each runnable **standalone in a fresh Claude Code session** with no
prior context. Every file carries its own diagnosis, preconditions, acceptance criteria and
commit conventions.

Two ways to run one:

```bash
/fix-admin-url                      # slash command (wrappers in .claude/commands/)
```

or paste the whole of `docs/prompts/02-fix-admin-url.md` into a fresh session.

All of them derive from the audit in [`docs/audit/`](../audit/README.md), which carries the
evidence behind every finding ID referenced below.

> `start.md` is the original 2026-08 seed prompt (`/constitution` + `/specify` + `/plan`) that
> created this project. It is kept as a historical record and is **not** a template — it predates
> the authoring standard at the bottom of this file.

---

## Run order

Waves are sequential; prompts inside a wave are independent and can be done in any order or in
parallel by different sessions.

| # | Prompt | Fixes | Effort |
|---|---|---|---|
| **Wave 0 — the ratchet.** Lock in the currently-green build before changing code. ||||
| 01 | [`ci-pipeline`](01-ci-pipeline.md) | B1 · no CI exists | ~4 h |
| **Wave 1 — restore the admin surface.** Nothing can be authored until these land. ||||
| 02 | [`fix-admin-url`](02-fix-admin-url.md) | A1 · backoffice unreachable | ~2 h |
| 03 | [`fix-flow-editor`](03-fix-flow-editor.md) | A2, A3 · flow editor unusable | ~2 h |
| 22 | [`fix-admin-save`](22-fix-admin-save.md) | A6 · no backoffice form can be saved | ~3 h |
| **Wave 2 — the crash.** ||||
| 04 | [`fix-logged-roll`](04-fix-logged-roll.md) | A5 · "Log to journal" white-screens | ~2 h |
| **Wave 3 — oracle authoring.** ||||
| 05 | [`oracle-authoring`](05-oracle-authoring.md) | A4 + the deleted US3 feature | ~1 d |
| **Wave 4 — the missing player UI.** Three independent stories. ||||
| 06 | [`character-sheet-ui`](06-character-sheet-ui.md) | B2 · no character form | ~1 d |
| 07 | [`journal-pagination`](07-journal-pagination.md) | B3 · journal cannot page back | ~half d |
| 08 | [`session-lifecycle`](08-session-lifecycle.md) | B4 · no sign-out, no 401 handling · C1 | ~half d |
| **Wave 5 — process debt.** Independent of each other and of the code fixes. ||||
| 09 | [`claude-md`](09-claude-md.md) | `AGENTS.md` never auto-loaded | ~1 h |
| 10 | [`task-integrity`](10-task-integrity.md) | corrupted task IDs, phantom deliverable | ~2 h |
| 11 | [`toolchain-hygiene`](11-toolchain-hygiene.md) | untracked skills, C9 | ~1 h |
| 12 | [`traceability-matrix`](12-traceability-matrix.md) | 12 untraced requirements | ~half d |
| 13 | [`converge-increment`](13-converge-increment.md) | untracked increment work | ~2 h |
| 14 | [`constitution-amendment`](14-constitution-amendment.md) | Principle V tension | ~1 h |
| 15 | [`contract-gate-payloads`](15-contract-gate-payloads.md) | contract gate blind to bodies | ~half d |
| 16 | [`cleanup-sweep`](16-cleanup-sweep.md) | C2–C8, C10–C12 | ~half d |
| **Wave 6 — design.** Sequential; 17 feeds 18. ||||
| 17 | [`design-canvas`](17-design-canvas.md) | — | ~2 h |
| 18 | [`design-tokens`](18-design-tokens.md) | C4 + foundations | ~1 d |
| 19 | [`ui-primitives`](19-ui-primitives.md) | drawers are not dialogs | ~1–2 d |
| 20 | [`visual-regression`](20-visual-regression.md) | — | ~half d |
| **Wave 7 — research.** Independent of everything. ||||
| 21 | [`solo-oracle-research`](21-solo-oracle-research.md) | — | ~2 h |

### Dependencies

```text
01 ci-pipeline ──┬─> 02 fix-admin-url ──> 03 fix-flow-editor ──> 22 fix-admin-save ──> 05 oracle-authoring
                 ├─> 04 fix-logged-roll
                 ├─> 06 / 07 / 08          (player UI, independent)
                 ├─> 09..16                (process debt, independent)
                 └─> 17 design-canvas ──> 18 tokens ──> 19 primitives ──> 20 regression

21 solo-oracle-research — no dependencies, but do 05 first if you intend to build what it finds
```

`01` is not a hard blocker for any of them, but running it first means every later prompt lands
gated instead of on trust. `05` depends on `02` and `03` only because you cannot manually verify
an oracle form on a backoffice you cannot reach.

## Finding → prompt

| Finding | Severity | Prompt |
|---|---|---|
| A1 backoffice unreachable | Critical | 02 |
| A2 flow-editor selects empty | Critical | 03 |
| A3 selects offer the system name | High | 03 |
| A6 no backoffice form can be saved | Critical | 22 |
| A4 oracle entries unauthorable | Critical | 05 |
| A5 logged roll crashes the app | Critical | 04 |
| B1 no CI | Critical | 01 |
| B2 no character UI | High | 06 |
| B3 journal cannot page back | Medium | 07 |
| B4 session lifecycle gaps | Medium | 08 |
| C1 `Bearer undefined` | Low | 08 |
| C9 unused `openrtk` dep | Low | 11 |
| C2–C8, C10–C12 | Low | 16 |
| Task ledger corruption | High | 10, 13 |
| 12 untraced requirements | High | 12 |
| Design maturity ≈ 0/10 | High | 17–20 |
| Contract gate blind to bodies | Medium | 15 |
| Principle V unamended | Medium | 14 |

---

## Authoring standard

If you add a prompt here, follow the same shape. It exists because these files are executed
months later by a session that knows nothing about the audit that produced them.

- **Context before instructions.** Reference material first, the ask last.
- **XML tags, not headings** — `<context>`, `<preconditions>`, `<problem>`, `<pattern>`,
  `<instructions>`, `<constraints>`, `<acceptance_criteria>`, `<completion>`. They parse
  reliably when the file is pasted wholesale.
- **Explicit over inferred.** Exact paths, line numbers, commands and expected output.
- **Say why**, not just what — which requirement or user story the change restores.
- **Show a pattern to imitate** from this repo wherever one exists.
- **Step 1 is always "confirm the diagnosis still holds"**, so a stale prompt fails loudly.
- **Name what is out of scope**, so a fix does not become a refactor.
- **Acceptance criteria must be runnable**, so the session can verify itself.
- **Instruct honest reporting.** Every prompt ends with: run the gates, report failures verbatim,
  never weaken or delete a test to go green. This project has a commit (`ccf09a6`) that deleted a
  failing test under a fabricated justification; these files are where that gets prevented.
- **One task per prompt.** One prompt, one PR, one session.
