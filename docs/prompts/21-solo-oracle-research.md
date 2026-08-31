# 21 · Research solo-RPG oracle and GM-emulator features

Wave 7 · no dependencies · branch — none, this produces a brief · ~2 h · **needs web access**

<context>
Lone Wolf is a multi-system solo-TTRPG assistant. Admins author "game systems" as a graph of named
stages with per-stage guidance; players run campaigns along that graph, journal per stage, consult
weighted random tables ("oracles" — scoped globally or to one system), maintain system-shaped
character sheets, and roll dice. Monorepo: `backend/` is Symfony 7.4 + API Platform in hexagonal
DDD across eight bounded contexts — Shared, Rulesets, Campaigns, Journal, Oracles, Characters,
Dice, Identity — and `frontend/` is Next.js talking to it only through the OpenAPI contract.

Two architectural facts make this research worth doing: **oracles are data, not code**, and
**campaign flows are data, not code**. Most of what the solo-RPG toolkit offers is therefore new
*kinds* of oracle and new *campaign state*, not a new application.

Read before starting:
- `docs/audit/04-solo-rpg-features.md` — the landscape brief this prompt extends and corrects
- `docs/functional-guide.md` — what the app actually does today
- `specs/001-solo-ttrpg-assistant/data-model.md` — the existing model, annotated per requirement
- `specs/001-solo-ttrpg-assistant/spec.md` — the requirement style your draft FRs must match
</context>

<preconditions>
Web access. This is a research session, not an implementation session.

Nothing needs to be running. You will not change application code.
</preconditions>

<problem>
The product's original vision (`docs/prompts/start.md`) called for the backend to act as an
automated Game Master that "actively prompts the user". Today it advises based on **position in
the stage graph alone**. The established solo-RPG toolkit — Mythic's Chaos Factor and Fate Chart,
Ironsworn's move-driven oracles, UNE, the Adventure Crafter — solves the half that is missing:
advising based on position **and narrative pressure**.

`docs/audit/04-solo-rpg-features.md` sketches that landscape and maps it onto the bounded contexts,
but it was written from working knowledge without web access. It needs verifying, correcting and
extending — and one question in it is genuinely decision-blocking: **which of these systems'
content can lawfully be shipped, and where the line falls between an unprotectable game mechanic
and protected expression.**
</problem>

<instructions>
1. **Survey the systems.** For Mythic GME (2nd edition and the Mythic Variations), Ironsworn and
   Starforged, UNE, the Adventure Crafter, One Page Solo Engine, and anything significant released
   since: what mechanic does each contribute, and why do players choose it? Correct anything in
   `docs/audit/04-solo-rpg-features.md` §4.1 that is out of date or wrong — treat that section as
   claims to verify, not as established fact.

2. **Survey the digital tools.** What solo-play apps exist, what do they get right and wrong, and
   where does Lone Wolf's specific thesis — multi-system, admin-authored campaign flows, structural
   pacing — actually differ from what is already served? Be honest if the answer is "barely".

3. **Resolve the licensing question, with sources.** This is the part that blocks decisions.
   - Which of these systems publish content under a licence permitting redistribution in an app
     (Ironsworn's Creative Commons licence, One Page Solo Engine, any SRD or ORC-licensed
     material), and what attribution does each require?
   - Which do not — Mythic in particular — and where does the practical boundary sit between an
     unprotectable mechanic (an odds ladder, a chaos counter, a two-axis lookup) and protected
     expression (specific chart values, meaning-table word lists, event-focus categories)?
   - Would shipping only the **mechanism**, with users authoring their own table content, avoid
     the issue entirely? Lone Wolf's oracles are already user-authored data, so this may be both
     the safest and the better product answer.
   - Mark clearly where the question needs a lawyer rather than a search result. Do not present a
     legal conclusion as settled.

4. **Assess the mapping.** `docs/audit/04-solo-rpg-features.md` §4.2 places each feature in a
   bounded context with a cost estimate. Read the actual code — `mcp__codegraph__codegraph_explore`
   is installed and pre-approved — and judge it. Which placements are wrong? What was missed? What
   is cheaper or dearer than estimated?

5. **Write the deliverable:** `specs/002-solo-oracle-engine/research-brief.md`, structured so that
   `/speckit-specify` can be run against it immediately.
   - A ranked feature list. For each: the player-facing behaviour in one sentence, the bounded
     context(s) it touches, whether it needs new persistence, its licensing status, and a build-cost
     estimate.
   - A proposed **Wave 1** that is genuinely shippable in one increment, with draft FR-style
     requirements — RFC-2119, testable, matching the style of
     `specs/001-solo-ttrpg-assistant/spec.md`.
   - Open questions needing a human decision, listed explicitly.
   - Sources with links for every licensing claim.

6. Note one prerequisite prominently in the brief: **audit finding A4 must be fixed first**
   (`docs/prompts/05-oracle-authoring.md`). Every feature here is an oracle, and admins currently
   cannot author oracle content at all through the backoffice. Shipping more oracle kinds onto a
   surface where you cannot enter a single row would compound the problem.
</instructions>

<constraints>
- **Write no code.** This session produces a brief.
- **Do not modify `specs/001-*`.** The new brief lives in `specs/002-solo-oracle-engine/`.
- Do not reproduce any copyrighted table content in the brief — not as an example, not as a test
  fixture, not "just to illustrate". Describe mechanics; do not transcribe tables.
- Do not present a licensing conclusion as legal advice. Cite sources, state confidence, and flag
  what needs professional review.
- Rank by value to *this* product, not by fame. A feature that fits the existing flow-engine
  thesis beats a better-known one that does not.
- If the honest answer to step 2 is that an existing tool already does this better, say so. That
  is a more useful finding than a feature list.
</constraints>

<acceptance_criteria>
- `specs/002-solo-oracle-engine/research-brief.md` exists with all five sections from step 5.
- Every licensing claim carries a source link, and every uncertain one is marked as needing legal
  review.
- Every correction to `docs/audit/04-solo-rpg-features.md` is called out explicitly, so the
  original brief's errors are visible rather than silently overwritten.
- The proposed Wave 1's draft requirements are testable as written — a reader could write a
  failing test from each without asking a question.
- Each feature's bounded-context placement is justified against code you actually read, with file
  paths.
- No copyrighted table content appears anywhere in the deliverable.
</acceptance_criteria>

<completion>
No branch and no application commits. Commit the brief on `research-solo-oracle` if you want it in
version control; it is a specification artifact, so it belongs in the repository.

Report honestly: the ranked feature list in summary, the licensing verdict per system **with your
confidence level**, every correction you made to the existing brief, and the single question you
most want a human to answer before any of this is built. Never weaken a licensing caveat or a
"needs legal review" flag to make a feature look shippable, and never present an inference as a
sourced fact — say what you could not establish.
</completion>
