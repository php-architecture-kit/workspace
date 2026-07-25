# 50 — Sequencing, risk/rollback, and the generator unblock

> The order of work, what is safe at each step, and exactly how a self-consistent
> tree lets the **next** task (the tree-generator fix) stop guessing.

## Staged order (each stage independently shippable)

| # | Stage | Depends on | Acceptance |
|---|---|---|---|
| 0 | **Design docs** (`00`–`50`) | — | This folder. |
| 1 | **`/c` content marker** — parse in `SequenceNode`/`NestedSequence`, carry `isContent` to matching layer, propagate in `RuleToSequenceCompiler`; round-trip `toString()`. | — | Grammars round-trip with `/c`; matching-layer node exposes `isContent`. |
| 2 | **Decouple `contentOffsets`** in `SequenceAttribute::withValidSequence()` from `autoFactories` keys → use the marker. | 1 | `SequenceAttributeGetUnitTest` partition correct (still hand-wired cursor for now). |
| 3 | **Defaults builders** — implement `Rule::withDefaults()` / `RegionConfigApi::withDefaults()`; add `Defaults::managedRole`; `StructuralDefaultResolver`. | — | Unit tests: a `Defaults` resolves to the right structural attribute per style. |
| 4 | **Context level model** — `NodeContext.breaksLine`/`beginsLine`, `ContextStack::indentationLevel()`/`indentUnit()`/`beginsLine()`. | — | Layer-3a tests in [40](40-testing-strategy.md). |
| 5 | **Parse-time wiring** — `ParsingContext::resolveSequence()`; `NodeAttrFactory::fillSequenceBasedNodeWithAttributes()` builds a self-consistent `SequenceAttribute` (cursor + `autoFactories` from Defaults via Context). | 1–3 | Units correct from a **real parse**; `SequenceAttributeGetUnitTest` green & un-excluded. |
| 6 | **Reformat path** — revive `Whitespace::indentationResolver()`; make `Node::applyFormatting()` a DFS walk with break policy + role-keyed idempotent trivia upsert. | 4,5 | Layer-2/3b reformat + idempotency tests. |
| 7 | **Generator unblock** (separate task) | 1,5 | TreeSchema facade tests. |

Stages 1–2, 3, 4 are largely parallel; 5 joins 1–3; 6 joins 4–5.

## What stays scaffolded vs wired, per stage

- After **1–2**: classification is real, but Units are still only wired by
  tests/facades. No behavior change for end users.
- After **3–4**: Defaults and the level model exist and are unit-tested, but
  nothing consumes them in a real parse yet. Still inert for users.
- After **5**: a real parse yields self-consistent `SequenceAttribute`s. Still
  **lossless-identical output** — no formatting happens unless explicitly invoked.
- After **6**: re-formatting is live, but **only** for grammars that declare styles
  + indentation. Grammars that don't are unaffected (Mode 1 lossless re-emit).

## Risk & rollback

- **Lossless invariant is the safety net.** Layer-1 tests
  ([40](40-testing-strategy.md)) run from stage 1 and must stay green; any
  regression is caught immediately and is a hard stop.
- **Formatting is opt-in.** The reformat path only activates when a grammar
  declares styles/indent and the caller invokes `applyFormatting(newStyle)`.
  Default behavior (parse → `__toString()`) is untouched. So stage 6 cannot break
  existing consumers.
- **The one behavior change to watch** is stage 2 (contentOffsets now from the
  marker). Guarded directly by `SequenceAttributeGetUnitTest`. If a grammar lacks
  `/c` on a `/g` group, treat as: no content units detected → fall back to the old
  heuristic *or* fail loudly (decide during execution; failing loud is more in
  keeping with the project's "surface real bugs" stance, cf. `NodeTypeResolver`).
- Each stage is revertible in isolation because later stages only *consume* earlier
  additions; nothing earlier depends on later wiring.

## How this unblocks the tree generator (the next task)

The generator today has three faults, all downstream of the tangle this refactor
removes:

1. **Emits a non-existent class.** Generated facades under
   `src/Infrastructure/TreeSchema/Model/Json/{Rfc8259,C}/*.php` declare / import /
   instantiate `GroupedAttribute` (e.g. `new GroupedAttribute('items', null, [])`),
   but that class was renamed to `SequenceAttribute` in commit `3741544`. Output is
   **uncompilable**. → Mechanical rename in the emitter
   (`FacadeClassRenderer`), plus the same in `StructuralFactoryInfo` which still
   imports the **stale** `Foundation\Parsing\Model\Attribute\GroupAttribute` /
   `StructureAttribute` namespaces (now under `…\Attribute\Node\` / `…\Structure\`).

2. **Guesses content vs structural from a sample parse.**
   [NodeSchemaCollector.php:180-234](../../src/Infrastructure/TreeSchema/Generator/NodeSchemaCollector.php#L180-L234)
   decides the content node by matching `ANCHOR_NAME_META_KEY` against an example
   tree. After this refactor the **`/c` marker** carries that fact through to the
   matching layer and the parsed `SequenceAttribute`. The generator reads the
   partition directly — deterministic, no sample dependency.

3. **`autoFactories`/Units only existed via after-the-fact facade wiring.** The
   generated `with{Prop}Validation()` methods were unreachable from real parse
   output. After stage 5, parsed `SequenceAttribute`s are already valid, so the
   generated facades wrap an **already-consistent** tree; the generator's job
   shrinks to typing (union types for Node choices, enums for Raw choices — its
   existing, working behavior).

So the generator task becomes: (a) fix emitted class names + namespaces, (b) read
the `/c`-derived partition instead of guessing, (c) regenerate facades, (d) green
the `TreeSchema` facade suites. None of that is attempted here.

## Out of scope (restated)

- The generator fix itself (above) — next task.
- `Foundation/AST/**` — nonexistent for now.
- Full style library (only `minified`/`pretty` + the JSON grammars are the proving
  ground); other grammars adopt the builders incrementally.
