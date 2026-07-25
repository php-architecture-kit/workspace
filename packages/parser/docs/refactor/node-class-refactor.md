# Node class refactor — shape-based subclasses

Companion to `../node-type-origin-cardinality.md` (the spec) and
`node-type-cardinality-ideal.md`. The library is pre-release (no standalone repo yet),
so **breaking changes are acceptable** — no BC shims, no deprecation paths.

## Problem

`Node` is one self-sufficient base class that all semantic facades extend. It fuses two
responsibilities:

1. **Identity / infrastructure** (origin-agnostic): `name`, `origin`, `parent`
   (WeakReference), `contextStack`, `meta`/`tags`, `applyFormatting`. Fine — genuinely
   shared.
2. **Structure / content**: a flat `attributes[]` with generic mutators
   (`addAttribute`, `removeAttributeByOffset/Filter`, `__get` by name).

Responsibility 2 is the lowest common denominator of three genuinely different shapes,
so it is too permissive for some and too unstructured for others — and the missing
semantics get re-imposed from outside (facades indexing `attributes[2]`, validity in
`SequenceAttribute` + external `withValidSequence()`). That outside-patching is the
complexity.

## The three shapes — derived from `(NodeOrigin × NodeType)`, not from origin

| Shape | From (Table 2) | Inner contract |
|---|---|---|
| **Leaf** | `Token/*`, `Region/Raw`, `Region/Structure`, `Sequence/Raw`, `Sequence/Structure` | exactly **1** attribute |
| **Group** | `Region/Node` | **0..∞** children ∈ allowed type set; no order |
| **Sequence** | `Sequence/Node` | **1..N** ordered slots; valid against a compiled sequence; content/structural classified |

`Region` bifurcates (Group vs Leaf by NodeType) and `Sequence/Raw|Structure` collapse to
Leaf — so the axis is **shape**, with `NodeOrigin` kept as a stored property.

## Class hierarchy (inheritance, not composition)

`abstract class AbstractNode implements NodeInterface` owns responsibility 1 + the
common `attributes[]` storage + a `protected insertAttribute()` (the `array_splice`
mechanics) + read helpers + `__toString`. Three concrete subclasses own responsibility
2:

- `LeafNode`
- `GroupNode`
- `SequenceNode` (in `Parsing\Model` — distinct namespace/responsibility from the
  `Grammar` and `Matching` `SequenceNode`; no real conflict)

`NodeFactory` picks the subclass by `(origin × nodeType)` (it already branches by origin
in `createNodeFrom*`). Facades re-parent onto the matching shape (`ArrayNode extends
SequenceNode`, `InlineWsNode extends LeafNode`, …). The old generic `Node` is removed.

The shared sequence machinery (validity cursor, content/structural classification,
units) lives in a **`SequenceCarrier` trait** used by **both** `SequenceNode` and
`SequenceAttribute` — because `SequenceAttribute` is an *attribute*, not a node, so it
cannot inherit from `SequenceNode`; composition/trait is the only way to share. (We keep
`SequenceAttribute`: its role is a nested `/g` sub-run promoted to one anchor-named,
addressable attribute.)

## `addAttribute` — one interface, shape-specific, self-validating & self-completing

`NodeInterface::addAttribute(NodeAttributeInterface, Placement, offset)` keeps one
uniform signature so `NodeAttrFactory` stays trivial (`$node->addAttribute($item)` in a
loop). Behavior is internal and per shape:

| Shape | `addAttribute($attr)` |
|---|---|
| Leaf | already has 1 → **reject** (or explicit `replace`); else insert |
| Group | membership-check the type → **append** |
| Sequence | **auto-complete**: synthesize missing structural slots from `autoFactories` until `$attr` is admissible per the cursor, then insert; if the gap is not autoFactory-fillable → throw |

Sequence auto-complete (== today's `SequenceAttribute::addUnit`, lifted into
`addAttribute`): adding `member2` after `member1` synthesizes `comma`, `trailingWs`,
`leadingWs` from `autoFactories`, then inserts `member2`.

## Two modes — `parsing` vs `creation` (a tree-lifecycle phase, not a call argument)

Auto-complete cannot run during parsing: `autoFactories` produce **context-dependent**
values (`(ContextStack $context, string $style)`), and `style`/`indentUnit`/full-tree
navigation only exist **after** the tree is built and `ContextDefinition` initializers
run on the root. So the two modes need different collaborators:

| | parsing | creation |
|---|---|---|
| input | full ordered stream from the matcher | content only |
| `autoFactories` | not used | required |
| context (style/indent/navigation) | unavailable | available (post-init) |
| cursor validation | redundant (matcher already enforced grammar) — may skip | required |
| `addAttribute` | **append** | **auto-complete + insert** |

The mode is a **phase carried on `ContextStack`** (`parsing` → after
`ContextDefinition`-init → `creation`); `addAttribute` branches on it internally, so the
signature stays uniform and the caller never chooses a mode. A tree built from scratch
via a facade `::create()` starts in `creation` (context must be initialized first).

Consequence: `autoFactories` + cursor + factory-context are **creation-mode
collaborators**, injected at/after context init — not during parsing. External
`withValidSequence()` is removed; unit consistency is established inside the node.

## Navigation — `TreeNavigator` (needed by indentation)

Indentation must read the **value of the previous attribute in global document order**
(the parse-tree analog of the tokenization-time `previousEndedWithNewline`, and what the
dead `Whitespace::indentationResolver()` — `return ''; // TODO` — needs).

- "Previous attribute in global order" ≠ `attributes[offset-1]`: it is the predecessor
  in a document-order leaf traversal — descend into the previous sibling's **last leaf**
  (recurse through `NodeAttribute` → node → last attribute), or ascend to the parent's
  position and recurse. A real traversal, not an index.
- Substrate today is incomplete: nodes have `parent` (WeakRef) but **leaf attributes do
  not store their owner** — `withParent()` is a **no-op** on `RawContentAttribute` /
  `StructureAttribute`. That misleading no-op is removed.
- Recommended: a **node-centric `TreeNavigator`** over `NodeInterface`
  (`previousLeaf(node, offset)`), keeping attributes lean; the formatting walk already
  holds `(node, offset)`. Keep an explicit owner-ref only where already meaningful
  (`SequenceAttribute->parent`, used to resolve `nodeParent`).
- Mutable tree → **do not cache indices**; compute positions lazily (by identity).

Order-aware reachability (parent + siblings + descent to last leaf) is therefore a
**first-class tree requirement**, not an add-on.

## Breaking-change cleanups (enabled by pre-release status)

- Remove the generic `Node`; replace with `AbstractNode` + 3 shape subclasses.
- Trim `NodeInterface` to the genuinely common surface; shape-specific operations live
  on the concrete classes (e.g. no name-`__get`/offset-removal forced onto `Leaf`).
- Delete the no-op `withParent()` from the attribute contract.
- Delete external `withValidSequence()`; move validation into `SequenceNode` /
  `SequenceCarrier`, run at the right phase.

This refactor lands together with the Tree Generator refactor (facades are generated and
currently uncompilable anyway), so facades are regenerated against the new subclasses.

## Open / next

Write-path beyond `addAttribute` — `removeAttribute`, `replaceAttribute`, and the
read-path `getAttribute` — is under analysis (addressing scheme, validity preservation,
mode). To be folded in once converged.
