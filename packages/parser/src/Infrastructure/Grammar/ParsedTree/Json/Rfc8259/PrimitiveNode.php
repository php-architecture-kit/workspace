<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259;

use InvalidArgumentException;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

class PrimitiveNode extends Node
{
    public RawRegionAttribute $primitive { get => $this->attributes[0]; }

    public static function create(string $primitive): self
    {
        return new self(
            name: 'primitive',
            attributes: [
            new RawRegionAttribute(
                opener: new StructureAttribute(true, 'doubleQuote', '"'),
                closer: new StructureAttribute(true, 'doubleQuote', '"'),
                content: $primitive,
                name: 'string',
                anchorName: 'primitive',
            ),
            ],
            parent: null,
        );
    }

    public function setPrimitive(PrimitiveType $type, ?string $content = null): self
    {
        if ($type === PrimitiveType::String) {
            if ($content === null) {
                throw new InvalidArgumentException('Content required for string.');
            }
            $this->primitive = new RawRegionAttribute(
                new StructureAttribute(true, 'doubleQuote', '"'),
                new StructureAttribute(true, 'doubleQuote', '"'),
                $content, 'string', null,
            );
            return $this;
        }

        if ($type === PrimitiveType::Number) {
            if ($content === null) {
                throw new InvalidArgumentException('Content required for number.');
            }
            $this->primitive = new RawRegionAttribute(null, null, $content, 'number', null);
            return $this;
        }

        if ($type === PrimitiveType::True) {
            $this->primitive = new RawContentAttribute('true', 'true', null);
            return $this;
        }

        if ($type === PrimitiveType::False) {
            $this->primitive = new RawContentAttribute('false', 'false', null);
            return $this;
        }

        if ($type === PrimitiveType::Null) {
            $this->primitive = new RawContentAttribute('null', 'null', null);
            return $this;
        }

        throw new InvalidArgumentException('Unsupported type: ' . $type->value);
    }

    public function getPrimitiveType(): PrimitiveType|null
    {
        return PrimitiveType::from($this->primitive->name);
    }

    public function getPrimitiveContent(): string|null
    {
        return $this->primitive->content;
    }
}
