# 11 · Settle the AI tool-chain configuration

Wave 5 · no dependencies · branch `toolchain-hygiene` · ~1 h · fixes **C9**

<context>
Lone Wolf is a multi-system solo-TTRPG assistant built with GitHub Spec Kit. The spec workflow —
`/speckit-specify` → `/speckit-plan` → `/speckit-tasks` → `/speckit-implement`, plus `analyze`,
`clarify`, `checklist`, `constitution`, `converge` and `taskstoissues` — is installed as agent
commands. Spec Kit supports several agent front-ends and installs a different flavour of the same
command set for each.

Read before changing anything:
- `docs/audit/01-ai-workflow.md` §1.2.4 — the analysis this prompt comes from
- `.specify/init-options.json`, `.specify/integration.json`,
  `.specify/integrations/*.manifest.json`
- `.gitignore` — its "AI tooling: shared config stays, local state goes" section
</context>

<preconditions>
None. This prompt changes configuration and version control only; no application code.

Run `git status` first and record the current state, because part of this work is committing
things that are presently untracked. If the working tree has unrelated changes, stop and report —
you need a clean baseline to do this safely.
</preconditions>

<problem>
The tool-chain actually in use is invisible to version control, while its superseded predecessor
is tracked.

**1. The live integration is untracked.** `.specify/init-options.json` records `"ai": "claude"`,
and `.claude/skills/` holds ten `speckit-*` skills. Both `.claude/skills/` and
`.specify/integrations/claude.manifest.json` are untracked (`??` in `git status`).

**2. The superseded one is tracked.** `.opencode/commands/speckit.*.md` — ten dot-separated
equivalents of the same commands, with different frontmatter — are committed, along with an
`.opencode/package.json` and a `node_modules/` tree. `.specify/integration.json` shows the
migration from opencode (separator `.`) to claude (separator `-`), and that migration is sitting
uncommitted in the working tree.

The result: two command sets that will drift independently, and the one that runs is the one
nobody can review.

**3. Five speckit files no longer match their recorded hashes.**
`.specify/integrations/speckit.manifest.json` is a SHA-256 integrity record;
`.specify/scripts/bash/check-prerequisites.sh`, `.specify/scripts/bash/setup-tasks.sh`, and the checklist, plan and
tasks templates have all drifted from it. They were hand-edited, and no rationale survives.

**4. An unexplained dependency.** The root `package.json` declares exactly one dependency,
`openrtk`, which appears nowhere in the repository and in no specification artifact.
</problem>

<instructions>
1. Confirm the current state before changing anything: `git status --short`, `cat
   .specify/init-options.json .specify/integration.json`, `ls .claude/skills/ .opencode/commands/`.
   If the picture differs from the above, work from what is actually there and say so.

2. Commit the Claude integration. Add `.claude/skills/` and
   `.specify/integrations/claude.manifest.json`, plus the modified `.specify/init-options.json`,
   `.specify/integration.json` and `.specify/integrations/speckit.manifest.json`. Check
   `.gitignore` does not exclude any of it, and check nothing being added contains a secret.

3. Decide the opencode question explicitly rather than by default. If opencode is no longer used,
   remove `.opencode/` entirely and drop its now-dead `.gitignore` entries. If it *is* still in
   use, keep it and add a short note in `AGENTS.md` saying both sets are maintained and must be
   kept in sync — but do not leave the ambiguity in place. Ask the operator if you cannot tell;
   do not guess and delete.

4. Resolve the five drifted speckit files. For each: `git log --oneline --` the file to see when
   and why it was edited, then either revert it to the manifest's recorded version, or keep the
   edit and re-record its hash in `speckit.manifest.json` with a one-line comment saying why it
   was customised. Both outcomes are fine; an unexplained mismatch is not.

5. Remove `openrtk` from the root `package.json` unless you can find something that uses it —
   grep the whole repo including config and scripts before deleting. If the root `package.json`
   then has no purpose at all, say so in your report rather than deleting it unilaterally.

6. Document the outcome in `AGENTS.md` (Constitution VI): which agent integration is canonical,
   where its commands live, and that changing it means re-running Spec Kit's init rather than
   hand-editing.
</instructions>

<constraints>
- Do not edit the contents of the ten `speckit-*` skills. Committing them is the task; changing
  what they do is not.
- Do not add hooks, or change `.claude/settings.json` permissions. Those are separate decisions.
- Do not commit `.claude/settings.local.json` — it is machine-local and correctly gitignored.
- Do not commit anything under `.opencode/node_modules/` or `.opencode/state/`.
- Do not delete `.opencode/` without deciding step 3 deliberately, and do not delete anything you
  have not first inspected.
</constraints>

<acceptance_criteria>
    git status --short
    # expected: clean. Nothing untracked under .claude/ or .specify/, no stray modifications.

    git ls-files .claude/skills/ | wc -l
    # expected: 10 skill files tracked

    grep -c openrtk package.json 2>/dev/null || echo "removed"
    # expected: removed, or a justification in your report

    make lint && make test
    # expected: unchanged and green — this change must not touch application behaviour

Every file listed in `.specify/integrations/speckit.manifest.json` either matches its recorded
hash or has an adjacent comment explaining the deliberate customisation.

`AGENTS.md` names the canonical agent integration.
</acceptance_criteria>

<completion>
Branch `toolchain-hygiene` off an updated `master`. One commit per numbered instruction above, so
each decision is separately reviewable and revertible.

Before finishing, run and report `make lint` and `make test`.

If a gate fails, report its output verbatim and stop. Never weaken, skip or delete a test to make
a suite pass. Do not create or push git remotes.

Report: what you committed, what you removed, the decision you made about `.opencode/` and why,
what you found for each of the five drifted files, and whether `openrtk` was load-bearing.
</completion>
