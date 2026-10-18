<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\Tests\StateMachine;

final class AtmScenarioTest extends ScenarioTestCase
{
    private const string JAN = '4000 1234 5678 9010';
    private const string ANNA = '4000 9876 5432 1098';

    protected function scenario(): string
    {
        return 'atm';
    }

    public function testCashWithdrawalCardFirstThenCashWithAParallelReceipt(): void
    {
        $this->act('insertCard', ['card' => self::JAN]);
        $snapshot = $this->act('key', ['type' => 'pin', 'value' => '1234']);
        self::assertSame('amount', $snapshot['panel']['atm']['mode']);

        $snapshot = $this->act('key', ['type' => 'amount', 'value' => 200, 'receipt' => true]);
        self::assertSame('take-card', $snapshot['panel']['atm']['mode'], 'the card comes back before the cash');
        self::assertSame(['demo.atm.await-card-first'], self::at($snapshot));

        $snapshot = $this->act('takeCard');
        self::assertContains('demo.atm.fork', $snapshot['trail']);
        self::assertContains('demo.atm.count-cash', $snapshot['trail']);
        self::assertContains('demo.atm.print-receipt', $snapshot['trail']);
        self::assertContains('demo.atm.join', $snapshot['trail']);
        self::assertTrue(self::logContains($snapshot, 'forks'), 'the fork creates parallel pointers');
        self::assertSame('presented', $snapshot['panel']['atm']['cashSlot']);
        self::assertSame('printed', $snapshot['panel']['atm']['receipt']);

        $snapshot = $this->act('takeCash');
        self::assertSame('completed', $snapshot['status']);
        self::assertSame('Cash withdrawn: 200', $snapshot['panel']['atm']['outcome']);
        self::assertSame(2300, $this->account($snapshot, self::JAN)['balance']);
    }

    public function testWrongPinLoopsBackWithAFreshTimeoutAndTheThirdRetainsTheCard(): void
    {
        $this->act('insertCard', ['card' => self::JAN]);
        $this->tick(25);
        $snapshot = $this->act('key', ['type' => 'pin', 'value' => '0000']);
        self::assertSame('pin', $snapshot['panel']['atm']['mode']);
        self::assertSame(['demo.atm.await-pin'], self::at($snapshot), 'back in the same AwaitState component');

        // 25 s into the first window, 20 s into the second one: still waiting for the PIN
        $snapshot = $this->tick(20);
        self::assertSame('pin', $snapshot['panel']['atm']['mode']);

        $this->act('key', ['type' => 'pin', 'value' => '1111']);
        $snapshot = $this->act('key', ['type' => 'pin', 'value' => '2222']);
        self::assertSame('retained', $snapshot['panel']['atm']['cardSlot']);
        self::assertSame('completed', $snapshot['status']);
    }

    public function testCashNotTakenIsRetractedAndTheWithdrawalReversed(): void
    {
        $this->act('insertCard', ['card' => self::JAN]);
        $this->act('key', ['type' => 'pin', 'value' => '1234']);
        $this->act('key', ['type' => 'amount', 'value' => 500, 'receipt' => false]);
        $snapshot = $this->act('takeCard');
        self::assertSame(2000, $this->account($snapshot, self::JAN)['balance']);
        self::assertSame('skipped', $snapshot['panel']['atm']['receipt']);

        $snapshot = $this->tick(31);
        self::assertContains('demo.atm.retract-cash', $snapshot['trail']);
        self::assertContains('demo.atm.bank-reverse', $snapshot['trail']);
        self::assertSame('completed', $snapshot['status']);
        self::assertSame(2500, $this->account($snapshot, self::JAN)['balance']);
    }

    public function testDeclinedByTheBankAnsweredLaterFromTheInbox(): void
    {
        $this->act('bankAuto', ['on' => false]);
        $this->act('insertCard', ['card' => self::ANNA]);
        $snapshot = $this->act('key', ['type' => 'pin', 'value' => '4321']);
        self::assertSame('VerifyPin', $snapshot['panel']['bank']['inbox'][0]['task']);
        self::assertSame(['demo.atm.bank-verify-pin'], self::at($snapshot));

        $snapshot = $this->act('bankAnswer', ['task' => $snapshot['panel']['bank']['inbox'][0]['id']]);
        $snapshot = $this->act('key', ['type' => 'amount', 'value' => 1000, 'receipt' => false]);
        self::assertSame('Insufficient funds', $snapshot['panel']['bank']['inbox'][0]['assessment']);

        $snapshot = $this->act('bankAnswer', ['task' => $snapshot['panel']['bank']['inbox'][0]['id']]);
        self::assertSame('Declined: Insufficient funds.', $snapshot['panel']['atm']['notice']);
        self::assertSame('take-card', $snapshot['panel']['atm']['mode']);
    }

    public function testBankThatDoesNotAnswerEndsTheSessionThroughTheTimeout(): void
    {
        $this->act('bankAuto', ['on' => false]);
        $this->act('insertCard', ['card' => self::JAN]);
        $this->act('key', ['type' => 'pin', 'value' => '1234']);

        $snapshot = $this->tick(21);
        self::assertContains('demo.atm.bank-unavailable', $snapshot['trail']);
        self::assertSame('take-card', $snapshot['panel']['atm']['mode']);
    }

    public function testTheWorldRejectsImpossibleActions(): void
    {
        self::assertSame('There is nothing to take.', $this->rejected('takeCash'));
        self::assertSame('The keypad does not accept this now.', $this->rejected('key', ['type' => 'pin', 'value' => '1234']));
    }

    public function testNextCustomerStartsANewExecutionButTheBankRemembers(): void
    {
        $this->act('insertCard', ['card' => self::JAN]);
        $this->act('key', ['type' => 'pin', 'value' => '1234']);
        $this->act('key', ['type' => 'amount', 'value' => 100, 'receipt' => false]);
        $this->act('takeCard');
        $this->act('takeCash');

        $snapshot = $this->act('reset');
        self::assertSame('insert-card', $snapshot['panel']['atm']['mode']);
        self::assertSame(2, $snapshot['panel']['customer']);
        self::assertSame(2400, $this->account($snapshot, self::JAN)['balance']);
    }

    /**
     * @param array<string,mixed> $snapshot
     *
     * @return array<string,mixed>
     */
    private function account(array $snapshot, string $number): array
    {
        foreach ($snapshot['panel']['bank']['accounts'] as $account) {
            if ($account['number'] === $number) {
                return $account;
            }
        }

        self::fail("No account {$number}");
    }
}
