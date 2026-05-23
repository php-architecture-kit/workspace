# Refactoring klasy Matcher

Usunięcie duplikacji `processWithRoot`/`processWithoutRoot` przez scalenie w jeden przepływ skanowania oraz ekstrakcja pomocnika `matchNodeList` eliminującego powtórzony dispatch węzłów.

---

## Duplikacja 1: `processWithRoot` vs `processWithoutRoot`

Obie metody robią to samo konceptualnie: iterują przez sekwencje i próbują dopasować je do strumienia tokenów. Różnią się tylko:
- `processWithRoot` próbuje **raz** z rootSequence i rzuca wyjątek przy niepowodzeniu
- `processWithoutRoot` skanuje iteracyjnie przez **wszystkie** sekwencje z fallbackiem na "unmatched"

**Plan**: usunąć obie prywatne metody, logikę scalić bezpośrednio w `process()`:

```php
public function process(TokenRegion $region): MatchedRegion
{
    $this->context->markMatchingStarted();

    $rootSequence = $this->context->getSequenceLibrary()->rootSequence;
    $stream = $region->stream;
    $offset = 0;

    while ($stream->has($offset)) {
        $matched = false;
        $startOffset = $offset;

        // Root sequence ma priorytet – próbujemy pierwsza
        if ($rootSequence !== null) {
            $matchedSequence = $this->matchSequence($rootSequence, $stream, $offset);
            if ($matchedSequence !== null) {
                $this->context->addMatchedSequence($matchedSequence);
                $matched = true;
            } else {
                $offset = $startOffset;
            }
        }

        // Fallback na pozostałe sekwencje
        if (!$matched) {
            foreach ($this->context->getSequenceLibrary()->sequences as $sequence) {
                $startOffset = $offset;
                $matchedSequence = $this->matchSequence($sequence, $stream, $offset);
                if ($matchedSequence !== null) {
                    $this->context->addMatchedSequence($matchedSequence);
                    $matched = true;
                    break;
                }
                $offset = $startOffset;
            }
        }

        if (!$matched) {
            $element = $stream->peek($offset++);
            if ($element instanceof Token) {
                $this->context->addUnmatchedToken($element);
            } elseif ($element instanceof TokenRegion) {
                $this->context->addUnmatchedTokenRegion($element);
            }
        }
    }

    $this->context->markMatchingFinished();
    return $this->context->getOutput();
}
```

### Zmiana return type: `MatchedRegion|MatchedSequence` → `MatchedRegion`

Caller `NodeFactory::fromTokenRegion()` obecnie rozgałęzia się:
```php
if ($matchedSeqOrRegion instanceof MatchedRegion) {
    return $this->createNodeFromMatchedRegion(...);
}
return $this->createNodeFromMatchedSequence(...);  // ← ten branch odpada
```

Po refactoringu `process()` zawsze zwraca `MatchedRegion`. Dopasowane sekwencje trafiają do `MatchedRegion.items` poprzez `context->addMatchedSequence()`.
`NodeFactory` traci branch dla `MatchedSequence` i zawsze woła `createNodeFromMatchedRegion()`.

**Uwaga**: `createNodeFromMatchedRegion` używa `fillRegionBasedNodeWithAttributes`, które obsługuje `MatchedSequence` w itemach – więc semantyka pozostaje poprawna.

### Usunięcie error message z `processWithRoot`

Szczegółowy komunikat błędu z `processWithRoot` (lista węzłów, tokeny) odpada. Jeśli root sequence nie pasuje, po prostu skanujemy dalej. Błąd nie jest już rzucany.

---

## Duplikacja 2: dispatch węzłów w `matchSequence` vs `matchNestedSequence`

`matchSequence` wewnętrzna pętla:
```php
foreach ($sequence->nodes as $node) {
    if ($node instanceof NestedSequence) { ... matchNestedSequence ... }
    elseif ($node instanceof SequenceNode) { ... matchSequenceNode ... }
}
```

`matchNestedSequence` wewnętrzna pętla alternatywy:
```php
foreach ($alternativeNodes as $node) {
    // offset-- dla lookbehind
    if ($node instanceof NestedSequence) { ... matchNestedSequence + lookahead/lookbehind ... }
    elseif ($node instanceof SequenceNode) { ... matchSequenceNode + lookahead/lookbehind ... }
}
```

Ten sam dispatch `NestedSequence|SequenceNode` jest zduplikowany. `matchSequence` nie obsługuje lookahead/lookbehind (pre-existing bug).

**Plan**: wyekstrahować `matchNodeList(array $nodes, TokenStream $stream, int &$offset): ?array`

- Iteruje listę węzłów w kolejności
- Obsługuje lookahead/lookbehind (naprawia bug w `matchSequence`)
- Zwraca `array<MatchedSequenceNode|MatchedSequence>` lub `null` przy niepowodzeniu

Wtedy:
- `matchSequence` → zamiast własnej pętli woła `matchNodeList($sequence->nodes, ...)`
- `matchNestedSequence` → wewnętrzna pętla alternatywy zastąpiona przez `matchNodeList($alternativeNodes, ...)`

---

## Podsumowanie zmian

| Zmiana | Plik |
|---|---|
| Usunąć `processWithRoot()` i `processWithoutRoot()`, scalić w `process()` | `Matcher.php` |
| `process()` zwraca `MatchedRegion` (nie union) | `Matcher.php` |
| Wyekstrahować `matchNodeList()` | `Matcher.php` |
| `matchSequence()` używa `matchNodeList()` (naprawa lookahead/lookbehind) | `Matcher.php` |
| `matchNestedSequence()` używa `matchNodeList()` | `Matcher.php` |
| Usunąć branch `instanceof MatchedSequence` w `fromTokenRegion()` | `NodeFactory.php` |
| Zmiana type-hinta return type `process()` | `Matcher.php` |
