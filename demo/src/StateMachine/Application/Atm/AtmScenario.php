<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Application\Atm;

use PhpArchitecture\Demo\StateMachine\Application\Exception\ActionRejected;
use PhpArchitecture\Demo\StateMachine\Application\Scenario;
use PhpArchitecture\Demo\StateMachine\Application\ScenarioSession;
use PhpArchitecture\Demo\StateMachine\Application\SimulationClock;
use PhpArchitecture\Demo\StateMachine\Domain\Atm\AtmMachine;
use PhpArchitecture\Demo\StateMachine\Domain\Atm\Bank\Bank;
use PhpArchitecture\Demo\StateMachine\Domain\Atm\Bank\BankAnswer;
use PhpArchitecture\Demo\StateMachine\Domain\Atm\Task\AuthorizeWithdrawal;
use PhpArchitecture\Demo\StateMachine\Domain\Atm\Task\ReverseWithdrawal;
use PhpArchitecture\Demo\StateMachine\Domain\Atm\Task\VerifyPin;
use PhpArchitecture\Demo\StateMachine\Domain\Shared\DemoStateMachine;
use PhpArchitecture\Demo\StateMachine\Domain\Shared\World;
use PhpArchitecture\StateMachine\Foundation\Component\Async\AsyncTaskHandledNotifier;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskEnvelope;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskBusInterface;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;

/**
 * The customer and the bank around AtmMachine.
 *
 * The bank receives the tasks the machine dispatched. In auto mode it answers right away;
 * otherwise the user answers (or loses) every task from the bank panel - later, in another
 * request, after the Execution was persisted.
 */
final class AtmScenario implements Scenario
{
    private const string DAMAGED_CARD = 'damaged';

    public function id(): string
    {
        return 'atm';
    }

    public function title(): string
    {
        return 'ATM cash withdrawal';
    }

    public function machine(ContainerInterface $handlers, ClockInterface $clock, TaskBusInterface $taskBus): DemoStateMachine
    {
        return new AtmMachine($handlers, $clock, $taskBus);
    }

    public function newSession(DemoStateMachine $machine, SimulationClock $clock, ?ScenarioSession $previous): ScenarioSession
    {
        $bank = $previous?->data['bank'] ?? Bank::withDemoAccounts();
        $session = new ScenarioSession(Execution::create(), $clock->now(), [
            'bank' => $bank,
            'bankAuto' => $previous?->data['bankAuto'] ?? true,
            'customers' => ($previous?->data['customers'] ?? 0) + 1,
        ]);
        $session->note('actor', sprintf('customer #%d walks up to the ATM', $session->data['customers']));
        $machine->start($session->execution);

        return $session;
    }

    public function act(string $action, array $payload, ScenarioSession $session): void
    {
        $world = new World($session->execution->states);
        $atm = $world->get(AtmMachine::ATM);

        match ($action) {
            'insertCard' => $this->insertCard($world, $atm, (string) ($payload['card'] ?? ''), $session),
            'key' => $this->pressKey($world, $atm, $payload, $session),
            'takeCard' => $this->take($world, $atm['cardSlot'] === 'ejected', AtmMachine::CARD_TAKEN, ['cardSlot' => 'empty'], 'customer takes the card', $session),
            'takeCash' => $this->take($world, $atm['cashSlot'] === 'presented', AtmMachine::CASH_TAKEN, ['cashSlot' => 'empty'], 'customer takes the cash', $session),
            'bankAuto' => $session->data['bankAuto'] = (bool) ($payload['on'] ?? true),
            'bankAnswer' => $this->answer($session, (string) ($payload['task'] ?? ''), ($payload['decline'] ?? false) === true),
            'bankLose' => $this->lose($session, (string) ($payload['task'] ?? '')),
            default => throw new ActionRejected("Unknown action '{$action}'."),
        };
    }

    public function react(ScenarioSession $session): bool
    {
        if ($session->data['bankAuto'] !== true || $session->inbox === []) {
            return false;
        }

        foreach ($session->inbox as $envelope) {
            $this->answer($session, $envelope->id->toString(), false);
        }

        return true;
    }

    public function panel(ScenarioSession $session): array
    {
        $world = new World($session->execution->states);
        $bank = $this->bank($session);

        return [
            'atm' => $world->get(AtmMachine::ATM),
            'card' => $world->get(AtmMachine::CARD),
            'cards' => [
                ...array_map(
                    static fn (string $number, array $account): array => ['number' => $number, 'label' => "{$account['holder']} (PIN {$account['pin']})"],
                    array_keys($bank->accounts()),
                    $bank->accounts(),
                ),
                ['number' => self::DAMAGED_CARD, 'label' => 'Damaged card'],
            ],
            'quickAmounts' => [100, 200, 500, 1000],
            'bank' => [
                'auto' => $session->data['bankAuto'],
                'accounts' => array_map(
                    static fn (string $number, array $account): array => ['number' => $number] + $account,
                    array_keys($bank->accounts()),
                    $bank->accounts(),
                ),
                'inbox' => array_map(fn (TaskEnvelope $envelope): array => [
                    'id' => $envelope->id->toString(),
                    'task' => (new ReflectionClass($envelope->task))->getShortName(),
                    'summary' => $this->summary($envelope),
                    'assessment' => $bank->assess($envelope->task)->reason,
                ], $session->inbox),
            ],
            'customer' => $session->data['customers'],
            'timeouts' => ['input' => AtmMachine::INPUT_TIMEOUT, 'bank' => AtmMachine::BANK_TIMEOUT],
        ];
    }

    /** @param array<string,mixed> $atm */
    private function insertCard(World $world, array $atm, string $number, ScenarioSession $session): void
    {
        if ($atm['mode'] !== 'insert-card') {
            throw new ActionRejected('The card slot is not ready.');
        }

        $account = $this->bank($session)->accounts()[$number] ?? null;
        if ($account === null && $number !== self::DAMAGED_CARD) {
            throw new ActionRejected('Unknown card.');
        }

        $world->put(AtmMachine::CARD, [
            'number' => $number,
            'holder' => $account['holder'] ?? '?',
            'readable' => $account !== null,
        ]);
        $world->put(AtmMachine::ATM, ['cardSlot' => 'inside']);
        $session->note('actor', 'customer inserts ' . ($account === null ? 'a damaged card' : "the card of {$account['holder']}"));
    }

    /**
     * @param array<string,mixed> $atm
     * @param array<string,mixed> $payload
     */
    private function pressKey(World $world, array $atm, array $payload, ScenarioSession $session): void
    {
        $type = (string) ($payload['type'] ?? '');
        $allowed = match ($atm['mode']) {
            'pin' => ['pin', 'cancel'],
            'amount' => ['amount', 'cancel'],
            default => [],
        };
        if (!in_array($type, $allowed, true)) {
            throw new ActionRejected('The keypad does not accept this now.');
        }

        $key = ['type' => $type];
        if ($type === 'pin') {
            $pin = (string) ($payload['value'] ?? '');
            if (!preg_match('/^\d{4}$/', $pin)) {
                throw new ActionRejected('A PIN has 4 digits.');
            }
            $key['value'] = $pin;
        }
        if ($type === 'amount') {
            $key['value'] = (int) ($payload['value'] ?? 0);
            $key['receipt'] = ($payload['receipt'] ?? false) === true;
        }

        $world->put(AtmMachine::KEYPAD, $key);
        $session->note('actor', match ($type) {
            'pin' => 'customer enters a PIN',
            'amount' => sprintf('customer asks for %d%s', $key['value'], $key['receipt'] ? ' with a receipt' : ''),
            default => 'customer presses Cancel',
        });
    }

    /** @param array<string,mixed> $atmChanges */
    private function take(World $world, bool $possible, string $state, array $atmChanges, string $note, ScenarioSession $session): void
    {
        if (!$possible) {
            throw new ActionRejected('There is nothing to take.');
        }

        $world->put($state, ['at' => $session->time->format('H:i:s')]);
        $world->put(AtmMachine::ATM, $atmChanges);
        $session->note('actor', $note);
    }

    /** The bank sends its answer back: business details first, then the async result. */
    private function answer(ScenarioSession $session, string $taskId, bool $decline): void
    {
        $envelope = $this->takeFromInbox($session, $taskId);
        $bank = $this->bank($session);
        $answer = $decline ? BankAnswer::declined('Declined by the bank operator') : $bank->assess($envelope->task);
        $execution = $session->execution;

        $accepted = $answer->approved
            ? AsyncTaskHandledNotifier::success($execution, $envelope)
            : AsyncTaskHandledNotifier::fail($execution, $envelope);

        $name = (new ReflectionClass($envelope->task))->getShortName();
        if (!$accepted) {
            $session->note('task', "bank answers {$name} too late - ignored, the ATM no longer waits for it");

            return;
        }

        (new World($execution->states))->put(AtmMachine::BANK_REPLY, ['task' => $name, 'approved' => $answer->approved, 'reason' => $answer->reason]);
        $bank->commit($envelope->task, $answer);
        $session->note('task', sprintf('bank answers %s: %s', $name, $answer->reason));
    }

    private function lose(ScenarioSession $session, string $taskId): void
    {
        $envelope = $this->takeFromInbox($session, $taskId);
        $session->note('task', sprintf('the answer to %s is lost', (new ReflectionClass($envelope->task))->getShortName()));
    }

    private function takeFromInbox(ScenarioSession $session, string $taskId): TaskEnvelope
    {
        foreach ($session->inbox as $index => $envelope) {
            if ($envelope->id->toString() === $taskId) {
                array_splice($session->inbox, $index, 1);

                return $envelope;
            }
        }

        throw new ActionRejected('The bank has no such request.');
    }

    private function bank(ScenarioSession $session): Bank
    {
        $bank = $session->data['bank'];
        assert($bank instanceof Bank);

        return $bank;
    }

    private function summary(TaskEnvelope $envelope): string
    {
        $task = $envelope->task;

        return match (true) {
            $task instanceof VerifyPin => "card {$task->cardNumber}, PIN {$task->pin}",
            $task instanceof AuthorizeWithdrawal => "card {$task->cardNumber}, amount {$task->amount}",
            $task instanceof ReverseWithdrawal => "card {$task->cardNumber}, give back {$task->amount}",
            default => '',
        };
    }
}
