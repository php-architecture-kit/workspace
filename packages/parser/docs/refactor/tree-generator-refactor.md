# Tree Generator refactor — emit facades per the documented rules

Implements the facade-class rules from `../node-type-origin-cardinality.md`
("Facade form — first by the node's NodeOrigin, then per attribute" + "Alternatives
influence") into the generator, and unblocks it (it is currently uncompilable).

## Active pipeline (what to change)

`parser:tree:generate` → `FacadeSchemaGenerator` →
`NodeSchemaCollector` (collect `NodeSchema`/`AttributeSchema` from parsed example
trees) → `GrammarAugmentor` (augment from compiled grammar) → `FacadeClassRenderer`
(emit PHP) + `EnumFileRenderer` (enum per raw choice).

`TreeSchemaGenerator` (the other class, Renderer/Template-based) is **unused** by the
command and references stale namespaces — **delete it** (and `Renderer/Template/*` if
nothing else uses them).

## What already matches the rules (keep)

- **Property form**: `renderPropertyHooks()` emits `public <AttrClass> $prop { get =>
  $this->attributes[i]; }` — property typed as the **attribute class**, exactly the
  documented "per named slot / single attribute" rule. ✔
- **Alternatives → member shape**: `renderNodeAttributeMethods`/`...Optional`/`...Group`
  emit `getNode{P}()/setNode{P}()`/`addNodeTo{P}()...` typed as the **node class
  union**; `renderChoiceRawMethods` emits a **backed enum** (`{Prop}Type`) +
  `get{P}Type()/set{P}()`; `StructureAttribute` adds nothing. Matches Node→union,
  Raw→enum, Structure→none. ✔
- **Sequence unit methods**: `renderSequenceAttributeMethods` emits
  `with{P}Validation/add{C}/remove{C}ByIndex/get{C}Unit/get{Plural}`. ✔

## Gaps to fix

### 1. Stale attribute namespaces (root cause of "uncompilable")
`Schema/AttributeSchema.php` and `Schema/StructuralFactoryInfo.php` import the **old**
flat namespaces (`Foundation\Parsing\Model\Attribute\{GroupAttribute,NodeAttribute,
RawContentAttribute,RawRegionAttribute,StructureAttribute,...}`). The current classes
live under `…\Attribute\Node\*`, `…\Attribute\Raw\*`, `…\Attribute\Structure\*`. So
`isGroupAttribute()`/`isRawContentAttribute()`/… compare a runtime `$attribute::class`
(new FQCN) against the **old** FQCN → always false; emitted `use`/type names are
wrong → output uncompilable.
- Fix: update every attribute-class reference (schema models, `NodeSchemaCollector`,
  `FacadeClassRenderer`) to the current sub-namespaced FQCNs. (`SequenceAttribute`,
  `OptionalRawAttribute`, `RawGroupAttribute`, `RawSequenceAttribute` too where used.)
- Drop the `GroupedAttribute` name entirely — the class is `SequenceAttribute`
  (already used in code; only stale committed output and comments say "Grouped").

### 2. Base class by shape (the new rule)
`FacadeClassRenderer::render()` hardcodes `$imports[] = Node::class;` (line 41) and
`class X extends Node` (line 80). Replace with the **shape** base derived from the
node's `(NodeOrigin × NodeType)` — i.e. the same class the runtime now materializes:
`LeafNode` / `GroupNode` / `SequenceNode`.
- Simplest source of truth: the collector already walks **real parsed nodes**, which
  are now shape instances. Capture the shape on `NodeSchema` (e.g.
  `public string $baseClass`) from `get_class($node)` (one of the three shapes), and
  have the renderer emit `extends <shortName(baseClass)>` + import it.
- Facade then `class ArrayNode extends SequenceNode`, `InlineWsNode extends LeafNode`,
  a comment node `extends GroupNode`, etc. — matching `nodeClassMap` overrides.

### 3. content/structural from the `/c` marker, not a sample-parse guess
`NodeSchemaCollector` (~180-234) picks the GroupedAttribute content node by matching
`ANCHOR_NAME_META_KEY` against the example tree. Replace with the deterministic
`/c` marker: an inner attribute of a `SequenceAttribute` is **content** iff it carries
`SequenceAttribute::CONTENT_TAG` (stamped at compile time); everything else is
structural (→ `structuralFactories`). No dependence on a representative sample.

## Out of scope here
- Parse-time unit wiring / Defaults auto-complete (separate roadmap; the generated
  facades wrap an already-shaped tree and only add typing + edit helpers).
- Removing the `Node` shim — do it once generated facades extend shapes (this task)
  AND the AST layer no longer references `Node`.

## Verification
1. Regenerate: `bin/console parser:tree:generate <input> <Grammar> --namespace …` for
   JsonRfc8259 and JsonC; `php -l` every emitted file (must be compilable).
2. Generated facades `extends LeafNode|GroupNode|SequenceNode`; property types use
   current namespaces; no `GroupedAttribute`.
3. Facade test suites currently in the failing baseline go green:
   `JsonRfc8259FacadeNodeTest`, `JsonRfc8259FacadeBuildTest`, `JsonCFacadeNodeTest`
   (+ `JsonRfc8259FacadeBuildTest` build/round-trip). Full phpunit: failing count
   drops; **zero** previously-green tests regress.
4. Parser/lossless untouched (generator does not touch the parse path) — `parser:parse`
   snapshots stay byte-identical.
