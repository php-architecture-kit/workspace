<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Ast\Context;

readonly class AstContextStack
{
    /**
     * @param AstNodeContext[] $stack
     */
    public function __construct(
        private array $stack = [],
    ) {}

    public function push(AstNodeContext $context): self
    {
        return new self([...$this->stack, $context]);
    }

    public function pop(): self
    {
        if (empty($this->stack)) {
            return $this;
        }

        $newStack = $this->stack;
        array_pop($newStack);

        return new self($newStack);
    }

    public function current(): ?AstNodeContext
    {
        if (empty($this->stack)) {
            return null;
        }

        return $this->stack[array_key_last($this->stack)];
    }

    public function root(): ?AstNodeContext
    {
        return $this->stack[0] ?? null;
    }

    public function at(int $index): ?AstNodeContext
    {
        if ($index < 0) {
            $index = count($this->stack) + $index;
        }

        return $this->stack[$index] ?? null;
    }

    public function named(string $name): ?AstNodeContext
    {
        foreach ($this->stack as $context) {
            if ($context->name === $name) {
                return $context;
            }
        }

        return null;
    }

    public function depth(): int
    {
        return count($this->stack);
    }

    public function isEmpty(): bool
    {
        return empty($this->stack);
    }

    public function all(): array
    {
        return $this->stack;
    }
}
