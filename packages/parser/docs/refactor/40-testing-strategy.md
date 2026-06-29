# 40 — Testing strategy

> How to prove each layer correct, and how to guard the lossless invariant while
> adding re-formatting. Existing golden fixtures already cover the JSON-C case.

## Assets already in place

`assets/parser-source-files/json/c/` holds the **same document** in several shapes
— perfect golden pairs:

- `json-c.minified.jsonc`
- `json-c.pretty.jsonc`
- `json-c.messy.jsonc`, `json-c.messy-2.jsonc`, `json-c.messy-3.jsonc`

These drive reformat and normalisation tests without authoring new goldens. Add
matching minified/pretty pairs for `JsonRfc8259` if coverage gaps appear.

## Harness

- Base: [GrammarTestCase](../../tests/Func/Grammar/GrammarTestCase.php) —
  `assertGrammarParsing(string, Grammar, ...stageCallbacks)` exposes every stage
  (`assertParsingResultValid`, `assertCompiledGrammarValid`, …).
- Suite: `./vendor/bin/phpunit -c tools/phpunit/phpunit.xml --testsuite "Parser Package Test Suite"`.
- Smoke / manual: `bin/console parser:parse <fixture> <grammarFqcn>`.

## Layer 1 — Lossless round-trip (regression guard, must never break)

The core invariant, independent of this refactor; assert it **first** so reformat
work cannot silently regress it.

```
for each fixture F and its grammar G:
    assertSame(F, parse(F, G)->__toString());
```

Implement as a data-provider test over every `assets/**/*.jsonc`. Also assert a
**no-op format is identity**: `parse(F)->applyFormatting(currentStyle)->__toString() === F`
(Mode 1 in [20-context-and-indentation.md](20-context-and-indentation.md) must not
touch a tree whose style is unchanged).

## Layer 2 — Reformat correctness + idempotency

```
assertSame(pretty,   parse(minified, G)->applyFormatting('pretty')->__toString());
assertSame(minified, parse(pretty,   G)->applyFormatting('minified')->__toString());
assertSame(pretty,   parse(messy,    G)->applyFormatting('pretty')->__toString());     // normalisation
```

**Idempotency** (catches duplicate-trivia bugs from problem B):

```
$once  = parse(minified, G)->applyFormatting('pretty')->__toString();
$twice = parse($once,    G)->applyFormatting('pretty')->__toString();
assertSame($once, $twice);          // format ∘ format == format
```

And a direct duplicate guard: after two `pretty` passes, every trivia
`GroupAttribute` contains **at most one** `leadingWs` node.

## Layer 3 — Indentation level (the newline-driven model)

Two complementary styles of test:

**3a. Unit test the level math directly** (fast, isolates problem A):

```
// construct ContextStacks with known breaksLine ancestry, assert:
assertSame(0, $inlineNestedCtx->indentationLevel());      // nested but no broken ancestor
assertSame(2, $deeplyBrokenCtx->indentationLevel());      // two broken ancestors
// indentationResolver returns '' when !beginsLine, indentUnit×level when beginsLine
```

**3b. End-to-end on a mixed fixture** — a document where one nested container is
broken and a sibling stays inline (the JSON analogue of the `array_map` cases):

```
{
    "a": {"x": 1},        // inner inline → "x" NOT indented (level contribution 0)
    "b": {
        "y": 1            // inner broken → "y" indented one level deeper
    }
}
```
Assert the produced `leadingWs` contents match (`"x"` → none beyond its line;
`"y"` → `indentUnit × 2`). This is the test that fails under a naive
nesting-depth model and passes under the newline-driven one.

## Layer 4 — Unit mutation against a REAL parse

The crux of "self-sufficient": operations work on trees from `parse()`, not
hand-assembled ones.

```
$obj = findSequenceAttribute(parse(prettyObject, G));   // members /g group
$before = $obj->getUnitCount();

$obj->addUnit(newMemberContent());
assertSame($before + 1, $obj->getUnitCount());
// the inserted unit carries the correct style-aware separator (comma) + leadingWs
assertValidPretty($obj->getUnit($before));
// idempotent / no duplicate trivia
$obj->addUnit(anotherMember());
assertNoDuplicateTrivia($obj);

$obj->removeUnit(0);                                     // whole unit incl. its structural block
assertSame($before, $obj->getUnitCount());
assertSame(/* exact reconstructed text */, (string) $obj);   // still lossless
```

## Layer 5 — Revive the currently-failing suites

- [SequenceAttributeGetUnitTest](../../tests/Func/Grammar/Json/SequenceAttributeGetUnitTest.php)
  — its `getUnit()/getUnitCount()` mismatches are caused precisely by parsed
  `SequenceAttribute`s having no `contentOffsets`. Once
  [30-defaults-and-units.md](30-defaults-and-units.md) wires construction, these
  become the acceptance test for content/structural partition. **Move this suite
  out of "excluded" once green.**
- `JsonRfc8259ParsedTreeTest` — keep green throughout (it pins anchor names /
  `meta['alternatives']`); the `/c` marker must not disturb it.
- `TreeSchema` facade tests (`JsonRfc8259FacadeBuildTest`, …) — remain excluded
  here; they are the acceptance target of the **next** task
  ([50-sequencing-and-generator-unblock.md](50-sequencing-and-generator-unblock.md)).

## Test placement & naming

- Round-trip + reformat: `tests/Func/Grammar/Format/…` (new).
- Indentation unit tests: `tests/Unit/Parsing/Context/…` (new) for `3a`;
  `tests/Func/Grammar/Format/…` for `3b`.
- Mutation: extend `tests/Func/Grammar/Json/SequenceAttributeGetUnitTest.php`
  family, sourced from real `parse()`.

## Definition of done (for this refactor, not the generator)

1. Layer 1 green for all fixtures (no lossless regression, no-op format is identity).
2. Layer 2 green for the JSON-C minified/pretty/messy goldens, including idempotency.
3. Layer 3 green — indentation follows newlines, proven by the mixed fixture.
4. Layer 4 green — mutation on real-parse trees, duplicate-free.
5. `SequenceAttributeGetUnitTest` green and un-excluded.
6. Overall `Parser Package Test Suite` no worse than baseline; ideally the
   non-facade failures from the prior session are resolved.
