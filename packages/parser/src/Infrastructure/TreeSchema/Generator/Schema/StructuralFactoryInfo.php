<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;

/**
 * Describes one structural attribute that belongs to a GroupedAttribute unit
 * (separator, trivia group). Used to generate autoFactories in withXValidation().
 */
final class StructuralFactoryInfo
{
    /**
     * Node names observed inside this structural slot across sample parses —
     * only populated for GroupAttribute-typed slots (trivia). Mirrors
     * AttributeSchema::$unionNodeNames but scoped to one named structural
     * position within a SequenceAttribute, used to decide whether that
     * specific slot has a non-whitespace alternative worth a
     * TriviaInsertionPolicy hook.
     *
     * @var string[]
     */
    public array $unionNodeNames = [];

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
