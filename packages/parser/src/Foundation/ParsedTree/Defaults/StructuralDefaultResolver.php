<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\ParsedTree\Defaults;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Creation\DefaultsDefinition;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Foundation\ParsedTree\Context\ContextStack;

/**
 * Bridges a structural slot's {@see DefaultsDefinition} (style + context → string) into a
 * concrete structural {@see NodeAttributeInterface}.
 *
 * - A trivia slot (managedRole !== null) produces a role-named whitespace Node
 *   (e.g. `leadingWs`) wrapped in the slot's trivia GroupAttribute.
 * - A plain separator (managedRole === null) produces a StructureAttribute.
 *
 * Consumed by NodeAttrFactory's autoFactories (addUnit) and by the reformat walk.
 */
final class StructuralDefaultResolver
{
    /**
     * Resolves only the default *text* for a structural slot in the given style.
     */
    public function resolveValue(DefaultsDefinition $defaults, ContextStack $context, string $style): string
    {
        $factory = $defaults->factoryFor($style);

        return $factory !== null ? ($factory)($context, $style) : '';
    }

    /**
     * Resolves a structural slot's DefaultsDefinition into a concrete structural attribute.
     *
     * @param string $name the slot's attribute name (e.g. `comma`, or the trivia
     *                      group name such as `trivia`)
     */
    public function resolve(string $name, DefaultsDefinition $defaults, ContextStack $context, string $style): NodeAttributeInterface
    {
        $value = $this->resolveValue($defaults, $context, $style);

        return new StructureAttribute(true, $name, $value === '' ? null : $value);
    }
}
