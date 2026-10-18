<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Presentation\View;

use DateTimeInterface;
use PhpArchitecture\Demo\StateMachine\Application\RunResult;
use PhpArchitecture\Demo\StateMachine\Domain\Shared\DemoStateMachine;
use PhpArchitecture\StateMachine\Foundation\Pointer\NodeHandlingStatus;
use PhpArchitecture\StateMachine\Foundation\State\State;

final class SnapshotViewFactory
{
    public function __construct(
        private readonly GraphLayout $layout,
    ) {
    }

    /** @return array<string,mixed> the static part: boxes and connections */
    public function graph(string $scenarioId, DemoStateMachine $machine): array
    {
        $positions = $this->layout->of($scenarioId);
        $nodes = [];
        foreach ($machine->boxes() as $id => $box) {
            [$x, $y] = $positions[$id] ?? [5, count($nodes)];
            $nodes[] = ['id' => $id, 'label' => $box['label'], 'kind' => $box['kind'], 'x' => $x, 'y' => $y];
        }

        return ['nodes' => $nodes, 'edges' => $machine->connections()];
    }

    /** @return array<string,mixed> */
    public function snapshot(RunResult $result): array
    {
        $session = $result->session;
        $execution = $session->execution;

        $pointers = [];
        foreach ($execution->pointers->pointers as $pointer) {
            $pointers[] = [
                'name' => $session->pointerName($pointer->id->toString()),
                'box' => $result->machine->displayOf($pointer->nodeId),
                'node' => $result->machine->nodeName($pointer->nodeId),
                'waiting' => $pointer->handlingStatus === NodeHandlingStatus::Completed,
            ];
        }
        usort($pointers, static fn (array $a, array $b): int => strnatcmp($a['name'], $b['name']));

        $states = [];
        foreach ($execution->states->states as $state) {
            $details = [];
            foreach ($state->details as $name => $detail) {
                $details[$result->machine->readable((string) $name)] = $this->format($detail->value);
            }
            $states[] = ['name' => $state->name, 'technical' => $state->name === State::Technical, 'details' => $details];
        }
        usort($states, static fn (array $a, array $b): int => [$a['technical'], $a['name']] <=> [$b['technical'], $b['name']]);

        return [
            'time' => $session->time->format('H:i:s'),
            'status' => $result->status,
            'pointers' => $pointers,
            'trail' => $result->trail,
            'states' => $states,
            'log' => array_slice($session->log, -120),
            'panel' => $result->scenario->panel($session),
        ];
    }

    private function format(mixed $value): string
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format('H:i:s'),
            is_string($value) => $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }
}
