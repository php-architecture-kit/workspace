<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\LazyOperators\Presentation\View;

use PhpArchitecture\LazyOperators\Foundation\Expression\Expression;
use PhpArchitecture\LazyOperators\Foundation\Registry\Entry\Argument\CallbackArgument;
use PhpArchitecture\LazyOperators\Foundation\Registry\Entry\Argument\CaseArgument;
use PhpArchitecture\LazyOperators\Foundation\Registry\Entry\Argument\EnumArgument;
use PhpArchitecture\LazyOperators\Foundation\Registry\Entry\ExpressionArgument;
use PhpArchitecture\LazyOperators\Foundation\Registry\Entry\ExpressionEntry;
use PhpArchitecture\LazyOperators\Foundation\Registry\ExpressionRegistryInterface;
use ReflectionClass;

/**
 * Turns the registry's entries into what the builder UI needs: how to edit each argument
 * ("kind") and which nodes fit where (every value type a node implements, derived from its class).
 */
final class CatalogViewFactory
{
    private const NATIVE_TYPES = ['int', 'float', 'bool', 'string', 'array', 'null'];

    public function __construct(
        private readonly ExpressionRegistryInterface $registry,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function create(): array
    {
        return array_map($this->entry(...), $this->registry->getAll());
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(ExpressionEntry $entry): array
    {
        return [
            'key' => $entry->key,
            'version' => $entry->version,
            'name' => $entry->attributes->name->value ?? $entry->key,
            'group' => $entry->attributes->group->value ?? 'Other',
            'formula' => $entry->attributes->formula?->value,
            'description' => $entry->attributes->description?->value,
            'type' => $entry->type,
            'types' => $this->valueTypes($entry->fqcn),
            'available' => $entry->available,
            'unavailableReason' => $entry->unavailableReason,
            'arguments' => array_map($this->argument(...), $entry->arguments),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function argument(ExpressionArgument $argument): array
    {
        $tokens = explode('|', ltrim($argument->type, '?'));
        $native = array_diff($tokens, self::NATIVE_TYPES) === [];

        return [
            'name' => $argument->name,
            'kind' => match (true) {
                $argument instanceof CallbackArgument => 'callback',
                $argument instanceof EnumArgument => 'enum',
                $argument instanceof CaseArgument => 'cases',
                $argument->spread => 'list',
                $native => 'native',
                default => 'expression',
            },
            'type' => $argument->type,
            'accepts' => array_values(array_diff($tokens, self::NATIVE_TYPES)),
            'nativeType' => $native ? $tokens[0] : null,
            'optional' => $argument->optional,
            'defaultValue' => $argument->defaultValue,
            'description' => $argument->description?->value,
            'options' => $argument instanceof EnumArgument ? $argument->options : null,
            'fields' => $argument instanceof CaseArgument ? array_map($this->argument(...), $argument->fields) : null,
        ];
    }

    /**
     * Every value-type interface the node implements (e.g. IntegerValue, NumberValue, or an
     * extension's own such as DateTimeValue), plus Expression, so the UI can tell whether it fits
     * an argument typed with any of them.
     *
     * @return list<string>
     */
    private function valueTypes(string $class): array
    {
        $types = [];

        foreach (class_implements($class) ?: [] as $interface) {
            if (is_subclass_of($interface, Expression::class)) {
                $types[] = (new ReflectionClass($interface))->getShortName();
            }
        }

        $types[] = (new ReflectionClass(Expression::class))->getShortName();

        return $types;
    }
}
