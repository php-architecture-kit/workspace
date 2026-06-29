# 00 — Overview: a self-sufficient, formattable Parsed Tree

> Part of the refactor specified in the master plan. Read this first; the other
> documents (`10`–`50`) drill into each concern.

## Why this refactor exists

The parser produces a **lossless Parsed Tree**: every character of the input is
representable, and `Node::__toString()` reconstructs the original exactly. We now
want that tree to do two things it cannot do today:

1. **Guard its own structural consistency.** A `/g` grouped sequence
   (`SequenceAttribute`) should know which of its children are repeating *content*
   units and which are *structural* separators/trivia — and keep that invariant
   when units are added or removed — without any external (test/facade) code
   wiring it after the fact.
2. **Re-format itself between styles** (e.g. `minified` ↔ `pretty`) while staying
   lossless: insert/adjust whitespace and indentation correctly and idempotently.

Three mechanisms already exist in scaffolded form but are tangled and mostly
dead. They are **one mechanism**, not three, and must be designed together:

| Concern | Role in the design | Today |
|---|---|---|
| **Units** (`SequenceAttribute`) | Holds repeating content + structural separators; maintains consistency on mutation. | `withValidSequence()`/`autoFactories` only run from tests/hand-built facades, never from a real parse. |
| **Defaults** (`Defaults::factoryByStyles`) | The **value source** for structural pieces (separators, trivia, whitespace, indentation). Signature is already `Closure(ContextStack, string $style): string`. | Write-only; populated only by `Rule::token()`/`Rule::keyword()`; never read. |
| **Formatting / Style** (`ContextStack::STYLE`, `setStyleResolver()`, `withIndentationSupport()`) | **Selects** which defaults are correct (compact vs pretty). | Style flows; the indentation resolver and re-render path are commented out. |
| **Context** (`ContextStack`) | The tree's own **knowledge layer**: active style, indentation level, preceding state, nesting. | Exists and threads through every `Node`; only carries `STYLE` so far. |

This refactor is the **prerequisite** that unblocks fixing the tree generator
(which today emits the non-existent `GroupedAttribute` and *guesses* content vs
structural from a sample parse). The generator fix itself is the **next** task —
see [50-sequencing-and-generator-unblock.md](50-sequencing-and-generator-unblock.md).

## Layering (who knows what)

```
Style (selector)        ── picks which Defaults branch is correct for the active style
   │
Context (knowledge)     ── ContextStack: style + indentation level + preceding state + depth
   │                        carried on every Node (Node::$contextStack), threaded parent→child
Defaults (values)       ── style+context → the default text for a structural piece
   │
Units (consistency)     ── SequenceAttribute consumes Context-resolved Defaults to build/keep
                            correct structural attributes (separators, trivia, leadingWs)
```

The **Parsed Tree is self-sufficient**: it carries (via Context) everything it
needs to stay consistent and to re-format. It never reaches into a not-yet-existing
AST layer, nor relies on test/facade assembly. `Foundation/AST/**` is treated as
**nonexistent / out of scope** for this work.

## The two hard problems that drive the design

### (A) Indentation is newline-driven, not nesting-driven

Indentation level increments only when a region is actually **broken** across
lines — not whenever structural nesting deepens. The canonical illustration:

```
array_map(fn () => '', $array);     // '(' not followed by newline → args stay inline → no indent
array_map(
    fn () => '', $array             // '(' followed by newline → level+1; the two args share a line → one leadingWs
);
array_map(
    fn () => '',
    $array                          // each arg begins a line → each gets leadingWs = indentUnit × level
);
```

A naive "indent = nesting depth" model is wrong: in line 1 the lambda is nested
but nothing indents. The level is a function of **actual line breaks**, tracked in
Context. Full model in
[20-context-and-indentation.md](20-context-and-indentation.md).

### (B) Trivia is a generic group; formatting must upsert a specific role, idempotently

A leading-trivia slot written `-l*` in the grammar is named `trivia` by
[TriviaSequenceNamingMiddleware](../../src/Foundation/Grammar/Definition/Middleware/Standard/TriviaSequenceNamingMiddleware.php)
(anchor `trivia`, `NodeType::Node`, `ZeroOrMore`) and therefore parses into a
**`GroupAttribute`** holding whatever whitespace regions occurred, each renamed by
role (`leadingWs` / `trailingWs` / `emptyLine` / `inlineWs`) at tokenization-end by
[Whitespace.php:79-95](../../src/Infrastructure/Grammar/Definition/Technical/Whitespace.php#L79-L95).

To pretty-print we must insert a `leadingWs` of the right indentation **into that
generic group**. That demands:

1. **Declaring which role a slot manages** (a `-l*` slot manages `leadingWs`) — so
   we know what to insert and don't guess.
2. **Role-keyed idempotent upsert** — find an existing `leadingWs` and replace its
   content, else insert exactly one; never blind-append. Re-formatting twice must
   equal formatting once.
3. **A value** = `indentUnit × level` from (A), via style-aware Defaults.

Full mechanism in [30-defaults-and-units.md](30-defaults-and-units.md).

## Glossary

- **content vs structural** — within a `/g` group, the *content* node is the
  repeating payload (e.g. `value`/`member`); everything else (separators like
  `comma`, trivia) is *structural*. Declared explicitly via the `/c` marker — see
  [10-dsl-and-builders.md](10-dsl-and-builders.md). Replaces the generator's
  fragile sample-parse guess.
- **trivia roles** — `leadingWs`, `trailingWs`, `emptyLine`, `inlineWs`: the four
  renamings a `whitespace` region can take (its `possibleNames`), decided by
  newline context during tokenization.
- **Unit** — one content attribute plus its surrounding structural attributes,
  as exposed by `SequenceAttribute::getUnit()` / `getUnitCount()`.
- **line-break level** — the newline-driven indentation depth from problem (A),
  held in `ContextStack.treeContext`.
- **style** — a named formatting profile (e.g. `minified`, `pretty`); selects
  which Defaults apply. `Defaults::DEFAULT_STYLE = 'default'`.

## Scope / non-goals

- **In scope:** the integrated Context · Defaults · Style · Units mechanism that
  makes the Parsed Tree self-consistent and re-formattable; the `/c` content
  marker; bringing `Defaults`/`Formatter`/indentation scaffolding to life.
- **Out of scope (next task):** the tree-generator fix (`GroupedAttribute` →
  `SequenceAttribute`, stale namespaces, facade regeneration).
- **Out of scope (treated as nonexistent):** `Foundation/AST/**`.
- **Keep, do not remove:** `Defaults`, `Formatter`, `FormatDefinition`,
  `ContextDefinition`, the indentation scaffolding. They are the backbone.
