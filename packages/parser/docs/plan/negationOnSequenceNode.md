# Negative lookahead i negative lookbehind

Implementacja `!>ruleName` (negative lookahead) i `!<ruleName` (negative lookbehind) w składni sekwencji, kompilatorze i Matcherze.

---

## Pytanie architektoniczne: czy `negative` ma być ogólne czy tylko dla lookahead/lookbehind?

**Odpowiedź: tylko dla lookahead/lookbehind.**

Lookahead i lookbehind to **zero-width assertions** – nie konsumują tokenów, tylko testują warunek.
Negacja consuming rule jest semantycznie niezdefiniowana: jeśli `!ruleA` "nie dopasowuje ruleA", to co konsumuje?
Żadna znana gramatyka formalna (PEG, ANTLR, ABNF) nie wprowadza negacji na konsumujących węzłach bez jawnej alternatywy.
W PEG `!e` to zawsze predykat (nie-konsumujący). To samo tutaj.

---

## Proponowana składnia

| Notacja | Znaczenie | Pozycja |
|---|---|---|
| `>ruleName` | pozytywny lookahead (istniejący) | ostatni element |
| `!>ruleName` | **negatywny lookahead** (nowy) | ostatni element |
| `<ruleName` | pozytywny lookbehind (istniejący) | pierwszy element |
| `!<ruleName` | **negatywny lookbehind** (nowy) | pierwszy element |

`!` jako prefiks negacji, spójne z PEG.

---

## Obserwacja: luka w `matchSequence()` (pre-existing)

`matchSequence()` (linia 187-204 `Matcher.php`) **w ogóle nie obsługuje** `isLookahead`/`isLookbehind` dla węzłów na najwyższym poziomie sekwencji. Lookahead/lookbehind działa tylko wewnątrz `matchNestedSequence()` (tj. wewnątrz `()`).

Naprawa tej luki jest konieczna przy implementacji negacji – bez niej `!>` na najwyższym poziomie też by nie działało.

---

## Zmiany – lista plików

### 1. Grammar Definition (DSL parsing)

**`SequenceNode.php` (Definition)**
`src/Foundation/Grammar/Definition/Model/Sequence/SequenceNode.php`
- Dodać `public bool $isNegativeLookahead = false` i `public bool $isNegativeLookbehind = false` do konstruktora
- Aktualizacja regex w `fromString()`: dodać `(?<negate>!)?` przed `(?<lookahead>>)?(?<lookbehind><)?`
- Walidacja: `!` dozwolone tylko gdy jednocześnie `>` lub `<` (inaczej wyjątek)
- Aktualizacja `toString()`: serializuje `!>` / `!<` zamiast samego `>` / `<`

**`NestedSequence.php` (Definition)**
`src/Foundation/Grammar/Definition/Model/Sequence/NestedSequence.php`
- Identyczne zmiany jak w SequenceNode

**`SequenceRule.php`** (walidacja)
`src/Foundation/Grammar/Definition/Model/Sequence/SequenceRule.php`
- `validateSequenceNodes()`: negatywny lookahead traktowany identycznie jak pozytywny (musi być ostatni), negatywny lookbehind identycznie jak pozytywny (musi być pierwszy)

---

### 2. Matching Models (compiled layer)

**`SequenceNode.php` (Matching)**
`src/Foundation/Matching/Model/SequenceNode.php`
- Dodać `public bool $isNegativeLookahead = false` i `public bool $isNegativeLookbehind = false`

**`NestedSequence.php` (Matching)**
`src/Foundation/Matching/Model/NestedSequence.php`
- Identycznie
- `getFirstValidNodeNodeNames()`: pominąć węzły z `isNegativeLookbehind = true` (analogicznie jak `isLookbehind`)

**`Sequence.php` (Matching)**
`src/Foundation/Matching/Model/Sequence.php`
- `getFirstValidNodeNodeNames()`: pominąć `isNegativeLookbehind` (analogicznie jak `isLookbehind`)

---

### 3. Compiler

**`RuleToSequenceCompiler.php`**
`src/Foundation/Grammar/Compiled/Compiler/RuleToSequenceCompiler.php`
- `compileSequenceNode()`: przekazać `isNegativeLookahead` / `isNegativeLookbehind` do `CompiledSequenceNode`
- `compileNestedSequence()`: przekazać do `CompiledNestedSequence`

**`SequenceNodeEnricher.php`**
`src/Foundation/Grammar/Compiled/Compiler/SequenceNodeEnricher.php`
- Przy rekonstrukcji `CompiledSequenceNode` i `NestedSequence` – przekazać nowe flagi (dwa miejsca: `enrichNode()` i `enrichNestedSequence()`)

---

### 4. Matcher (logika dopasowania)

**`Matcher.php`**
`src/Foundation/Matching/Matcher.php`

#### 4a. Naprawić `matchSequence()` – brakująca obsługa lookahead/lookbehind na top-level

Dla każdego węzła `SequenceNode` z `isLookbehind`/`isLookahead`:
- **lookbehind**: `$offset--` przed matchem; po matchu → `$offset = $nodeStart; continue`; brak matchu → fail
- **lookahead**: po matchu → `$offset = $nodeStart` (nie dodawać do `$items`); brak matchu → fail

(Nie dotyczy `NestedSequence` na top-level – te już działają przez `matchNestedSequence()`)

#### 4b. Dodać obsługę negative w `matchNestedSequence()`

Logika odwrócona w stosunku do pozytywnej:

**Negatywny lookbehind (`isNegativeLookbehind`)**:
```
$offset--;
match = matchSequenceNode/matchNestedSequence
if (match !== null) → allMatched = false; break  // jest za nami → FAIL
if (match === null) → $offset = $nodeStart; continue  // nie ma za nami → PASS
```

**Negatywny lookahead (`isNegativeLookahead`)**:
```
match = matchSequenceNode/matchNestedSequence
if (match !== null) → $offset = $nodeStart; allMatched = false; break  // jest przed nami → FAIL
if (match === null) → break  // nie ma przed nami → PASS (ostatni element, break)
```

#### 4c. Dodać obsługę negative w `matchSequence()` (top-level)

Analogicznie do 4a, rozszerzyć o `isNegativeLookahead`/`isNegativeLookbehind`.

---

## Kolejność implementacji

1. Modele Definition (`SequenceNode`, `NestedSequence`) – flagi + DSL parsing
2. Modele Matching (`SequenceNode`, `NestedSequence`, `Sequence`) – flagi + `getFirstValidNodeNodeNames`
3. `RuleToSequenceCompiler` + `SequenceNodeEnricher` – pass-through
4. `Matcher::matchSequence()` – naprawienie luki dla positive lookahead/lookbehind
5. `Matcher::matchNestedSequence()` – dodanie logiki negative
6. `Matcher::matchSequence()` – dodanie negative na top-level

---

## Pliki do zmiany – podsumowanie (9 plików)

| Plik | Zmiana |
|---|---|
| `Definition/Model/Sequence/SequenceNode.php` | Nowe flagi + parsowanie `!>` / `!<` + `toString()` |
| `Definition/Model/Sequence/NestedSequence.php` | Nowe flagi + parsowanie + `toString()` |
| `Definition/Model/Sequence/SequenceRule.php` | Walidacja pozycji dla negative |
| `Matching/Model/SequenceNode.php` | Nowe flagi |
| `Matching/Model/NestedSequence.php` | Nowe flagi + `getFirstValidNodeNodeNames()` |
| `Matching/Model/Sequence.php` | `getFirstValidNodeNodeNames()` |
| `Compiled/Compiler/RuleToSequenceCompiler.php` | Pass-through nowych flag |
| `Compiled/Compiler/SequenceNodeEnricher.php` | Pass-through nowych flag |
| `Matching/Matcher.php` | Fix top-level lookahead + negative logic |
