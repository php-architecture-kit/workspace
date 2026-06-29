# 10 — DSL & builder surface

> How grammar authors declare the two things the engine cannot infer:
> (1) which node in a `/g` group is **content**, and (2) the **formatting
> semantics** (styles, indent unit, per-slot structural role + default value).
>
> Decision (locked): **structure** stays in the string-DSL; **formatting
> semantics** live in **PHP builders** that extend what already exists.

## Part 1 — String-DSL: the `/c` content marker (the only DSL addition)

### Problem it solves

A `/g` group such as
`?(-l* value[item]/c (-* comma -t* -l* value[item]/c)* -t*)[items]/g`
must expose its repeating **content** (`value`) distinctly from its **structural**
separators (`comma`, trivia). Today the tree generator *guesses* this by matching
anchor-meta against a sample parsed tree
([NodeSchemaCollector.php:180-234](../../src/Infrastructure/TreeSchema/Generator/NodeSchemaCollector.php#L180-L234)).
We replace the guess with a fact declared in the grammar.

### Syntax

A new single-letter **SequenceNode tag**, `c` (for *content*), applied to each
occurrence of the content node:

```
value[item]/c          # this node is the repeating content of its enclosing /g group
member/c
```

`c` is free — existing tag letters are `n`,`s`,`r` (SequenceNode `NodeType`),
`g`,`r`,`t` (NestedSequence). It composes with the others: `value[item]/cn` =
content + `NodeType::Node`.

### Parsing & model

- [SequenceNode::fromString()](../../src/Foundation/Grammar/Definition/Model/Sequence/SequenceNode.php#L54)
  already captures tags via `(?:\/(?<tags>[a-zA-Z]+))?` → `str_split`. No regex
  change needed; add `c` handling in the constructor (alongside the existing
  `n`/`s`/`r` → `nodeType` mapping at lines 30-36):
  ```php
  public bool $isContent = false;       // new
  // in __construct:
  if (in_array('c', $tags, true)) { $this->isContent = true; }
  ```
- [SequenceNode::toString()](../../src/Foundation/Grammar/Definition/Model/Sequence/SequenceNode.php#L113)
  already serialises `$this->tags`, so round-trip is preserved as long as `c`
  stays in `$tags`. Verify `c` is not stripped anywhere.
- Carry `isContent` into the **matching-layer** node
  (`Foundation/Matching/Model/SequenceNode.php`) and propagate it in
  [RuleToSequenceCompiler](../../src/Foundation/Grammar/Compiled/Compiler/RuleToSequenceCompiler.php)
  alongside the existing `/g` → `SequenceAttribute::TAG` tagging. This is what
  `SequenceAttribute` reads to compute `contentOffsets` — see
  [30-defaults-and-units.md](30-defaults-and-units.md).

### Validation

- `/c` is only meaningful inside a `/g` (or `/r`) NestedSequence; reject or warn
  otherwise.
- A `/g` group must declare **exactly one** content node-name (it may appear
  multiple times — leading + inside the repeat — but all occurrences must share
  one name). Enforce in `NestedSequence`/`SequenceRule` validation, mirroring the
  existing `validateSequenceNodes()` guards.

## Part 2 — PHP builders: formatting semantics

Three things are declared in code, extending existing entry points. **Nothing here
changes the string-DSL.**

### 2a. Styles & indent unit

Today styles are implicit in
[Whitespace::withIndentationSupport($stylesWithIndentation, $indentUnitResolver)](../../src/Infrastructure/Grammar/Definition/Technical/Whitespace.php#L114)
and selected by
[Grammar::setStyleResolver()](../../src/Foundation/Grammar/Definition/Grammar.php#L64).
Make the set explicit and attach the indent unit per indented style:

```php
$grammar
    ->withStyles('minified', 'pretty')      // declares the known style names
    ->setStyleResolver(fn($root) => /* detect from input or config */ 'pretty');

$whitespace->withIndentationSupport(
    stylesWithIndentation: ['pretty'],
    indentUnitResolver: fn(NodeInterface $root): string => '    ', // 4 spaces; or detect from input
);
```

- `withStyles()` records the legal style names (on `Grammar`/`FormatDefinition`);
  used to validate per-style default maps and to drive reformat tests.
- `withIndentationSupport()` stays as-is: a `ContextDefinition` initializer that
  writes `treeContext[Whitespace::CONTEXT_INDENT_UNIT]` for indented styles
  ([Whitespace.php:118-127](../../src/Infrastructure/Grammar/Definition/Technical/Whitespace.php#L118-L127)).
  The indent **unit** (one level's string) is style/global; the indent **amount**
  (`unit × level`) is computed per node from Context — see
  [20-context-and-indentation.md](20-context-and-indentation.md).

### 2b. Per-slot structural role + default — extending `Defaults`

`Defaults` already has the right shape:
`factoryByStyles: array<string $style, Closure(ContextStack $ctx, string $style): string>`
([Defaults.php](../../src/Foundation/Grammar/Definition/Defaults.php)), attachable
to `SequenceNode::$defaults` / `RegexRule::$defaults`. The **one missing concept**
is the **managed role** for trivia slots: a generic `-l*` trivia `GroupAttribute`
can hold `leadingWs`/`emptyLine`/`inlineWs`, so the slot must declare which role
*formatting* owns.

Extend `Defaults` with an optional managed role:

```php
final class Defaults
{
    public const DEFAULT_STYLE = 'default';
    public ?string $managedRole = null;     // new: e.g. 'leadingWs' for a -l* slot; null = role is the node's own name

    public function forStyle(string $style, callable $factory): self { /* setFactoryForStyle */ }
    public static function managingRole(string $role): self { $d = new self(); $d->managedRole = $role; return $d; }
}
```

### 2c. Implementing the `withDefaults()` builders (currently stubs)

[Rule::withDefaults(array $defaults)](../../src/Foundation/Grammar/Definition/Rule.php#L327)
and `RegionConfigApi::withDefaults()` are empty. Define the array shape as
*slot-name → `Defaults`* and have the builder attach each `Defaults` onto the
matching structural `SequenceNode` (by anchor/name) within the rule's sequence:

```php
Rule::seq("members",
    "-l* member/c (-* comma -t* -l* member/c)* -t*")
    ->withDefaults([
        // structural separator: role == its own name ('comma')
        'comma'  => (new Defaults())
            ->forStyle('pretty',   fn(ContextStack $c, string $s) => ',')
            ->forStyle('minified', fn(ContextStack $c, string $s) => ','),

        // trivia slot: a generic 'trivia' group whose formatting-managed role is leadingWs
        'trivia' => Defaults::managingRole('leadingWs')
            ->forStyle('pretty',   $whitespace->indentationResolver(leadingNewline: true))
            ->forStyle('minified', fn(ContextStack $c, string $s) => ''),
    ]);
```

- The slot key (`'comma'`, `'trivia'`) selects the structural `SequenceNode` by
  its (post-middleware) anchor/name. `trivia` is the anchor assigned by
  [TriviaSequenceNamingMiddleware](../../src/Foundation/Grammar/Definition/Middleware/Standard/TriviaSequenceNamingMiddleware.php)
  (or `trivia0`/`trivia1` when several).
- `content` nodes (`/c`) are **never** given structural defaults — they are the
  payload, supplied by the caller of `addUnit()`.
- `$whitespace->indentationResolver(...)` is the revived resolver
  ([Whitespace.php:135-146](../../src/Infrastructure/Grammar/Definition/Technical/Whitespace.php#L135-L146),
  currently commented out): a `Closure(ContextStack, string): string` returning
  `indentUnit × level` — full definition in
  [20-context-and-indentation.md](20-context-and-indentation.md).

## Why builders, not string-DSL, for formatting

The default **values** are inherently `Closure(ContextStack, string $style)` — they
depend on runtime Context (indentation level, preceding state) and the active
style. That is PHP, not a static string. Encoding it inline in rule strings would
force the string-DSL to embed closures. Keeping structure in the string and
semantics in builders is the clean seam, and it reuses the existing (today dead)
`Defaults`/`withDefaults`/`withIndentationSupport` scaffolding rather than
inventing a parallel system.

## Open details to finalise during execution

- Exact slot-selector semantics in `withDefaults()` when a name repeats (leading
  `trivia` vs the in-repeat `trivia`) — likely one `Defaults` applies to all
  occurrences of that anchor; confirm against the indentation model.
- Whether `withStyles()` lands on `Grammar` or `FormatDefinition` (the latter is
  the natural home and is already collected by the compiler).
- Whether `Defaults` factories may need to return a richer structural attribute
  (not just `string`) for non-whitespace structural roles; default to `string` +
  a thin wrapper (the `StructuralDefaultResolver` in
  [30-defaults-and-units.md](30-defaults-and-units.md)).
