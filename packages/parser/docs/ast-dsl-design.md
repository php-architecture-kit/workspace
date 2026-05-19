# DSL Design: Grammar Definition z AST Mapping

## Kontekst

Parser jest region-centryczny i event-driven. Obecnie plik gramatyki jak `JsonRfc8259.php` definiuje tokenizację i sekwencje parsowania, ale mapowanie Node Tree → AST wymaga ręcznego pisania zamknięć (`toGraphMapper`/`toTreeMapper`) per każda właściwość. Celem jest DSL, który:
- minimalizuje jawne mapery poprzez strategie semi-automatyczne,
- integruje deklarację AST bezpośrednio w definicji gramatyki,
- jest obustronny (Node Tree ↔ AST) bez duplikacji logiki,
- obsługuje format jako pierwszoklasowy byt – nazwy wariantów w 100% pod kontrolą autora gramatyki.

---

## 1. Składnia tagów na SequenceNode – format poprawny per `SequenceNode::fromString`

Wzorzec (z regex w `SequenceNode::fromString`):
```
[>|<][?]name[+*][anchorName]/tags
```

Przykłady poprawnej składni:
```
value[item]/n          → name='value', anchor='item', NodeType::Node
string[key]/r          → name='string', anchor='key', NodeType::Raw
nameSeparator/s        → name='nameSeparator', NodeType::Structure
-*/f                   → name='-' (trivia), cardinality=ZeroOrMore, tag='f'
value[item]/nf         → name='value', anchor='item', NodeType::Node + tag 'f'
?items                 → name='items', cardinality=ZeroOrOne
beginArray/s           → name='beginArray', NodeType::Structure (redundant jeśli Rule ma już type)
```

Istniejące tagi NodeType i ich domyślna inferencja dla TagBasedStrategy:

| Tag | NodeType | Domyślna rola w AST |
|-----|----------|---------------------|
| `/n` | Node | **Child** (edge name = anchor) |
| `/r` | Raw | **Attribute** (key = anchor) |
| `/s` | Structure | **pominięte** z atrybutów AST |
| `/f` | *(nowy, nie wpływa na NodeType)* | **Format marker** |

### `/f` – mechanika (nowy tag)

`-*/f` parsuje jako: name=`-`, cardinality=ZeroOrMore, tags=['f']. Po przejściu przez `TriviaSequenceNamingMiddleware`:
- `isTriviaNode` sprawdza `alternatives === ['-'] && anchorName === null` → spełnione dla `-*/f`
- Middleware przypisuje `anchorName` (np. `leadingTrivia`, `trailingTrivia`, `inlineTrivia`)
- Tablica `tags` ['f'] jest ZACHOWANA – middleware tylko zmienia `anchorName`

Ostatecznie `-*/f` w sekwencji `"beginArray -*/f ?items -*/f endArray"` staje się:
- `SequenceNode(name='-', cardinality=ZeroOrMore, anchor='leadingTrivia', tags=['f'])`
- `SequenceNode(name='-', cardinality=ZeroOrMore, anchor='trailingTrivia', tags=['f'])`

`Node::formatTrivia(): array` (nowa metoda) zwraca atrybuty, które mają tag `f` w swojej meta:
```php
public function formatTrivia(): array {
    return array_filter($this->attributes, fn($a) => in_array('f', $a->tags));
}
```

Wariant formatu to jednak ZAWSZE deklaracja autora gramatyki przez `Definition::format()` – tag `/f` tylko OZNACZA pozycje w Node dostępne przez `formatTrivia()`. Biblioteka nie generuje żadnych wariantów automatycznie.

---

## 2. Strategie mapowania

### TagBasedStrategy (domyślna)

Kompilator (`NodeDefinitionCompiler`) przechodzi przez skompilowaną sekwencję i generuje bidirectional mappery automatycznie z tagów + anchor names.

`Definition::attribute()` i `Definition::child()` służą do:
1. nadania typu PHP (`'string'`, `'float'`, `'bool'`)
2. opcjonalnej zamiany nazwy klucza/krawędzi gdy różni się od anchor
3. oznaczenia opcjonalności
4. jawnego `$from`/`$to` gdy auto-inferencja nie wystarcza

```php
// Sekwencja:
"string[key]/r -* nameSeparator/s -* value[value]/n"
// TagBasedStrategy:
//   anchor 'key'   + /r → Attribute('key', ...)
//   /s             → pominięte
//   anchor 'value' + /n → Child('value', ...)

->asAstNode('Member',
    Definition::attribute('key', 'string'),
    Definition::child('Value', 'value'),
)
```

**Bidirectionality auto-generowana:**
- `toGraphMapper`: `fn($node) => $node->attributes['key']->content` (via anchor)
- `toTreeMapper`: `fn($node, $value) => ...` (odtworzenie węzła na pozycji anchor='key')

### PathBasedStrategy

Gdy brak anchor names w sekwencji:

```php
->asAstNode('Member',
    strategy: MappingStrategy::PathBased,
    Definition::attribute('key', 'string', path: 'string'),
    Definition::child('Value', 'value', path: 'value'),
)
```

`path` to nazwa węzła lub notacja `a.b.c` dla zagnieżdżonych.

---

## 3. Definition Builder API

### Modyfikacje istniejących metod

```php
Definition::attribute(
    string $name,
    string $type,
    bool $optional = false,
    ?string $defaultValue = null,
    ?string $path = null,       // PathBasedStrategy
    ?Closure $from = null,      // fn(Node $node): mixed – override toGraphMapper
    ?Closure $to = null,        // fn(Node $node, mixed $value): void – override toTreeMapper
): AttributeDefinition

Definition::child(
    string $name,               // typ docelowego AST Node
    string $edgeName,           // = anchor name jeśli TagBased
    EdgeType $edgeType = EdgeType::Structural,
    bool $optional = false,
    ?string $path = null,
    ?Closure $from = null,
    ?Closure $to = null,
): ChildDefinition
```

### Nowe metody

```php
// Wiele children z powtarzalnej pozycji (GroupAttribute)
Definition::children(
    string $name,               // typ każdego dziecka
    string $edgeName,           // anchor name
    EdgeType $edgeType = EdgeType::Structural,
    ?string $path = null,
    ?Closure $from = null,      // fn(Node $node): iterable<Node>
    ?Closure $to = null,
): ChildDefinition

// Format variant – ZAWSZE jawny, nazwy user-defined
Definition::format(
    string $variantName,        // całkowicie user-defined
    ?Closure $detect = null,    // fn(Node $node): bool – czy wariant pasuje do Node
    array $values = [],         // ['key' => fn(?Node $node): mixed]
                                //   $node === null gdy tworzy się nowy AST node (nie parsowany)
                                //   → closure powinna zwrócić wartość domyślną
    bool $default = false,      // true → fallback gdy żaden detect nie pasuje ORAZ
                                //         domyślny wariant przy tworzeniu nowego AST node
    ?Closure $reconstruct = null, // fn(AstNode $a, NodeDefinition $def): Node
                                  // potrzebny gdy wariant pochodzi z innej Rule niż inne warianty
): FormatVariantDefinition

Definition::reference(
    string $name,
    string $edgeName,
    EdgeType $edgeType = EdgeType::Semantic,
    ?Closure $locate = null,    // fn(AstNode $node, AstGraph $graph): ?AstNode
): ReferenceDefinition
```

### `asAstNode` z opcjonalną strategią

```php
->asAstNode(
    string $name,
    MappingStrategy $strategy = MappingStrategy::TagBased,
    AstDefinitionInterface ...$definitions,
)
```

---

## 4. Format

`AstNodeFormat` przechowuje który wariant zastosowano i wartości wariantu.

### Detekcja wariantu podczas parsowania (Node Tree → AST)

`AstGraphFactory` dla danego Node'a wywołuje `detect` closures **tylko z DefinitionSource odpowiadającego temu konkretnego rule/region**. Pierwsze zamknięcie zwracające `true` → wybrany wariant. Jeśli żaden nie pasuje → wariant z `default: true`.

W gramatyce z jednym rule na jeden typ AST Node (np. JSON Array) detect closures rozróżniają warianty przez inspekcję trivia:

```php
Definition::format('inline',
    default: true,                                   // fallback + domyślny przy tworzeniu
    detect: fn(Node $n) => empty($n->formatTrivia()),
),
Definition::format('multiline',
    detect: fn(Node $n) => !empty($n->formatTrivia()),
    values: [
        // ?Node: gdy null → nowy node (nie parsowany) → zwróć wartość domyślną
        'indent' => fn(?Node $n) => $n !== null
            ? extractLeadingSpaces($n->formatTrivia()[1] ?? null)
            : '  ',
    ],
),
```

### Wartości domyślne w `values`

Closures w `values` przyjmują `?Node` – gdy `$n === null`, node jest tworzony programowo (nie pochodzi z parsowania), closure powinna zwrócić rozsądną wartość domyślną:

```php
// Typowy wzorzec:
'indent' => fn(?Node $n) => $n !== null ? parseIndent($n) : '    ',
'trailingComma' => fn(?Node $n) => $n !== null ? detectTrailingComma($n) : false,
```

Format variant z `default: true` jest stosowany automatycznie gdy:
1. Tworzy się nowy `AstNode` (np. przez API grafu, nie z parsowania)
2. Żaden `detect` nie zwrócił `true` podczas parsowania (fallback – błąd definicji, ale recoverable)

### Rekonstrukcja (AST → Node Tree)

`NodeTreeFactory` czyta `astNode.format.variantName` i wywołuje `reconstruct` closure wybranego wariantu. Jeśli `reconstruct` nie podano, domyślnie stosuje się `toTreeMapper` dla każdego atrybutu/dziecka (wystarczające dla tego samego syntactic shape z różnym formatowaniem).

---

## 5. Dwa Rules → jeden AST Node

Przypadek: dwie całkowicie różne struktury syntaktyczne mapują na ten sam typ AST Node z różnym wariantem formatu.

**Przykład** (YAML: flow vs block array):
```
flow:  [1, 2, 3]
block: - 1\n- 2\n- 3
```

**W DSL:**
```php
// Wariant flow
Rule::token("beginFlow", "[")
    ->startRegion('flowArray')
    ->asAstNode('Array',
        Definition::children('Item', 'item'),
        Definition::format(
            'flow',
            detect: fn(Node $n) => true, // jedyna reguła tego source → zawsze true
            reconstruct: fn(AstNode $a, NodeDefinition $def): Node => /* buduje Node z flowArray region */,
        ),
    ),

// Wariant block
Rule::taggedWith('_blockListItem')
    ->startRegion('blockArray')
    ->asAstNode('Array',      // ten sam name = merge w NodeDefinitionCompiler
        Definition::children('Item', 'item'),
        Definition::format(
            'block',
            detect: fn(Node $n) => true,
            reconstruct: fn(AstNode $a, NodeDefinition $def): Node => /* buduje Node z blockArray region */,
        ),
    ),
```

### Jak `NodeDefinitionCompiler` scala dwa sources

1. Zbiera wszystkie `DefinitionSource` z tym samym `$name` ('Array')
2. Scala `attributes` + `children` + `contexts` + `references` (merge po name, konflikty → błąd kompilacji)
3. Format variants: każdy source wnosi swoje własne warianty → finalna lista zawiera oba: `['flow', 'block']`
4. Każdy FormatVariantDefinition zachowuje referencję do swojego `sourceName` (np. 'flowArray')

### Jak `AstGraphFactory` wybiera wariant

```
1. Przetwarzany Node pochodzi z regionu 'flowArray'
2. Factory szuka FormatVariantDefinitions gdzie sourceName == 'flowArray'
3. Wywołuje detect dla tych wariantów (w tym przypadku jeden → 'flow')
4. Pierwszy true → variantName = 'flow'
```

### Jak `NodeTreeFactory` rekonstruuje syntaktykę

```
1. AstNode ma format.variantName = 'block'
2. Factory szuka FormatVariantDefinition 'block' → wywołuje reconstruct
3. reconstruct buduje Node Tree z blockArray region ze zrekonstruowanymi Items
```

W przypadku gdy warianty różnią się tylko formatowaniem (np. JSON inline vs multiline – ta sama `[...]` struktura, inne whitespace), `reconstruct` nie jest potrzebny – wystarczą `toTreeMapper` atrybutów i dzieci.

---

## 6. Bidirectionality – konwencja

Każda deklaracja `Definition::*` generuje parę mapperów. `$from` i `$to` zawsze podawane razem lub wcale (walidacja przy kompilacji).

```php
// Auto (TagBasedStrategy + anchor + tag)
Definition::attribute('key', 'string'),

// Jawny override
Definition::attribute('numericValue', 'float',
    from: fn(Node $n) => (float) ($n->anchor('integer')->raw()
                        . ($n->anchor('frac')?->raw() ?? '')),
    to: fn(AstNode $a, float $v) => /* rekonstrukcja integer + frac */,
),
```

---

## 7. MissingDefinitions – debug helper

`missingDefinitions` w `NodeDefinition` zbiera węzły sekwencji:
- bez tagu `/s` i nie będące trivia (`-*`)
- niepokryte żadnym `Definition::attribute()`, `Definition::child()`, `Definition::children()`
- tag `/f` nie powoduje wpisania do MissingDefinitions (trivia formatowe są intentional)

---

## 8. Przepisany JsonRfc8259.php – przykład docelowy

```php
public function grammar(): Grammar
{
    $grammar = parent::grammar();

    $jsonText = (new Region("json"))
        ->setInheritanceFromGlobal()
        ->withRootSequence("-* value -*");

    $grammar->global->add(
        $jsonText,

        // ── ARRAY ─────────────────────────────────────────────────────────
        Rule::token("beginArray", "[", type: NodeType::Structure)
            ->startRegion('array')
            ->enableInheritanceFromGlobal()
            ->add(
                Rule::token("valueSeparator", ",", type: NodeType::Structure),
                Rule::seq("itemContinuation", "-* valueSeparator/s -* value[item]/n"),
                Rule::seq("items", "value[item]/n itemContinuation*"),
            )
            ->withRootSequence("beginArray -*/f ?items -*/f endArray")
            ->closeWith(Rule::token("endArray", "]", type: NodeType::Structure))
            ->addTag("value")
            ->asAstNode('Array',
                Definition::children('Item', 'item'),
                Definition::format(
                    'inline',
                    default: true,
                    detect: fn(Node $n) => empty($n->formatTrivia()),
                ),
                Definition::format(
                    'multiline',
                    detect: fn(Node $n) => !empty($n->formatTrivia()),
                    values: [
                        'indent' => fn(?Node $n) => $n !== null
                            ? extractLeadingSpaces($n->formatTrivia()[1] ?? null)
                            : '  ',
                    ],
                ),
            ),

        // ── OBJECT ────────────────────────────────────────────────────────
        Rule::token("beginObject", "{", type: NodeType::Structure)
            ->startRegion('object')
            ->enableInheritanceFromGlobal()
            ->add(
                Rule::token("nameSeparator", ":", type: NodeType::Structure),
                Rule::token("valueSeparator", ",", type: NodeType::Structure),

                Rule::seq("member", "string[key]/r -* nameSeparator/s -* value[value]/n")
                    ->asAstNode('Member',
                        Definition::attribute('key', 'string'),
                        Definition::child('Value', 'value'),
                    ),

                Rule::seq("memberContinuation", "-* valueSeparator/s -* member"),
                Rule::seq("members", "member memberContinuation*"),
            )
            ->withRootSequence("beginObject -*/f ?members -*/f endObject")
            ->closeWith(Rule::token("endObject", "}", type: NodeType::Structure))
            ->addTag("value")
            ->asAstNode('Object',
                Definition::children('Member', 'member'),
                Definition::format(
                    'inline',
                    default: true,
                    detect: fn(Node $n) => empty($n->formatTrivia()),
                ),
                Definition::format(
                    'multiline',
                    detect: fn(Node $n) => !empty($n->formatTrivia()),
                ),
            ),

        // ── PRIMITIVE ─────────────────────────────────────────────────────
        Rule::choice("primitive", ["false", "null", "true", "number", "string"], tags: ["value"])
            ->asAstNode('Primitive',
                // choice jest nieregularny – brak anchor/tagu do inferencji
                Definition::attribute('kind', 'string',
                    from: fn(Node $n) => $n->attributes[0]->getName(),
                    to: fn(AstNode $a, string $v) => null,
                ),
            ),

        Rule::keyword("null"),
        Rule::keyword("false"),
        Rule::keyword("true"),

        // ── STRING ────────────────────────────────────────────────────────
        // NodeType::Raw → cała zawartość to RawRegionAttribute
        // brak asAstNode() → string nie jest samodzielnym AST Node, jest Attribute Member.key
        Rule::token("doubleQuote", "\"", type: NodeType::Structure)
            ->startRegion("string", true)
            ->add(
                Rule::expr("escapeChar", "\\\\[bfnrt\\\\\\\"]")->priority(1),
                Rule::expr("unescaped", "[^\\x00-\\x1F\\x22\\x5C]+"),
                Rule::expr("escapeUnicode", "\\\\u[0-9a-fA-F]{4}"),
            )
            ->setNodeType(NodeType::Raw)
            ->closeWith(Rule::token("doubleQuote", "\"", type: NodeType::Structure)),

        // ── NUMBER ────────────────────────────────────────────────────────
        Rule::token("decimalPoint", ".", tags: ["_number_part"]),
        Rule::token("plus", "+", tags: ["_number_part"]),
        Rule::token("minus", "-", tags: ["_number_part"]),
        Rule::token("zero", "0", tags: ["_number_part"]),
        Rule::expr("digit19", "[1-9]", tags: ["_number_part"]),
        Rule::expr("e", "[eE]", tags: ["_number_part"]),
        Rule::taggedWith("_number_part")
            ->startRegion("number", true)
            ->add(
                $this->addNodeTypeSetupForRules(NodeType::Raw),
                Rule::token("decimalPoint", ".", tags: ["_number_part"]),
                Rule::token("plus", "+", tags: ["_number_part"]),
                Rule::token("minus", "-", tags: ["_number_part"]),
                Rule::token("zero", "0", tags: ["_number_part"]),
                Rule::expr("digit19", "[1-9]", tags: ["_number_part"]),
                Rule::expr("e", "[eE]", tags: ["_number_part"]),
                Rule::seq("digit", "zero|digit19"),
                Rule::seq("digit19Seq", "digit19 digit*"),
                Rule::seq("exp", "e ?minus|plus digit+"),
                Rule::seq("integer", "zero|digit19Seq"),
                Rule::seq("frac", "decimalPoint digit+"),
            )
            ->withRootSequence("?minus/r integer/r ?frac/r ?exp/r")
            ->setNodeType(NodeType::Raw)
            ->closeWith(Rule::taggedWith("_number_part"), true, false),
    );

    $grammar->setRootRegion($jsonText);
    return $grammar;
}
```

---

## 9. Zmiany kompilatorowe

| Plik | Zmiana |
|------|--------|
| `Foundation/Grammar/Definition/Definition.php` | `Definition::children()`, `Definition::format()` z `detect`, `values`, `default`, `reconstruct`; `$path`, `$from`, `$to` w attribute/child |
| `Foundation/AST/Definition/FormatVariantDefinition.php` | Wypełnić stub: `variantName`, `sourceName`, `detect`, `values`, `default`, `reconstruct` |
| `Foundation/AST/Definition/MappingStrategy.php` | Nowe enum: `TagBased`, `PathBased` |
| `Foundation/AST/Definition/Compiler/NodeDefinitionCompiler.php` | TagBasedStrategy + PathBasedStrategy; merge wielu sources (ten sam name); zbieranie MissingDefinitions |
| `Foundation/Grammar/Definition/Model/Sequence/SequenceNode.php` | Obsługa tagu `/f` (nie wpływa na `nodeType`, zapisywany w `$tags`) |
| `Foundation/Parsing/Model/Node.php` | Metoda `formatTrivia(): array` – filtruje atrybuty po tagu 'f' w meta |
| `Foundation/AST/Factory/AstGraphFactory.php` | Implementacja: iteracja po NodeDefinition, sourceName-aware wybór format variant, wywołanie mapperów |
| `Foundation/AST/Factory/NodeTreeFactory.php` | Implementacja: AstGraph → parse tree, wybór reconstruct closure per format variant |

### Logika TagBasedStrategy w NodeDefinitionCompiler

```
1. Wczytaj DefinitionSource { Definition, sourceName, rootSequence }
2. Przejdź przez compiled SequenceNodes rootSequence
3. Dla każdego SequenceNode:
   a. Tag /s lub trivia bez /f → pomiń (nie do MissingDefinitions)
   b. Tag /f → trivia formatowe (nie do MissingDefinitions, dostępne przez formatTrivia())
   c. Tag /r + anchor → szukaj Definition::attribute gdzie name == anchor
   d. Tag /n + anchor → szukaj Definition::child|children gdzie edgeName == anchor
   e. Brak pokrycia → MissingDefinition
4. Zbierz FormatVariantDefinitions → przypisz sourceName do każdej
5. Zwróć NodeDefinition z missingDefinitions
```

---

## 10. Kształt AstNode

```
AstNode
├── NodeId $id                         (autogenerowany UUID)
├── string $name                       (z asAstNode('Name', ...))
├── array<string,mixed> $attributes    (np. ['key' => 'userId'])
├── AstNodeFormat $format              (variantName + values zdefiniowane w grammar)
└── Edges:
    ├── Children (Structural edges)
    └── References (Semantic edges)
```

---

## 11. Weryfikacja poprawności wdrożonego kodu

### Poziom 1 – istniejące testy regresyjne (muszą przejść bez zmian)

```bash
./vendor/bin/phpunit --group unit
./vendor/bin/phpunit --group feature

# Kluczowe smoke testy
./vendor/bin/phpunit --filter parser_parse_command_works_for_json
./vendor/bin/phpunit --filter parser_ast_definition_compiled_command_works_for_json
```

### Poziom 2 – nowe testy jednostkowe

**`SequenceNodeTest`** – rozszerzenie istniejącego:
```php
#[Test]
public function shouldParseFormatTagWhenFromString(): void
{
    $node = SequenceNode::fromString('-*/f');
    self::assertSame(['-'], $node->alternatives);
    self::assertSame(Cardinality::ZeroOrMore, $node->cardinality);
    self::assertContains('f', $node->tags);
    self::assertNull($node->anchorName); // middleware nada anchor później
}

#[Test]
public function shouldReconstructFormatTagWhenToString(): void
{
    $node = SequenceNode::fromString('-*/f');
    self::assertSame('-*/f', $node->toString());
}
```

**`NodeDefinitionCompilerTest`**: TagBasedStrategy auto-generuje AttributeDefinition/ChildDefinition z anchor+tag; merge dwóch sources; MissingDefinitions dla niepokrytych węzłów; `default: true` jako fallback.

### Poziom 3 – weryfikacja CLI

```bash
# Skompilowane definicje AST – powinny zawierać Array, Object, Member z FormatVariants
bin/console parser:ast:definition --compiled \
  'PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonRfc8259'

# Node Tree nie może się zmienić po refactorze gramatyki
bin/console parser:parse \
  assets/parser-source-files/json/json-rfc8259.minified.json \
  'PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonRfc8259'

# Missing Definitions powinny być puste
bin/console parser:ast:definition --compiled \
  'PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonRfc8259'
# Sekcja "Missing Definitions" = pusta → wszystkie węzły pokryte
```

### Poziom 4 – AstGraphFactory (po implementacji)

```bash
# Nowa komenda (do stworzenia)
bin/console parser:ast:graph \
  assets/parser-source-files/json/json-rfc8259.pretty.json \
  'PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonRfc8259'
# Oczekiwane: AstNode 'Object' format.variantName='multiline', dzieci Member
```

---

## 12. Otwarte pytania

1. **`detect: fn(Node $n) => true`** dla wariantów z różnych Rules – czy `sourceName` jest wystarczający do identyfikacji, czy detect musi być zawsze jawne?

2. **`Node::formatTrivia()`** – w `Node.php` (parses layer) czy w osobnym helperze? `Node` jest świadomy tagów (`TagsTrait`), ale atrybuty mają tagi w `meta` – trzeba ustalić, jak tag `/f` z SequenceNode trafia na atrybut.

3. **Merge conflicts** – co jeśli dwa sources dla tego samego AST Node mają `Definition::attribute('key')` o różnym typie? Błąd kompilacji czy last-wins?

4. **Strategy scope** – globalna domyślna (`TagBased`) czy per-region override? Propozycja: domyślna global, override przez `asAstNode(strategy: ...)`.
