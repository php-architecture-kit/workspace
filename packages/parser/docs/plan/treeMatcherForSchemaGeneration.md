# Plan: TreeMatcher — kompletne pokrycie gramatyki dla TreeSchemaGenerator

## Status

Odłożone — przed implementacją warto dopisać więcej gramatyk, aby:
- Zobaczyć więcej edge case'ów (szczególnie złożona rekurencja jak PHP `expression`)
- Upewnić się że parser ma już dostępne potrzebne feature'y

---

## Kontekst i cel

`GenerateTreeSchemaCommand` parsuje pliki wejściowe → `TreeSchemaGenerator` buduje PHP class templates (PHPDoc `@property` annotacje).

**Problem:** Pliki wejściowe nie pokrywają wszystkich alternatyw gramatyki → niekompletne TypeRefs w generated classes.
Przykład: `ChoiceAttribute<ObjectNode>` zamiast `ChoiceAttribute<ObjectNode|ArrayNode|PrimitiveNode>`.

**Cel:** Produkować kompletne schematy PHP klas bez konieczności pisania wielu plików wejściowych.

---

## Dlaczego nie można użyć istniejącego Matchera

`Matcher` konsumuje tokeny z `TokenStream`. Bez tokenów nie ma jak:
- Wybrać która z alternatyw `SequenceNode.alternatives` pasuje
- Zdecydować ile iteracji pętli cardinality (`*`, `+`)
- Obsłużyć lookahead/lookbehind

Dodanie flagi `$dryRun` do istniejącego `Matcher` zatrułoby kod produkcyjny warunkami i wymagałoby zmian sygnatur metod (np. `?Token`). Osobna klasa `TreeMatcher` zachowuje istniejące klasy bez zmian.

---

## Dlaczego generowanie "od roota" nie działa dla rekurencyjnych gramatyk

Dla PHP `expression` z dziesiątkami wariantów (każdy zawiera kolejne `expression`):

Strategia "jedno podstawienie na raz od root-sequence" nigdy nie dotrze do alternatyw wewnątrz `binary_expression`, bo domyślna ścieżka od roota idzie przez `literal` (pierwszą alternatywę `expression`). Aby dotrzeć do operatorów w `binary_expression`, trzeba wygenerować drzewo gdzie `expression → binary_expression` — ale wtedy `expression` wewnątrz `binary_expression` to cykl → pusty placeholder. Alternatywy operatorów byłyby nigdy nieodwiedzone w kontekście roota.

---

## Prawidłowe podejście: każda sekwencja z SequenceLibrary jako niezależny root

Zamiast jednego drzewa od root-sequence: jedno drzewo (lub kilka) **per sekwencja w bibliotece**.

Każda sekwencja jest przetwarzana niezależnie jako punkt wejścia. Rekurencja jest zatrzymywana przez **visited-set per aktywną ścieżkę** (nie globalny licznik):
- `expression` jako root → alternative[0] = `literal` → przetworzone poprawnie
- `binary_expression` jako root → `left`=`expression` (nie ma cyklu bo `expression` ≠ `binary_expression`) → expanded; `expression` wewnątrz ma własny visited-set i zatrzymuje się gdy wróci do `binary_expression`
- Cykl: `expression → binary_expression → expression` — drugie `expression` jest w visited → zwraca pusty `MatchedSequence('expression', [])`

### Enumeracja alternatyw (lokalna per sekwencja)

Dla każdej sekwencji jako root, enumeruj alternatywy zebrane PODCZAS przetwarzania tej sekwencji:
- Drzewo bazowe: `alternative[0]` dla każdego `SequenceNode`
- Drzewa dodatkowe: po jednym per `alternative[i>0]` gdzie ten `SequenceNode` jest OSIĄGALNY w tej sekwencji

Łączna liczba drzew = Σ_sekwencji(1 + liczba_dodatkowych_alternatyw_w_tej_sekwencji) — liniowa.

---

## Jedyna nowa klasa: TreeMatcher

**Plik:** `packages/parser/src/Foundation/Matching/Tree/TreeMatcher.php`
**Namespace:** `PhpArchitecture\Parser\Foundation\Matching\Tree`

**Wejście:** `SequenceLibrary $library`
**Wyjście:** `MatchedSequence[]` (jedno per sekwencja per alternative path)

```
generateAll(): MatchedSequence[]
  foreach sequence in library.getAllSequences():
    yield from generateForSequence(sequence.name)

generateForSequence(string $name): MatchedSequence[]
  collectedAlternatives = []
  base = processSequence(sequence, visited=[], altOverrides=[], collecting=collectedAlternatives)
  results = [base]
  foreach (nodeId, altIdx) in collectedAlternatives:
    results[] = processSequence(sequence, visited=[], altOverrides=[nodeId=>altIdx], collecting=[])
  return results

processSequence(Sequence, visited, altOverrides, &collecting): MatchedSequence
  if sequence.name in visited:
    return MatchedSequence(name, items=[], ...)  // cykl — pusty placeholder
  visited[name] = true
  nodes = []
  foreach node in sequence.nodes:
    SequenceNode   → nodes[] = processSequenceNode(node, visited, altOverrides, collecting)
    NestedSequence → nodes[] = processNestedSequence(node, visited, altOverrides, collecting)
  unset(visited[name])
  return MatchedSequence(name, nodes, ...)

processSequenceNode(SequenceNode, visited, altOverrides, &collecting): MatchedSequenceNode
  name = node.anchorName ?? join('|', node.alternatives)
  nodeId = spl_object_id(node)

  // Zbierz wszystkie alternatywy[1..n] do późniejszego generowania
  foreach alternatives[1..n] as (i, alt):
    collecting[] = [nodeId, i]

  // Specjalne przypadki → brak items (jak optional)
  if node.min === 0 || isLookahead || isLookbehind || isNegation:
    return MatchedSequenceNode(name, items=[], min, max, ...)

  // Wybór alternatywy (domyślna lub override)
  altIdx = altOverrides[nodeId] ?? 0
  alternative = node.alternatives[altIdx]

  if library.hasSequence(alternative):
    seq = processSequence(library.get(alternative), visited, altOverrides, collecting)
    items = [seq]
  else:
    // Fake token — name i tags z SequenceNode (NodeTypeResolver sprawdza tylko tags)
    token = Token::default(alternative, raw='', 0, 0)
    foreach node.tags as tag: token.addTag(tag)
    items = [token]

  return MatchedSequenceNode(name, items, min, max, ...)

processNestedSequence(NestedSequence, visited, altOverrides, &collecting): MatchedSequenceNode[]
  // min=0 lub lookahead/lookbehind → brak items
  if node.min === 0 || isLookahead || isLookbehind:
    return []
  // Weź pierwszą alternativę sekwencji (alternativeSequences[0])
  // i przetwórz jej węzły jak zwykłe SequenceNode/NestedSequence
  altNodes = node.alternativeSequences[0]
  result = []
  foreach altNodes as child:
    SequenceNode   → result[] = processSequenceNode(child, visited, altOverrides, collecting)
    NestedSequence → result[] = ...processNestedSequence(child, ...)
  return result
```

---

## Integracja z GenerateTreeSchemaCommand

```php
$compiledGrammar = (new GrammarCompiler())->compile($definition->grammar());
$treeMatcher     = new TreeMatcher($compiledGrammar->getSequenceLibrary());
$generator       = new TreeSchemaGenerator();
$nodeFactory     = ...;  // ParsingContext z matchingContextForRegion()=null

// 1. Drzewa z TreeMatcher (kompletne pokrycie gramatyki)
foreach ($treeMatcher->generateAll() as $matchedSequence) {
    $node      = $nodeFactory->fromMatchedSequence($matchedSequence, parentNode: null);
    $templates = $generator->generate($node, $namespace);
}

// 2. Drzewa z prawdziwych plików (prawdziwe wartości)
foreach ($inputFiles as $inputFile) {
    $parseTree = $parser->parse(new StringStream(file_get_contents($inputFile)), ...);
    $templates = $generator->generate($parseTree, $namespace);
}
```

---

## Krytyczne pytania do weryfikacji przed implementacją

| Pytanie | Gdzie sprawdzić |
|---------|----------------|
| Czy `TreeSchemaGenerator.generate()` akumuluje state między wywołaniami? | `Infrastructure/TreeSchema/Generator/TreeSchemaGenerator.php` |
| Czy `ChoiceAttribute` w `TreeSchemaGenerator` używa `$choices` czy `$selected` dla TypeRefs? | ta sama klasa — jeśli `$selected`, potrzebny fix |
| Czy `Token::addTag()` mutuje czy zwraca nową instancję? | `Foundation/Shared/Tags/TagsTrait.php` |
| Jak `SequenceLibrary` eksponuje wszystkie sekwencje (`getAllSequences()`)? | `Foundation/Matching/Model/SequenceLibrary.php` |
| Jak `CompiledGrammar` eksponuje `SequenceLibrary`? | `Foundation/Grammar/Compiled/CompiledGrammar.php` |

---

## Pliki do zmiany / utworzenia

| Plik | Akcja |
|------|-------|
| `Foundation/Matching/Tree/TreeMatcher.php` | nowy |
| `Presentation/Controller/CLI/GenerateTreeSchemaCommand.php` | dodaj TreeMatcher loop |
| `Infrastructure/TreeSchema/Generator/TreeSchemaGenerator.php` | potencjalny fix: `ChoiceAttribute` → `$choices` zamiast `$selected` |

Istniejące `NodeFactory`, `NodeAttrFactory`, `Matcher` — **bez zmian**.

---

## Weryfikacja

1. `TreeMatcher::generateAll()` dla JSON grammar → liczba drzew powinna być rozsądna
2. Porównać generated PHP klasy: z TreeMatcher vs bez → więcej TypeRefs w union typach
3. `JsonNode` powinien mieć `ChoiceAttribute<ObjectNode|ArrayNode|PrimitiveNode>` a nie tylko jeden wybrany wariant
4. Test rekurencji: gramatyka z cyklicznym `expression → binary_expression → expression` → bez zapętlenia
