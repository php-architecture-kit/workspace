<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;

/**
 * Describes one structural attribute that belongs to a GroupedAttribute unit
 * (separator, trivia group). Used to generate autoFactories in withXValidation().
 */
final class StructuralFactoryInfo
{
    public function __construct(
        public readonly string $name,
        public readonly string $attrClass,
        public readonly ?string $content = null,
    ) {}

    public function isStructureAttribute(): bool
    {
        return $this->attrClass === StructureAttribute::class;
    }

    public function isGroupAttribute(): bool
    {
        return $this->attrClass === GroupAttribute::class;
    }
}
