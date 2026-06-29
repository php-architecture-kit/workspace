<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Env\Environment;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\GroupNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;

class EnvExpressionNode extends GroupNode
{
    /** @param NodeAttributeInterface[] $attributes */
    public static function create(array $attributes = []): self
    {
        return new self(
            name: 'envExpression',
            origin: NodeOrigin::Region,
            attributes: $attributes,
            parent: null,
        );
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

    public function addUnknown(string $content): self
    {
        $this->addAttribute(new RawContentAttribute($content, 'unknown'));
        return $this;
    }

    /** @return string[] */
    public function getUnknowns(): array
    {
        $result = [];
        foreach ($this->getByName('unknown') as $attr) {
            if ($attr instanceof RawContentAttribute) {
                $result[] = $attr->content;
            }
        }
        return $result;
    }

    public function removeUnknownByIndex(int $index): self
    {
        $matches = $this->getByName('unknown');
        if (isset($matches[$index])) {
            $this->removeAttribute($matches[$index]);
        }
        return $this;
    }

    public function addString(string $content): self
    {
        $this->addAttribute(new RawContentAttribute($content, 'string'));
        return $this;
    }

    /** @return string[] */
    public function getStrings(): array
    {
        $result = [];
        foreach ($this->getByName('string') as $attr) {
            if ($attr instanceof RawContentAttribute) {
                $result[] = $attr->content;
            }
        }
        return $result;
    }

    public function removeStringByIndex(int $index): self
    {
        $matches = $this->getByName('string');
        if (isset($matches[$index])) {
            $this->removeAttribute($matches[$index]);
        }
        return $this;
    }
}
