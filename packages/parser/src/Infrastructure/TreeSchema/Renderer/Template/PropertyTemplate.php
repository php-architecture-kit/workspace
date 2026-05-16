<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Renderer\Template;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use Stringable;

final class PropertyTemplate implements Stringable
{
    /**
     * @param class-string<NodeAttributeInterface> $attributeClass
     * @param class-string<NodeInterface>[] $nodeClasses
     */
    public function __construct(
        public string $name,
        public string $attributeClass,
        public array $nodeClasses = [],
    ) {}

    public function addNodeClass(string $fqcn): void
    {
        if (!in_array($fqcn, $this->nodeClasses, true)) {
            $this->nodeClasses[] = $fqcn;
        }
    }

    public function __toString(): string
    {
        $attribute   = self::shortName($this->attributeClass);
        $nodesUnion  = implode('|', array_map(self::shortName(...), $this->nodeClasses));

        return empty($nodesUnion)
            ? " * @property {$attribute} \${$this->name}"
            : " * @property {$attribute}<{$nodesUnion}> \${$this->name}";
    }

    private static function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos !== false ? substr($fqcn, $pos + 1) : $fqcn;
    }
}
