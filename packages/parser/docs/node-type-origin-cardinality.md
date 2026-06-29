# NodeType × NodeOrigin × Cardinality

## `Attribute` vs `Node attributes:` — two different things being counted

- **`Attribute`** (Table 1) — what the **parent** actually adds to its own `attributes[]` for this match, given `(NodeOrigin, NodeType, Cardinality)`. One object, always — even `GroupAttribute`/`RawGroupAttribute` are a single object in the parent's list; they just happen to hold multiple `Node`s/raw-attributes inside themselves.
- **`Node attributes:` (min/max/list type/allowed)** (Table 2) — a completely separate question: if this same matched fragment were instead materialized as its **own standalone `Node`** (origin = this row's `NodeOrigin`), what would *that* `Node`'s own attribute list look like?

**Example — `Token | Node | ZeroOrMore | GroupAttribute`:**
Table 1 says the parent adds exactly **one** `GroupAttribute` object. Inside it sits an array of N `Node`s — one per repeated token match (N itself is bounded by the `ZeroOrMore` cardinality, 0..∞, already expressed in column 3). Table 2, same row, says each of those N `Node`s, individually, has `min=1, max=1, list type=single, allowed=RawContentAttribute` — i.e. exactly one `RawContentAttribute`, never 0, never more. Table 1 counts *Nodes in the group*; Table 2 counts *attributes inside one Node*. Different axes, never confuse them.

Both tables list the same 36 `(NodeOrigin, NodeType, Cardinality)` variants — match rows by those three columns.

**Table 2 branches only on `(NodeOrigin, NodeType)`.** A standalone `Node` mirrors the matched fragment's own `NodeType` (the same `match($nodeType)` that drives Table 1): `Raw` collapses the whole fragment into one raw attribute (`RawContentAttribute`/`RawRegionAttribute`/`RawSequenceAttribute` by origin), `Structure` into one `StructureAttribute`, and `Node` decomposes into a multi-child group/sequence — but only for `Region`/`Sequence` origins; a `Token` has no inner structure, so every `Token` row is a single leaf attribute. So for every `Raw`/`Structure` row (and all `Token` rows) the four cardinalities are identical (`single`, `1..1`) — `Cardinality` affects the Table 1 wrapper, not the standalone node's inner shape. See [`NodeAttrFactory::fillRegionBasedNodeWithAttributes()`](../src/Foundation/Parsing/Factory/NodeAttrFactory.php).

## Table 1 — Attribute

| NodeOrigin | NodeType | Cardinality | Attribute | Alternatives influence |
|---|---|---|---|---|
| Token | Node | ExactlyOne | NodeAttribute | class |
| Token | Node | ZeroOrOne | OptionalAttribute | class |
| Token | Node | ZeroOrMore | GroupAttribute | class |
| Token | Node | OneOrMore | GroupAttribute | class |
| Token | Raw | ExactlyOne | RawContentAttribute | enum |
| Token | Raw | ZeroOrOne | OptionalRawAttribute | enum |
| Token | Raw | ZeroOrMore | RawGroupAttribute | enum |
| Token | Raw | OneOrMore | RawGroupAttribute | enum |
| Token | Structure | ExactlyOne | StructureAttribute | no |
| Token | Structure | ZeroOrOne | StructureAttribute | no |
| Token | Structure | ZeroOrMore | StructureAttribute | no |
| Token | Structure | OneOrMore | StructureAttribute | no |
| Region | Node | ExactlyOne | NodeAttribute | class |
| Region | Node | ZeroOrOne | OptionalAttribute | class |
| Region | Node | ZeroOrMore | GroupAttribute | class |
| Region | Node | OneOrMore | GroupAttribute | class |
| Region | Raw | ExactlyOne | RawRegionAttribute | enum |
| Region | Raw | ZeroOrOne | OptionalRawAttribute | enum |
| Region | Raw | ZeroOrMore | RawGroupAttribute | enum |
| Region | Raw | OneOrMore | RawGroupAttribute | enum |
| Region | Structure | ExactlyOne | StructureAttribute | no |
| Region | Structure | ZeroOrOne | StructureAttribute | no |
| Region | Structure | ZeroOrMore | StructureAttribute | no |
| Region | Structure | OneOrMore | StructureAttribute | no |
| Sequence | Node | ExactlyOne | NodeAttribute | class |
| Sequence | Node | ZeroOrOne | OptionalAttribute | class |
| Sequence | Node | ZeroOrMore | GroupAttribute | class |
| Sequence | Node | OneOrMore | GroupAttribute | class |
| Sequence | Raw | ExactlyOne | RawSequenceAttribute | enum |
| Sequence | Raw | ZeroOrOne | OptionalRawAttribute | enum |
| Sequence | Raw | ZeroOrMore | RawGroupAttribute | enum |
| Sequence | Raw | OneOrMore | RawGroupAttribute | enum |
| Sequence | Structure | ExactlyOne | StructureAttribute | no |
| Sequence | Structure | ZeroOrOne | StructureAttribute | no |
| Sequence | Structure | ZeroOrMore | StructureAttribute | no |
| Sequence | Structure | OneOrMore | StructureAttribute | no |

## Table 2 — Node and its attributes

| NodeOrigin | NodeType | Cardinality | min | max | list type | allowed |
|---|---|---|---|---|---|---|
| Token | Node | ExactlyOne | 1 | 1 | single | RawContentAttribute |
| Token | Node | ZeroOrOne | 1 | 1 | single | RawContentAttribute |
| Token | Node | ZeroOrMore | 1 | 1 | single | RawContentAttribute |
| Token | Node | OneOrMore | 1 | 1 | single | RawContentAttribute |
| Token | Raw | ExactlyOne | 1 | 1 | single | RawContentAttribute |
| Token | Raw | ZeroOrOne | 1 | 1 | single | RawContentAttribute |
| Token | Raw | ZeroOrMore | 1 | 1 | single | RawContentAttribute |
| Token | Raw | OneOrMore | 1 | 1 | single | RawContentAttribute |
| Token | Structure | ExactlyOne | 1 | 1 | single | StructureAttribute |
| Token | Structure | ZeroOrOne | 1 | 1 | single | StructureAttribute |
| Token | Structure | ZeroOrMore | 1 | 1 | single | StructureAttribute |
| Token | Structure | OneOrMore | 1 | 1 | single | StructureAttribute |
| Region | Node | ExactlyOne | 0 | ∞ | group | NodeAttribute, StructureAttribute, RawContentAttribute, RawRegionAttribute, RawSequenceAttribute |
| Region | Node | ZeroOrOne | 0 | ∞ | group | NodeAttribute, StructureAttribute, RawContentAttribute, RawRegionAttribute, RawSequenceAttribute |
| Region | Node | ZeroOrMore | 0 | ∞ | group | NodeAttribute, StructureAttribute, RawContentAttribute, RawRegionAttribute, RawSequenceAttribute |
| Region | Node | OneOrMore | 0 | ∞ | group | NodeAttribute, StructureAttribute, RawContentAttribute, RawRegionAttribute, RawSequenceAttribute |
| Region | Raw | ExactlyOne | 1 | 1 | single | RawRegionAttribute |
| Region | Raw | ZeroOrOne | 1 | 1 | single | RawRegionAttribute |
| Region | Raw | ZeroOrMore | 1 | 1 | single | RawRegionAttribute |
| Region | Raw | OneOrMore | 1 | 1 | single | RawRegionAttribute |
| Region | Structure | ExactlyOne | 1 | 1 | single | StructureAttribute |
| Region | Structure | ZeroOrOne | 1 | 1 | single | StructureAttribute |
| Region | Structure | ZeroOrMore | 1 | 1 | single | StructureAttribute |
| Region | Structure | OneOrMore | 1 | 1 | single | StructureAttribute |
| Sequence | Node | ExactlyOne | 1 | N (rule-defined) | sequence | NodeAttribute, OptionalAttribute, GroupAttribute, RawContentAttribute, RawRegionAttribute, RawSequenceAttribute, RawGroupAttribute, OptionalRawAttribute, StructureAttribute, SequenceAttribute |
| Sequence | Node | ZeroOrOne | 1 | N (rule-defined) | sequence | NodeAttribute, OptionalAttribute, GroupAttribute, RawContentAttribute, RawRegionAttribute, RawSequenceAttribute, RawGroupAttribute, OptionalRawAttribute, StructureAttribute, SequenceAttribute |
| Sequence | Node | ZeroOrMore | 1 | N (rule-defined) | sequence | NodeAttribute, OptionalAttribute, GroupAttribute, RawContentAttribute, RawRegionAttribute, RawSequenceAttribute, RawGroupAttribute, OptionalRawAttribute, StructureAttribute, SequenceAttribute |
| Sequence | Node | OneOrMore | 1 | N (rule-defined) | sequence | NodeAttribute, OptionalAttribute, GroupAttribute, RawContentAttribute, RawRegionAttribute, RawSequenceAttribute, RawGroupAttribute, OptionalRawAttribute, StructureAttribute, SequenceAttribute |
| Sequence | Raw | ExactlyOne | 1 | 1 | single | RawSequenceAttribute |
| Sequence | Raw | ZeroOrOne | 1 | 1 | single | RawSequenceAttribute |
| Sequence | Raw | ZeroOrMore | 1 | 1 | single | RawSequenceAttribute |
| Sequence | Raw | OneOrMore | 1 | 1 | single | RawSequenceAttribute |
| Sequence | Structure | ExactlyOne | 1 | 1 | single | StructureAttribute |
| Sequence | Structure | ZeroOrOne | 1 | 1 | single | StructureAttribute |
| Sequence | Structure | ZeroOrMore | 1 | 1 | single | StructureAttribute |
| Sequence | Structure | OneOrMore | 1 | 1 | single | StructureAttribute |

## NestedSequence `/g` vs `/r` — a different axis entirely

Tables 1-2 are about a single matched item. These last two are about a `NestedSequence` tagged `/g` (grouped) or `/r` (raw) — i.e. multiple consecutive sequence items collapsed by `NodeAttrFactory::fillSequenceBasedNodeWithAttributes()` into one combined attribute. The relevant distinction here isn't `NodeOrigin`/`NodeType`/`Cardinality`, it's **role**: each item in the run is either a *content* item (the repeated thing you actually care about, e.g. `member`) or a *structural* item (separators/trivia between repetitions, e.g. `comma`, whitespace). `/g` keeps that role distinction and groups content into addressable **Units**; `/r` throws the role distinction away and concatenates everything into one string.

## Table 3 — Grouped (`/g`) variant, by Unit

Produces one `SequenceAttribute`. Per [`SequenceAttribute::getUnit()`](../src/Foundation/Parsing/Model/Attribute/SequenceAttribute.php), a Unit = a run of structural attributes + exactly one content attribute.

| Unit component | Count per Unit | Attribute | Notes |
|---|---|---|---|
| structural attribute | 0..N | whatever its own rule resolves to in Table 1 (typically `RawContentAttribute`/`StructureAttribute`) | Names listed in `$autoFactories`; excluded from `contentOffsets`, auto-insertable via `addUnit()` |
| content attribute | exactly 1 | any row of Table 1, depending on the matched rule's own `(NodeOrigin, NodeType, Cardinality)` | Tracked in `contentOffsets[]`; returned by `getUnitContent($i)` |
| Unit (whole) | 1 structural-run + 1 content attribute | — | `getUnit($i)` returns the full slice; `getUnitCount()` = number of content attributes = number of matched repetitions |

## Table 4 — Raw (`/r`) variant

Produces one `RawContentAttribute`. Per [`NodeAttrFactory::flushRawGroup()`](../src/Foundation/Parsing/Factory/NodeAttrFactory.php), every item in the run — structural or content, regardless of its own `NodeType`/`NodeOrigin` — is reduced to `__toString()` and concatenated.

| Component | Count | Attribute | Notes |
|---|---|---|---|
| structural attribute + content attribute, undistinguished | N/A — no per-item tracking | single `RawContentAttribute` | No Unit concept exists here at all — there is no `getUnitCount()`/`addUnit()` for a flat `RawContentAttribute`. Each item's individual `NodeType` is discarded; only its verbatim text survives, in original order. |

## Alternatives influence — one rule, every table

`(NodeOrigin, NodeType, Cardinality)` decides the *attribute class*; the **number of
alternatives a slot/item can match** decides how the matched variant's *identity* is
encoded in the facade. The rule is uniform and recurses through every table:

- **`Node` → class union.** Each alternative is a distinct node rule → a distinct
  facade class. The facade member is typed as the union of those classes; you
  disambiguate with `instanceof`. (`ArrayNode::$items` →
  `GroupAttribute<NodeAttribute<PrimitiveNode|ObjectNode|ArrayNode>>`; `addItem()`
  takes `PrimitiveNode|ObjectNode|ArrayNode`.)
- **`Raw` → backed enum.** All alternatives share one raw attribute class; *which*
  alternative matched is carried by the attribute's `name` and exposed as a generated
  backed enum (one case per alternative, value = matched name). The facade keeps a
  single raw property plus `get{Slot}Type(): SlotEnum` / `set{Slot}(SlotEnum, …)`.
  (`PrimitiveNode::$primitive: RawRegionAttribute` + enum `PrimitiveType`
  {String,Number,True,False,Null}; `getPrimitiveType() = PrimitiveType::from($this->primitive->name)`.)
  The enum file is emitted by `EnumFileRenderer` for raw choices.
- **`Structure` → none.** A delimiter is a literal; there is no variant identity to
  encode. Single `StructureAttribute`, regardless of alternative count.

A single alternative degenerates: `Node` → one concrete class (no union), `Raw` → no
enum (one fixed name).

### Per table

- **Table 1** — as documented in its `Alternatives influence` column (`class`/`enum`/`no`).
- **Table 2** — two layers. **(a)** The standalone node's **own** identity: a region/
  sequence declared with `withPossibleNames(...)` materializes as one of N facade
  **classes** — always the *class* form (a node is always a class), and it is what
  fills the union inside the parent's `GroupAttribute<…>` (e.g. whitespace →
  `TrailingWsNode|LeadingWsNode|InlineWsNode|EmptyLineNode`). **(b)** Its **inner**
  attributes: `Region/Node` children each recurse through this same rule; `Region/Raw`
  and `Region/Structure` have a single fixed inner attribute (the variant is already
  encoded by the node class from layer (a)).
- **Table 3** (`/g`) — recurses per Unit component: the **content** attribute uses the
  rule for its own `NodeType` (class union / enum), and each **structural** attribute
  likewise (trivia ws → class union; `comma` → none).
- **Table 4** (`/r`) — **no influence.** `/r` reduces every item to `__toString()` and
  concatenates; each item's `NodeType` and variant identity are discarded. No union, no
  enum — just one `RawContentAttribute` string.

## Facade form — first by the node's NodeOrigin, then per attribute

A node's facade *class shape* is decided by the node's own `(NodeOrigin, NodeType)`
(Table 2) **before** any per-attribute detail. **Fixed, named, positional “slots” exist
only in a `Sequence | Node`** — the case the per-attribute table further down assumes. A
region is different.

| Node `(NodeOrigin, NodeType)` | Inner layout (Table 2) | Facade class shape |
|---|---|---|
| `Sequence \| Node` | sequence, `1..N` | fixed ordered **named slots** — one typed property per slot (`attributes[i]`), each shaped by the per-attribute table below. (`json`, `array`, `object`, `member`, `lineComment` — regions with `withRootSequence`, matched as sequences.) |
| `Region \| Node` | group, `0..∞` | **no fixed slots** — a dynamic collection of children; a `GroupAttribute`-style API on the node itself (`getNodes()/filter`, `add*`, `remove*`). Child node classes form a possible-names union. |
| `Token \| *`, `Region \| Raw`, `Region \| Structure`, `Sequence \| Raw`, `Sequence \| Structure` | single, `1..1` | one accessor over the **sole** attribute (`InlineWsNode::$raw`). |

So a `Region | Node` node is a **group**: its children are reached through the
collection API, and each child is itself a standalone node (recurse Table 2) — not a
compile-time-fixed named property. `Region | Raw` / `Region | Structure` carry exactly
one attribute, not a slot list. The per-attribute rows below therefore apply to a
**named slot** of a `Sequence | Node`, or to the **sole attribute** of a single-attribute
node.

### Per named slot / single attribute

**The property is always typed as the attribute class** and reads `attributes[i]`; a
node facade class (`XNode` — a Table 2 standalone node) is *never* the property type —
it appears only inside the attribute's PHPDoc generic `<…>` and in the accessor method
signatures.

| Attribute | Facade property (always the attribute class) | Node/variant type lives in |
|---|---|---|
| NodeAttribute | `public NodeAttribute $slot { get => $this->attributes[i]; }` | `@var NodeAttribute<XNode>`; `getNode{Slot}(): XNode`, `setNode{Slot}(XNode)` |
| OptionalAttribute | `public OptionalAttribute $slot { … }` | `@var OptionalAttribute<XNode>`; nullable accessors |
| GroupAttribute | `public GroupAttribute $slot { … }` | `@var GroupAttribute<XNode>`; `addNodeTo{Slot}()`, `getNodesFrom{Slot}(): XNode[]`, `removeNodeFrom{Slot}By*()` |
| StructureAttribute | `public StructureAttribute $slot { … }` | literal content — no node, no variant |
| RawContentAttribute | `public RawContentAttribute $slot { … }` | `getRaw{Slot}(): string`, `setRaw{Slot}(string)` |
| RawRegionAttribute | `public RawRegionAttribute $slot { … }` | opener/content/closer; `getRaw{Slot}()`, `setRaw{Slot}()` |
| OptionalRawAttribute | `public OptionalRawAttribute $slot { … }` | nullable raw accessors |
| RawGroupAttribute | `public RawGroupAttribute $slot { … }` | repeated raws |
| SequenceAttribute | `public SequenceAttribute $slot { … }` | `@var SequenceAttribute<…>`; `with{Slot}Validation()`, `add{Content}()`, `remove{Content}ByIndex()`, `get{Content}Unit()`, `get{Slot}s(): XNode[]` |

**Where `XNode` comes from:** it is a Table 2 standalone node materialized as its own
facade class (`class XNode extends Node`), which in turn exposes *its* inner attributes
through the same rows above. A node facade class therefore only ever appears nested
inside another attribute's generic / accessor types — never as the `attributes[i]`
property type itself.

**`≥2` alternatives** (the “Alternatives influence” rule applied here): Node-bearing
rows turn `XNode` into a union `A|B|C` in the generic and in every accessor signature
(see `JsonNode::$value: NodeAttribute<ObjectNode|ArrayNode>`,
`getNodeValue(): ObjectNode|ArrayNode`); Raw rows add a backed enum plus
`get{Slot}Type()` / `set{Slot}(Enum, …)` (`PrimitiveNode` + `PrimitiveType`); Structure
rows are unaffected.

## Formatting, Defaults, and Units — current state (must be refactored before the tree generator can be fixed)

Three separate, currently tangled mechanisms. None of them is fully wired end-to-end. This is the blocker list for the tree generator refactor.

### Formatting

- **`FormatDefinition`** (`Grammar/Definition/FormatDefinition.php`) holds `formatters: Closure(NodeInterface, string $style):void`, set via `addFormatter()`. Constructed empty in `Grammar::__construct()` (`Grammar.php:31`). **No grammar anywhere calls `addFormatter()`.** It's collected into the compiled grammar (`GrammarCompiler.php:114`), but nothing downstream ever invokes a formatter. Dead: wired into compilation, never populated, never executed.
- There is a **second, unrelated** `FormatDefinition`/`ContextDefinition` pair under `src/Foundation/AST/Definition/` (used by `Definition::format()`/`Definition::context()` and `NodeDefinitionCompiler.php`) — a parallel concept with the same names as the Grammar-level one. This naming collision is itself a refactor target.
- **`ContextStack::STYLE`** (`ContextStack.php:15`) defaults to `Defaults::DEFAULT_STYLE` (`'default'`). Set via `Grammar::setStyleResolver()` (`Grammar.php:62-69`, registers a `ContextDefinition` initializer) or directly via `Node::setStyle()` (`Node.php:64`). Read by `Whitespace::withIndentationSupport()` (`Whitespace.php:120`) and `JsonRfc8259::STYLE_PRETTY` (`JsonRfc8259.php:127`).
- **`ContextDefinition`** (Grammar-level) holds `initializers: Closure(NodeInterface $rootNode):void` — this is the live, working half: `setStyleResolver()` and `withIndentationSupport()` both register real initializers here, collected into the compiled grammar (`GrammarCompiler.php:113`).
- **The actual indentation/pretty-print logic is stubbed out.** `Whitespace::withIndentationSupport()` only stores an indent-unit string (`Whitespace.php:125`); the consumer, `indentationResolver()`, is commented out with the body literally `return ''; // TODO` (`Whitespace.php:135-146`). `JsonRfc8259.php` lines 51-72 (the pretty-style whitespace wiring) are likewise commented out. **No code path anywhere actually produces indentation/line-wrapping at parse or render time.**

### Defaults

Confirmed (see prior section, now folded in here): `Defaults::factoryByStyles` (`Defaults.php:20`) is populated only by `Rule::token()`/`Rule::keyword()` (`Rule.php:75,95`), both just returning literal token/keyword text for the `'default'` style. `Rule::withDefaults()` (`Rule.php:327`) and `RegionConfigApi::withDefaults()` (line 95) exist but both call sites are commented out in `JsonRfc8259.php` (lines 51, 60). **Nothing reads `->defaults`/`factoryByStyles` anywhere in compilation or parsing — write-only, dead data.** Its only connection to Formatting is sharing the string constant `Defaults::DEFAULT_STYLE` as `ContextStack::STYLE`'s fallback value — there is no functional link.

### Units (`SequenceAttribute`)

- `withValidSequence()` **is called in production code**, but only from facade methods named `with{Prop}Validation()` — both hand-written (`Infrastructure/Grammar/ParsedTree/Json/Rfc8259/ArrayNode.php`) and generated (`Infrastructure/TreeSchema/Model/Json/{Rfc8259,C}/*.php`). Those facade methods are themselves **only ever invoked from test code** (`tests/Func/Grammar/TreeSchema/JsonRfc8259FacadeBuildTest.php:34,45`), which manually builds a `SequenceValidityCursor` from a compiled grammar region. **No production parsing path (`Parser.php`, the compiler) calls `withValidSequence()` or wires `$autoFactories` from a live parse** — Units only exist for hand-assembled trees in tests, never for a tree produced by actually parsing input.
- `$autoFactories` is populated by the generator: `FacadeClassRenderer.php:482-491` emits `GroupAttribute`/`StructureAttribute` factory closures from `AttributeSchema->structuralFactories` (`Schema/StructuralFactoryInfo.php`).
- `FacadeClassRenderer::renderSequenceAttributeMethods()` (~lines 446-530) does emit Unit-aware methods: `with{Prop}Validation()`, `add{Content}()` (`addUnit`), `remove{Content}ByIndex()` (`removeUnit`), `get{Content}Unit()` (`getUnit`).

### The actual blocker: generator emits a class that doesn't exist

The TreeSchema-generated facades (`Infrastructure/TreeSchema/Model/Json/{Rfc8259,C}/ArrayNode.php`, `ObjectNode.php`, and `JsonRfc8259/*`) declare/import/instantiate **`GroupedAttribute`** (e.g. `new GroupedAttribute('items', null, [])`, `public GroupedAttribute $items`) — but **`GroupedAttribute` does not exist anywhere in the codebase** (renamed to `SequenceAttribute` per commit `3741544`, "changed grouped attribute into sequence attribute"; the generator was never updated). The hand-written equivalent correctly uses `SequenceAttribute`. **This makes current generator output uncompilable** for every Unit-bearing property — almost certainly the central defect to fix first.

No handling of `Defaults`, `FormatDefinition`, or `ContextDefinition`/style was found anywhere in the generator (`NodeSchemaCollector.php`, `FacadeClassRenderer.php`, `FacadeSchemaGenerator.php`, `EnumFileRenderer.php`, `GrammarAugmentor.php`) — Units/`SequenceAttribute` is the only one of the three mechanisms the generator currently touches at all, and it touches it incorrectly (wrong class name).

### Architectural premise for this refactor

**AST does not exist yet — treat it as nonexistent for now.** AST is a layer *above* the Parsed Tree; it is out of scope. The `Foundation/AST/Definition/` `FormatDefinition`/`ContextDefinition` pair is therefore not a "naming collision to resolve" — it's a different layer we're not touching, full stop.

**The Parsed Tree must be self-sufficient**: it has to guard its own consistency itself, without relying on anything from a not-yet-existing AST layer or on external/test-only assembly code. This directly reframes the Units finding above: today, `withValidSequence()`/`$autoFactories` only run when test code or a hand-built facade manually assembles a `SequenceAttribute` after the fact — the actual parse (`Parser.php`/`NodeAttrFactory`) never enforces unit validity itself. Under "Parsed Tree must be self-sufficient," that's backwards: a `SequenceAttribute` produced by parsing real input should already be consistent (valid units, correctly classified structural vs. content attributes) the moment it's built — not consistent only if and when some downstream facade later calls `withValidSequence()` on it.

**Context's role.** Two unrelated things share the word "context" — don't conflate them:

- **`ParsingContext`** (`Parsing/Contract/ParsingContext.php`) is a plain DI container for the *parsing phase itself*: `grammar()`, `nodeFactory()`, `nodeAttrFactory()`, `tokenizationContext()`, `matchingContextForRegion()`. It hands out factories/services so parsing can run. It has nothing to do with the tree's own self-knowledge or consistency.
- **`ContextStack`/`ContextDefinition`** (`Parsing/Model/Context/ContextStack.php`, `Grammar/Definition/ContextDefinition.php`) is the actual "Context" the user means here: a `treeContext` carried *on/with the tree* (currently used for `STYLE`), populated by `initializers` run against the root `Node`. **This** is the layer meant to support the Parsed Tree in staying self-sufficient — it's the mechanism by which the tree can carry knowledge about itself (today: style; potentially: validity-cursor/Unit state) without reaching outside to AST or test/facade code.

So the Unit-consistency fix (punch list item 2 below) should plug into `ContextStack`/`ContextDefinition`, *not* `ParsingContext` — `ParsingContext` is just the service locator that gets the parse running in the first place.

### Refactor punch list (in dependency order)
1. Rename `GroupedAttribute` → `SequenceAttribute` in the generator's emitted code (`FacadeClassRenderer.php`) — unblocks compiling generated facades.
2. Move Unit-consistency enforcement into the Parsed Tree itself, via `ContextStack`/`ContextDefinition` (not `ParsingContext`, which is unrelated DI plumbing): `NodeAttrFactory`/`SequenceAttribute` construction during real parsing should establish `contentOffsets`/structural-vs-content classification directly, carried on the tree's own `ContextStack`, so a parsed `SequenceAttribute` is self-consistent without anyone having to call `withValidSequence()` afterward. The current call sites (test code, hand-built facades) prove the cursor/validation logic works — it just needs to run *during* parsing, sourced from the tree's `ContextStack`, not bolted on after by an outside caller.
3. Decide the fate of `Defaults`/`FormatDefinition` (Grammar-level only — AST-level is out of scope) — both are dead scaffolding with no consumer. Either wire them in (style-aware default values, real formatters) or remove them before the generator is asked to account for them.
