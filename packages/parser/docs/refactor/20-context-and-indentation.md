# 20 — Context & the newline-driven indentation model

> The hardest concern. Gets the indentation level **right**: it follows actual
> line breaks, not structural nesting. This is the model the earlier (rejected)
> plan got wrong.

## Principle

> **Indentation level = the number of enclosing regions that are actually *broken*
> across lines. Structural nesting alone never changes it.**

Two orthogonal ideas; keep them separate:

1. **Break policy (per-style):** decides *which* regions are laid across multiple
   lines and *where* the newlines go. `minified` breaks nothing; `pretty` breaks
   (say) every non-empty object/array onto member-per-line.
2. **Level math (universal):** once the newlines are placed, the indentation of a
   piece that **begins a line** is `indentUnit × level`, where `level` = how many
   enclosing regions are broken. This rule is the same for every style.

A piece that does **not** begin a line gets no indentation (empty `leadingWs`),
regardless of how deep it sits.

## Worked examples

### The reference cases (problem A)

```
array_map(fn () => '', $array);     // call '(' inline → not broken → level stays 0 → args carry no leadingWs
array_map(
    fn () => '', $array             // '(' broken → level 0→1; arg #1 begins a line → leadingWs = unit×1;
);                                  //   arg #2 shares the line → no leadingWs; ')' begins a line at level 0
array_map(
    fn () => '',
    $array                          // both args begin lines → each leadingWs = unit×1
);
```

Note line 1: the lambda is *nested* but nothing indents, because the call region
is not broken. Cases 2 vs 3 differ only in **break policy** (how many break points
the style inserts); the **level math** (`unit × broken-ancestors`) is identical.

### JSON analogues (the engine's actual domain)

```
{"a":{"x":1},"b":2}               // minified: nothing broken, level 0 everywhere
```
```
{
    "a": {
        "x": 1                     // outer broken (level 1 for "a"/"b"); inner broken (level 2 for "x")
    },
    "b": 2
}
```
**The hard case — nesting grows, but no newline:**
```
{
    "a": {"x": 1},                # outer broken → "a","b" at level 1
    "b": 2                        # inner object NOT broken → "x" stays inline, contributes 0 to level
}
```
`"a"`'s value is an object (structural depth +1) yet `"x"` is not indented, because
the inner object is not broken. The model yields this for free: `level` counts
**broken** enclosing regions, and the inline inner object is not one.

## Where `level` lives (and why not a global counter)

`ContextStack` has two stores
([ContextStack.php](../../src/Foundation/Parsing/Model/Context/ContextStack.php)):

- `treeContext` (`ArrayObject`) — **tree-global, shared by reference** across all
  nodes (`push()` keeps the same reference). Holds `STYLE` and the indent **unit**
  (`Whitespace::CONTEXT_INDENT_UNIT`). It is *config*, not a descend counter — a
  mutable `treeContext['level']++/--` would leak across the whole tree and is
  fragile.
- `stack` (`NodeContext[]`) — the **ancestry path** to this node; each
  [NodeContext](../../src/Foundation/Parsing/Model/Context/NodeContext.php) carries
  a per-node `nodeContext` map.

So model `level` as a **pure function of ancestry**: each region node that is
broken records a per-node flag; `level` = count of broken ancestors.

```php
// NodeContext: per-node formatting state
$nodeContext['breaksLine'] = true;   // set by the formatter when this region is broken for the active style

// ContextStack: derived, no mutable global
public function indentationLevel(): int
{
    $level = 0;
    foreach ($this->stack as $ctx) {
        if (($ctx->nodeContext['breaksLine'] ?? false) === true) {
            $level++;
        }
    }
    return $level;
}

public function indentUnit(): string
{
    return $this->treeContext[Whitespace::CONTEXT_INDENT_UNIT] ?? '';
}
```

`level` becomes a property of *position in the tree*, recomputed anywhere from the
node's own `contextStack` — exactly the "Context is the tree's self-knowledge"
principle. No save/restore discipline, no cross-talk.

## "Begins a line?" — the second input

A managed `leadingWs` = `indentUnit × level` **iff** its entity begins a line;
otherwise empty. An entity begins a line iff a newline-bearing trivia immediately
precedes it (e.g. the broken region inserted a `newline` before this child). The
formatter knows this directly (it just placed the newline); a derivation from an
existing tree reads it from the preceding sibling/trivia. Expose it the same way:

```php
// set by the formatter on the node it just placed after a newline
$nodeContext['beginsLine'] = true;
```

## Two operating modes

### Mode 1 — Lossless re-emission (style unchanged)

The tree already contains exact trivia (`leadingWs`, `newline`, …). `__toString()`
concatenates as today; **no level computation happens**. This preserves the core
lossless invariant and must not regress. `level`/`breaksLine` are only consulted
when re-formatting.

### Mode 2 — Re-formatting (style change)

`Node::applyFormatting()`
([Node.php:62-72](../../src/Foundation/Parsing/Model/Node.php#L62-L72), currently
non-recursive — `LogicException` on recursion) becomes a DFS walk that, per node:

1. Sets `treeContext[STYLE]` to the target style (already done at line 64).
2. Applies the **break policy** for the style: decides `breaksLine` for this
   region; if broken, ensures a `newline`-bearing trivia after its opener and
   before each broken child, and marks those children `beginsLine`.
3. For each managed structural slot, **upserts** its role via the resolved Default
   (e.g. `leadingWs` content = `indentUnit × indentationLevel()` when
   `beginsLine`, else `''`) — see the idempotent upsert in
   [30-defaults-and-units.md](30-defaults-and-units.md).
4. Recurses into children (their `contextStack` now reflects this region's
   `breaksLine`, so their `indentationLevel()` is correct).

## The revived indentation resolver

[Whitespace::indentationResolver()](../../src/Infrastructure/Grammar/Definition/Technical/Whitespace.php#L135-L146)
is currently commented out with `return ''; // TODO`. Implement it as the
`Defaults` factory for a `leadingWs`-managing slot:

```php
public function indentationResolver(bool $leadingNewline = true): callable
{
    return static function (ContextStack $ctx, string $style): string {
        if (!$leadingNewline || !$ctx->beginsLine()) {
            return '';                       // inline → no indentation
        }
        return str_repeat($ctx->indentUnit(), $ctx->indentationLevel());
    };
}
```

It reads everything from the `ContextStack` it is handed — the same stack carried
by the node being formatted. This is why Defaults' signature is
`Closure(ContextStack, string $style): string`: it was designed for exactly this.

## Break policy — where it is declared

Break policy is a **style** concern, declared in PHP builders (per
[10-dsl-and-builders.md](10-dsl-and-builders.md)), not in the level math. Minimum
viable: a per-style predicate "should region R break?" (e.g. pretty: break any
object/array with ≥1 member; minified: never). It belongs on
`FormatDefinition`/`Formatter` alongside the existing
[Formatter](../../src/Foundation/Parsing/Model/Format/Formatter.php) closures, and
runs in Mode 2 step 2. Keeping it out of the level math is what makes cases 2 and 3
(same level math, different break points) fall out naturally.

## Edge cases to specify during execution

- Empty containers (`{}`, `[]`) — never broken; no inner indentation.
- Trailing separators (`?comma`) under pretty vs minified.
- `emptyLine` preservation/normalisation across reformat (a blank line is content
  the user may want kept; minified likely drops it, pretty may cap consecutive
  blanks).
- The closer (`}`/`)`) begins a line at the **parent** level (it leaves the broken
  region before its own `leadingWs` is computed) — verify the walk decrements
  level before resolving the closer's leading trivia.
- Tabs vs spaces / detected indent unit — handled purely by `indentUnit()`; level
  math is unit-agnostic.
