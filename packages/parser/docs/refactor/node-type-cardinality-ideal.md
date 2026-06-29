# NodeType × NodeOrigin × Cardinality — ideal shape (both paths)

## Why this document

`node-type-origin-cardinality.md` documents *current* behavior, which encodes an
inconsistency: the two paths a matched fragment can take handle `NodeType`
differently.

- **Path A — fragment consumed as an attribute of a parent** (Table 1): branches
  on `NodeType` correctly. `match($nodeType)` in
  `NodeAttrFactory::fromTokenRegion/fromMatchedRegion/...` → `Node` recurses, `Raw`
  collapses (`createRawRegionAttribute`), `Structure` → `StructureAttribute`.
- **Path B — fragment materialized as its own standalone Node** (Table 2):
  *ignores* `NodeType`. `fillRegionBasedNodeWithAttributes` /
  `fillTokenBasedNodeWithAttributes` always decompose per element, as if everything
  were `NodeType::Node`.

Result: setting a region to `NodeType::Raw` collapses it on Path A but not on Path
B — e.g. whitespace trivia (forced into a Node wrapper by
`TriviaSequenceNamingMiddleware`) expands one `RawContentAttribute` per space
instead of one collapsed raw attribute.

This document proposes the ideal, *path-symmetric* shape: **`NodeType` means the
same thing on both paths.**

## Governing principle

`NodeType` describes how a fragment's content is represented, independent of path:

- **`Node`** — meaningful internal structure → decompose into child nodes/attributes.
- **`Raw`** — opaque verbatim text → collapse to a single raw attribute; no internal
  decomposition.
- **`Structure`** — a structural delimiter → a single `StructureAttribute`.
- **`Skip`** — omitted entirely.

## Two NodeTypes, one composition rule

Two distinct `NodeType`s participate in any materialization:

1. **The slot's** `NodeType` (how the *parent sequence/region references* this
   fragment) — selects the **Path A** wrapper.
2. **The fragment's own** `NodeType` (the token/region/sequence definition itself) —
   selects the **Path B** internal shape, *but only when the slot wrapped it in a
   Node*.

Composition:

- Slot wrapper is **Raw or Structure** (`RawContentAttribute` / `RawRegionAttribute`
  / `RawSequenceAttribute` / `OptionalRawAttribute` / `RawGroupAttribute` /
  `StructureAttribute`) → **leaf**. The fragment is collapsed inline; **no standalone
  Node is created** (Path B does not run).
- Slot wrapper is **Node-bearing** (`NodeAttribute` / `OptionalAttribute` /
  `GroupAttribute`) → it carries one or more **Nodes**, and each Node is shaped by
  **Path B** using the *fragment's own* `(NodeOrigin, NodeType)`.

This is exactly the trivia case: the slot is forced to `Node` (so we get named
whitespace Nodes in a group), while the fragment (`whitespace` region) is `Raw` (so
each Node's content is one collapsed raw attribute).

## Path A — fragment as parent attribute (unchanged; already correct)

Wrapper is chosen by `(NodeType, Cardinality)`; for `Raw`, the concrete subtype is
chosen by `NodeOrigin`.

| NodeType | Cardinality | Wrapper attribute |
|---|---|---|
| Node | ExactlyOne | NodeAttribute |
| Node | ZeroOrOne | OptionalAttribute |
| Node | ZeroOrMore / OneOrMore | GroupAttribute |
| Raw | ExactlyOne | RawContentAttribute (Token) · RawRegionAttribute (Region) · RawSequenceAttribute (Sequence) |
| Raw | ZeroOrOne | OptionalRawAttribute |
| Raw | ZeroOrMore / OneOrMore | RawGroupAttribute |
| Structure | any | StructureAttribute |
| Skip | any | — (omitted) |

`Node`-bearing wrappers carry Node(s) shaped by Path B. `Raw`/`Structure` wrappers
are leaves.

## Path B — fragment as standalone Node (the fix)

Keyed by `(NodeOrigin, NodeType)`. Cardinality does **not** affect Path B —
multiplicity lives in the Path A wrapper.

| NodeOrigin | NodeType | Materialized Node contains | list type | min..max |
|---|---|---|---|---|
| Token | Node | 1× RawContentAttribute (verbatim) | single | 1..1 |
| Token | Raw | 1× RawContentAttribute | single | 1..1 |
| Token | Structure | 1× StructureAttribute | single | 1..1 |
| Region | Node | group of children — each child resolved by Path A on its own (Origin, Type, Cardinality) | group | 0..∞ |
| **Region** | **Raw** | **1× RawRegionAttribute (collapsed opener/content/closer)** | single | 1..1 |
| **Region** | **Structure** | **1× StructureAttribute (whole region)** | single | 1..1 |
| Sequence | Node | the sequence's slot attributes (SequenceAttribute units for `/g`) | sequence | 1..N |
| **Sequence** | **Raw** | **1× RawSequenceAttribute (collapsed)** | single | 1..1 |
| **Sequence** | **Structure** | **1× StructureAttribute** | single | 1..1 |

Bold rows are the changes versus current behavior (currently all `Region`/`Sequence`
rows behave like the `Node` row — per-element group, ignoring `NodeType`).

Only `Region | Node` (and `Sequence | Node`) stays a multi-child group — that is the
*only* case where internal decomposition is meaningful.

## End-to-end: whitespace trivia

Grammar: `json` root sequence `-* value -*`; `-*` is a whitespace trivia slot.

1. `TriviaSequenceNamingMiddleware` → slot `nodeType = Node`,
   `anchorName = trivia0/1`, cardinality `ZeroOrMore`.
2. **Path A** (slot = Node, ZeroOrMore) → `GroupAttribute "trivia0"` of Nodes.
   ✔ correct now and ideal.
3. Each matched whitespace region → a Node. Fragment's own type: region
   `whitespace` = `(Region, Raw)`.
4. **Path B** — ideal: `Region/Raw` → `Node "inlineWs" (origin Region)` containing
   **1× RawRegionAttribute = "    "**.
   - current (bug): treated as `Region/Node` → group of per-token
     `RawContentAttribute` (one per space).

```
GroupAttribute: trivia0
└─ Node: inlineWs (origin: Region) [NodeType.Raw]
   └─ RawRegionAttribute: inlineWs = "    "      ← ideal (one collapsed)
```

## Code deltas implied (reference, not part of this doc task)

- `NodeAttrFactory::fillRegionBasedNodeWithAttributes` — branch on
  `NodeTypeResolver::resolveNodeType($region)`: `Raw` → one `createRawRegionAttribute`;
  `Structure` → one `StructureAttribute`; `Node` → current per-item loop.
- `NodeAttrFactory::fillTokenBasedNodeWithAttributes` — branch on token type:
  `Structure` → `StructureAttribute`; else `RawContentAttribute`.
- `NodeAttrFactory::fillSequenceBasedNodeWithAttributes` — `Raw` → one
  `createRawSequenceAttribute`; `Structure` → one `StructureAttribute`; `Node` →
  current.
- Update `node-type-origin-cardinality.md` Table 2 to the table above.

## Open decision: RawRegionAttribute vs RawContentAttribute for `Region | Raw`

The ideal table uses **`RawRegionAttribute`** for `Region/Raw` Nodes — symmetric with
Path A and preserving opener/closer for raw regions that have structural delimiters.
For whitespace (no opener/closer) it degenerates to content-only and renders
identically to a `RawContentAttribute`.

The WIP `StructuralDefaultResolver` currently synthesizes the simpler
`RawContentAttribute`
(`new Node($role, NodeOrigin::Region, [new RawContentAttribute($value)])`). To stay
path-symmetric, prefer `RawRegionAttribute`; keep `RawContentAttribute` only if
region roles are guaranteed never to carry opener/closer. **Recommendation:
`RawRegionAttribute`.**
