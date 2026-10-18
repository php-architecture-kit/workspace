<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Domain\Atm\Bank;

use PhpArchitecture\Demo\StateMachine\Domain\Atm\Task\AuthorizeWithdrawal;
use PhpArchitecture\Demo\StateMachine\Domain\Atm\Task\ReverseWithdrawal;
use PhpArchitecture\Demo\StateMachine\Domain\Atm\Task\VerifyPin;
use PhpArchitecture\StateMachine\Foundation\Task\Task;

/**
 * The card issuer's authorization host - an external system the ATM talks to through tasks.
 * Lives outside the Execution: its accounts outlast every ATM session.
 */
final class Bank
{
    /** @param array<string,array{holder:string,pin:string,balance:int,dailyLimit:int,withdrawnToday:int}> $accounts */
    private function __construct(
        private array $accounts,
    ) {
    }

    public static function withDemoAccounts(): self
    {
        return new self([
            '4000 1234 5678 9010' => ['holder' => 'Jan Kowalski', 'pin' => '1234', 'balance' => 2500, 'dailyLimit' => 3000, 'withdrawnToday' => 0],
            '4000 9876 5432 1098' => ['holder' => 'Anna Nowak', 'pin' => '4321', 'balance' => 300, 'dailyLimit' => 1000, 'withdrawnToday' => 0],
        ]);
    }

    /** @return array<string,array{holder:string,pin:string,balance:int,dailyLimit:int,withdrawnToday:int}> */
    public function accounts(): array
    {
        return $this->accounts;
    }

    /** What the bank would answer, without changing any account. */
    public function assess(Task $task): BankAnswer
    {
        return match (true) {
            $task instanceof VerifyPin => $this->assessPin($task),
            $task instanceof AuthorizeWithdrawal => $this->assessWithdrawal($task),
            $task instanceof ReverseWithdrawal => isset($this->accounts[$task->cardNumber])
                ? BankAnswer::approved('Withdrawal reversed')
                : BankAnswer::declined('Unknown card'),
            default => BankAnswer::declined('Unsupported request'),
        };
    }

    /** Applies the answer that was sent back to the ATM. */
    public function commit(Task $task, BankAnswer $answer): void
    {
        if (!$answer->approved) {
            return;
        }

        if ($task instanceof AuthorizeWithdrawal) {
            $this->accounts[$task->cardNumber]['balance'] -= $task->amount;
            $this->accounts[$task->cardNumber]['withdrawnToday'] += $task->amount;
        }

        if ($task instanceof ReverseWithdrawal) {
            $this->accounts[$task->cardNumber]['balance'] += $task->amount;
            $this->accounts[$task->cardNumber]['withdrawnToday'] -= $task->amount;
        }
    }

    private function assessPin(VerifyPin $task): BankAnswer
    {
        $account = $this->accounts[$task->cardNumber] ?? null;
        if ($account === null) {
            return BankAnswer::declined('Unknown card');
        }

        return $account['pin'] === $task->pin ? BankAnswer::approved('PIN correct') : BankAnswer::declined('Wrong PIN');
    }

    private function assessWithdrawal(AuthorizeWithdrawal $task): BankAnswer
    {
        $account = $this->accounts[$task->cardNumber] ?? null;

        return match (true) {
            $account === null => BankAnswer::declined('Unknown card'),
            $task->amount > $account['balance'] => BankAnswer::declined('Insufficient funds'),
            $account['withdrawnToday'] + $task->amount > $account['dailyLimit'] => BankAnswer::declined('Daily limit exceeded'),
            default => BankAnswer::approved('Approved'),
        };
    }
}
