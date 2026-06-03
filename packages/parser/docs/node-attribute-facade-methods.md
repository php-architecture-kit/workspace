# Metody fasad dla atrybutów node'ów

Dokument opisuje, jakie metody powinien generować generator fasad dla każdego typu atrybutu.
Struktura: opis atrybutu → rozważanie metod → źródła danych → przykład kodu.

Konwencja nazw w przykładach: `{prop}` = nazwa właściwości (anchorName ?? name), `{Node}` = union type node'ów.

**Dwa źródła danych:**
- **Output parsowania** (wiele plików źródłowych, zmergowany) — source of truth. Mówi co faktycznie wystąpiło: jakie atrybuty na jakich pozycjach, jakie typy, jakie wartości.
- **CompiledGrammar** — rozszerza output parsowania o warianty nieobecne w plikach źródłowych (np. typy, które są dopuszczalne przez gramatykę, ale nie pojawiły się w przykładach). Dostarcza też informacje strukturalne niedostępne w drzewie parsowania.

**Pochodzenie reguły (`Rule::META_ORIGIN`):**

Każda reguła w CompiledGrammar nosi `GrammarOrigin(format, variant)` (np. `("technical", "whitespace")` albo `("json", "rfc8259")`). Gramatyki mogą dziedziczyć reguły przez PHP extends — `JsonRfc8259 extends Whitespace` — a `stampOrigin` z `overwriteExisting = false` zachowuje oryginalny origin dziedziczonych reguł.

**Reguła generatora (na razie):** warianty tego samego formatu są separowane w całości — każdy wariant dostaje kompletny, niezależny zestaw fasad we własnym namespace'ie. Nie importują klas fasad między sobą, nawet jeśli `JsonC extends JsonRfc8259`. Gramatyki z **innego formatu** pełniące rolę współdzielonej infrastruktury (np. `technical/whitespace`) mają już swoje fasady i są zawsze importowane, nigdy duplikowane.

Zasada: przed wygenerowaniem klasy fasady sprawdź `META_ORIGIN.format`:
- **`META_ORIGIN.format === generatedGrammar.format`** (ten sam format, dowolny wariant) → generuj nową klasę fasady w namespace'ie docelowej gramatyki
- **`META_ORIGIN.format !== generatedGrammar.format`** (inny format — współdzielona infrastruktura) → importuj istniejącą klasę fasady z namespace'u tamtej gramatyki; **nie generuj duplikatu**

**Konwencja namespace'ów i katalogów:**

Ścieżka fasad: `Infrastructure/Grammar/ParsedTree/{Format}/{Variant}/`

Format i wariant są zapisywane w PascalCase. Znaki niebędące literą ani cyfrą (kropki, myślniki itp.) są usuwane. Jeśli po tym zabiegu wynikowy string zaczyna się od cyfry, poprzedza się go `Ver`:

| Gramatyka | Ścieżka |
|---|---|
| `json/rfc8259` | `ParsedTree/Json/Rfc8259/` |
| `json/c` | `ParsedTree/Json/C/` |
| `json/5` | `ParsedTree/Json/Ver5/` |
| `yaml/1.1.0` | `ParsedTree/Yaml/Ver110/` |
| `env/dotenv` | `ParsedTree/Env/Dotenv/` |
| `env/environment` | `ParsedTree/Env/Environment/` |
| `technical/whitespace` | `ParsedTree/Technical/Whitespace/` |

Bez tej zasady każda gramatyka dziedzicząca np. `technical/whitespace` (`JsonRfc8259`, `JsonC`, `JsonVer5`, `EnvDotenv`, …) generowałaby własne kopie `LeadingWsNode`, `TrailingWsNode`, itd.

---

## Konwencja nazewnicza metod

Schemat: `{akcja}{Byt}{Przyimek}{Prop}`

| Element | Wartości | Uwagi |
|---|---|---|
| `{akcja}` | `get`, `set`, `add`, `remove`, `with` | |
| `{Byt}` | `Node` / `Nodes`, `Raw`, `Unit`, nazwa klasy content-elementu | patrz tabela poniżej |
| `{Przyimek}` | `To` (add), `From` (get/remove), brak (set/with) | |
| `{Prop}` | `anchorName` atrybutu, a jeśli brak — `name` | **anchorName ma zawsze pierwszeństwo** |

**Dobór `{Byt}` w zależności od typu atrybutu:**

| Typ atrybutu | `{Byt}` |
|---|---|
| `NodeAttribute` | `Node` |
| `OptionalAttribute` | `Node` |
| `GroupAttribute` (pojedyncza operacja) | `Node` |
| `GroupAttribute` (get kolekcji) | `Nodes` |
| `ChoiceAttribute` — choices to NodeInterface | `Node` |
| `ChoiceAttribute` — choices to raw | brak — metody nazywane bezpośrednio od `{Prop}` (np. `set{Prop}`, `get{Prop}Type`, `get{Prop}Content`) |
| `GroupedAttribute` — add/remove/getUnit | nazwa klasy content-elementu (np. `Member`, `Item`) |
| `GroupedAttribute` — getAll | nazwa klasy content-elementu w liczbie mnogiej (np. `Members`, `Items`) |
| `GroupedAttribute` — withValidation | brak bytu — `with{Prop}Validation` |
| `RawContentAttribute` / `RawRegionAttribute` | `Raw` |

**Przykłady:**

```
addNodeToTrivia0        → add + Node     + To   + trivia0   (GroupAttribute, add)
getNodesFromTrivia0     → get + Nodes    + From + trivia0   (GroupAttribute, get kolekcji)
removeNodeFromTrivia0ByOffset  → remove + Node + From + trivia0
getNodeValue            → get + Node     +       + value    (ChoiceAttribute nodes, brak przyimka)
setNodeValue            → set + Node     +       + value
removeNodeValue         → remove + Node  +       + value
addMemberToMembers      → add + Member   + To   + members   (GroupedAttribute)
getMembersFromMembers   → get + Members  + From + members
getMemberUnitFromMembers→ get + MemberUnit + From + members
withMembersValidation   → with +          +       + members + Validation
getRawIdentifier        → get + Raw      +       + identifier  (anchorName='identifier', name='string')
setRawIdentifier        → set + Raw      +       + identifier
setPrimitive            → set +           +       + primitive  (ChoiceAttribute raws)
getPrimitiveType        → get +           +       + primitive + Type
getPrimitiveContent     → get +           +       + primitive + Content
```

---

## `NodeAttribute`

Opakowuje pojedynczy **wymagany** `NodeInterface`. Cardinality = 1 — `$node` nigdy nie jest `null`.

### Metody

**getter** — zawsze. Zwraca `NodeInterface` pod typowanym union type.

**setter** — zawsze. Przyjmuje node, ustawia parent, deleguje do `NodeAttribute::fromNode()`.

**remove** — nigdy. Cardinality = 1 nie dopuszcza braku wartości.

### Źródła danych

| Informacja | Skąd |
|---|---|
| Nazwa właściwości (`{prop}`) | output parsowania — `anchorName ?? name` atrybutu |
| Typ node'a w union type | output parsowania — konkretna klasa node'a na tej pozycji |
| Potwierdzenie cardinality = 1 | CompiledGrammar — atrybut nie jest opakowany w opcjonalną sekwencję |
| Origin node'a | CompiledGrammar — `Rule::META_ORIGIN.format`; jeśli różny od formatu generowanej gramatyki → importuj fasadę z namespace'u tamtej gramatyki; jeśli ten sam format (dowolny wariant) → generuj nową klasę w namespace'ie docelowej gramatyki |

```php
// --- property hook (generowany zawsze) ---
public NodeAttribute $value { get => $this->attributes[1]; }

// --- getter ---
public function getNodeValue(): ObjectNode|ArrayNode|PrimitiveNode
{
    /** @var NodeAttribute $attribute */
    $attribute = $this->value;
    return $attribute->node;
}

// --- setter ---
public function setNodeValue(ObjectNode|ArrayNode|PrimitiveNode $value): self
{
    $this->value = NodeAttribute::fromNode($value->setParent($this));
    return $this;
}
```

---

## `OptionalAttribute`

Opakowuje pojedynczy **opcjonalny** `NodeInterface`. Cardinality = 0..1 — `$node` może być `null`.

### Metody

**getter** — zawsze. Zwraca `NodeInterface|null`.

**setter** — zawsze.

**remove** — zawsze. Cardinality = 0..1 jest konstytutywna dla tego typu — brak wartości jest legalny.

### Źródła danych

| Informacja | Skąd |
|---|---|
| Nazwa właściwości (`{prop}`) | output parsowania — `anchorName ?? name` atrybutu |
| Typ node'a | output parsowania — konkretna klasa node'a gdy atrybut jest obecny |
| Potwierdzenie opcjonalności | sam fakt bycia `OptionalAttribute` — cardinality 0..1 jest konstytutywna dla klasy; CompiledGrammar nie jest potrzebna |
| Origin node'a | CompiledGrammar — `Rule::META_ORIGIN.format`; jeśli różny od formatu generowanej gramatyki → importuj fasadę z namespace'u tamtej gramatyki; jeśli ten sam format (dowolny wariant) → generuj nową klasę w namespace'ie docelowej gramatyki |

```php
// --- property hook ---
public OptionalAttribute $trailingComma { get => $this->attributes[3]; }

// --- getter ---
public function getNodeTrailingComma(): TrailingCommaNode|null
{
    return $this->trailingComma->node;
}

// --- setter ---
public function setNodeTrailingComma(TrailingCommaNode $node): self
{
    $this->trailingComma->node = $node->setParent($this);
    return $this;
}

// --- remove ---
public function removeNodeTrailingComma(): self
{
    $this->trailingComma->node = null;
    return $this;
}
```

---

## `GroupAttribute<T>`

Opakowuje **kolekcję** `NodeInterface[]` o dowolnej długości (0..n).

### Metody

**add** — zawsze. Przyjmuje node + opcjonalne `Placement` i `$offset`. Deleguje do `GroupAttribute::addNode()`.

**get** — zawsze. Zwraca `array<T>`, opcjonalny callable filter. Deleguje do `GroupAttribute::getNodes()`.

**removeByOffset** — zawsze. Usuwa element po pozycji w tablicy. Deleguje do `GroupAttribute::removeNodeByOffset()`.

**removeByFilter** — zawsze. Usuwa elementy nie spełniające predykatu. Deleguje do `GroupAttribute::removeNodeByFilter()`.

### Źródła danych

| Informacja | Skąd |
|---|---|
| Nazwa właściwości (`{prop}`) | output parsowania — `anchorName ?? name` atrybutu |
| Union type w `@var` i sygnaturach | output parsowania — zbiór wszystkich klas node'ów faktycznie znalezionych w grupie **+** CompiledGrammar — dopuszczalne typy wg gramatyki, których nie było w plikach źródłowych |
| Origin każdego typu w union | CompiledGrammar — `Rule::META_ORIGIN.format` każdej reguły; inny format niż generowana gramatyka → importuj fasadę z tamtego namespace'u; ten sam format (dowolny wariant) → generuj nową klasę w namespace'ie docelowej gramatyki |

```php
// --- property hook ---
/** @var GroupAttribute<LeadingWsNode|TrailingWsNode|EmptyLineNode|InlineWsNode> */
public GroupAttribute $trivia0 { get => $this->attributes[0]; }

// --- add ---
public function addNodeToTrivia0(
    LeadingWsNode|TrailingWsNode|EmptyLineNode|InlineWsNode $node,
    Placement $placement = Placement::After,
    int $offset = -1,
): self {
    $this->trivia0->addNode($node->setParent($this), $placement, $offset);
    return $this;
}

// --- get ---
/** @return array<LeadingWsNode|TrailingWsNode|EmptyLineNode|InlineWsNode> */
public function getNodesFromTrivia0(?callable $filter = null): array
{
    return $this->trivia0->getNodes($filter);
}

// --- removeByOffset ---
public function removeNodeFromTrivia0ByOffset(int $offset): self
{
    $this->trivia0->removeNodeByOffset($offset);
    return $this;
}

// --- removeByFilter ---
/** @param callable(NodeInterface):bool $filter  true = zostaw, false = usuń */
public function removeNodeFromTrivia0ByFilter(callable $filter): self
{
    $this->trivia0->removeNodeByFilter($filter);
    return $this;
}
```

---

## `ChoiceAttribute` — wariant A: choices to `NodeInterface`

`$selected` wskazuje na `NodeAttribute` opakowujący konkretny node z listy choices.

### Metody

**getter** — zawsze. Rozpakowuje `NodeAttribute::$node` z `$selected`, zwraca `null` gdy nieobecny.

**setter** — zawsze. Tworzy `NodeAttribute::fromNode()`, deleguje do `ChoiceAttribute::setSelected()`.

**remove** — **tylko gdy cardinality = 0** (wybór może być nieobecny wg gramatyki). `$selected` jest zawsze nullable implementacyjnie, ale gramatyka decyduje, czy brak jest legalny semantycznie.

### Źródła danych

| Informacja | Skąd |
|---|---|
| Nazwa właściwości (`{prop}`) | output parsowania — `anchorName ?? name` atrybutu |
| Union type w sygnaturach | output parsowania — zbiór klas node'ów faktycznie wybranych jako `$selected` **+** CompiledGrammar — pełna lista `$choices` (warianty niewidoczne w plikach źródłowych) |
| Cardinality (czy generować `remove`) | CompiledGrammar — czy sekwencja opakowująca ten atrybut dopuszcza jego brak (opcjonalna sekwencja, `?`) |
| Origin każdego typu w union | CompiledGrammar — `Rule::META_ORIGIN.format` każdej reguły choice; inny format niż generowana gramatyka → importuj fasadę z tamtego namespace'u; ten sam format (dowolny wariant) → generuj nową klasę w namespace'ie docelowej gramatyki |

```php
// --- property hook ---
/** @var ChoiceAttribute<ObjectNode|ArrayNode|PrimitiveNode> */
public ChoiceAttribute $value { get => $this->attributes[1]; }

// --- getter (zawsze) ---
public function getNodeValue(): ObjectNode|ArrayNode|PrimitiveNode|null
{
    /** @var NodeAttribute|null $attribute */
    $attribute = $this->value->selected;
    return $attribute?->node;
}

// --- setter (zawsze) ---
public function setNodeValue(ObjectNode|ArrayNode|PrimitiveNode $value): self
{
    $this->value->setSelected(NodeAttribute::fromNode($value->setParent($this)));
    return $this;
}

// --- remove (tylko cardinality = 0) ---
public function removeNodeValue(): self
{
    $this->value->removeSelected();
    return $this;
}
```

---

## `ChoiceAttribute` — wariant B: choices to `RawContentAttribute` / `RawRegionAttribute`

`$selected` wskazuje na atrybut raw (nie node). Model getter/setter węzłów nie ma sensu — API opiera się na typowanym enumie reprezentującym rodzaj tokenu.

### Artefakty i metody

**enum `{Prop}Type`** — zawsze. Generowany obok klasy node'a jako osobny plik. Cases wyprowadzane z listy choices, wartości = nazwy tokenów z gramatyki.

**setter** — zawsze. Przyjmuje `{Prop}Type` + opcjonalny `?string $content`. Dla keyword content jest znany z gramatyki i hardkodowany w ifach. Dla expression/raw region content musi być podany — brak rzuca wyjątek.

**type getter** — zawsze. Zwraca `{Prop}Type|null` (null gdy `$selected === null`).

**content getter** — zawsze. Zwraca surowy string lub null.

**remove** — nigdy. Semantycznie odpowiada "jaki token jest wybrany", nie "czy atrybut jest obecny".

### Źródła danych

| Informacja | Skąd |
|---|---|
| Nazwa właściwości (`{prop}`) | output parsowania — `anchorName ?? name` atrybutu |
| Cases enuma | output parsowania — nazwy tokenów faktycznie wybranych jako `$selected->name` **+** CompiledGrammar — pełna lista `$choices` (warianty niewidoczne w plikach źródłowych) |
| Czy dany case to keyword (stały content) czy expression (wymaga content) | CompiledGrammar — meta reguły tokenu (`RawContentAttribute` = keyword/expression, `RawRegionAttribute` = expression z delimiterami) |
| Stała wartość contentu dla keyword | CompiledGrammar — wartość tokenu z reguły gramatyki (np. `"true"`, `","`) |
| Opener/closer dla `RawRegionAttribute` | output parsowania — faktyczne wartości `$opener`/`$closer` znalezione w drzewie; potwierdzenie przez CompiledGrammar czy są zawsze obecne czy opcjonalne |
| Origin reguły tokenów | CompiledGrammar — `Rule::META_ORIGIN.format`; inny format niż generowana gramatyka → setter importuje stałe z namespace'u tamtej gramatyki; ten sam format (dowolny wariant) → generuj w namespace'ie docelowej gramatyki |

```php
// --- wygenerowany enum (osobny plik) ---
enum PrimitiveType: string
{
    case False  = 'false';   // keyword — content stały
    case Null   = 'null';    // keyword — content stały
    case True   = 'true';    // keyword — content stały
    case Number = 'number';  // expression — wymaga content
    case String = 'string';  // expression — wymaga content
}

// --- property hook ---
/** @var ChoiceAttribute<RawRegionAttribute|RawContentAttribute> */
public ChoiceAttribute $primitive { get => $this->attributes[0]; }

// --- setter ---
public function setPrimitive(PrimitiveType $type, ?string $content = null): self
{
    if ($type === PrimitiveType::False) {
        $this->primitive->setSelected(new RawContentAttribute('false', 'false', null));
        return $this;
    }
    if ($type === PrimitiveType::Null) {
        $this->primitive->setSelected(new RawContentAttribute('null', 'null', null));
        return $this;
    }
    if ($type === PrimitiveType::True) {
        $this->primitive->setSelected(new RawContentAttribute('true', 'true', null));
        return $this;
    }
    if ($type === PrimitiveType::Number) {
        if ($content === null) {
            throw new InvalidArgumentException('Content required for number.');
        }
        $this->primitive->setSelected(new RawRegionAttribute(null, null, $content, 'number', null));
        return $this;
    }
    if ($type === PrimitiveType::String) {
        if ($content === null) {
            throw new InvalidArgumentException('Content required for string.');
        }
        $this->primitive->setSelected(new RawRegionAttribute(
            new StructureAttribute(true, 'doubleQuote', '"'),
            new StructureAttribute(true, 'doubleQuote', '"'),
            $content, 'string', null,
        ));
        return $this;
    }
    throw new InvalidArgumentException('Unsupported type: ' . $type->value);
}

// --- type getter ---
public function getPrimitiveType(): PrimitiveType|null
{
    $attribute = $this->primitive->selected;
    return $attribute !== null ? PrimitiveType::from($attribute->name) : null;
}

// --- content getter ---
public function getPrimitiveContent(): string|null
{
    return $this->primitive->selected?->content;
}
```

---

## `GroupedAttribute`

Powtarzalna sekwencja z zagnieżdżonymi atrybutami strukturalnymi (separatory, trivia). Jeden `GroupedAttribute` generuje 5 metod.

### Metody

**withValidation** — zawsze. Rejestruje `autoFactories` dla atrybutów structural (separator, trivia) na podstawie `NestedSequence` z gramatyki. Bez wywołania tej metody `addUnit()` nie wstawia structural automatycznie.

**add** — zawsze. Przyjmuje content-element (jedyny `{Content}`, który nie jest structural). Deleguje do `GroupedAttribute::addUnit()`.

**remove** — zawsze. Usuwa cały unit po indeksie logicznym (content + poprzedzające structural). Deleguje do `GroupedAttribute::removeUnit()`.

**getUnit** — zawsze. Zwraca pełen unit po indeksie (array atrybutów: content + structural). Wymaga wcześniejszego wywołania `withValidation`.

**getAll** — zawsze. Iteruje po `$attributes` i zwraca tylko elementy content (filtruje po nazwie). Działa bez `withValidation`.

### Źródła danych

| Informacja | Skąd |
|---|---|
| Nazwa właściwości (`{prop}`) | output parsowania — `anchorName ?? name` atrybutu |
| Typ content-elementu (`{Content}`) | output parsowania — klasa node'a faktycznie pojawiającego się jako content w unitach **+** CompiledGrammar — pełna lista dopuszczalnych content-typów z `NestedSequence` |
| Definicja structural-elementów (`autoFactories`) | CompiledGrammar — `NestedSequence` zna typy, nazwy i wartości domyślne każdego atrybutu structural (np. `comma = ','`, `trivia = GroupAttribute`); output parsowania tylko potwierdza jakie structural faktycznie wystąpiły |
| Nazwa content-atrybutu w `getAll` (filtr po `getName()`) | output parsowania — faktyczna `name` atrybutu content w `GroupedAttribute::$attributes` |
| Origin content-typów i structural-typów | CompiledGrammar — `Rule::META_ORIGIN.format` każdej reguły; inny format niż generowana gramatyka → importuj z namespace'u tamtej gramatyki (dotyczy też factory lambda w `autoFactories`); ten sam format (dowolny wariant) → generuj w namespace'ie docelowej gramatyki |

```php
// --- property hook ---
/** @var GroupedAttribute<MemberNode|StructureAttribute|TrailingWsNode|EmptyLineNode|InlineWsNode|LeadingWsNode> */
public GroupedAttribute $members { get => $this->attributes[2]; }

// --- withValidation ---
public function withMembersValidation(NestedSequence|SequenceValidityCursor $sequence): self
{
    $this->members->withValidSequence($sequence, [
        'trivia0' => static fn() => new GroupAttribute('trivia0', []),
        'comma'   => static fn() => new StructureAttribute(true, 'comma', ','),
        'trivia1' => static fn() => new GroupAttribute('trivia1', []),
    ]);
    return $this;
}

// --- add ---
public function addMemberToMembers(MemberNode $member): self
{
    $this->members->addUnit(NodeAttribute::fromNode($member->setParent($this)));
    return $this;
}

// --- remove ---
public function removeMemberFromMembersByIndex(int $index): self
{
    $this->members->removeUnit($index);
    return $this;
}

// --- getUnit ---
/** @return NodeAttributeInterface[] */
public function getMemberUnitFromMembers(int $index): array
{
    return $this->members->getUnit($index);
}

// --- getAll ---
/** @return MemberNode[] */
public function getMembersFromMembers(): array
{
    $result = [];
    foreach ($this->members->attributes as $attr) {
        if ($attr instanceof NodeAttribute && $attr->getName() === 'member') {
            $result[] = $attr->node;
        }
    }
    return $result;
}
```

---

## `StructureAttribute`

Znacznik składniowy (nawias, przecinek, dwukropek, cudzysłów). Zawsze wymagany i stały.

### Metody

Żadnych. Dostęp wyłącznie przez property hook.

Wyjątek: gdy `StructureAttribute` pełni rolę `$opener`/`$closer` wewnątrz `RawRegionAttribute`, jest częścią implementacji `getRaw{Prop}` / `setRaw{Prop}` — ale sam nie generuje osobnych metod.

### Źródła danych

| Informacja | Skąd |
|---|---|
| Nazwa właściwości (`{prop}`) | output parsowania — `name` atrybutu |
| Wartość tokenu (`$content`) | output parsowania — faktyczna wartość (np. `'['`, `','`) |
| Potwierdzenie że to structural (nie content) | CompiledGrammar — atrybut pochodzi ze structural-elementu sekwencji, nie z content-kotwicy |
| Origin reguły | CompiledGrammar — `Rule::META_ORIGIN.format`; inny format niż generowana gramatyka → property hook wskazuje na istniejącą klasę z tamtego namespace'u, nie generuj; ten sam format (dowolny wariant) → generuj |

```php
// --- property hook (jedyne co jest generowane) ---
public StructureAttribute $beginArray { get => $this->attributes[0]; }
public StructureAttribute $endArray   { get => $this->attributes[4]; }
```

---

## `RawContentAttribute`

Przechowuje surowy string bez delimiterów (keyword, liczba, identyfikator bez cudzysłowów).

### Metody

**getRaw** — zawsze. Zwraca `$content`.

**setRaw** — zawsze. Ustawia `$content`.

### Źródła danych

| Informacja | Skąd |
|---|---|
| Nazwa właściwości (`{prop}`) | output parsowania — `anchorName ?? name` atrybutu |
| Potwierdzenie braku delimiterów | output parsowania — atrybut jest instancją `RawContentAttribute`, nie `RawRegionAttribute` |
| Origin reguły tokenu | CompiledGrammar — `Rule::META_ORIGIN.format`; `RawContentAttribute` reprezentuje token, nie node — inny format niż generowana gramatyka → nie generuj, użyj klasy z tamtego namespace'u; ten sam format (dowolny wariant) → generuj w namespace'ie docelowej gramatyki |

```php
// --- property hook ---
public RawContentAttribute $number { get => $this->attributes[0]; }

// --- getter ---
public function getRawNumber(): string
{
    return $this->number->content;
}

// --- setter ---
public function setRawNumber(string $value): self
{
    $this->number->content = $value;
    return $this;
}
```

---

## `RawRegionAttribute`

Rozszerza `RawContentAttribute` o opcjonalne `$opener` / `$closer` (np. cudzysłowy przy stringu). Fasada eksponuje wyłącznie `$content` — delimitery są stałą strukturą definiowaną przez gramatykę.

### Metody

**getRaw** — zawsze. Zwraca `$content` (bez delimiterów).

**setRaw** — zawsze. Ustawia `$content` (delimitery bez zmian).

### Źródła danych

| Informacja | Skąd |
|---|---|
| Nazwa właściwości (`{prop}`) | output parsowania — `anchorName ?? name` atrybutu |
| Obecność i wartości `$opener`/`$closer` | output parsowania — faktyczne instancje `StructureAttribute` znalezione w drzewie; `null` jeśli delimiterów nie było |
| Czy opener/closer są zawsze obecne czy opcjonalne | CompiledGrammar — reguła tokenu określa czy delimitery są obowiązkowe (potrzebne do poprawnej rekonstrukcji w `setRaw`) |
| Origin reguły tokenu | CompiledGrammar — `Rule::META_ORIGIN.format`; `RawRegionAttribute` reprezentuje token, nie node — inny format niż generowana gramatyka → property hook wskazuje na istniejącą klasę z tamtego namespace'u, nie generuj; ten sam format (dowolny wariant) → generuj w namespace'ie docelowej gramatyki |

```php
// --- property hook ---
public RawRegionAttribute $identifier { get => $this->attributes[0]; }

// --- getter ---
public function getRawIdentifier(): string
{
    return $this->identifier->content;
}

// --- setter ---
public function setRawIdentifier(string $identifier): self
{
    $this->identifier->content = $identifier;
    return $this;
}
```

---

## Podsumowanie: kiedy generować `remove`

| Typ atrybutu | `remove`? | Uzasadnienie |
|---|---|---|
| `NodeAttribute` | nie | cardinality 1..1 |
| `OptionalAttribute` | **tak** | cardinality 0..1 |
| `GroupAttribute` | **tak** (×2) | kolekcja, każdy element usuwalny |
| `ChoiceAttribute` (nodes, cardinality 0) | **tak** | gramatyka dopuszcza brak |
| `ChoiceAttribute` (nodes, cardinality 1) | nie | gramatyka wymaga obecności |
| `ChoiceAttribute` (raws) | nie | "jaki token", nie "czy obecny" |
| `GroupedAttribute` | **tak** (by index) | usuwa cały unit |
| `StructureAttribute` | nie | brak metod w ogóle |
| `RawContentAttribute` | nie | zawsze obecny, zmieniamy tylko content |
| `RawRegionAttribute` | nie | zawsze obecny, zmieniamy tylko content |
