<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Env\Dotenv;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\GroupNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;

class DoubleQuotedValueNode extends GroupNode
{
    /** @param NodeAttributeInterface[] $attributes */
    public static function create(array $attributes = []): self
    {
        return new self(
            name: 'doubleQuotedValue',
            origin: NodeOrigin::Region,
            attributes: $attributes,
            parent: null,
        );
    }

    public function addLineContinuation(string $content): self
    {
        $this->addAttribute(new RawContentAttribute($content, 'lineContinuation'));
        return $this;
    }

    /** @return string[] */
    public function getLineContinuations(): array
    {
        $result = [];
        foreach ($this->getByName('lineContinuation') as $attr) {
            if ($attr instanceof RawContentAttribute) {
                $result[] = $attr->content;
            }
        }
        return $result;
    }

    public function removeLineContinuationByIndex(int $index): self
    {
        $matches = $this->getByName('lineContinuation');
        if (isset($matches[$index])) {
            $this->removeAttribute($matches[$index]);
        }
        return $this;
    }

    public function addEscapeChar(string $content): self
    {
        $this->addAttribute(new RawContentAttribute($content, 'escapeChar'));
        return $this;
    }

    /** @return string[] */
    public function getEscapeChars(): array
    {
        $result = [];
        foreach ($this->getByName('escapeChar') as $attr) {
            if ($attr instanceof RawContentAttribute) {
                $result[] = $attr->content;
            }
        }
        return $result;
    }

    public function removeEscapeCharByIndex(int $index): self
    {
        $matches = $this->getByName('escapeChar');
        if (isset($matches[$index])) {
            $this->removeAttribute($matches[$index]);
        }
        return $this;
    }

    public function addDoubleQuotedContent(string $content): self
    {
        $this->addAttribute(new RawContentAttribute($content, 'doubleQuotedContent'));
        return $this;
    }

    /** @return string[] */
    public function getDoubleQuotedContents(): array
    {
        $result = [];
        foreach ($this->getByName('doubleQuotedContent') as $attr) {
            if ($attr instanceof RawContentAttribute) {
                $result[] = $attr->content;
            }
        }
        return $result;
    }

    public function removeDoubleQuotedContentByIndex(int $index): self
    {
        $matches = $this->getByName('doubleQuotedContent');
        if (isset($matches[$index])) {
            $this->removeAttribute($matches[$index]);
        }
        return $this;
    }
}
