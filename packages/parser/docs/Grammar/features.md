# Features dostępne przy definicji Grammar

> Wykluczone: AST-related (asAstNode, extendAstNode, Definition.*)

---

## Reguły tokenizacji (Lexer)

| Feature | Jak zdefiniować |
|---|---|
| Dosłowny token (literal string) | `Rule::token("name", "[")` |
| Keyword (case-sensitive lub nie) | `Rule::keyword("null")` / `Rule::keyword("IF", caseSensitive: false)` |
| Wyrażenie regularne (regex) | `Rule::expr("name", "regex")` |
| Dynamiczny token (callback triggered by rule) | `Rule::dynamic("name", callable, "triggerRule")` |
| Techniczny token (bof / eof / unknown) | `Rule::technical("bof")` / `Rule::technical("eof")` |
| Priorytet dopasowania reguł | `rule->priority(int)` |

## Reguły parsowania (Node Factory)

| Feature | Jak zdefiniować |
|---|---|
| Sekwencja reguł (ordered) | `Rule::seq("name", "ruleA ruleB ruleC")` |
| Wybór (choice/alternative) | `Rule::choice("name", ["ruleA", "ruleB"])` |
| Dopasowanie wszystkich reguł z danym tagiem | `Rule::taggedWith("tagName")` |

## Składnia sekwencji

| Feature | Składnia |
|---|---|
| Dokładnie jeden | `ruleName` |
| Opcjonalny (0 lub 1) | `?ruleName` |
| Jeden lub więcej | `ruleName+` |
| Zero lub więcej | `ruleName*` |
| Alternatywa w sekwencji | `ruleA\|ruleB` |
| Zagnieżdżona sekwencja / grupowanie | `(ruleA ruleB)\|ruleC` |
| Zagnieżdżona sekwencja z kardynalnością | `(ruleA ruleB)*` |
| Lookahead (match without consuming) | `>ruleName` (ostatni element sekwencji) |
| Lookbehind | `<ruleName` (pierwszy element sekwencji) |
| Anchor (nazwana pozycja) | `ruleName[anchorName]` |
| Trivia / whitespace marker | `-` (kreska) np. `"-* value -*"` |

## Regiony

| Feature | Jak zdefiniować |
|---|---|
| Nowy region | `new Region("name")` |
| Zagnieżdżony region | `parentRegion->add($region)` |
| Zmiana root regionu gramatyki | `$grammar->setRootRegion($region)` |
| Otwarcie regionu przez regułę | `$region->openWith($rule, includeMatch: true)` |
| Zamknięcie regionu przez regułę | `$region->closeWith($rule, negated: false, includeMatch: true)` |
| Zamknięcie gdy reguła NIE pasuje | `$region->closeWith($rule, negated: true)` |
| Root sequence (struktura parsowania regionu) | `$region->withRootSequence("beginArray -* ?items -* endArray")` |
| NodeType dla regionu (Node / Raw / Structure) | `$region->setNodeType(NodeType::Node)` |

## Dziedziczenie

| Feature | Jak zdefiniować |
|---|---|
| Dziedziczenie reguł z global regionu | `$region->enableInheritanceFromGlobal(Region::RULES)` |
| Dziedziczenie regionów z global | `$region->enableInheritanceFromGlobal(Region::REGIONS)` |
| Dziedziczenie event subscribers z global | `$region->enableInheritanceFromGlobal(Region::EVENT_SUBSCRIBERS)` |
| Wyłączenie dziedziczenia z global | `$region->disableInheritanceFromGlobal(Region::RULES)` |
| Dziedziczenie z bezpośredniego rodzica (ancestor) | `$region->enableInheritanceFromAncestor(Region::RULES)` |
| Retokenizacja regionu przez inną gramatykę | `$region->retokenizedByInnerGrammar($grammar)` |
| Scalenie (merge) gramatyki wewnętrznej | `$region->withMergedInnerGrammar($grammar)` |

## Tagi

| Feature | Jak zdefiniować |
|---|---|
| Dodanie tagu do reguły / regionu | `$rule->addTag("tagName")` |
| Usunięcie tagu | `$rule->removeTag("tagName")` |
| Zastąpienie wszystkich tagów | `$rule->replaceTags(["tag1", "tag2"])` |
| Tag w factory rule | `Rule::token("name", "x", tags: ["value"])` |
| Dopasowanie po tagu (jako reguła) | `Rule::taggedWith("tagName")` |

## Eventy

| Feature | Jak zdefiniować |
|---|---|
| Listener na evencie dla konkretnej reguły | `$rule->onEvent(TokenMatchedEvent::class, $listener)` |
| Globalny listener w regionie | `$region->add(EventSubscriber::on(TokenAddedEvent::class, $listener))` |
| Listener tylko dla konkretnej reguły w regionie | `$subscriber->onlyForRuleName("ruleName")` |
| Priorytet listenera | `$subscriber->priority(int)` |

Dostępne typy eventów: `TokenMatchedEvent`, `TokenAddedEvent`, `TokenRegionEndedEvent` oraz dowolne własne `TokenizationEvent` / `MatchingEvent`.

## Middleware (transformacje przy dodawaniu do regionu)

| Feature | Jak zdefiniować |
|---|---|
| Transformacja reguły przed dodaniem do regionu | `AddRuleMiddleware::fromCallable(fn(Rule $r): Rule => ...)` |
| Transformacja regionu przed dodaniem | `AddRegionMiddleware::fromCallable(fn(Region $r): Region => ...)` |
| Transformacja event subscribera | `AddEventSubscriberMiddleware::fromCallable(...)` |
| Dodanie middleware do regionu | `$region->add($middleware)` |

## Inne

| Feature | Jak zdefiniować |
|---|---|
| Wyłączenie wymogu BOF/EOF | `$grammar->requireBofEof = false` |
| Priorytet reguły tokenizacji | `$rule->priority(int)` — wyższy wygrywa przy kolizji |
| Metadata na regionie (arbitrary key-value) | `$region->setMeta("key", $value)` |
