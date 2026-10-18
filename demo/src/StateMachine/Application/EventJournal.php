<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Application;

use DateTimeInterface;
use PhpArchitecture\Demo\StateMachine\Domain\Shared\DemoStateMachine;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Pointer\Event\PointerCreatedEvent;
use PhpArchitecture\StateMachine\Foundation\Pointer\Event\PointerForkedEvent;
use PhpArchitecture\StateMachine\Foundation\Pointer\Event\PointerJoinedEvent;
use PhpArchitecture\StateMachine\Foundation\Pointer\Event\PointerRemovedEvent;
use PhpArchitecture\StateMachine\Foundation\Pointer\Event\PointerTransitionedEvent;
use PhpArchitecture\StateMachine\Foundation\State\Event\StateDefinedEvent;
use PhpArchitecture\StateMachine\Foundation\State\Event\StateModifiedEvent;
use PhpArchitecture\StateMachine\Foundation\State\Event\StateRemovedEvent;
use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;
use PhpArchitecture\StateMachine\Foundation\State\State;

/**
 * Turns the domain events recorded by Pointers and States into the scenario log,
 * and reports the boxes the pointers passed through.
 */
final class EventJournal
{
    /** @return list<string> display ids entered, in order */
    public function record(ScenarioSession $session, DemoStateMachine $machine): array
    {
        $execution = $session->execution;
        $trail = [];

        foreach ($execution->pointers->releaseEvents() as $event) {
            match (true) {
                $event instanceof PointerCreatedEvent => $session->note('pointer', sprintf('%s starts at %s', $session->pointerName($event->pointerId->toString()), $machine->nodeName($event->nodeId))),
                $event instanceof PointerForkedEvent => $session->note('pointer', sprintf('%s forks %s', $session->pointerName($event->parentId->toString()), $session->pointerName($event->pointerId->toString()))),
                $event instanceof PointerJoinedEvent => $session->note('pointer', sprintf('%s joins at %s', $session->pointerName($event->pointerId->toString()), $machine->nodeName($event->nodeId))),
                $event instanceof PointerRemovedEvent => $session->note('pointer', sprintf('%s ends at %s', $session->pointerName($event->pointerId->toString()), $machine->nodeName($event->lastNodeId))),
                $event instanceof PointerTransitionedEvent => $session->note('pointer', sprintf('%s → %s', $session->pointerName($event->pointerId->toString()), $machine->nodeName($event->output))),
                default => null,
            };

            if ($event instanceof PointerTransitionedEvent) {
                $display = $machine->displayOf($event->output);
                if (end($trail) !== $display) {
                    $trail[] = $display;
                }
            }
        }

        foreach ($execution->states->releaseEvents() as $event) {
            if ($event instanceof StateDefinedEvent) {
                $session->stateNames[$event->stateId->toString()] = $event->stateName;
                $session->note($this->kind($event->stateName), $machine->readable(sprintf('state %s defined %s', $event->stateName, $this->details($event->details))));
            }
            if ($event instanceof StateModifiedEvent) {
                $name = $session->stateNames[$event->stateId->toString()] ?? $this->nameOf($execution, $event->stateId->toString());
                $removed = array_diff_key($event->removedDetails, $event->addedDetails);
                $text = sprintf('state %s: %s', $name, $this->details($event->addedDetails));
                if ($removed !== []) {
                    $text .= ' removed ' . implode(', ', array_keys($removed));
                }
                $session->note($this->kind($name), $machine->readable($text));
            }
            if ($event instanceof StateRemovedEvent) {
                $session->note('state', sprintf('state %s removed', $session->stateNames[$event->stateId->toString()] ?? '?'));
            }
        }

        return $trail;
    }

    private function kind(string $stateName): string
    {
        return $stateName === State::Technical ? 'technical' : 'state';
    }

    private function nameOf(Execution $execution, string $stateId): string
    {
        return ($execution->states->states[$stateId] ?? null)?->name ?? '?';
    }

    /** @param array<array-key,mixed> $details */
    private function details(array $details): string
    {
        $parts = [];
        foreach ($details as $key => $detail) {
            $name = $detail instanceof StateDetail ? $detail->name : (string) $key;
            $value = $detail instanceof StateDetail ? $detail->value : $detail;
            $parts[] = $name . '=' . self::format($value);
        }

        return '{' . implode(', ', $parts) . '}';
    }

    public static function format(mixed $value): string
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format('H:i:s'),
            is_string($value) => mb_strimwidth($value, 0, 48, '…'),
            is_object($value) => (new \ReflectionClass($value))->getShortName(),
            default => (string) json_encode($value),
        };
    }
}
