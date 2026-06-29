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

    public function addSpace(string $content): self
    {
        $this->addAttribute(new RawContentAttribute($content, 'space'));
        return $this;
    }

    /** @return string[] */
    public function getSpaces(): array
    {
        $result = [];
        foreach ($this->getByName('space') as $attr) {
            if ($attr instanceof RawContentAttribute) {
                $result[] = $attr->content;
            }
        }
        return $result;
    }

    public function removeSpaceByIndex(int $index): self
    {
        $matches = $this->getByName('space');
        if (isset($matches[$index])) {
            $this->removeAttribute($matches[$index]);
        }
        return $this;
    }

    public function addSimpleExpansion(SimpleExpansionNode $simpleExpansion): self
    {
        $this->addAttribute(NodeAttribute::fromNode($simpleExpansion->setParent($this)));
        return $this;
    }

    /** @return SimpleExpansionNode[] */
    public function getSimpleExpansions(): array
    {
        $result = [];
        foreach ($this->getByName('simpleExpansion') as $attr) {
            if ($attr instanceof NodeAttribute) {
                /** @var SimpleExpansionNode $node */
                $node = $attr->node;
                $result[] = $node;
            }
        }
        return $result;
    }

    public function removeSimpleExpansion(SimpleExpansionNode $simpleExpansion): self
    {
        foreach ($this->getByName('simpleExpansion') as $attr) {
            if ($attr instanceof NodeAttribute && $attr->node === $simpleExpansion) {
                $this->removeAttribute($attr);
                break;
            }
        }
        return $this;
    }

    public function addUnquotedText(string $content): self
    {
        $this->addAttribute(new RawContentAttribute($content, 'unquotedText'));
        return $this;
    }

    /** @return string[] */
    public function getUnquotedTexts(): array
    {
        $result = [];
        foreach ($this->getByName('unquotedText') as $attr) {
            if ($attr instanceof RawContentAttribute) {
                $result[] = $attr->content;
            }
        }
        return $result;
    }

    public function removeUnquotedTextByIndex(int $index): self
    {
        $matches = $this->getByName('unquotedText');
        if (isset($matches[$index])) {
            $this->removeAttribute($matches[$index]);
        }
        return $this;
    }

    public function addBracedExpansion(BracedExpansionNode $bracedExpansion): self
    {
        $this->addAttribute(NodeAttribute::fromNode($bracedExpansion->setParent($this)));
        return $this;
    }

    /** @return BracedExpansionNode[] */
    public function getBracedExpansions(): array
    {
        $result = [];
        foreach ($this->getByName('bracedExpansion') as $attr) {
            if ($attr instanceof NodeAttribute) {
                /** @var BracedExpansionNode $node */
                $node = $attr->node;
                $result[] = $node;
            }
        }
        return $result;
    }

    public function removeBracedExpansion(BracedExpansionNode $bracedExpansion): self
    {
        foreach ($this->getByName('bracedExpansion') as $attr) {
            if ($attr instanceof NodeAttribute && $attr->node === $bracedExpansion) {
                $this->removeAttribute($attr);
                break;
            }
        }
        return $this;
    }
}
