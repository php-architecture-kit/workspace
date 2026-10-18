<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Application;

use DateTimeImmutable;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskEnvelope;

/**
 * Everything a scenario keeps between requests. The Execution is stored as is
 * (serialized) and resumed by the next request - nothing is replayed.
 */
final class ScenarioSession
{
    /** @var list<TaskEnvelope> tasks dispatched by the machine, not answered yet */
    public array $inbox = [];

    /** @var array<string,true> ids of every task ever taken from the task bus */
    public array $dispatched = [];

    /** @var list<array{id:int,time:string,kind:string,text:string}> */
    public array $log = [];

    /** @var array<string,string> pointer id => "P1", "P2", … */
    public array $pointerNames = [];

    /** @var array<string,string> state id => state name (removed states are only known by id) */
    public array $stateNames = [];

    public int $logSequence = 0;

    /** @param array<string,mixed> $data scenario-specific (e.g. the bank) */
    public function __construct(
        public Execution $execution,
        public DateTimeImmutable $time,
        public array $data = [],
    ) {
    }

    public function note(string $kind, string $text): void
    {
        $this->log[] = ['id' => ++$this->logSequence, 'time' => $this->time->format('H:i:s'), 'kind' => $kind, 'text' => $text];
        $this->log = array_slice($this->log, -150);
    }

    public function pointerName(string $pointerId): string
    {
        return $this->pointerNames[$pointerId] ??= 'P' . (count($this->pointerNames) + 1);
    }
}
