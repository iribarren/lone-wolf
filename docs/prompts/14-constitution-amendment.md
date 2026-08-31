# 14 · Amend the constitution for the admin session firewall

Wave 5 · no dependencies · branch `constitution-amendment` · ~1 h

<context>
Lone Wolf is a multi-system solo-TTRPG assistant. Its governance lives in
`.specify/memory/constitution.md` — six ratified principles that supersede every other convention
in the repository, with a formal amendment procedure and semantic versioning. It is currently at
**version 1.0.0, ratified 2026-08-21**.

The system has two front ends: a Next.js player app that talks to the backend only through the
OpenAPI contract with JWT bearer tokens, and an EasyAdmin backoffice that is server-rendered
inside the Symfony application and authenticates by browser session.

Read before changing anything:
- `.specify/memory/constitution.md` in full — especially Principle V and the Governance section
- `docs/audit/02-specs.md` §2.2.7 — the analysis this prompt comes from
- `backend/config/packages/security.yaml` — the four firewalls as built
- `specs/001-solo-ttrpg-assistant/contracts/admin-backoffice.md` and `docs/architecture.md`
</context>

<preconditions>
None. This prompt changes a governance document and no code.

This is a governance change, so if you are unsure whether the maintainer wants it, ask rather than
proceeding. A constitution amended without intent is worse than one that is out of date.
</preconditions>

<problem>
**The constitution prohibits something the code does, and nobody filed an amendment.**

Principle V, "Contract-First Decoupled API", states:

> The React/Next.js frontend and the Symfony backend MUST remain entirely decoupled,
> communicating exclusively through the defined API contract. … Direct database access, **session
> sharing**, or server-side templating between frontend and backend is prohibited.

Commit `32e18b7` added a session-based `form_login` firewall for the backoffice
(`security.yaml`, the `admin` firewall: `login_path: admin_login`, `enable_csrf: true`,
`default_target_path: admin_dashboard`). Backoffice pages are Twig-rendered server-side.

That is almost certainly *fine*. The prohibition is about coupling the decoupled player frontend
to the backend, and the backoffice is not that frontend — it is an admin surface internal to the
backend, with its own separate contract in `contracts/admin-backoffice.md`.
`docs/architecture.md` already scopes it that way.

But the reading is inferred, not ratified. The Governance section requires amendments to carry a
written rationale, an explicit diff of affected principles, a migration plan, and ratification.
None was filed. So the document reads absolutely while the code does otherwise, which quietly
teaches every future reader — human or agent — that the constitution is negotiable in practice.
That is corrosive in a repository whose entire quality story rests on six principles being
non-negotiable.

The behaviour is right. The document needs to say so.
</problem>

<instructions>
1. Read the constitution in full, in particular Principle V and the Governance section's
   amendment procedure and versioning policy. Read `security.yaml` and confirm the four firewalls
   are still as described. Read `contracts/admin-backoffice.md`.

2. Follow the amendment procedure the document specifies for itself — it is the only prompt in
   this set where the process is more important than the outcome:
   - a **written rationale**
   - an **explicit diff** of the affected principle
   - a **migration plan** for existing code — here, none: the amendment ratifies the status quo,
     and saying so explicitly is the correct migration plan
   - **ratification**, i.e. the amendment lands before any further work depends on it

3. Amend Principle V, narrowly. Scope it to the **player-facing API**: the Next.js frontend must
   communicate with the backend exclusively through the documented contract, and direct database
   access, session sharing and server-side templating between *them* remain prohibited. Add an
   explicit carve-out that the EasyAdmin backoffice is a server-rendered administrative surface
   internal to the backend and may authenticate by browser session, governed by its own contract
   in `specs/<feature>/contracts/admin-backoffice.md`.

   Keep every other prohibition absolute. Nothing about the player app changes.

4. Bump the version to **1.1.0** — MINOR, per the document's own policy ("new principle/section
   added or materially expanded guidance"). Update the `Last Amended` date; leave `Ratified`
   at the original date.

5. Update the `SYNC IMPACT REPORT` comment block at the top of the file, following the format
   already there: version change, modified principles, added/removed sections, follow-up TODOs.

6. Check whether anything else in the repository asserts the old absolute reading and update it in
   the same change set (Constitution VI). At minimum check `AGENTS.md`, `docs/architecture.md`,
   `specs/001-solo-ttrpg-assistant/plan.md` (its Constitution Check gate table) and
   `docs/audit/02-specs.md`.
</instructions>

<constraints>
- Amend **only** Principle V, and only in the narrow way described. This is not an opportunity to
  revise the constitution generally.
- Do not weaken the player-app decoupling in any respect. The carve-out is for the backoffice
  only.
- Do not change any code, configuration or security setting. The behaviour is correct; the
  document is what is out of date.
- Do not change the versioning policy, the governance procedure, or the technology-stack section.
- If you conclude the amendment is *not* warranted — that the session firewall genuinely violates
  Principle V and the code should change instead — stop and report that argument rather than
  amending. That is a legitimate outcome and a much bigger decision than this PR.
</constraints>

<acceptance_criteria>
- `.specify/memory/constitution.md` reads `**Version**: 1.1.0` with an updated `Last Amended` date
  and an unchanged `Ratified` date.
- The `SYNC IMPACT REPORT` block records the change in the existing format.
- Principle V explicitly permits the backoffice session firewall and explicitly retains every
  prohibition on the player frontend.
- The amendment carries its rationale, its principle diff, and an explicit statement that no code
  migration is required.
- `grep -rn "session sharing" .` returns no document still asserting the unqualified prohibition.
- `make lint && make test` unchanged and green — this change must touch no behaviour.
</acceptance_criteria>

<completion>
Branch `constitution-amendment` off an updated `master`. A single, well-described commit is
appropriate here: an amendment is one act.

Before finishing, run and report `make lint` and `make test`.

If a gate fails, report its output verbatim and stop. Do not create or push git remotes.

Report: the diff to Principle V, the rationale as written, every other document you updated, and
explicitly confirm that no code or configuration changed.
</completion>
