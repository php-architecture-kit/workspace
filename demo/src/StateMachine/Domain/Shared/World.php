<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Domain\Shared;

use PhpArchitecture\StateMachine\Foundation\State\States;

/**
 * Business view of an Execution's States: named states as plain key-value maps.
 *
 * Every node, condition and outside actor (a button, the bank) reads and writes the same
 * States - the state is shared by the whole graph and persisted with the Execution.
 */
final readonly class World
{
    public function __construct(
        public States $states,
    ) {
    }

    public function has(string $state): bool
    {
        return $this->states->getState($state) !== null;
    }

    /** @return array<string,mixed> */
    public function get(string $state): array
    {
        $found = $this->states->getState($state);
        if ($found === null) {
            return [];
        }

        $values = [];
        foreach ($found->details as $name => $detail) {
            $values[$name] = $detail->value;
        }

        return $values;
    }

    public function value(string $state, string $detail, mixed $default = null): mixed
    {
        return $this->get($state)[$detail] ?? $default;
    }

    /**
     * Defines the state or sets the given details on the existing one.
     *
     * @param array<string,mixed> $values
     */
    public function put(string $state, array $values): void
    {
        $found = $this->states->getState($state);
        if ($found === null) {
            $this->states->defineState($state, $values);

            return;
        }

        $changed = array_filter(
            $values,
            static fn (mixed $value, string $name): bool => !array_key_exists($name, $found->details) || $found->details[$name]->value !== $value,
            ARRAY_FILTER_USE_BOTH,
        );
        if ($changed !== []) {
            $this->states->modifyState($found->id, $changed);
        }
    }

    public function remove(string $state): void
    {
        $found = $this->states->getState($state);
        if ($found !== null) {
            $this->states->removeState($found->id);
        }
    }
}
