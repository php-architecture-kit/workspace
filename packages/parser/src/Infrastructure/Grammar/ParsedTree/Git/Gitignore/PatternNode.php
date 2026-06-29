<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitignore;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\GroupNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;

class PatternNode extends GroupNode
{
    /** @param NodeAttributeInterface[] $attributes */
    public static function create(array $attributes = []): self
    {
        return new self(
            name: 'pattern',
            origin: NodeOrigin::Region,
            attributes: $attributes,
            parent: null,
        );
    }

    public function addRawContent(string $content, string $name): self
    {
        $this->addAttribute(new RawContentAttribute($content, $name));
        return $this;
    }

    /**
     * @param (callable(RawContentAttribute):bool)|null $filter
     * @return string[]
     */
    public function getRawContents(?callable $filter = null): array
    {
        $result = [];
        foreach ($this->getAttributes() as $attr) {
            if (!$attr instanceof RawContentAttribute || ($filter !== null && !$filter($attr))) {
                continue;
            }
            $result[] = $attr->content;
        }
        return $result;
    }
}
