# What Is a Node?

A `Node` is a **named container of attributes** that represents a parsed fragment of the input string. It sits at the heart of the parse tree and answers two questions: *where does this fragment come from* (its `name`, derived from the matched grammar rule) and *what does it contain* (its ordered list of `NodeAttributeInterface` instances).

## Structure

```
Node
├── name      — which rule produced this node
├── parent    — weak reference to the parent node (null for the root)
├── tags      — rule-level tags propagated from the grammar
├── meta      — arbitrary key-value metadata
└── attributes[]  — ordered list of NodeAttributeInterface
```

## Attributes

Each attribute describes a piece of the node's content with a different semantic:

| Attribute | Meaning |
|---|---|
| `RawContentAttribute` | A raw text leaf — verbatim input characters, no further structure. |
| `StructureAttribute` | A structural marker (keyword, operator, delimiter). Carries a `present` flag and a `content` string. |
| `NodeAttribute` | A named child node — a nested subtree produced by a sub-rule. |
| `OptionalAttribute` | An optional child node. Wraps a `NodeInterface` or `null`. |
| `GroupAttribute` | A list of child nodes — used when a rule matches zero or more repetitions. |

## NodeType controls which attribute is produced

The `NodeType` assigned to a grammar rule determines how the matched token or region is turned into an attribute on its parent node:

| NodeType | Attribute produced |
|---|---|
| `Raw` | `RawContentAttribute` |
| `Structure` | `StructureAttribute` |
| `Node` | `NodeAttribute` wrapping a child `Node` |
| `Skip` | *(nothing — the token is excluded from the tree)* |

`Raw` is the default for token-level rules.

## The lossless contract

`Node.__toString()` concatenates `__toString()` of every attribute in order. Because no input characters are dropped (only infrastructure tokens like `bof`/`eof` are skipped), the result always reconstructs the original input fragment exactly.

## Relation to the AST

The parse tree produced by `Node` is a **lossless concrete syntax tree** — it preserves whitespace, punctuation, and all formatting. A separate AST layer reads selected attributes from this tree and promotes them into typed domain objects, discarding the structural noise it does not need.
