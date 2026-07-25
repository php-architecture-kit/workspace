<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Env\Environment;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\GroupNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;

class ValueNode extends GroupNode
{
    /** @param NodeAttributeInterface[] $attributes */
    public static function create(array $attributes = []): self
    {
        return new self(
            name: 'value',
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

    public function addNode(SimpleExpansionNode|BracedExpansionNode $node): self
    {
        $this->addAttribute(NodeAttribute::fromNode($node->setParent($this)));
        return $this;
    }

    /**
     * @param (callable(NodeAttribute):bool)|null $filter
     * @return array<SimpleExpansionNode|BracedExpansionNode>
     */
    public function getNodes(?callable $filter = null): array
    {
        $result = [];
        foreach ($this->getAttributes() as $attr) {
            if (!$attr instanceof NodeAttribute || ($filter !== null && !$filter($attr))) {
                continue;
            }
            /** @var SimpleExpansionNode|BracedExpansionNode $node */
            $node = $attr->node;
            $result[] = $node;
        }
        return $result;
    }
}
