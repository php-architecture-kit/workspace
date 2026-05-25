<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Renderer\Template;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use Stringable;

final class PropertyTemplate implements Stringable
{
    /**
     * @param class-string<NodeAttributeInterface> $attributeClass
     * @param TypeRef[] $typeRefs
     */
    public function __construct(
        public string $name,
        public string $attributeClass,
        public array $typeRefs = [],
    ) {}

    public function addTypeRef(TypeRef $incoming): void
    {
        foreach ($this->typeRefs as &$existing) {
            if ($existing->fqcn === $incoming->fqcn) {
                $existing = $existing->merge($incoming);
                return;
            }
        }
        $this->typeRefs[] = $incoming;
    }

    public function __toString(): string
    {
        $attribute = self::shortName($this->attributeClass);
        $union     = implode('|', array_map(fn(TypeRef $r) => $r->toShortString(), $this->typeRefs));

        return empty($union)
            ? " * @property {$attribute} \${$this->name}"
            : " * @property {$attribute}<{$union}> \${$this->name}";
    }

    private static function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos !== false ? substr($fqcn, $pos + 1) : $fqcn;
    }
}
