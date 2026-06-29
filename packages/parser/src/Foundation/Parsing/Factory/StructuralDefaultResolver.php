<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Factory;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Defaults;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Context\ContextStack;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;

/**
 * Bridges a structural slot's {@see Defaults} (style + context → string) into a
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
    public function resolveValue(Defaults $defaults, ContextStack $context, string $style): string
    {
        $factory = $defaults->factoryFor($style);

        return $factory !== null ? ($factory)($context, $style) : '';
    }

    /**
     * Resolves a structural slot's Defaults into a concrete structural attribute.
     *
     * @param string $name the slot's attribute name (e.g. `comma`, or the trivia
     *                      group name such as `trivia`)
     */
    public function resolve(string $name, Defaults $defaults, ContextStack $context, string $style): NodeAttributeInterface
    {
        $value = $this->resolveValue($defaults, $context, $style);
        $role = $defaults->managedRole;

        if ($role !== null) {
            $roleNode = new Node($role, NodeOrigin::Region, [new RawRegionAttribute(null, $value === '' ? null : $value, null, $role)], null);

            return new GroupAttribute($name, [$roleNode]);
        }

        return new StructureAttribute(true, $name, $value === '' ? null : $value);
    }
}
