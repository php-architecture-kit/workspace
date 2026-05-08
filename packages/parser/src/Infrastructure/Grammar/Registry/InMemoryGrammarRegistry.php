<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Registry;

use InvalidArgumentException;
use PhpArchitecture\Parser\Foundation\Grammar\Contract\GrammarDefinitionInterface;
use PhpArchitecture\Parser\Foundation\Grammar\Contract\GrammarRegistry;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonRfc8259;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Technical\Whitespace;

final class InMemoryGrammarRegistry implements GrammarRegistry
{
    /**
     * Creates a registry pre-loaded with all built-in grammar definitions:
     *  - json       (rfc8259)   — JsonRfc8259
     *  - technical  (whitespace) — Whitespace
     */
    public static function withBuiltIn(): self
    {
        return (new self())->register(
            new JsonRfc8259(),
            new Whitespace(),
        );
    }

    /** @var array<string, GrammarDefinitionInterface> */
    private array $definitions = [];

    /** @var array<string, Grammar> */
    private array $cache = [];

    /**
     * Register one or more grammar definitions.
     */
    public function register(GrammarDefinitionInterface ...$definitions): self
    {
        foreach ($definitions as $definition) {
            $grammar = $definition->grammar();
            $key = $this->key($grammar->name, $grammar->variant);
            $this->definitions[$key] = $definition;
            unset($this->cache[$key]);
        }

        return $this;
    }

    public function get(string $name, ?string $variant = null): Grammar
    {
        $key = $this->key($name, $variant);

        if (!isset($this->definitions[$key])) {
            $label = $variant !== null ? "{$name} ({$variant})" : $name;
            throw new InvalidArgumentException("Grammar '{$label}' is not registered.");
        }

        return $this->cache[$key] ??= $this->definitions[$key]->grammar();
    }

    public function getDefinition(string $name, ?string $variant = null): GrammarDefinitionInterface
    {
        $key = $this->key($name, $variant);

        if (!isset($this->definitions[$key])) {
            $label = $variant !== null ? "{$name} ({$variant})" : $name;
            throw new InvalidArgumentException("Grammar '{$label}' is not registered.");
        }

        return $this->definitions[$key];
    }

    public function has(string $name, ?string $variant = null): bool
    {
        return isset($this->definitions[$this->key($name, $variant)]);
    }

    /**
     * @return list<array{name: string, variant: string|null}>
     */
    public function list(): array
    {
        $result = [];
        foreach (array_keys($this->definitions) as $key) {
            [$name, $variant] = explode('|', $key, 2);
            $result[] = ['name' => $name, 'variant' => $variant === '' ? null : $variant];
        }
        return $result;
    }

    private function key(string $name, ?string $variant): string
    {
        return $name . '|' . ($variant ?? '');
    }
}
