<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Renderer\Template;

use Stringable;

final class DocblockTemplate implements Stringable
{
    /**
     * @param PropertyTemplate[] $propertyTemplates
     */
    public function __construct(
        public array $propertyTemplates
    ) {}

    public function upsertProperty(PropertyTemplate $incoming): void
    {
        foreach ($this->propertyTemplates as $existing) {
            if ($existing->name === $incoming->name) {
                foreach ($incoming->typeRefs as $typeRef) {
                    $existing->addTypeRef($typeRef);
                }
                return;
            }
        }
        $this->propertyTemplates[] = $incoming;
    }

    public function __toString(): string
    {
        return "/**\n" . implode("\n", array_map(fn($template) => (string) $template, $this->propertyTemplates)) . "\n */";
    }
}
