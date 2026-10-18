# Demo `state-machine` — analiza biznesowa i techniczna

> Status: analiza (2026-10-18). Dotyczy `php-architecture-kit/state-machine` 1.7.1 i aplikacji `demo/`.
> Scenariusze: **sterowanie windą** oraz **wypłata gotówki w bankomacie**.

## Stan realizacji (2026-10-18)

Demo jest gotowe: `/state-machine` w `demo/`, zakładki **ATM cash withdrawal** i **Elevator control**,
testy funkcjonalne w `demo/tests/StateMachine/` (`cd demo && php bin/phpunit`).

**Poprawki w paczce** — wydanie patch **1.7.2**, PR
[php-architecture-kit/state-machine#8](https://github.com/php-architecture-kit/state-machine/pull/8)
(CI zielone, czeka na merge i tag `1.7.2`):

- `AwaitStateComponent` i `AsyncComponent` bezpieczne w cyklach przy zapisywanym `Execution`
  (§6.1, ważność wydawana przez węzeł przy każdej wizycie; stare wyniki zadań są ignorowane).
- Wyjście `expired` w `AsyncComponent` działa (§6.6, błąd obecny też w 1.7.1).
- Wbudowane handlery paczki są tworzone automatycznie, gdy kontener ich nie ma. Dzięki temu
  nowy `AwaitStateNodeHandler` nie wymaga rejestracji i zmiana jest zgodna wstecz.
- README zgodne z kodem (§6.3); `branch-alias` `dev-main` → `1.7.x-dev`.

**Decyzje:**

1. Patch, a nie minor: brak zmian łamiących. `AsyncTaskHandledNotifier` zwraca `bool` zamiast
   `void`, co nie wpływa na istniejące wywołania.
2. Granica limitu czasu (`>` → `>=`, §6.7): **nie w patchu**. Zmienia czas działania już
   uruchomionych procesów, więc trafi do wydania minor.
3. `RaceFirstComponent` / `AwaitAllComponent` w cyklach: **osobne zadanie**. Najpierw projekt
   pojęcia „rundy” (kilka wskaźników, brak węzła startowego), potem implementacja.

**Czego demo świadomie nie używa w cyklach:** `RaceFirstComponent` i `AwaitAllComponent` nadal nie
są bezpieczne w pętli (§6.1, punkt otwarty). Dlatego bankomat ma jedno `Execution` na klienta
(fork + await all wykonują się raz), a drzwi windy to `AwaitStateComponent` z dwoma wyjściami
(przycisk „zamknij” albo upływ czasu), a nie wyścig dwóch wskaźników.

---

## Część I — analiza biznesowa

### 1. Cel strony demo

Strona ma pokazać to, czego nie widać w README: że maszyna stanów z tej paczki **żyje między
requestami**. Zatrzymuje się na zdarzeniach z zewnątrz (przycisk, karta, odpowiedź banku, upływ
czasu), zapisuje się, wznawia, a wiele wskaźników (pointerów) może przechodzić przez graf równolegle.

Odbiorca: programista PHP, który zna Symfony Workflow i zastanawia się, czym ta paczka się różni.
Po 2–3 minutach klikania powinien rozumieć:

| Chcemy pokazać | Dlaczego to ważne | Gdzie w scenariuszach |
|---|---|---|
| Graf węzłów i przejść, a nie lista „places” | podstawowy model paczki | oba |
| Zawieszenie (`Suspended`) i wznowienie po zdarzeniu z zewnątrz | główny kontrakt paczki (Task ↔ State ↔ TransitionCondition) | oba |
| Warunki przejść z trzema wynikami: `Accepted` / `Wait` / `Rejected` | „Wait” odróżnia paczkę od guardów typu tak/nie | oba |
| Limity czasu (`AwaitStateComponent`, wyjście `expired`) | realne procesy mają limity czasu | drzwi windy, PIN, odbiór gotówki |
| Zadania asynchroniczne (`AsyncComponent`: `success` / `fail` / `expired`) | integracja z systemem zewnętrznym | autoryzacja w banku |
| Równoległe wskaźniki: fork (AND-split) i join (AND-join) | widać, że to nie jest klasyczny FSM z jednym stanem | wydruk potwierdzenia ‖ liczenie banknotów |
| Wyścig (`RaceFirstComponent`) | wygrywa pierwsze zdarzenie | drzwi: limit czasu vs przycisk „zamknij” |
| Cykle | procesy, które się powtarzają | winda (cały czas), ponowne wpisanie PIN-u |
| Kompensacja | wycofanie operacji, kiedy coś pójdzie źle | cofnięcie gotówki → reversal transakcji |
| Zdarzenia domenowe (`PointerTransitioned`, `StateModified`, …) | audyt i debugowanie | dziennik zdarzeń na stronie |

### 2. Scenariusz A — wypłata w bankomacie

#### 2.1 Aktorzy i urządzenia

- **Klient** — wkłada kartę, wpisuje PIN, wybiera kwotę, odbiera kartę, gotówkę i potwierdzenie.
- **Bankomat** — czytnik kart, klawiatura PIN, dyspenser, drukarka, kaseta z banknotami.
- **Bank (host autoryzacyjny)** — weryfikuje PIN i środki; odpowiada asynchronicznie albo wcale.
- **Czas** — limity na każdą interakcję z klientem.

#### 2.2 Przebieg główny (happy path)

1. Ekran powitalny → klient wkłada kartę.
2. Odczyt karty → OK.
3. Wpisanie PIN-u (limit czasu 30 s).
4. Wybór kwoty: szybkie kwoty albo „inna kwota”.
5. Walidacja lokalna: wielokrotność 50 zł, kaseta ma takie banknoty, nie przekracza limitu jednorazowego.
6. Autoryzacja w banku (PIN + saldo + limit dzienny), zadanie asynchroniczne.
7. **Najpierw karta, potem gotówka** — bankomat wydaje kartę i czeka, aż klient ją zabierze.
   To zasada bezpieczeństwa: klient nie zapomni karty.
8. Równolegle: liczenie banknotów ‖ wydruk potwierdzenia (jeśli klient go chciał). Join.
9. Gotówka w szczelinie (limit czasu 30 s) → klient ją zabiera → koniec, powrót do ekranu powitalnego.

#### 2.3 Ścieżki alternatywne (to one robią wrażenie)

| # | Sytuacja | Reakcja | Pokazywana funkcja paczki |
|---|---|---|---|
| A1 | Karta nieczytelna | wysunięcie karty, koniec | `ChoiceComponent` |
| A2 | Klient nie wpisze PIN-u w 30 s | wysunięcie karty | `AwaitStateComponent` → `expired` |
| A3 | Zły PIN, 1. lub 2. raz | komunikat, powrót do wpisywania PIN-u | **cykl** |
| A4 | Zły PIN po raz 3. | **zatrzymanie karty** | `ChoiceComponent` + licznik w `State` |
| A5 | Bank odrzuca (brak środków / limit) | komunikat z powodem, wysunięcie karty | `AsyncComponent` → `fail` |
| A6 | Bank nie odpowiada | anulowanie, wysunięcie karty | `AsyncComponent` → `expired` |
| A7 | Klient nie zabierze karty | karta zostaje zatrzymana, gotówka nie jest wydawana | limit czasu |
| A8 | Klient nie zabierze gotówki w 30 s | **cofnięcie gotówki + reversal w banku** | kompensacja (saga) |
| A9 | Klient anuluje na dowolnym ekranie | wysunięcie karty | wyścig: akcja klienta vs `cancel` |

#### 2.4 Reguły biznesowe (parametry demo)

- Nominały w kasecie: 50, 100, 200 zł; kwota musi się dać złożyć z dostępnych banknotów.
- Limit jednorazowy 2000 zł, dzienny limit karty 3000 zł (sprawdza „bank”).
- 3 próby PIN-u; licznik przypisany do karty, nie do sesji.
- Wszystkie limity czasu interakcji: 30 s (w demo czas symulowany, patrz §8).

#### 2.5 Panel „Bank” w demo

Użytkownik demo gra jednocześnie klienta i bank. Każde zadanie wysłane przez maszynę
(`DeferredTaskBus`) pojawia się w skrzynce z przyciskami **Zatwierdź / Odrzuć / Nie odpowiadaj**.
Tak widać na żywo kontrakt Task → TaskHandler → `AsyncTaskHandledNotifier` → wznowienie.

### 3. Scenariusz B — sterowanie windą

#### 3.1 Aktorzy i urządzenia

- **Pasażerowie** — przyciski wezwania na piętrach (▲/▼) i przyciski pięter w kabinie.
- **Kabina** — silnik, czujnik piętra, waga (przeciążenie).
- **Drzwi** — otwieranie i zamykanie, fotokomórka (przeszkoda), przyciski „otwórz” i „zamknij”.
- **Bezpieczeństwo** — STOP awaryjny, tryb serwisowy.
- **Czas** — czas otwarcia drzwi, czas przejazdu między piętrami.

#### 3.2 Model zachowania (sterowanie zbiorcze, algorytm SCAN)

Winda jedzie w bieżącym kierunku, dopóki przed nią są zgłoszenia. Zatrzymuje się na piętrach
z zgłoszeniem z kabiny albo z wezwaniem w tym samym kierunku. Kierunek zmienia, kiedy przed nią
nic już nie ma. Bez zgłoszeń stoi z zamkniętymi drzwiami.

Cykl pracy (to jest graf maszyny):

```
Bezczynna ──(jest zgłoszenie)──▶ Wybór kierunku ──▶ Jazda o 1 piętro ──▶ Czy zatrzymać się?
    ▲                                                   ▲                    │ nie      │ tak
    │                                                   └────────────────────┘          ▼
    │                                                                         Otwieranie drzwi
    │                                                                                   ▼
    │                                                        Drzwi otwarte: wyścig {limit 5 s | „zamknij”}
    │                                                                                   ▼
    │                               przeszkoda / przeciążenie ◀── Zamykanie drzwi ──(fotokomórka)
    │                                                                                   ▼
    └─────────────────────────(brak zgłoszeń)────────────── Obsłużono piętro ──(są zgłoszenia)──▶ Wybór kierunku
```

#### 3.3 Ścieżki alternatywne

| # | Sytuacja | Reakcja | Pokazywana funkcja paczki |
|---|---|---|---|
| B1 | Przeszkoda w drzwiach podczas zamykania | ponowne otwarcie, nowy limit czasu | cykl + `AwaitState` |
| B2 | Przycisk „zamknij” przy otwartych drzwiach | natychmiastowe zamknięcie | `RaceFirstComponent` |
| B3 | Przeciążenie | drzwi zostają otwarte, alarm, czeka na zmniejszenie wagi | warunek `Wait` |
| B4 | STOP awaryjny | wszystko staje, dopóki ktoś go nie zwolni | drugi pointer pilnujący bezpieczeństwa (fork na starcie) |
| B5 | Nowe wezwanie w trakcie jazdy | dopisane do kolejki, uwzględnione na następnym piętrze | `State` modyfikowany z zewnątrz |

#### 3.4 Reguły (parametry demo)

- 1 kabina, 6 pięter (0–5). Kilka kabin to kilka `Execution`, a nie kilka wskaźników — można to
  wspomnieć w opisie, ale nie budować.
- Drzwi otwarte 5 s, przejazd o jedno piętro 2 s, maks. 8 osób (prosty licznik „wsiada” / „wysiada”).

### 4. Proponowany układ strony

Ten sam szablon dla obu scenariuszy, z przełącznikiem (zakładki) **Bankomat | Winda**:

```
┌───────────────────────────────┬────────────────────────────────────────┐
│  SYMULACJA                    │  GRAF MASZYNY                          │
│  (ekran i klawiatura bankomatu│  węzły + przejścia, aktywne wskaźniki   │
│   / szyb windy z kabiną)      │  podświetlone; etykiety warunków        │
│                               │  z wynikiem Accepted/Wait/Rejected      │
├───────────────────────────────┼────────────────────────────────────────┤
│  STEROWANIE CZASEM            │  STAN (States)   │ DZIENNIK ZDARZEŃ    │
│  ⏵ auto  ⏭ +1 s  ⏩ +30 s      │  JSON / tabela   │ PointerTransitioned  │
│  [Bank: oczekujące zadania]   │                  │ StateModified …      │
└───────────────────────────────┴────────────────────────────────────────┘
```

Przyciski „przewiń do przodu” są kluczowe: bez nich ścieżki z limitami czasu (A2, A6, A7, A8, B1)
wymagałyby czekania.

---

## Część II — analiza techniczna

### 5. Co paczka daje dziś (stan 1.7.1)

| Element | Klasa | Uwagi do demo |
|---|---|---|
| Silnik | `Foundation\StateMachine` | definicję buduje się w konstruktorze lub metodzie; `execute(Execution, ?maxIterations)` |
| Instancja procesu | `Execution` = `Pointers` + `States` | **`serialize()` działa** (sprawdzone: ~5 KB, wznowienie OK) |
| Węzeł | `Node` (wymaga `globallyUniqueName` w formacie `vendor.module.purpose`) + `NodeHandlerInterface` z kontenera PSR-11 | handler zwraca `Continue` / `Suspended` |
| Warunki | `TransitionCondition::check(States)` → `Accepted` / `Wait` / `Rejected` | domknięcia są w grafie, nie w `Execution`, więc nie przeszkadzają w serializacji |
| Wybór przejść | `AllValidTransitionsStrategy` (domyślna), `FirstValidTransitionStrategy` | |
| Ruch wskaźników | `AllPointersUntilBlockedStrategy` (domyślna), `AllPointersStepStrategy` | dla demo „krok po kroku” lepsza jest **Step** |
| Komponenty | `ChoiceComponent` (XOR), `ForkComponent` (AND-split), `AwaitAllComponent` (AND-join), `RaceFirstComponent`, `AwaitStateComponent` (limit czasu), `AsyncComponent` | każdy przyjmuje `ClockInterface`, więc da się podstawić zegar symulowany |
| Zadania | `Task`, `DeferredTaskBus` (zbiera koperty), `ImmediateTaskBus`, `AsyncTaskHandledNotifier::success/fail` | koperty z `DeferredTaskBus::$defferedTasks` to gotowa „skrzynka banku” |
| Zdarzenia | `Pointers` / `States` to `AggregateRoot` → `getEvents()` / `releaseEvents()` | źródło dziennika na stronie |
| Widok definicji | `Infrastructure\Mapper\View\StateMachineViewMapper` → `StateMachineView::toArray()` | węzły i przejścia jako JSON; brak danych runtime (wskaźniki, decyzje) |

Testy paczki: 383/383 zielone (PHP 8.5.4).

### 6. Problemy w paczce wykryte podczas analizy

Sprawdzone krótkimi skryptami (spike) albo przez czytanie kodu.

Założenie: paczka ma działać z **zapisywanym `Execution`** (zapis → wznowienie w kolejnym
requeście lub wiadomości), zgodnie z `docs/workflow-ux-research.md`. Problemy z 6.1 nie są
specyficzne dla demo. Dotyczą każdego procesu, który ma cykl i jest wznawiany po zapisie.
W trybie jednoprzebiegowym (nowe `Execution` przy każdym uruchomieniu, `ImmediateTaskBus`) nie
występują. Dlatego dotychczasowe użycie (formularz CLI) ich nie ujawniło.

#### 6.1 Komponenty nie są bezpieczne w cyklach przy zapisywanym `Execution`

Komponenty zapisują swój stan pomocniczy w stanie technicznym (`__technical`) pod kluczem
zależnym **tylko od id węzła**. Ponowne wejście w komponent nie odnawia tego stanu:

| Komponent | Klucz | Skutek przy drugim przejściu pętli |
|---|---|---|
| `AwaitStateComponent` | `<nodeId>.expiresAt` (`AwaitStateComponent.php:78-105`) | stary termin wygaśnięcia → **natychmiastowe `expired`** |
| `AsyncComponent` | `<nodeId>.executionResult` (`CreateAsyncTaskNode.php:31`) | wynik poprzedniego zadania → **natychmiastowe `success`/`fail` bez czekania** |
| `RaceFirstComponent` | `<nodeId>.winner` (`RaceFirstComponent.php:45`) | zwycięzca już jest → **wszystkie wskaźniki przegrywają i znikają** |
| `AwaitAllComponent` | `<nodeId>.<branch>.arrived` (`AwaitAllArrivalNode.php:25`) | join przepuszcza bez czekania na wszystkie gałęzie |

Odtworzone:

- `AwaitStateComponent` (limit 10 s): pierwsze okrążenie trwa 8 s, w drugim po 5 s wskaźnik już
  jest na wyjściu `expired`.
- `AsyncComponent` w pętli „sprawdź → poproś o naprawę → sprawdź ponownie” (wyniki: *fail*, potem
  *success*):
  - `ImmediateTaskBus`: działa poprawnie, bo nowy wynik nadpisuje stary, zanim warunek go przeczyta.
  - `DeferredTaskBus`: po ponownym wysłaniu zadania maszyna od razu bierze stary *fail* i wraca do
    „poproś o naprawę”, a późniejszy *success* przepada.

`RaceFirst` i `AwaitAll` wynikają z tego samego wzorca w kodzie (nieodtworzone).

Wpływ na demo: drzwi windy (każdy cykl), ponowne wpisanie PIN-u i autoryzacja w bankomacie.

**Kierunek poprawki (uzgodniony):**

- Ważność jest zapisana **w samym stanie**.
- Wydaje ją **węzeł**, a nie mechanizm w rdzeniu.
- Silnik nie dostaje nowego mechanizmu czyszczenia.
- Stan zostaje współdzielony w całym grafie: stała nazwa klucza, widoczny z każdego miejsca.

Handler węzła wykonuje się dokładnie raz przy każdym wejściu wskaźnika, więc to on wydaje lub
odnawia wartość. Warunki przejść tylko ją sprawdzają.

- `AwaitStateComponent`: własny węzeł z handlerem, który przy każdym wejściu zapisuje
  `expiresAt = now + timeout`. Dziś termin tworzy warunek i tylko wtedy, gdy klucza jeszcze nie ma.
- `AsyncComponent`: handler wysyłający zadanie zapisuje id zadania, na które czeka. Notifier
  zapisuje wynik razem z id zadania z koperty. Warunek uznaje tylko wynik z pasującym id. Spóźniona
  odpowiedź na poprzednie zadanie jest wtedy ignorowana.
- `RaceFirstComponent`, `AwaitAllComponent`: **otwarte**. Runda obejmuje kilka wskaźników i nie ma
  węzła, który oznacza jej start. `TransitionCondition::check(States)` nie zna wskaźnika. Opcje:
  logika w handlerach węzłów komponentu albo kontekst wskaźnika w warunkach (zmiana interfejsu).
  Do osobnego projektu.

#### 6.2 `AllPointersUntilBlockedStrategy` + cykl = nieskończona pętla w `execute()`

`handlePointerOnPath()` wykonuje do `PHP_INT_MAX` kroków (`StateMachine.php:118`). Jeśli warunki
w cyklu ciągle są `Accepted`, `execute()` nigdy się nie kończy. `maxIterations` ogranicza tylko
pętlę zewnętrzną, więc tu nie pomaga. W spike'u proces trzeba było zabić.

Propozycja: limit kroków na wskaźnik w jednym `execute()` z wyjątkiem lub statusem (np.
`StateMachineConfig::$maxStepsPerExecute`). Demo i tak użyje `AllPointersStepStrategy`, ale bez
zabezpieczenia błąd w definicji maszyny zawiesza request.

#### 6.3 README nie zgadza się z kodem

- `SwitchCaseComponent` / `ParallelComponent` → w kodzie `ChoiceComponent` / `ForkComponent`
  (z obowiązkowym `uniqueName`).
- Porty `AwaitStateComponent`: w kodzie `input->at`, `output->run` / `output->expired`
  (README: `trigger` / `done`). `AsyncComponent` ma wyjścia `success` / `fail` / `expired`.
- Wzorzec z README `addDefinition()`, a potem `addTransition($x, $component->input->…)` **rzuca**
  `VertexNotInGraphException`, bo kompilator usuwa porty. Działa tylko
  `$component->input->at->attach($node->id)` **przed** `addDefinition()`.
- Quick Start: `new SendEmailNode()` bez `globallyUniqueName` się nie skompiluje.

Demo i tak będzie pierwszym działającym przykładem, więc README warto poprawić w tym samym ruchu.

#### 6.4 Braki ważne dla wizualizacji

- `StateMachine::addTransition()` nie przyjmuje `tags`, choć `Transition::create()` je obsługuje.
  Krawędzie w widoku nie mają etykiet, bo `TransitionConditionView` zawiera tylko klasę, a dla
  domknięć to zawsze `TransitionConditionCallback`.
  → Propozycja: parametr `tags` w `addTransition()`, a w demo nazwane klasy warunków
  (`PinAttemptsExhausted`, `CashTaken`, …), których nazwa jest etykietą.
- Komponenty rozwijają się w wiele wewnętrznych węzłów `state-machine.*` (porty, passthrough,
  race). Graf trzeba grupować lub zwijać po prefiksie nazwy komponentu.
- `ExecutionStatus::Running` znaczy „był postęp”, a nie „nie czeka”. UI powinien pokazywać, na co
  czeka każdy wskaźnik (przejścia z wynikiem `Wait`), a nie sam status.

#### 6.5 Drobne (nie blokują)

Literówka w API publicznym: `Task\Attribute\Defferred`, `DeferredTaskBus::$defferedTasks`.
Poprawka wymaga zmiany łamiącej kompatybilność (BC break) albo aliasu.

#### 6.6 Wyjście `expired` w `AsyncComponent` nigdy nie działało (znalezione przy budowie demo)

Wbudowany `AwaitStateComponent` miał niepodłączony port `expired`. Kompilator traktuje taki port
jako ślepy zaułek i usuwa całą ścieżkę do wyjścia `expired` komponentu `AsyncComponent`.
Po przekroczeniu limitu czasu wskaźnik nie miał już żadnego przejścia i był usuwany, więc
`Execution` kończyło się po cichu, zamiast pójść ścieżką `expired`. Potwierdzone także na 1.7.1.
Poprawione w osobnym commicie, z testem `AsyncTimeoutFeatureTest`.

#### 6.7 Drobne obserwacje z budowy demo (bez zmian w paczce)

- **Limit czasu kończy się sekundę później, niż sugeruje nazwa.** `AwaitStateComponent` uznaje
  limit za przekroczony przy `now > expiresAt`, a nie `>=`. Przy pełnych sekundach limit 5 s
  kończy się w 6. sekundzie (w windzie: jazda „2 s” trwa 3 tyknięcia). Zmiana na `>=` jest
  jednoznakowa, ale zmienia zachowanie, więc zostawiam to do decyzji.
- **`AwaitStateComponent::create()` zwraca `SingleNodeDefinition`.** Typowane właściwości
  `$at`, `$run`, `$expired` klasy `AwaitStateComponent` nigdy nie są używane, a porty są dostępne
  tylko przez `->input->at` / `->output->run`.
- **Warunkowa gałąź `ForkComponent` + `AwaitAllComponent` się blokuje.** Join czeka na
  wszystkie zadeklarowane gałęzie, więc gałąź odrzucona przez warunek forka blokuje go na zawsze.
  W demo gałąź „receipt” zawsze się wykonuje, a o druku decyduje węzeł.
- **Mieszanie `Wait` i `Accepted` na jednym węźle forkuje wskaźnik** (`WaitAndForkStrategy`),
  także przy `FirstValidTransitionStrategy`, bo przekazuje zebrane wcześniej `Wait`. Demo używa
  warunków z jedną wspólną decyzją (`Route`), żeby gałęzie się wykluczały. Warto to opisać w README.

### 7. Architektura modułu w `demo/`

Zgodnie z archetypem z `packages/clean-architecture` i istniejącymi modułami `Uuid` i `LazyOperators`:

```
demo/src/StateMachine/
├── Domain/
│   ├── Atm/            AtmWorkflow (extends StateMachine), węzły, handlery, nazwane warunki, Taski
│   └── Elevator/       ElevatorWorkflow, węzły, handlery, warunki, polityka SCAN (czysta funkcja)
├── Application/
│   ├── ScenarioRunner.php        load → apply action → execute (krokami) → snapshot → save
│   ├── SimulationClock.php       PSR-20, czas przechowywany w sesji, „przewiń o N s”
│   ├── TaskInbox.php             koperty z DeferredTaskBus między requestami
│   └── Snapshot/                 DTO: pointers, waitingOn, states, events, pendingTasks
├── Infrastructure/
│   ├── SessionExecutionStore.php serialize(Execution) w sesji (bez bazy danych)
│   └── HandlerLocator.php        PSR-11 z #[AutowireLocator] na otagowane handlery
└── Presentation/
    ├── Controller/StateMachineController.php   GET strona, POST /api/{scenario}/{action}
    └── View/GraphViewFactory.php               StateMachineViewMapper + grupowanie + układ
```

Decyzje:

- **Trwałość: sesja + `serialize()`** — zgodnie z `demo/AGENTS.md` (najmniejsza opcja, bez Doctrine).
  Stan zajmuje kilka KB. Przycisk „Reset” zaczyna nowe `Execution`.
- **Kontener dla handlerów:** Symfony `ServiceLocator` przez `#[AutowireLocator]` — handlery jako
  zwykłe autowirowane serwisy, bez publicznych definicji w YAML.
- **Czas:** `SimulationClock` wstrzykiwany do komponentów przy budowie maszyny w każdym requeście.
  Komponenty łapią zegar w domknięciu przy `create()`, więc maszynę trzeba budować na request.
  Przy jednej definicji to i tak tanie.
- **Wykonanie:** `AllPointersStepStrategy`, `execute($execution, 1)` w pętli do braku postępu
  (z limitem). Każdy krok jest zapisywany w snapshotach, więc frontend może animować przejścia.
- **Zdarzenia:** `releaseEvents()` z `Pointers` i `States` po każdym kroku → dziennik.
- **API:** jeden endpoint akcji (`insertCard`, `enterPin`, `bank.approve`, `tick {seconds}`,
  `callFloor {floor, dir}`, …) zwraca pełny snapshot. Wzorzec ten sam co w `LazyOperatorsController`.
- **Frontend:** React/TSX jak w istniejących stronach. Graf: **ręcznie ustawione współrzędne**
  per scenariusz (2 grafy po kilkanaście węzłów logicznych — czytelniej niż automatyczny układ
  i bez nowej zależności), krawędzie w SVG. Symulacje: komponenty `AtmScreen` i `ElevatorShaft`.

### 8. Mapowanie scenariuszy na elementy paczki

**Bankomat**

| Krok | Implementacja |
|---|---|
| czekanie na kartę, PIN, kwotę, odbiór | `AwaitStateComponent` (stan `card`, `pin`, `amount`, `cash_taken`), limit 30 s |
| weryfikacja PIN-u i środków | `AsyncComponent` + `AuthorizeWithdrawalTask` → panel „Bank” → `AsyncTaskHandledNotifier` |
| zły PIN / 3. próba | `ChoiceComponent` z warunkami `PinAttemptsLeft` / `PinAttemptsExhausted`, cykl do kroku PIN |
| karta → gotówka | sekwencja: `AwaitState(card_taken)` przed dyspenserem |
| liczenie ‖ wydruk | `ForkComponent` (gałąź `receipt` warunkowa) + `AwaitAllComponent` |
| brak odbioru gotówki | `expired` → `RetractCashNode` → `AsyncComponent(ReverseTransactionTask)` |
| anulowanie | `RaceFirstComponent` między akcją klienta a `cancel` |

**Winda**

| Krok | Implementacja |
|---|---|
| kolejka zgłoszeń | `State` `calls` modyfikowany przez akcje użytkownika (z zewnątrz, między `execute`) |
| bezczynność | warunek `HasPendingCalls` → `Wait` |
| kierunek / zatrzymanie | `ChoiceComponent` + czysta funkcja `ScanPolicy` w Domain |
| jazda | węzeł `MoveOneFloor` + `AwaitState` z limitem = czas przejazdu (zdarzenie `arrived` z ticka) |
| drzwi | `RaceFirstComponent` { `AwaitState` limit 5 s → `expired` ‖ przycisk „zamknij” } |
| fotokomórka / przeciążenie | warunki `Wait` / powrót do „Otwieranie drzwi” (cykl) |
| STOP | drugi wskaźnik od startu (`ForkComponent`), który pilnuje stanu `emergency`; główna gałąź ma warunek `Wait`, dopóki STOP jest aktywny |

### 9. Plan realizacji

| Faza | Zakres | Gdzie |
|---|---|---|
| 0 | Poprawki 6.1 (komponenty w cyklach), 6.2 (limit kroków), `tags` w `addTransition`, README → **state-machine 1.8.0** | repo paczki (PR) |
| 1 | Szkielet modułu: `ScenarioRunner`, `SimulationClock`, `SessionExecutionStore`, `TaskInbox`, snapshot, graf | `demo/` |
| 2 | **Bankomat** — najpierw, bo jest liniowy i używa wszystkich komponentów | `demo/` |
| 3 | **Winda** — mocno zależy od cykli, więc po fazie 0 | `demo/` |
| 4 (opcjonalnie) | Warunki jako wyrażenia `lazy-operators` (np. limit dzienny, przeciążenie) — połączenie dwóch dem | `demo/` |

Do każdego scenariusza testy funkcjonalne (`WebTestCase`): pełna ścieżka główna i co najmniej
A3/A4, A8, B1, B2 jako testy regresji dla poprawek z fazy 0.

### 10. Decyzje do podjęcia

1. ~~Poprawiać paczkę czy obejść problemy w demo?~~ **Rozstrzygnięte:** poprawiamy paczkę.
   Wymaganiem jest zapisywane `Execution`. Kierunek poprawki opisuje §6.1.
   Otwarte: `RaceFirst` / `AwaitAll`.
2. **Kolejność:** bankomat → winda (rekomendacja) czy odwrotnie?
3. **Zakres windy:** 1 kabina / 6 pięter wystarczy? Czy STOP awaryjny jako drugi wskaźnik jest w zakresie?
4. **Czas:** tylko ręczne „przewiń” czy też tryb auto (tick co 1 s z frontendu)?
