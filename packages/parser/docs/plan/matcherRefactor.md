# Refactoring klasy Matcher

Ekstrakcja pomocnika `matchNodeList` eliminującego zduplikowany dispatch węzłów między `matchSequence` i `matchNestedSequence`. Jest to jedyna zmiana funkcjonalna: naprawienie buga polegającego na tym, że `matchSequence` pomijało obsługę lookahead/lookbehind.

**Kontrakt `process()` oraz logika `processWithRoot`/`processWithoutRoot` pozostają bez zmian.**

---

## Jedyny bug: brak lookahead/lookbehind w `matchSequence`

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

Ten sam dispatch `NestedSequence|SequenceNode` jest zduplikowany. `matchSequence` nie obsługuje lookahead/lookbehind — to pre-existing bug, który refactoring ma naprawić.

---

## Plan: ekstrakcja `matchNodeList`

Wyekstrahować `matchNodeList(array $nodes, TokenStream $stream, int &$offset): ?array`

- Iteruje listę węzłów w kolejności
- Obsługuje lookahead/lookbehind (naprawia bug w `matchSequence`)
- Zwraca `array<MatchedSequenceNode|MatchedSequence>` lub `null` przy niepowodzeniu

Następnie:
- `matchSequence` → zamiast własnej pętli woła `matchNodeList($sequence->nodes, ...)`
- `matchNestedSequence` → wewnętrzna pętla alternatywy zastąpiona przez `matchNodeList($alternativeNodes, ...)`

---

## Co NIE ulega zmianie

- `process()` zachowuje obecny return type (`MatchedRegion|MatchedSequence` — union)
- `processWithRoot` zachowuje wyjątek przy niezmatchowanym root sequence (root sequence w regionie musi zostać zmatchowany)
- `processWithoutRoot` zachowuje obecną logikę iteracyjnego skanowania z fallbackiem na "unmatched"
- `NodeFactory::fromTokenRegion()` — brak zmian (branch `instanceof MatchedSequence` pozostaje)

---

## Podsumowanie zmian

| Zmiana | Plik |
|---|---|
| Wyekstrahować `matchNodeList()` z obsługą lookahead/lookbehind | `Matcher.php` |
| `matchSequence()` używa `matchNodeList()` (naprawa lookahead/lookbehind) | `Matcher.php` |
| `matchNestedSequence()` używa `matchNodeList()` | `Matcher.php` |
