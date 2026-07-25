<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5;

use InvalidArgumentException;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;

class PrimitiveNode extends SequenceNode
{
    public RawAttributeInterface $primitive { get => $this->attributes[0]; }

    public static function create(PrimitiveType $primitiveType, ?string $primitive = null): self
    {
        return new self(
            name: 'primitive',
            origin: NodeOrigin::Sequence,
            attributes: [
                self::makePrimitive($primitiveType, $primitive),
            ],
            parent: null,
        );
    }

    private static function makePrimitive(PrimitiveType $primitiveType, ?string $primitive = null): RawAttributeInterface
    {
        if ($primitiveType === PrimitiveType::False) {
            return new RawContentAttribute($primitive ?? 'false', 'false', null);
        }

        if ($primitiveType === PrimitiveType::Null) {
            return new RawContentAttribute($primitive ?? 'null', 'null', null);
        }

        if ($primitiveType === PrimitiveType::True) {
            return new RawContentAttribute($primitive ?? 'true', 'true', null);
        }

        if ($primitiveType === PrimitiveType::Infinity) {
            if ($primitive === null) {
                throw new InvalidArgumentException('Content is required for type: ' . $primitiveType->value);
            }
            return new RawContentAttribute($primitive, 'infinity', null);
        }

        if ($primitiveType === PrimitiveType::Nan) {
            return new RawContentAttribute($primitive ?? 'NaN', 'nan', null);
        }

        if ($primitiveType === PrimitiveType::Number) {
            if ($primitive === null) {
                throw new InvalidArgumentException('Content is required for type: ' . $primitiveType->value);
            }
            return new RawRegionAttribute(opener: null, content: $primitive, closer: null, name: 'number', anchorName: null);
        }

        if ($primitiveType === PrimitiveType::DoubleQuotedString) {
            if ($primitive === null) {
                throw new InvalidArgumentException('Content is required for type: ' . $primitiveType->value);
            }
            return new RawRegionAttribute(opener: '"', content: $primitive, closer: '"', name: 'doubleQuotedString', anchorName: null);
        }

        if ($primitiveType === PrimitiveType::SingleQuotedString) {
            if ($primitive === null) {
                throw new InvalidArgumentException('Content is required for type: ' . $primitiveType->value);
            }
            return new RawRegionAttribute(opener: '\'', content: $primitive, closer: '\'', name: 'singleQuotedString', anchorName: null);
        }

        throw new InvalidArgumentException('Unsupported type: ' . $primitiveType->value);
    }

    public function setPrimitive(PrimitiveType $primitiveType, ?string $primitive = null): self
    {
        $this->attributes[0] = self::makePrimitive($primitiveType, $primitive);
        return $this;
    }

    public function getPrimitiveType(): PrimitiveType|null
    {
        return PrimitiveType::tryFrom($this->primitive->name)
            ?? PrimitiveType::tryFrom((string) ($this->primitive->content ?? ''));
    }

    public function getPrimitiveContent(): string|null
    {
        return $this->primitive->content;
    }
}
