<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Exception;

use LogicException;

/**
 * Thrown when a single node materializes two attributes that resolve to the same property
 * name (e.g. two `?asterisk/s` slots in one sequence). The facade generator needs a unique
 * name per attribute, so it cannot emit a valid class — the grammar author must give the
 * colliding sequence node(s) a distinct anchor name to disambiguate.
 */
final class AmbiguousAttributeNameException extends LogicException
{
    public static function forNode(
        string $nodeName,
        string $attributeName,
        int $firstPosition,
        int $secondPosition,
    ): self {
        return new self(sprintf(
            'Node `%s` declares two attributes named `%s` (positions %d and %d). The facade '
            . 'generator needs a unique name per attribute. Give the colliding sequence node(s) '
            . 'a distinct anchor name in the grammar, e.g. `?%s[opening]/s` and `?%s[closing]/s`.',
            $nodeName,
            $attributeName,
            $firstPosition,
            $secondPosition,
            $attributeName,
            $attributeName,
        ));
    }
}
