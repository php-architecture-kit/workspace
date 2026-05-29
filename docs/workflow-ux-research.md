# Raport porównawczy: Symfony Workflow vs Temporal PHP SDK

> Cel: zebranie API obu bibliotek z przykładami PHP jako baza do zaprojektowania ulepszeń UX paczki `php-architecture-kit/state-machine`.

---

## 0. Przypadki użycia paczki `php-architecture-kit/state-machine`

### Przypadki użycia

| # | Przypadek | Charakter wykonania | Kluczowe cechy |
|---|---|---|---|
| 1 | Interaktywny formularz CLI do setupu środowiska wielu repozytoriów | synchroniczny, sekwencyjny | czekanie na input użytkownika, warunki rozgałęziające |
| 2 | Tablica kanban / workflow dokumentu | event-driven, długo żyjący | named transitions, guardy per rola, historia zmian |
| 3 | Pipeline wieloetapowego zadania przez AI | asynchroniczny, równoległy | fork/join, timeout na krok, retry, częściowe wyniki |
| 4 | Saga — kompensacja rozproszonych operacji | asynchroniczny | kompensacja kroków, rollback, at-least-once delivery |

### Przekrojowe wymagania

**Persistence** — Execution musi być serializowalny i odtwarzalny w późniejszym czasie (branch w toku, bez approve). Oznacza to że paczka nie może zakładać ciągłości procesu PHP między krokami.

**Asynchroniczność z AMQP** — paczka musi działać jako element procesu asynchronicznego: wiadomość przychodzi z kolejki → odtwórz Execution → wykonaj krok → zapisz Execution → wyślij następną wiadomość. Cykl życia to jedno żądanie/wiadomość, nie ciągły proces.

### Co to oznacza dla modelu wykonania

```
[zewnętrzny trigger: HTTP / AMQP / CLI]
        │
        ▼
 odtwórz Execution (z persistence)
        │
        ▼
  machine.execute(execution)
        │
   ┌────┴────┐
   │         │
Completed  Suspended ──► zapisz Execution ──► (opcjonalnie) wyślij wiadomość AMQP
```

Stan `Suspended` jest kluczowy — to punkt zapisu i przekazania sterowania do zewnętrznego systemu. Każdy use case zawiesza się z innego powodu:

| Use case | Powód zawieszenia |
|---|---|
| CLI form | czekanie na input użytkownika |
| Kanban | czekanie na akcję użytkownika (zmiana kolumny, approve) |
| AI pipeline | czekanie na wynik zadania AI (odpowiedź z kolejki) |
| Saga | czekanie na potwierdzenie operacji w zewnętrznym serwisie |

### Mechanizm wznowienia — Task + States + TransitionCondition

Wznowienie Execution po zawieszeniu nie wymaga osobnego mechanizmu "sygnałów". Kontrakt leży w parze **Task ↔ TaskHandler ↔ TransitionCondition**:

```
NodeHandler → dispatchTask(PaymentTask) → return Suspended
     │
     ▼  (AMQP / CLI / HTTP — zewnętrzny transport)
TaskHandler przetwarza PaymentTask
     │
     ▼
$execution->states->defineState('payment_result', [
    new StateDetail('status', 'confirmed'),
    new StateDetail('amount', 150_00),
])
     │
     ▼
$sm->execute($execution)
     │
     ▼
TransitionCondition: getState('payment_result') !== null → Accepted
```

NodeHandler wie jakiego Task wysłać. TaskHandler wie jaki State zapisać. TransitionCondition wie na jaki State czekać. Trzy elementy pisane razem dla konkretnego węzła — discoverability przez kod, nie przez zewnętrzny kontrakt.

### Selektywna persystencja States — StateResolver

Nie wszystkie States powinny być utrwalane. StateResolver to warstwa hydration/dehydration decydująca per-State co trafia do persistence:

| Typ stanu | Przykład | Persystowany |
|---|---|---|
| Wynik taska | `payment_result` z kwotą i statusem | tak |
| Dane zebrane od użytkownika | odpowiedzi z formularza CLI | tak |
| Stan biznesowy do wznowienia | bieżący krok onboardingu | tak |
| Runtime context | bieżący user, dane requestu | nie |
| Wartości obliczalne | pochodne innych stanów | nie |
| Flagi tymczasowe | aktywne tylko w trakcie jednego `execute()` | nie |

**Kontrast z Temporal:** Temporal wymusza event sourcing na _całym_ stanie pól klasy workflow — wszystko co jest polem klasy jest automatycznie serializowane i odtwarzane przez replay. Wymaga to dyscypliny żeby nie wciągnąć do pól workflow obiektów nieserializowalnych lub zależności infrastrukturalnych. StateResolver daje jawną, per-state kontrolę nad tym co trafia do persistence — bez narzucania modelu całej klasie.

### Czego paczka nie aspiruje robić (w odróżnieniu od Temporal)

- Nie zarządza własną infrastrukturą (brak serwera, brak workera)
- Nie ma wbudowanego event sourcingu ani replaya historii
- Nie obsługuje długo żyjących workflow w skali distributed systems
- Retry, AMQP, scheduling — odpowiedzialność warstwy infrastruktury aplikacji

---

## 1. Symfony Workflow Component

### 1.1 Definicja workflow — PHP programmatic API

```php
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

$builder = new DefinitionBuilder();
$builder->addPlaces(['draft', 'review', 'published']);
$builder->addTransition(new Transition('to_review', 'draft',  'review'));
$builder->addTransition(new Transition('publish',   'review', 'published'));
$definition = $builder->build();

$markingStore = new MethodMarkingStore(true, 'currentPlace');
$workflow = new Workflow($definition, $markingStore, $dispatcher, 'blog_publishing');
```

### 1.2 Definicja workflow — YAML (najczęściej używana forma)

```yaml
# config/packages/workflow.yaml
framework:
    workflows:
        blog_publishing:
            type: workflow          # lub state_machine
            marking_store:
                type: method
                property: currentPlace
            supports:
                - App\Entity\BlogPost
            initial_marking: draft
            places:
                - draft
                - review
                - published
            transitions:
                to_review:
                    from: draft
                    to:   review
                publish:
                    from: review
                    to:   published
```

### 1.3 Definicja — PHP config builder (Symfony 7+)

```php
// config/packages/workflow.php
return static function (FrameworkConfig $framework): void {
    $wf = $framework->workflows()->workflows('blog_publishing');
    $wf->type('state_machine');
    $wf->supports([BlogPost::class]);
    $wf->initialMarking(['draft']);
    $wf->markingStore()->type('method')->property('currentPlace');

    $wf->place()->name('draft');
    $wf->place()->name('review');
    $wf->place()->name('published');

    $t = $wf->transition()->name('to_review');
    $t->from(['draft']);
    $t->to(['review']);

    $t = $wf->transition()->name('publish');
    $t->from(['review']);
    $t->to(['published']);
};
```

### 1.4 Wykonanie i odpytywanie (runtime API)

```php
// Sprawdzenie czy przejście jest możliwe
$canApply = $workflow->can($post, 'to_review');  // bool

// Wykonanie przejścia (opcjonalny kontekst przekazywany do eventów)
$marking = $workflow->apply($post, 'to_review', ['user' => $user]);

// Lista dostępnych przejść
$transitions = $workflow->getEnabledTransitions($post);  // Transition[]

// Pojedyncze przejście po nazwie
$transition = $workflow->getEnabledTransition($post, 'publish');

// Aktualne miejsca
$places = $workflow->getMarking($post)->getPlaces();  // ['review' => 1]

// Metadata o miejscu / przejściu / workflow
$meta = $workflow->getMetadataStore();
$meta->getWorkflowMetadata();
$meta->getPlaceMetadata('draft');
$meta->getTransitionMetadata($transition);
```

### 1.5 Guardy — ExpressionLanguage (YAML)

```yaml
transitions:
    publish:
        guard: "is_granted('ROLE_EDITOR') and subject.isReady()"
        from: review
        to:   published
    reject:
        guard: "subject.getScore() < 5"
        from: review
        to:   draft
```

### 1.6 Guardy — PHP listener

```php
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

$dispatcher->addListener(
    'workflow.blog_publishing.guard.publish',
    function (GuardEvent $event): void {
        /** @var BlogPost $post */
        $post = $event->getSubject();

        if (!$post->isReady()) {
            $event->addTransitionBlocker(
                new TransitionBlocker('Post nie jest gotowy.', '0')
            );
        }

        // Blokada z własnym kodem
        $event->addTransitionBlocker(TransitionBlocker::createBlockedByMarking($marking));
    }
);
```

### 1.7 System zdarzeń (kolejność przy `apply()`)

```
workflow.guard       — czy przejście jest dozwolone (blokowanie możliwe)
workflow.leave       — opuszczanie aktualnego miejsca
workflow.transition  — w trakcie przejścia
workflow.enter       — wejście do nowego miejsca
workflow.entered     — po wejściu (marking już zaktualizowany)
workflow.completed   — przejście zakończone pomyślnie
workflow.announce    — ogłoszenie dostępnych kolejnych przejść
```

Zdarzenia subskrybuje się globalnie lub per-workflow/per-miejsce/per-przejście:

```php
// Globalne
$dispatcher->addListener('workflow.guard', fn(GuardEvent $e) => ...);

// Per workflow
$dispatcher->addListener('workflow.blog_publishing.guard', fn(GuardEvent $e) => ...);

// Per przejście
$dispatcher->addListener('workflow.blog_publishing.guard.publish', fn(GuardEvent $e) => ...);

// Per miejsce
$dispatcher->addListener('workflow.blog_publishing.entered.published', fn(EnteredEvent $e) => ...);
```

Typy zdarzeń PHP:

```php
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\LeaveEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Event\EnterEvent;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\AnnounceEvent;

// Dane dostępne w każdym zdarzeniu:
$event->getSubject();           // obiekt domenowy
$event->getTransition();        // Transition
$event->getWorkflow();          // WorkflowInterface
$event->getContext();           // array przekazany do apply()
```

### 1.8 Workflow vs State Machine (rozróżnienie specyficzne dla Symfony)

> **Uwaga:** To jest wewnętrzna klasyfikacja Symfony — nie definicja ogólna. W Symfony `type: state_machine` to celowe _uproszczenie_, które wymusza dokładnie jedno aktywne miejsce. Nie jest to cecha state machine jako wzorca — np. `php-architecture-kit/state-machine` obsługuje dowolną liczbę współbieżnych Pointerów podróżujących po węzłach (bliżej Petri net / Temporal `async` niż Symfony `state_machine`).

| Cecha | `type: workflow` (Symfony) | `type: state_machine` (Symfony) |
|---|---|---|
| Jednoczesne aktywne miejsca | wiele (Petri net) | dokładnie 1 — hardcoded limit |
| Typ marking | multi-place | single-place |
| Przejście z wielu `from` | tak (AND-join) | nie |
| Przejście do wielu `to` | tak (AND-split) | nie |
| Diagram | DAG / Petri net | klasyczny FSM |

### 1.9 MarkingStore — custom

```php
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;

class CustomMarkingStore implements MarkingStoreInterface
{
    public function getMarking(object $subject): Marking
    {
        // Odczytaj bieżący stan z $subject lub zewnętrznego źródła
        $places = $subject->getPlaces();
        return new Marking($places);
    }

    public function setMarking(object $subject, Marking $marking, array $context = []): void
    {
        $subject->setPlaces($marking->getPlaces());
    }
}
```

### 1.10 Metadata na przejściach i miejscach

```yaml
transitions:
    publish:
        from: review
        to:   published
        metadata:
            title: 'Opublikuj'
            color: green
places:
    draft:
        metadata:
            max_duration: 30
```

```php
$store = $workflow->getMetadataStore();
$title = $store->getTransitionMetadata($transition)['title'] ?? '';
$max   = $store->getPlaceMetadata('draft')['max_duration'] ?? null;
```

---

## 2. Temporal PHP SDK

### 2.1 Definicja Workflow — interface + atrybuty PHP 8

```php
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\UpdateMethod;
use Temporal\Workflow\UpdateValidatorMethod;

#[WorkflowInterface]
interface OrderWorkflowInterface
{
    #[WorkflowMethod(name: 'OrderWorkflow')]
    public function run(string $orderId): \Generator;

    #[SignalMethod]
    public function approve(): void;

    #[SignalMethod]
    public function reject(string $reason): void;

    #[QueryMethod]
    public function getStatus(): string;

    #[UpdateMethod]
    public function addItem(string $itemId): void;

    #[UpdateValidatorMethod(forUpdate: 'addItem')]
    public function validateAddItem(string $itemId): void;
}
```

### 2.2 Implementacja Workflow — generator PHP (`yield`)

Każda operacja niedeterministyczna (aktywność, timer, child workflow, await) musi być `yield`-owana — Temporal odtwarza historię przez re-replay generatora.

```php
use Temporal\Workflow;
use Temporal\Activity\ActivityOptions;
use Carbon\CarbonInterval;

class OrderWorkflow implements OrderWorkflowInterface
{
    private string $status = 'pending';
    private bool $approved = false;

    public function run(string $orderId): \Generator
    {
        $activity = Workflow::newActivityStub(
            OrderActivityInterface::class,
            ActivityOptions::new()
                ->withScheduleToCloseTimeout(CarbonInterval::hours(1))
                ->withStartToCloseTimeout(CarbonInterval::minutes(10))
        );

        // Wykonaj aktywność (yield = czekaj na wynik)
        $paymentResult = yield $activity->processPayment($orderId);

        // Czekaj na sygnał (polling bez busy-wait)
        yield Workflow::await(fn() => $this->approved);

        // Czekaj na sygnał LUB timeout
        $approved = yield Workflow::awaitWithTimeout(
            CarbonInterval::hours(24),
            fn() => $this->approved
        );
        if (!$approved) {
            yield $activity->cancelOrder($orderId);
            return 'timed_out';
        }

        yield $activity->fulfillOrder($orderId);
        return 'completed';
    }

    public function approve(): void
    {
        $this->approved = true;
        $this->status   = 'approved';
    }

    public function reject(string $reason): void
    {
        $this->status = 'rejected:' . $reason;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function addItem(string $itemId): void
    {
        // wywoływane przez UpdateMethod
    }

    public function validateAddItem(string $itemId): void
    {
        if (empty($itemId)) {
            throw new \InvalidArgumentException('itemId nie może być pusty');
        }
    }
}
```

### 2.3 Definicja Activity

```php
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\Activity;

#[ActivityInterface(prefix: 'OrderActivity.')]
interface OrderActivityInterface
{
    #[ActivityMethod(name: 'processPayment')]
    public function processPayment(string $orderId): string;

    #[ActivityMethod(name: 'fulfillOrder')]
    public function fulfillOrder(string $orderId): void;
}

class OrderActivity implements OrderActivityInterface
{
    public function processPayment(string $orderId): string
    {
        // Heartbeat — informuje serwer że aktywność żyje (wymagane przy długich operacjach)
        Activity::heartbeat(['orderId' => $orderId, 'step' => 'charging']);

        // Dostęp do metadanych bieżącego wywołania
        $info = Activity::getInfo();
        $attempt = $info->attempt;

        return 'paid';
    }

    public function fulfillOrder(string $orderId): void
    {
        Activity::heartbeat($orderId);
    }
}
```

### 2.4 Uruchomienie workflow (klient)

```php
use Temporal\Client\WorkflowClient;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowOptions;

$serviceClient = ServiceClient::create('localhost:7233');
$client        = WorkflowClient::create($serviceClient);

$stub = $client->newWorkflowStub(
    OrderWorkflowInterface::class,
    WorkflowOptions::new()
        ->withWorkflowId('order-' . $orderId)
        ->withTaskQueue('orders')
        ->withWorkflowExecutionTimeout(CarbonInterval::days(7))
);

// Start asynchroniczny — nie czeka na zakończenie
$run = $client->start($stub, $orderId);
echo $run->getExecution()->getID();   // workflow ID
echo $run->getExecution()->getRunID(); // run ID

// Synchroniczny — blokuje do zakończenia
$result = $stub->run($orderId);

// Uruchom istniejące (po ID)
$existingStub = $client->newRunningWorkflowStub(
    OrderWorkflowInterface::class,
    'order-123'
);
```

### 2.5 Sygnały i zapytania (runtime)

```php
// Wyślij sygnał do uruchomionego workflow
$existingStub->approve();
$existingStub->reject('brak środków');

// Odpytaj stan bez przerywania workflow
$status = $existingStub->getStatus();

// Atomic signal-with-start: wyślij sygnał, uruchamiając workflow jeśli nie istnieje
$client->startWithSignal($stub, 'approve', [], [$orderId]);

// Update (z walidacją po stronie serwera przed akceptacją)
$existingStub->addItem('item-456');
```

### 2.6 Timery

```php
// Odczekaj 24 godziny (deterministycznie — Temporal zarządza czasem)
yield Workflow::timer(CarbonInterval::hours(24));

// Czekaj na warunek lub upływ czasu
$fulfilled = yield Workflow::awaitWithTimeout(
    CarbonInterval::minutes(10),
    fn() => $this->approved
);
// $fulfilled === false gdy timeout nastąpił przed spełnieniem warunku
```

### 2.7 Child Workflows

```php
use Temporal\Workflow\ChildWorkflowOptions;

$child = Workflow::newChildWorkflowStub(
    ShippingWorkflowInterface::class,
    ChildWorkflowOptions::new()
        ->withWorkflowId('shipping-' . $orderId)
        ->withTaskQueue('shipping')
);

// Synchronicznie (czeka na zakończenie child)
$trackingNumber = yield $child->run($orderId);

// Asynchronicznie
$childPromise = Workflow::async(fn() => yield $child->run($orderId));
// ... inne operacje ...
$trackingNumber = yield $childPromise;
```

### 2.8 Asynchroniczne gałęzie — równoległość

```php
use React\Promise\Promise;

// Uruchom dwie aktywności równolegle
$promiseA = Workflow::async(fn() => yield $activity->doA($orderId));
$promiseB = Workflow::async(fn() => yield $activity->doB($orderId));

// AND-join — czekaj na obie
[$resultA, $resultB] = yield Promise::all([$promiseA, $promiseB]);

// OR-join — czekaj na pierwszą
$first = yield Promise::any([$promiseA, $promiseB]);
```

### 2.9 Saga — wzorzec kompensacji

```php
use Temporal\Internal\Workflow\Saga;

$saga = new Saga();
$saga->setParallelCompensation(true);   // kompensacje równolegle

try {
    $saga->addCompensation(fn() => yield $activity->cancelPayment($orderId));
    yield $activity->chargePayment($orderId);

    $saga->addCompensation(fn() => yield $activity->releaseInventory($orderId));
    yield $activity->reserveInventory($orderId);

    $saga->addCompensation(fn() => yield $activity->cancelShipment($orderId));
    yield $activity->scheduleShipment($orderId);

} catch (\Throwable $e) {
    yield $saga->compensate();   // uruchamia kompensacje w odwrotnej kolejności
    throw $e;
}
```

### 2.10 Side Effects — logika niepowtarzalna

Kod wewnątrz `sideEffect` wykona się raz; wynik jest zapisywany w historii i odtwarzany przy replay.

```php
// Generowanie UUID wewnątrz workflow (niedeterministyczne — musi być sideEffect)
$uuid = yield Workflow::sideEffect(fn() => Uuid::uuid4()->toString());

// Bieżący czas (deterministyczny przez Temporal)
$now = yield Workflow::now();    // DateTimeInterface
```

### 2.11 Wersjonowanie workflow — obsługa migracji

Pozwala zmienić logikę workflow bez przerywania aktualnie uruchomionych egzekucji.

```php
$version = yield Workflow::getVersion(
    'add-notification',       // change ID (unikalny string)
    Workflow::DEFAULT_VERSION, // minVersion
    1                          // maxVersion
);

if ($version === Workflow::DEFAULT_VERSION) {
    // stara ścieżka (egzekucje sprzed zmiany)
} elseif ($version === 1) {
    yield $activity->sendNotification($orderId);
}
```

### 2.12 Retry Options

```php
use Temporal\Common\RetryOptions;

ActivityOptions::new()
    ->withRetryOptions(
        RetryOptions::new()
            ->withInitialInterval(CarbonInterval::seconds(1))
            ->withMaximumInterval(CarbonInterval::minutes(5))
            ->withBackoffCoefficient(2.0)
            ->withMaximumAttempts(3)
            ->withNonRetryableExceptions([
                \App\Exception\InvalidOrderException::class,
            ])
    );
```

### 2.13 Worker — rejestracja i uruchomienie

```php
use Temporal\Worker\WorkerFactory;

$factory = WorkerFactory::create();
$worker  = $factory->newWorker('orders');   // task queue

$worker->registerWorkflowTypes(OrderWorkflow::class);
$worker->registerActivity(OrderActivity::class);

$factory->run();   // blokuje — event loop RoadRunner/Swoole
```

---

## 3. Porównanie głównych konceptów

| Koncepcja | Symfony Workflow | Temporal PHP |
|---|---|---|
| Definicja stanów | `places` (YAML / PHP) | pola klasy + zmienne w generatorze |
| Definicja przejść | `transitions` z `from/to` + nazwa | `yield $activity->...` (implicit) |
| Named transitions | tak — pierwszorzędowy koncept | brak (przejście = wywołanie aktywności) |
| Guardy | `guard` expression / `GuardEvent` listener | `Workflow::await(fn() => $condition)` |
| Blokowanie przejścia | `TransitionBlocker` (z opisem) | `throw` lub `await` |
| Równoległość | `type: workflow` + multi-marking | `Workflow::async()` + `Promise::all()` |
| AND-split | wiele `to` w jednym przejściu | `Workflow::async()` per gałąź |
| AND-join | wiele `from` w jednym przejściu | `Promise::all([$p1, $p2])` |
| Czekanie na zewnętrzny event | brak wbudowanego | `yield Workflow::await(fn() => ...)` |
| Sygnały z zewnątrz | `EventDispatcher` (external, nie wbudowany) | `#[SignalMethod]` — first-class |
| Zapytanie stanu | `$workflow->getMarking()` | `#[QueryMethod]` — first-class |
| Timeout na stan | brak wbudowanego | `Workflow::awaitWithTimeout()` |
| Timer | brak | `yield Workflow::timer()` |
| Kompensacja | brak wbudowanej | `Workflow\Saga` |
| Wersjonowanie | brak | `Workflow::getVersion()` |
| Persystencja stanu | w obiekcie domenowym (MarkingStore) | serwer Temporal (event sourcing) |
| Kontekst uruchomienia | synchroniczny, w procesie PHP | asynchroniczny worker (RoadRunner) |
| Zdarzenia / hooks | 7 eventów per `apply()` | brak — saga/kompensacja zamiast tego |
| Metadata na stanach | tak (YAML `metadata:`) | brak natywnie |

---

## 4. Wnioski dla UX paczki `php-architecture-kit/state-machine`

### 4.1 Fluent builder API

Symfony pokazuje, że deklaratywne, czytelne API (YAML / PHP config builder) jest powszechnie preferowane nad konstruktorami z `addNode/addTransition`. Propozycja:

```php
// Obecne API
$this->addNode($node)->addTransition($from->id, $to->id, $condition);

// Propozycja — fluent builder
$builder
    ->state('draft')
    ->state('review')
    ->state('published')
    ->transition('to_review')->from('draft')->to('review')
    ->transition('publish')->from('review')->to('published')
        ->guard(fn(States $s) => $s->getState('post')?->details['ready']->value === true)
    ->build();
```

### 4.2 Named transitions

Symfony traktuje przejścia jako obiekty z nazwą. Ułatwia to:
- selektywne nasłuchiwanie zdarzeń (`guard.publish`)
- czytelniejsze logi i debugowanie
- dokumentację (`metadata` na przejściu)

### 4.3 Signals — zewnętrzne wstrzyknięcie zdarzenia

Temporal `#[SignalMethod]` to elegancki wzorzec na "coś z zewnątrz wpłynęło do egzekucji". Propozycja:

```php
// Zamiast: zapis stanu ręcznie + warunek w pętli
$machine->signal($execution, 'payment.received', ['amount' => 100]);

// W definicji node/transition:
->awaitSignal('payment.received')->onTimeout('48h', 'payment_expired')
```

### 4.4 Deklaratywny timeout na przejście/stan

Temporal `awaitWithTimeout` pokazuje wartość timeout jako first-class citizen. Propozycja:

```php
->transition('await_payment')
    ->from('pending')
    ->to('paid')
    ->awaitSignal('payment.confirmed')
    ->timeout('48h')
    ->onTimeout('payment_expired')
```

### 4.5 Query API

Zamiast bezpośredniego dostępu do `$execution->states`, formalny kontrakt odpytywania:

```php
$status = $machine->query($execution, 'getStatus');
// lub z typowaniem:
$status = $machine->query($execution, OrderStatusQuery::class);
```

### 4.6 Saga / kompensacja

Temporal Saga jest wzorcem, który paczka mogłaby wspierać natywnie jako komponent:

```php
$saga = SagaComponent::create('order_saga')
    ->step('charge_payment', compensate: 'cancel_payment')
    ->step('reserve_inventory', compensate: 'release_inventory')
    ->step('schedule_shipment', compensate: 'cancel_shipment');
```

### 4.7 Zdarzenia per-stan i per-przejście (jak Symfony)

Symfony emituje 7 zdarzeń na `apply()`. Paczka mogłaby emitować analogiczne z możliwością filtrowania:

```
state-machine.guard.{transitionName}
state-machine.leave.{stateName}
state-machine.entered.{stateName}
state-machine.completed.{transitionName}
```

---

## Źródła

- [Symfony Workflow Component](https://symfony.com/doc/current/components/workflow.html)
- [Symfony Workflow — How to Use](https://symfony.com/doc/current/workflow.html)
- [Symfony Workflow vs State Machine](https://symfony.com/doc/current/workflow/workflow-and-state-machine.html)
- [Temporal PHP SDK Developer Guide](https://docs.temporal.io/develop/php)
- [Temporal PHP — Foundations](https://docs.temporal.io/dev-guide/php/foundations)
- [Temporal PHP — Message Passing](https://docs.temporal.io/develop/php/message-passing)
- [Temporal PHP — Child Workflows](https://docs.temporal.io/develop/php/child-workflows)
- [Temporal PHP — Failure Detection](https://docs.temporal.io/develop/php/failure-detection)
- [GitHub: temporalio/sdk-php](https://github.com/temporalio/sdk-php)
- [GitHub: temporalio/samples-php](https://github.com/temporalio/samples-php)
