# 4 · Solo-RPG Oracle Features

*A research-ready brief. Scoped deliberately as "explore later" — nothing here is a decision.*

The question: what could Lone Wolf learn from the established solo-play toolkit — Mythic GME,
Ironsworn, UNE, the Adventure Crafter and the rest — and where would each idea land in this
codebase?

The good news is architectural. Lone Wolf already made the two decisions that make this cheap:
oracles are **data, not code**, and campaign flows are **data, not code**. Most of what follows
is new *kinds* of oracle and new *campaign state*, not a new application.

---

## 4.1 The landscape

*Written from working knowledge of these systems. Treat details — especially exact table
contents and licensing — as claims to verify in the research session, not as settled facts.*

### Mythic Game Master Emulator

The reference implementation of solo play, and the source of most vocabulary in this space.

- **The Fate Chart / Fate Check.** You ask a closed question ("Is the guard asleep?"), assign it
  an odds rating (*Impossible → Very Unlikely → Unlikely → 50/50 → Likely → Very Likely → Sure
  Thing*), and roll. The answer is yes/no, but with **exceptional yes** and **exceptional no**
  bands at the extremes — a much richer signal than a coin flip.
- **The Chaos Factor.** A campaign-scoped counter, roughly 1–9, that tracks how far events have
  slipped from the protagonist's control. It *shifts the odds* on every question, and it governs
  how often the world interrupts. It rises when a scene goes badly and falls when the player
  regains control. This is the single most interesting mechanic in solo RPGs, and it is
  essentially a state machine bias — which is exactly what Lone Wolf's flow engine already is.
- **Random events.** When a roll comes up doubles under the Chaos Factor, the world intrudes.
  The event has an **event focus** (remote event / NPC action / new NPC / thread advanced /
  thread closed / PC negative…) plus two words drawn from **meaning tables** (an action and a
  subject) that the player interprets into fiction.
- **Scene alteration and interruption.** Before each scene you test against the Chaos Factor: the
  scene you planned may be *altered*, or *replaced entirely* by an interrupt. This is pacing
  control — and Lone Wolf's stages are precisely where it would attach.
- **Lists.** Running **threads** (open plot lines) and **characters** (NPCs in play) lists, which
  the random-event tables draw from. Simple, and the engine that makes the emulator feel coherent
  rather than random.

### Ironsworn / Starforged

- **Move-driven play.** Oracles are attached to *moves* rather than being a free-floating table
  library, so consultation happens at defined decision points.
- **Ask the Oracle** with a likelihood ladder, structurally similar to the Fate Chart.
- **Strong hit / weak hit / miss** — a three-outcome resolution rather than binary success.
- **Deep nested oracle tables** — names, locations, factions — often rolled in combination.
- Notably: Ironsworn is published under a permissive Creative Commons licence, which makes it the
  most realistic candidate for shipped content.

### The supporting cast

| Tool | Contribution |
|---|---|
| **UNE** (Universal NPC Emulator) | NPC generation and *conversation* resolution — mood, bearing, motivation, and what an NPC does when questioned |
| **Adventure Crafter** | Plot-point generation: turns a list of characters and threads into structured scene seeds |
| **One Page Solo Engine** | A compact, freely-licensed complete engine — oracle, complications, NPCs, plot hooks. The best reference for a minimal viable feature set |
| **Tiny Solitary Soldiers / "yes-but" tables** | The *yes and / yes / yes but / no but / no / no and* six-outcome answer, which many players prefer to Mythic's bands |
| **Prompt / journalling games** (Thousand Year Old Vampire, Artefact) | Card-and-prompt-driven play with no dice — a different shape Lone Wolf's flow engine could already express today |

---

## 4.2 Where each idea lands in this codebase

| Idea | Context | Shape of the change | Cost |
|---|---|---|---|
| **Yes/No oracle with odds** | `Oracles` | A second oracle *kind* beside the weighted table: an odds ladder plus outcome bands. `OracleScope` already proves the strategy pattern fits here. | **Low** — additive; no schema migration beyond a `kind` discriminator |
| **Six-outcome answers** (yes-and…no-and) | `Oracles` | Configuration of the above: outcome bands are just data | **Low** |
| **Roll-twice / combined tables** | `Oracles` | Consult returns *n* entries, or draws from two tables and pairs them (Mythic's action+subject) | **Low** |
| **Chaos Factor** | `Campaigns` | Campaign-scoped integer, adjustable by the player, that **biases the yes/no oracle's odds** and gates interrupts. Needs a `chaos_factor` column and an FR about its bounds and who may change it | **Medium** — new campaign state, touches the play loop |
| **Scene interruption** | `Campaigns` + `Oracles` | On stage entry, optionally test against the Chaos Factor and offer (never impose) an altered or interrupted scene. Fits `FlowEngine` exactly: today it returns `suggestedActions`; it would also return an optional *complication* | **Medium** — the most interesting change, and the one most aligned with the product's existing thesis |
| **Random events** (focus + meaning) | `Oracles` + `Journal` | Composite consultation across three tables. Needs `JournalEntryKind` to gain `random_event` | **Medium** |
| **Threads & NPC lists** | **New bounded context** | Named, campaign-scoped lists the player maintains and the random-event tables draw from. Genuinely new domain: open/closed lifecycle, ordering, selection | **High** — but the highest narrative payoff |
| **Per-system oracle bindings** | `Rulesets` | A system declares which oracle answers "the" yes/no question and what its default odds ladder is — so Ironsworn-flavoured and Mythic-flavoured systems each get their idiom | **Medium** |
| **Move-driven oracles** | `Rulesets` + `Campaigns` | Attach oracles to *stages*, so the console offers "the oracle for this beat" rather than a flat list | **Medium** |
| **NPC interaction (UNE-style)** | `Characters` + `Oracles` | NPCs already exist as first-class records; add mood/bearing attributes and an interaction consultation | **Medium** |

Two things worth noticing:

**The existing architecture holds up.** Every low- and medium-cost item above is additive within
an existing bounded context, using patterns already proven in the codebase (the scope strategy,
the injected `RandomSourceInterface`, the denormalised journal snapshot). Nothing here demands a
rewrite. That is a real endorsement of the hexagonal/DDD investment.

**The Chaos Factor is the keystone.** It is the mechanic that turns a stage graph into a
*pacing engine* — the thing the original product vision (`docs/prompts/start.md`) actually asked
for: *"the backend tracks the state of the campaign and actively prompts the user"*. Today Lone
Wolf advises based on position alone. Chaos would let it advise based on position **and
pressure**, which is the difference between a structured notebook and a GM emulator.

---

## 4.3 Suggested sequencing

**Wave 1 — additive, no new context, no migration risk.**
Yes/No oracle with an odds ladder and configurable outcome bands; roll-twice and paired tables.
Delivers the single most-used solo mechanic and validates the "second oracle kind" abstraction.

**Wave 2 — campaign state.**
Chaos Factor, biasing Wave 1's odds, plus optional scene interruption on stage entry. This is
where the product becomes a GM emulator rather than a journal with tables.

**Wave 3 — new context.**
Threads and NPC lists, and the composite random-event consultation that draws from them.

**Prerequisite for all of it:** fix defect **A4** first. Every one of these features is an oracle,
and admins currently cannot author oracle content at all through the backoffice. Shipping more
oracle *kinds* onto a surface where you cannot enter a single row would compound the problem.

---

## 4.4 Constraints to verify before building

Flagged, not resolved. **This needs proper verification — treat the following as the questions,
not the answers.**

- **Game mechanics are generally not copyrightable; the *expression* is.** A Fate-Chart-shaped
  odds ladder is very likely implementable; Mythic's actual chart values, its meaning-table word
  lists and its event-focus table are published commercial content and almost certainly are not
  redistributable. Mythic is Word Mill Games; there is no open licence.
- **Ironsworn is Creative Commons licensed** (CC BY 4.0 as of last knowledge) — the most
  realistic source of *shippable* table content, subject to attribution. Verify the current
  licence text and its attribution requirements.
- **One Page Solo Engine** is likewise freely licensed and worth checking as a reference
  implementation of a minimal engine.
- **Lone Wolf's own model is the safest path.** Oracles are already user-authored data. The app
  can ship the *mechanism* (odds ladders, chaos bias, composite draws) with only thin
  public-domain or original example content, and let users enter the tables they own. That
  sidesteps the licensing question almost entirely — and is also the better product decision,
  since it keeps the app system-agnostic, which is its stated thesis.
- **Trademarks.** "Mythic", "Ironsworn" and similar are marks. Describing compatibility is one
  thing; naming a feature after someone's product is another.

---

## 4.5 The research prompt

Extracted to [`docs/prompts/21-solo-oracle-research.md`](../prompts/21-solo-oracle-research.md) —
a standalone brief runnable in a fresh session with web access, via `/solo-oracle-research` or by
pasting the file.

It surveys the systems in §4.1, verifies and corrects this brief against current sources,
resolves the licensing question with citations, re-assesses the §4.2 mapping against the actual
code, and produces `specs/002-solo-oracle-engine/research-brief.md` ready for `/speckit-specify`.
