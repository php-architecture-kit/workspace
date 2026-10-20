<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Domain\Atm;

use Closure;
use PhpArchitecture\Demo\StateMachine\Domain\Atm\Task\AuthorizeWithdrawal;
use PhpArchitecture\Demo\StateMachine\Domain\Atm\Task\ReverseWithdrawal;
use PhpArchitecture\Demo\StateMachine\Domain\Atm\Task\VerifyPin;
use PhpArchitecture\Demo\StateMachine\Domain\Shared\DemoStateMachine;
use PhpArchitecture\Demo\StateMachine\Domain\Shared\Step\StepNode;
use PhpArchitecture\StateMachine\Foundation\Component\Async\AsyncComponent;
use PhpArchitecture\StateMachine\Foundation\Component\Await\AwaitStateComponent;
use PhpArchitecture\StateMachine\Foundation\Component\Join\JoinComponent;
use PhpArchitecture\StateMachine\Foundation\Component\Fork\ForkComponent;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\State\States;
use Psr\Clock\ClockInterface;

/**
 * Cash withdrawal - one Execution per customer session, from the inserted card to "thank you".
 *
 * Outside actors write states (the customer: card, keypad, card_taken, cash_taken; the bank:
 * bank_reply plus the async task result) and the machine is driven again.
 */
final class AtmMachine extends DemoStateMachine
{
    public const string ATM = 'atm';
    public const string CARD = 'card';
    public const string KEYPAD = 'keypad';
    public const string CARD_TAKEN = 'card_taken';
    public const string CASH_TAKEN = 'cash_taken';
    public const string BANK_REPLY = 'bank_reply';

    public const int MAX_PIN_ATTEMPTS = 3;
    public const int MAX_AMOUNT = 2000;
    public const int INPUT_TIMEOUT = 30;
    public const int BANK_TIMEOUT = 20;

    private StepNode $welcome;

    public function start(Execution $execution): void
    {
        $execution->states->defineOrModifyState(self::ATM, [
            'mode' => 'busy',
            'screen' => '',
            'cardSlot' => 'empty',
            'cashSlot' => 'empty',
            'receipt' => 'none',
            'pinAttempts' => 0,
            'pin' => null,
            'amount' => null,
            'wantsReceipt' => false,
            'notice' => null,
            'outcome' => null,
        ]);

        $execution->pointers->startAt($this->welcome->id);
    }

    protected function define(): void
    {
        $this->welcome = $this->step('demo.atm.welcome', 'Welcome', $this->screen('insert-card', 'Welcome! Insert your card.'));
        $readCard = $this->step('demo.atm.read-card', 'Read card', static function (States $states): void {
            $states->defineOrModifyState(self::ATM, ['cardSlot' => 'inside', 'mode' => 'busy', 'screen' => 'Reading your card…']);
        });
        $cardUnreadable = $this->step('demo.atm.card-unreadable', 'Card unreadable', $this->notice('This card cannot be read.', ['outcome' => 'Card unreadable']));
        $askPin = $this->step('demo.atm.ask-pin', 'Ask for PIN', static function (States $states): void {
            $left = self::MAX_PIN_ATTEMPTS - (int) ($states->getState(self::ATM)?->value('pinAttempts') ?? 0);
            $states->defineOrModifyState(self::ATM, ['mode' => 'pin', 'pin' => null, 'screen' => "Enter your PIN ({$left} " . ($left === 1 ? 'attempt' : 'attempts') . ' left).']);
        });
        $readPin = $this->step('demo.atm.read-pin', 'Read keypad', $this->readKeypad());
        $verifyPin = $this->step('demo.atm.verify-pin', 'Verify PIN', $this->screen('busy', 'Verifying your PIN…'));
        $wrongPin = $this->step('demo.atm.wrong-pin', 'Wrong PIN', static function (States $states): void {
            $states->defineOrModifyState(self::ATM, ['pinAttempts' => (int) ($states->getState(self::ATM)?->value('pinAttempts') ?? 0) + 1, 'pin' => null, 'notice' => 'Wrong PIN.']);
        });
        $askAmount = $this->step('demo.atm.ask-amount', 'Ask for amount', $this->screen('amount', 'Choose the amount.', ['pin' => null]));
        $readAmount = $this->step('demo.atm.read-amount', 'Read keypad', $this->readKeypad());
        $invalidAmount = $this->step('demo.atm.invalid-amount', 'Invalid amount', $this->notice('The amount must be a multiple of 50, up to ' . self::MAX_AMOUNT . '.'));
        $authorize = $this->step('demo.atm.authorize', 'Authorize', $this->screen('busy', 'Contacting your bank…'));
        $declined = $this->step('demo.atm.declined', 'Declined', static function (States $states): void {
            $reason = (string) ($states->getState(self::BANK_REPLY)?->value('reason') ?? 'Declined');
            $states->defineOrModifyState(self::ATM, ['notice' => "Declined: {$reason}.", 'outcome' => "Declined ({$reason})"]);
        });
        $bankUnavailable = $this->step('demo.atm.bank-unavailable', 'Bank unavailable', $this->notice('Your bank does not respond. Please try again later.', ['outcome' => 'Bank did not respond']));
        $inputTimeout = $this->step('demo.atm.input-timeout', 'No input', $this->notice('No input - the session is ending.', ['outcome' => 'Customer did not respond']));
        $cancelled = $this->step('demo.atm.cancelled', 'Cancelled', $this->notice('Cancelled.', ['outcome' => 'Cancelled by the customer']));
        $ejectCard = $this->step('demo.atm.eject-card', 'Eject card', $this->screen('take-card', 'Please take your card.', ['cardSlot' => 'ejected']));
        $retainCard = $this->step('demo.atm.retain-card', 'Retain card', static function (States $states): void {
            $states->defineOrModifyState(self::ATM, ['cardSlot' => 'retained', 'notice' => 'Your card has been retained. Please contact your bank.']);
            if ($states->getState(self::ATM)?->value('outcome') === null) {
                $states->defineOrModifyState(self::ATM, ['outcome' => 'Card retained (PIN blocked or card not taken)']);
            }
        });
        $returnCard = $this->step('demo.atm.return-card', 'Return card first', $this->screen('take-card', 'Please take your card - your cash is being prepared.', ['cardSlot' => 'ejected']));
        $cardForgotten = $this->step('demo.atm.card-forgotten', 'Card forgotten', $this->notice('Card not taken - it has been retained and the withdrawal cancelled.', ['cardSlot' => 'retained', 'outcome' => 'Card forgotten, withdrawal cancelled']));
        $dispense = $this->step('demo.atm.dispense', 'Dispense', static function (States $states): void {
            $states->defineOrModifyState(self::ATM, ['cardSlot' => 'empty', 'mode' => 'busy', 'screen' => 'Counting your notes…']);
        });
        $countCash = $this->step('demo.atm.count-cash', 'Count notes');
        $printReceipt = $this->step('demo.atm.print-receipt', 'Print receipt', static function (States $states): void {
            $states->defineOrModifyState(self::ATM, ['receipt' => $states->getState(self::ATM)?->value('wantsReceipt') === true ? 'printed' : 'skipped']);
        });
        $presentCash = $this->step('demo.atm.present-cash', 'Present cash', $this->screen('take-cash', 'Please take your cash.', ['cashSlot' => 'presented']));
        $retractCash = $this->step('demo.atm.retract-cash', 'Retract cash', $this->notice('Cash not taken - it has been retracted.', ['cashSlot' => 'retracted', 'outcome' => 'Cash retracted, withdrawal cancelled']));
        $reverse = $this->step('demo.atm.reverse', 'Cancel withdrawal', $this->screen('busy', 'Cancelling the withdrawal…'));
        $reversalFailed = $this->step('demo.atm.reversal-failed', 'Reversal pending', $this->notice('The reversal is pending - the bank will correct your balance.'));
        $goodbye = $this->step('demo.atm.goodbye', 'Thank you', static function (States $states): void {
            if ($states->getState(self::ATM)?->value('outcome') === null) {
                $states->defineOrModifyState(self::ATM, ['outcome' => 'Cash withdrawn: ' . $states->getState(self::ATM)?->value('amount')]);
            }
            $states->defineOrModifyState(self::ATM, ['mode' => 'finished', 'cashSlot' => $states->getState(self::ATM)?->value('cashSlot') === 'presented' ? 'empty' : $states->getState(self::ATM)?->value('cashSlot'), 'screen' => 'Thank you!']);
        });

        // card -> PIN
        $this->place(AwaitStateComponent::create('atm-insert-card', self::CARD), 'demo.atm.await-card', 'Await card',
            ['at' => $this->welcome], ['run' => [$readCard, 'card inserted']]);
        $this->routes($readCard, static fn (States $states): string => $states->getState(self::CARD)?->value('readable') === true ? 'ok' : 'unreadable', [
            'ok' => [$askPin, 'card read'],
            'unreadable' => [$cardUnreadable, 'unreadable'],
        ]);
        $this->then($cardUnreadable, $ejectCard);

        $this->place($this->awaitKeypad('atm-pin', $this->clock), 'demo.atm.await-pin', 'Await PIN (30 s)',
            ['at' => $askPin], ['run' => [$readPin, 'key pressed'], 'expired' => [$inputTimeout, 'no input']]);
        $this->routes($readPin, static fn (States $states): string => match ($states->getState(self::ATM)?->value('lastKey')) {
            'cancel' => 'cancel',
            'pin' => 'pin',
            default => 'other',
        }, [
            'pin' => [$verifyPin, 'PIN entered'],
            'cancel' => [$cancelled, 'cancel'],
            'other' => [$askPin, 'other key'],
        ]);

        // PIN verification at the bank; a wrong PIN loops back (up to 3 attempts)
        $this->place(
            AsyncComponent::create('atm-verify-pin', static fn (States $states): VerifyPin => new VerifyPin(
                (string) $states->getState(self::CARD)?->value('number'),
                (string) $states->getState(self::ATM)?->value('pin'),
            ), self::BANK_TIMEOUT, $this->clock),
            'demo.atm.bank-verify-pin', 'Bank: verify PIN (20 s)',
            ['trigger' => $verifyPin],
            ['success' => [$askAmount, 'PIN OK'], 'fail' => [$wrongPin, 'wrong PIN'], 'expired' => [$bankUnavailable, 'no answer']],
        );
        $this->routes($wrongPin, static fn (States $states): string => (int) ($states->getState(self::ATM)?->value('pinAttempts') ?? 0) >= self::MAX_PIN_ATTEMPTS ? 'blocked' : 'retry', [
            'retry' => [$askPin, 'attempts left'],
            'blocked' => [$retainCard, '3rd wrong PIN'],
        ]);

        // amount; an invalid one loops back
        $this->place($this->awaitKeypad('atm-amount', $this->clock), 'demo.atm.await-amount', 'Await amount (30 s)',
            ['at' => $askAmount], ['run' => [$readAmount, 'key pressed'], 'expired' => [$inputTimeout, 'no input']]);
        $this->routes($readAmount, static function (States $states): string {
            if ($states->getState(self::ATM)?->value('lastKey') === 'cancel') {
                return 'cancel';
            }
            $amount = (int) ($states->getState(self::ATM)?->value('amount') ?? 0);

            return $amount > 0 && $amount <= self::MAX_AMOUNT && $amount % 50 === 0 ? 'valid' : 'invalid';
        }, [
            'valid' => [$authorize, 'valid amount'],
            'invalid' => [$invalidAmount, 'invalid amount'],
            'cancel' => [$cancelled, 'cancel'],
        ]);
        $this->then($invalidAmount, $askAmount);

        $this->place(
            AsyncComponent::create('atm-authorize', static fn (States $states): AuthorizeWithdrawal => new AuthorizeWithdrawal(
                (string) $states->getState(self::CARD)?->value('number'),
                (int) $states->getState(self::ATM)?->value('amount'),
            ), self::BANK_TIMEOUT, $this->clock),
            'demo.atm.bank-authorize', 'Bank: authorize (20 s)',
            ['trigger' => $authorize],
            ['success' => [$returnCard, 'approved'], 'fail' => [$declined, 'declined'], 'expired' => [$bankUnavailable, 'no answer']],
        );
        $this->then($declined, $ejectCard);
        $this->then($bankUnavailable, $ejectCard);
        $this->then($inputTimeout, $ejectCard);
        $this->then($cancelled, $ejectCard);

        // ending without cash: card back, or retained when the customer walks away
        $this->place(AwaitStateComponent::create('atm-take-card', self::CARD_TAKEN, timeout: self::INPUT_TIMEOUT, clock: $this->clock),
            'demo.atm.await-card-taken', 'Await card taken (30 s)',
            ['at' => $ejectCard], ['run' => [$goodbye, 'card taken'], 'expired' => [$retainCard, 'not taken']]);
        $this->then($retainCard, $goodbye);

        // approved: card first, then the cash
        $this->place(AwaitStateComponent::create('atm-take-card-first', self::CARD_TAKEN, timeout: self::INPUT_TIMEOUT, clock: $this->clock),
            'demo.atm.await-card-first', 'Await card taken (30 s)',
            ['at' => $returnCard], ['run' => [$dispense, 'card taken'], 'expired' => [$cardForgotten, 'not taken']]);
        $this->then($cardForgotten, $reverse);

        // counting the notes and printing the receipt run in parallel pointers
        $this->place(ForkComponent::create('atm-dispense', ['cash', 'receipt']), 'demo.atm.fork', 'Fork',
            ['trigger' => $dispense], ['cash' => [$countCash, 'cash'], 'receipt' => [$printReceipt, 'receipt']]);
        $this->place(JoinComponent::create('atm-dispense', ['cash', 'receipt']), 'demo.atm.join', 'Join',
            ['cash' => $countCash, 'receipt' => $printReceipt], ['done' => [$presentCash, 'both done']]);

        $this->place(AwaitStateComponent::create('atm-take-cash', self::CASH_TAKEN, timeout: self::INPUT_TIMEOUT, clock: $this->clock),
            'demo.atm.await-cash-taken', 'Await cash taken (30 s)',
            ['at' => $presentCash], ['run' => [$goodbye, 'cash taken'], 'expired' => [$retractCash, 'not taken']]);
        $this->then($retractCash, $reverse);

        // compensation: give the money back
        $this->place(
            AsyncComponent::create('atm-reverse', static fn (States $states): ReverseWithdrawal => new ReverseWithdrawal(
                (string) $states->getState(self::CARD)?->value('number'),
                (int) $states->getState(self::ATM)?->value('amount'),
            ), self::BANK_TIMEOUT, $this->clock),
            'demo.atm.bank-reverse', 'Bank: reverse (20 s)',
            ['trigger' => $reverse],
            ['success' => [$goodbye, 'reversed'], 'fail' => [$reversalFailed, 'failed'], 'expired' => [$reversalFailed, 'no answer']],
        );
        $this->then($reversalFailed, $goodbye);
    }

    /**
     * @param array<string,mixed> $extra
     *
     * @return Closure(States): void
     */
    private function screen(string $mode, string $text, array $extra = []): Closure
    {
        return static function (States $states) use ($mode, $text, $extra): void {
            $states->defineOrModifyState(self::ATM, ['mode' => $mode, 'screen' => $text, ...$extra]);
        };
    }

    /**
     * A message explaining why the session takes another way; it stays on the screen
     * while the next steps (e.g. ejecting the card) change the main text.
     *
     * @param array<string,mixed> $extra
     *
     * @return Closure(States): void
     */
    private function notice(string $text, array $extra = []): Closure
    {
        return static function (States $states) use ($text, $extra): void {
            $states->defineOrModifyState(self::ATM, ['mode' => 'busy', 'notice' => $text, ...$extra]);
        };
    }

    /**
     * Moves the key press into the session and frees the keypad for the next one.
     *
     * @return Closure(States): void
     */
    private function readKeypad(): Closure
    {
        return static function (States $states): void {
            $key = $states->getState(self::KEYPAD)?->values() ?? [];
            $states->removeStateByName(self::KEYPAD);

            $values = ['mode' => 'busy', 'notice' => null, 'lastKey' => $key['type'] ?? null];
            if (($key['type'] ?? null) === 'pin') {
                $values['pin'] = (string) $key['value'];
            }
            if (($key['type'] ?? null) === 'amount') {
                $values['amount'] = (int) $key['value'];
                $values['wantsReceipt'] = (bool) ($key['receipt'] ?? false);
            }
            $states->defineOrModifyState(self::ATM, $values);
        };
    }

    private function awaitKeypad(string $name, ClockInterface $clock): Definition
    {
        return AwaitStateComponent::create($name, self::KEYPAD, timeout: self::INPUT_TIMEOUT, clock: $clock);
    }
}
