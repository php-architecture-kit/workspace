# lazy-valuation — ustalenia projektowe

Biblioteka łącząca archetypy `pricing` i `accounting` (`var/example/archetypes/{pricing,accounting,quantity}`)
na bazie `lazy-operators` (silnik leniwych, komponowalnych `Expression`). Poniższy dokument to zapis decyzji
podjętych w rozmowie projektowej, zanim ruszył development — punkt odniesienia dla kolejnych sesji.

## Odrzucone / odłożone

- **state-machine nie jest potrzebny na start.** Archetyp pricing (`Calculator`/`Component`) to czyste funkcje
  (`Parameters -> Money`) plus rozgałęzianie warunkowe (piecewise ranges, wybór wersji wg ważności czasowej) —
  nic tam nie wymaga grafu/pointerów/execution z `packages/state-machine`. Ewentualny powrót do tematu: tylko
  jeśli cykl życia wersji `Component` (historia wersji, aktualizacje) okaże się realnie stanowy, a nie tylko
  odczytem "która wersja obowiązuje teraz".
- **Wektory/macierze/liczby zespolone — poza scope w tej fazie.** Nie blokujemy ich architektonicznie na
  przyszłość, ale nie projektujemy teraz. Powód: nie mają naturalnego porządku liniowego (liczby zespolone to
  podręcznikowy przykład ciała, którego nie da się uporządkować zgodnie z działaniami), więc wymuszałyby
  rozdzielenie `Value`/`OrderableValue` już na starcie. Odłożone do czasu, aż faktycznie wejdą w scope.
- **Interval/tolerancja (np. 5.0 ± 0.1 [m], zakres 100–150 PLN)** — rozpoznane jako potencjalnie legalny
  kształt Value (własna, domknięta arytmetyka), ale nie zaprojektowane. Wymaga uporządkowanego Value pod spodem.

## Kluczowe decyzje

### Value
- Interfejs na wzór `Expression` (komponowalny), nie konkretna klasa.
- W tej fazie: **tylko wartości skalarne** — liczba + `Unit`. Bez wielu komponentów wewnętrznych.
- Arytmetyka (`add`/`subtract`/`multiply`/`divide`) jest obowiązkowa.
- `compareTo`/porządek: **zostaje częścią bazowego `Value`** (nie osobny `OrderableValue`) — bo przy scope
  ograniczonym do skalarów każda para z kompatybilną jednostką ma naturalny porządek. Rozdzielenie na
  `OrderableValue` to udokumentowany punkt rozszerzenia, gdyby wektory/macierze kiedyś wróciły do scope'u.
- Reprezentacja samej wielkości liczbowej (native `float`/`int` vs bcmath/GMP) — **otwarte**, patrz "Następny krok".

### Unit
- Interfejs komponowalny, **nie** płaska para symbol+nazwa (tak jak w archetypie Java, `quantity/Unit.java`,
  gdzie `squareMeters()` to osobna, ręcznie wpisana stała — brak `meters() * meters() -> squareMeters()`).
- Reprezentacja: mapa wymiar→wykładnik + współczynnik skali per wymiar bazowy.
  - Przykład: m/s = `{length: 1, time: -1}`; powierzchnia = `{length: 2}`; wielkość bezwymiarowa (%, szt.) = `{}`.
  - Nazwana jednostka (km, m, h, s) wpina się w wymiar bazowy ze współczynnikiem skali względem jednostki
    kanonicznej danego wymiaru (m=1, km=1000) — odróżnia to "niekompatybilne" (różne wymiary) od
    "kompatybilne, różna skala".
- Operacje: `multiply(Unit): Unit` (dodaje wykładniki, mnoży skale), `divide(Unit): Unit` (odejmuje wykładniki),
  `isCompatibleWith(Unit): bool` (te same wymiary, ignorując skalę — do +/− i porównań),
  `conversionFactorTo(Unit): float` (tylko dla kompatybilnych).
- Wykładnik 0 dla danego wymiaru znika z mapy — to daje `5 [m/s] * 7 [s] = 35 [m]` bez specjalnego przypadku.
- **Waluta = osobny wymiar per kod waluty** (`{currency:PLN: 1}` vs `{currency:EUR: 1}`), skala=1, **bez**
  zdefiniowanego `conversionFactorTo` między walutami — PLN i EUR są tak samo niekompatybilne jak metry
  i sekundy. Świadomie **brak wbudowanego kursu wymiany** (kurs to zewnętrzna, zmienna w czasie wartość,
  fundamentalnie inna niż stały współczynnik km→m) — jeśli kiedyś potrzebny, to osobna decyzja/mechanizm
  (dostawca kursu), nie rozszerzenie `conversionFactorTo`.

### Money, Quantity itd.
- **Cienkie fasady fabrykujące** (wzorem `Math`/`Arrays`/`Arithmetic` w lazy-operators), nie równoległa
  hierarchia typów obok `Value`. `Money::pln(100)` / `Quantity::of(5, Unit::meters())` zwracają zwykłe `Value`
  skonfigurowane z odpowiednim `Unit` — bez własnej logiki arytmetycznej do synchronizowania z `Value`.

### Kompozycja Value — kryterium i przypadki
Kryterium: Value może być kompozytem, dopóki kompozycja ma domknięte, dobrze zdefiniowane działania
arytmetyczne (i ew. porządek). Gdy przestaje mieć — to już nie jest jeden Value.

| Przypadek | Status |
|---|---|
| Wektor, liczba zespolona, macierz (ta sama jednostka, wiele komponentów) | poza scope (patrz wyżej) |
| Przedział/zakres, wartość z tolerancją (ta sama jednostka, własna arytmetyka) | rozpoznane, nie zaprojektowane |
| Wpis na fakturze (cena jednostkowa × ilość + podatek) | **nie jest** composite Value — to drzewo `Expression` nad liśćmi `Value`, już wyrażalne |
| Cena jako funkcja czasu (`ContinuousLinearTimeCalculator`) | **nie jest** composite Value — to już rola `Expression` (parametr = czas) |
| Koszyk niekompatybilnych jednostek (portfel, `AccountAmounts`: `Map<AccountId,Money>`) | **nie jest** Value — nazwana kolekcja Value, domena accounting |

### Accounting — osobna biblioteka/kontekst
Sprawdzone w archetypie (`accounting/Account.java`, `Entry.java`, `AccountAmounts.java`, `postingrules/*`):
`Account`/`Entry`/`Transaction` to pełny bounded context z tożsamością, wersjonowaniem (optimistic locking),
event sourcingiem (`DebitEntryRegistered`/`CreditEntryRegistered`), silnikiem reguł księgowań i repozytoriami —
`Money` jest tam tylko typem salda, nie jest z `Account` tożsamy. Decyzja: **accounting nie jest częścią
lazy-valuation** — to osobna biblioteka konsumująca `Value`/`Unit` jako typ kwoty, analogicznie do archetypu.
Powód głębszy niż porządkowy: `Value` ma być bezstanowe i komponowalne jak `Expression`; `Account` ma tożsamość,
mutowalny stan i historię zdarzeń — zlanie ich zepsułoby własność, która czyni `Value` pasującym do
lazy-operators.

## Następny krok
Praca równoległa: aktualizacja `lazy-operators` o obsługę bcmath — dotyczy reprezentacji wielkości liczbowej
w `Value` (patrz otwarty punkt wyżej). Powód: przy pracy nad `Allocation` w lazy-operators trafiliśmy realnie na
błędy precyzji `float` (sumy niezależnie zaokrąglonych liczb różniące się na poziomie bitów mimo identycznego
wyświetlania) — dla Money to nie jest teoretyczny problem, a PHP nie ma natywnego BigDecimal.

## Konsultowane pliki źródłowe (archetypy Java)
`var/example/archetypes/pricing/.../Calculator.java`, `Component.java`; `var/example/archetypes/quantity/.../Unit.java`,
`money/Money.java`; `var/example/archetypes/accounting/.../Account.java`, `Entry.java`, `AccountAmounts.java`,
`postingrules/*`; `var/example/archetypes/planvsexecution/.../tolerance/QuantityTolerance.java`.
