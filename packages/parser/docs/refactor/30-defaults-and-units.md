# 30 — Defaults & Units: consistent-by-construction `SequenceAttribute`

> How a `/g` group becomes self-consistent at parse time and stays consistent on
> mutation/reformat — with Defaults as the value source, Context as the knowledge
> source, and a role-keyed idempotent trivia upsert (problem B).

## Today vs target

[SequenceAttribute](../../src/Foundation/Parsing/Model/Attribute/SequenceAttribute.php)
already has the Unit machinery: `withValidSequence()`, `addUnit()`, `removeUnit()`,
`getUnit()`, `getUnitCount()`, `contentOffsets`, `autoFactories`. But:

- It is wired **only** by tests / hand-built facades after the fact — never by a
  real parse. A parsed `SequenceAttribute` has empty `contentOffsets` and no
  `autoFactories`, so it is **not** self-consistent.
- `withValidSequence()` **conflates** two things: it decides content-vs-structural
  purely by "is this name a key in `$autoFactories`?"
  ([SequenceAttribute.php:226-249](../../src/Foundation/Parsing/Model/Attribute/SequenceAttribute.php#L226-L249)).
  So classification is coupled to reconstruction.

Target: at parse time the `SequenceAttribute` is built already carrying its
content/structural partition (from the `/c` marker) and its `autoFactories` (from
`Defaults` resolved through `Context`).

## 1. Content/structural partition from the `/c` marker

Decouple classification from `autoFactories`. The matching-layer `SequenceNode`
carries `isContent` (from `/c`, see
[10-dsl-and-builders.md](10-dsl-and-builders.md)). `contentOffsets` is then "the
attributes whose originating node was `/c`", independent of any factory map:

```php
// withValidSequence(): classification now comes from the marker, not autoFactories keys
$this->contentOffsets = [];
foreach ($this->attributes as $idx => $attr) {
    $this->validityCursor->advance($attr->getName());
    if ($this->isContentAttribute($attr)) {   // ← was: !array_key_exists($name, $autoFactories)
        $this->contentOffsets[] = $idx;
    }
}
```

`isContentAttribute()` checks a content marker carried on the attribute (a tag/meta
stamped during construction from the node's `isContent`). `autoFactories` is now
used **only** for reconstruction in `addUnit()`.

## 2. Building the `SequenceAttribute` at parse time

In
[NodeAttrFactory::fillSequenceBasedNodeWithAttributes()](../../src/Foundation/Parsing/Factory/NodeAttrFactory.php#L66),
after the attribute list is assembled, resolve the compiled sequence and wire the
attribute:

```php
$sequenceDef = $this->context->resolveSequence($sequence->name);   // see §5 (ParsingContext gap)
if ($sequenceDef !== null && $sequenceAttr !== null) {
    $cursor = SequenceValidityCursor::fromSequence($sequenceDef, $sequenceAttr->getName());
    $sequenceAttr->withValidSequence($cursor, $this->buildAutoFactories($sequenceDef, $sequenceAttr));
}
```

- [SequenceValidityCursor::fromSequence(Sequence, anchorName)](../../src/Foundation/Parsing/Model/Attribute/SequenceValidityCursor.php#L64)
  already exists and finds the `NestedSequence` by the group's anchor.
- `buildAutoFactories()` produces, per **structural** slot, a
  `callable(): NodeAttributeInterface` that constructs the slot's structural
  attribute from its `Defaults` resolved through the node's `ContextStack` + active
  style (§3–§4).

The result: a parsed `SequenceAttribute` reports correct `getUnitCount()` /
`getUnit()` immediately, and `addUnit()` works against real parse output — the
acceptance target for the currently-failing `SequenceAttributeGetUnitTest`.

## 3. `StructuralDefaultResolver` — Defaults → structural attribute

`Defaults::factoryByStyles` returns a **string**; `autoFactories` needs a
**`NodeAttributeInterface`**. A thin resolver bridges them, and handles the
**managed role** for trivia slots:

```php
final class StructuralDefaultResolver
{
    public function resolve(Defaults $defaults, ContextStack $ctx, string $style): NodeAttributeInterface
    {
        $value = ($defaults->factoryFor($style))($ctx, $style);   // the default text

        $role = $defaults->managedRole;                            // e.g. 'leadingWs', or null
        if ($role !== null) {
            // a whitespace role: build the role node, wrapped as the slot's trivia GroupAttribute
            return $this->triviaGroupWith($role, $value);          // GroupAttribute['trivia'] → [ Node(role)[RawContentAttribute(value)] ]
        }

        // plain structural separator (role == own name), e.g. comma → StructureAttribute
        return new StructureAttribute(true, $defaults->name, $value === '' ? null : $value);
    }
}
```

This reuses the existing `Defaults` class unchanged except for the `managedRole`
field added in [10-dsl-and-builders.md](10-dsl-and-builders.md).

## 4. Role-keyed idempotent trivia upsert (problem B)

A `-l*` slot is a generic `trivia` `GroupAttribute`
([GroupAttribute](../../src/Foundation/Parsing/Model/Attribute/Node/GroupAttribute.php))
that may hold `leadingWs` / `emptyLine` / `inlineWs`. Formatting must place exactly
one `leadingWs` of the right value. **Never blind-append** (`addNode`) — that is
how you get a 2nd/3rd `leadingWs`. Upsert by role/name:

```php
// inside the trivia GroupAttribute: ensure exactly one node of $role, with $content
public function upsertRole(string $role, string $content, int $position): void
{
    foreach ($this->nodes as $node) {
        if ($node->getName() === $role) {
            $node->replaceContent($content);   // update in place → idempotent
            return;
        }
    }
    $this->insertRoleNode($role, $content, $position);   // none yet → insert exactly one
}
```

- Re-formatting twice == once: the second pass finds the existing `leadingWs` and
  only updates its text.
- The **position** within the group is the slot's declared position (leading
  trivia goes before content; trailing after) — deterministic, so insertion is
  stable.
- `removeUnit()` already removes the whole unit incl. its structural block
  ([SequenceAttribute.php:113-145](../../src/Foundation/Parsing/Model/Attribute/SequenceAttribute.php#L113-L145));
  no change beyond honoring the marker-based `contentOffsets`.

### Two flows that share the resolver

| Flow | Container exists? | Action |
|---|---|---|
| **addUnit** (structural growth) | No — slot missing for a new content | `autoFactories` builds the trivia group + separator from Defaults via Context, then inserts the unit. |
| **reformat** (existing tree) | Yes — trivia group already there | Formatter walks and `upsertRole()`s the managed role's value (`indentUnit × level` or `''`) idempotently. |

Both compute values through the same `StructuralDefaultResolver` +
`indentationResolver`; they differ only in whether the structural container is
created or updated.

## 5. The `ParsingContext` access gap

`NodeAttrFactory` holds a `ParsingContext`
([ParsingContext.php](../../src/Foundation/Parsing/Contract/ParsingContext.php)),
which today exposes only `matchingContextForRegion(TokenRegion)`. The compiled
`Sequence` definitions live in the
[SequenceLibrary](../../src/Foundation/Matching/Model/SequenceLibrary.php)
(`$sequences: array<string, Sequence>`) on a `MatchingContext`. Add a direct
resolver so `fillSequenceBasedNodeWithAttributes()` can fetch a `Sequence` by the
matched sequence's `name`:

```php
interface ParsingContext {
    // ...existing...
    public function resolveSequence(string $name): ?Sequence;   // new
}
```

`DefaultParsingContext` implements it from the compiled grammar's sequence library.
This is the main piece of plumbing the refactor adds; everything else reuses
existing models. (Open: whether to expose the whole `SequenceLibrary` or just this
lookup — prefer the narrow lookup.)

## 6. What stays unchanged

- The `/g` → `SequenceAttribute` grouping in
  `fillSequenceBasedNodeWithAttributes()` and the `/r` → `RawContentAttribute`
  raw-group path stay; we add wiring **after** the attribute list is built.
- `addUnit()`/`removeUnit()`/`getUnit()` algorithms are unchanged; they simply now
  run against `contentOffsets` derived from the marker and `autoFactories` derived
  from Defaults.
- The validity cursor (`SequenceValidityCursor`) is unchanged — it already walks
  the compiled `NestedSequence` correctly; we just start feeding it from a real
  parse.

## Acceptance signals

- A `SequenceAttribute` from **`parse()`** (not hand-assembled) gives the right
  `getUnitCount()`/`getUnit()` — fixes `SequenceAttributeGetUnitTest`.
- `addUnit($content)` on such an attribute inserts the correct style-aware
  separator + `leadingWs`; calling it twice never produces duplicate trivia.
- Reformatting an existing tree upserts `leadingWs` idempotently (format∘format ==
  format). See [40-testing-strategy.md](40-testing-strategy.md).
