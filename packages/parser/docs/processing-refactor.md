# Refaktoryzacja submodułu `Processing/`

Każdy wiersz odpowiada jednemu podkatalogowi (lub grupie plików) wewnątrz `src/Processing/`.
Obecna ścieżka podana względem `src/`.

---

## Tabela

| Obecna ścieżka (`Processing/…`) | Proponowana nowa lokalizacja | Uzasadnienie / Komentarz | Status |
|---|---|---|:---:|
| `Context/TokenizationContext.php` | `Tokenization/Contract/TokenizationContext.php` | Interfejs kontekstu tokenizacji — należy do modułu `Tokenization/`, bo `Lexer` go tworzy i konsumuje. Kontrakt publiczny wobec zewnętrznych klientów (event listenery, rozszerzenia). | ✅ |
| `Context/MatchingContext.php` | `Matching/Contract/MatchingContext.php` | Analogicznie do `TokenizationContext` — `Matcher` tworzy i konsumuje. | ✅ |
| `Context/ParsingContext.php` | `Parsing/Contract/ParsingContext.php` | Używany przez `Parser`, `NodeFactory`, `NodeAttrFactory` — należy do modułu `Parsing/`. Aktualnie implementacja jest już w `Parsing/Context/DefaultParsingContext.php`, więc kontrakt powinien być obok. | ✅ |
| `Event/Tokenization/Contract/TokenizationEvent.php` | `Tokenization/Event/Contract/TokenizationEvent.php` | Kontrakt bazowy eventów tokenizacji. | ✅ |
| `Event/Tokenization/Contract/TokenizationEventListener.php` | `Tokenization/Event/Contract/TokenizationEventListener.php` | Interfejs listenera — jest częścią publicznego API tokenizatora (rejestrowany przez `TokenizationContext`). | ✅ |
| `Event/Tokenization/Contract/TokenBasedEvent.php` | `Tokenization/Event/Contract/TokenBasedEvent.php` | Pomocniczy interfejs/bazowa klasa eventów operujących na `Token`. | ✅ |
| `Event/Tokenization/Contract/TokenRegionBasedEvent.php` | `Tokenization/Event/Contract/TokenRegionBasedEvent.php` | j.w. dla `TokenRegion`. | ✅ |
| `Event/Tokenization/Contract/RemovableEventListener.php` | `Tokenization/Event/Contract/RemovableEventListener.php` | Kontrakt opcjonalnego wyrejestrowywania listenera. | ✅ |
| `Event/Tokenization/Token*.php` + `TokenizationStarted/FinishedEvent.php` _(9 klas)_ | `Tokenization/Event/` | Konkretne eventy tokenizacji. Wszystkie operują wyłącznie na `Token`, `TokenRegion` i `TokenizationContext` — czysto wewnętrzne zdarzenia tokenizatora. | ✅ |
| `Event/Matching/Contract/MatchingEvent.php` | `Matching/Event/Contract/MatchingEvent.php` | Kontrakt bazowy eventów matchingu. | ✅ |
| `Event/Matching/Contract/MatchingEventListener.php` | `Matching/Event/Contract/MatchingEventListener.php` | Interfejs listenera — analogia do strony tokenizacji. | ✅ |
| `Event/Matching/Contract/SequenceBasedEvent.php` | `Matching/Event/Contract/SequenceBasedEvent.php` | Pomocniczy kontrakt eventów operujących na `MatchedSequence`. | ✅ |
| `Event/Matching/Contract/RemovableEventListener.php` | `Matching/Event/Contract/RemovableEventListener.php` | Analogia do strony tokenizacji. | ✅ |
| `Event/Matching/Sequence*.php` + `Matching*Event.php` + `Unmatched*.php` _(6 klas)_ | `Matching/Event/` | Konkretne eventy matchingu — operują wyłącznie na `MatchedSequence`, `Token`, `TokenRegion` i `MatchingContext`. | ✅ |
| `Extension/Tokenization/IdentifyRowsAndColumns.php` | `Tokenization/Extension/IdentifyRowsAndColumns.php` | Rozszerzenie tokenizatora (implementuje `TokenizationEventListener`). Logicznie należy do modułu `Tokenization/`. Można też rozważyć osobny namespace `Tokenization/BuiltIn/`, by odróżnić od rozszerzeń użytkownika. | ✅ |
| `Model/Tokenization/Token.php` | `Tokenization/Model/Token.php` | Model danych tokenizatora. | ✅ |
| `Model/Tokenization/TokenRegion.php` | `Tokenization/Model/TokenRegion.php` | j.w. | ✅ |
| `Model/Tokenization/TokenStream.php` | `Tokenization/Model/TokenStream.php` | j.w. | ✅ |
| `Model/Tokenization/Pattern.php` | `Tokenization/Model/Pattern.php` | j.w. | ✅ |
| `Model/Tokenization/PatternLibrary.php` | `Tokenization/Model/PatternLibrary.php` | j.w. | ✅ |
| `Model/Tokenization/Position.php` | `Tokenization/Model/Position.php` | j.w. Używane też w `IdentifyRowsAndColumns` — spójnie w tym samym module. | ✅ |
| `Model/Matching/MatchedRegion.php` | `Matching/Model/MatchedRegion.php` | Model wyjściowy matchera. | ✅ |
| `Model/Matching/MatchedSequence.php` | `Matching/Model/MatchedSequence.php` | j.w. | ✅ |
| `Model/Matching/MatchedSequenceNode.php` | `Matching/Model/MatchedSequenceNode.php` | j.w. | ✅ |
| `Model/Matching/NestedSequence.php` | `Matching/Model/NestedSequence.php` | j.w. | ✅ |
| `Model/Matching/Sequence.php` | `Matching/Model/Sequence.php` | j.w. | ✅ |
| `Model/Matching/SequenceLibrary.php` | `Matching/Model/SequenceLibrary.php` | j.w. | ✅ |
| `Model/Matching/SequenceNode.php` | `Matching/Model/SequenceNode.php` | j.w. | ✅ |
| `Model/Parsing/NodeInterface.php` | `Parsing/Contract/NodeInterface.php` | Kontrakt węzła — używany przez `NodeFactory`, `NodeAttrFactory`, atrybuty i `Parser`. Publiczne API modułu `Parsing/`. | ✅ |
| `Model/Parsing/NodeAttributeInterface.php` | `Parsing/Contract/NodeAttributeInterface.php` | j.w. — kontrakt atrybutu węzła. | ✅ |
| `Model/Parsing/NodeType.php` | `Parsing/Model/NodeType.php` | Enum używany wyłącznie wewnątrz `Parsing/` (factory, resolver). Można rozważyć `Parsing/Contract/NodeType.php` jeśli ma być częścią publicznego API (np. dla `NodeAttrFactory` od zewnętrznych klientów). | ✅ |
| `Model/Parsing/Placement.php` | `Parsing/Contract/Placement.php` | Enum używany w `NodeInterface::addAttribute()` — jest częścią publicznego API interfejsu, więc razem z `NodeInterface`. | ✅ |
| `Model/AST/AstGraph.php` | `AST/AstGraph.php` | Cały blok AST powinien tworzyć osobny moduł `src/AST/`. Aktualnie jest w środku `Processing/Model/`, co jest bez sensu — to nie jest model przetwarzania, to docelowy produkt parsera. | ✅ |
| `Model/AST/AstNode.php` | `AST/Model/AstNode.php` | j.w. | ✅ |
| `Model/AST/AstEdge.php` | `AST/Model/AstEdge.php` | j.w. | ✅ |
| `Model/AST/Identity/NodeId.php` | `AST/Model/Identity/NodeId.php` | j.w. | ✅ |
| `Model/AST/Identity/EdgeId.php` | `AST/Model/Identity/EdgeId.php` | j.w. | ✅ |
| `Model/AST/Definition/NodeDefinition.php` + pozostałe 10 klas z `Definition/` | `AST/Definition/` | Definicje DSL opisujące strukturę AST i mapowania. Należą do `AST/`, nie do `Processing/`. | ✅ |
| `Model/AST/Factory/AstGraphFactory.php` | `AST/Factory/AstGraphFactory.php` | Fabryka grafu AST — logika `Node → AstGraph`. | ✅ |
| `Model/AST/Factory/NodeTreeFactory.php` | `AST/Factory/NodeTreeFactory.php` | Odwrotne mapowanie `AstGraph → Node`. | ✅ |
| `Model/AST/Context/AstContextStack.php` | `AST/Context/AstContextStack.php` | Pomocniczy kontekst traversalu grafu. | ✅ |
| `Model/AST/Context/AstNodeContext.php` | `AST/Context/AstNodeContext.php` | j.w. | ✅ |
| `Model/AST/Format/AstNodeFormat.php` | `AST/Model/Format/AstNodeFormat.php` | Model formatu węzła AST. | ✅ |
| `Model/AST/Format/FormatValue.php` | `AST/Model/Format/FormatValue.php` | j.w. | ✅ |

---

## Docelowa struktura `src/` po refaktoryzacji

```
src/
├── Parser.php
├── Grammar/          (bez zmian)
├── Shared/           (bez zmian)
├── Tokenization/
│   ├── Lexer.php
│   ├── Context/      (istniejące pliki kompilatora)
│   ├── Contract/
│   │   └── TokenizationContext.php
│   ├── Event/
│   │   ├── Contract/   (4 interfejsy/klasy bazowe)
│   │   └── *.php       (9 konkretnych eventów)
│   ├── Extension/
│   │   └── IdentifyRowsAndColumns.php
│   └── Model/
│       └── (Token, TokenRegion, TokenStream, Pattern, PatternLibrary, Position)
├── Matching/
│   ├── Matcher.php
│   ├── Context/      (istniejące pliki)
│   ├── Contract/
│   │   └── MatchingContext.php
│   ├── Event/
│   │   ├── Contract/   (4 interfejsy/klasy bazowe)
│   │   └── *.php       (6 konkretnych eventów)
│   └── Model/
│       └── (MatchedRegion, MatchedSequence, MatchedSequenceNode, NestedSequence, Sequence, SequenceLibrary, SequenceNode)
├── Parsing/
│   ├── Contract/
│   │   ├── ParsingContext.php
│   │   ├── NodeInterface.php
│   │   ├── NodeAttributeInterface.php
│   │   └── Placement.php
│   ├── Context/      (DefaultParsingContext)
│   ├── Factory/      (NodeFactory, NodeAttrFactory)
│   ├── Model/
│   │   ├── Node.php
│   │   ├── Attribute/  (6 klas atrybutów)
│   │   └── NodeType.php
│   └── Resolver/
└── AST/
    ├── AstGraph.php
    ├── Contract/      (opcjonalnie — jeśli pojawi się interfejs grafu)
    ├── Context/
    │   ├── AstContextStack.php
    │   └── AstNodeContext.php
    ├── Definition/    (11 klas: NodeDefinition, ChildDefinition, EdgeDefinition, …)
    ├── Factory/
    │   ├── AstGraphFactory.php
    │   └── NodeTreeFactory.php
    └── Model/
        ├── AstNode.php
        ├── AstEdge.php
        ├── Format/
        │   ├── AstNodeFormat.php
        │   └── FormatValue.php
        └── Identity/
            ├── NodeId.php
            └── EdgeId.php
```

---

## Uwagi ogólne

- **`Processing/` może zniknąć całkowicie** — żaden plik w nim nie ma własnej logiki; to wyłącznie kontener na modele/kontrakty innych modułów.
- Moduły `Tokenization/`, `Matching/`, `Parsing/` i `AST/` stają się **autonomiczne**: każdy eksponuje własne `Contract/` i `Model/`, nie sięga do wspólnego `Processing/`.
- Zmiana jest czysto strukturalna (przeniesienie + rebasing namespaców) — **logika żadnego pliku nie wymaga modyfikacji**.
- Jedyny ryzykowny punkt to `RemovableEventListener.php` — istnieje zarówno w `Event/Tokenization/Contract/`, jak i `Event/Matching/Contract/`. Jeśli jest to ten sam interfejs (warto sprawdzić), wystarczy jedna kopia w `Shared/Event/` lub rozdzielone kopie w swoich modułach.
