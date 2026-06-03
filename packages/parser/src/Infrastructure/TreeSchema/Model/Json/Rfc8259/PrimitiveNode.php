<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json\Rfc8259;

use InvalidArgumentException;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

class PrimitiveNode extends Node
{
    /** @var ChoiceAttribute<RawRegionAttribute|RawContentAttribute> */
    public ChoiceAttribute $primitive { get => $this->attributes[0]; }

    public static function create(): self
    {
        return new self(
            name: 'primitive',
            attributes: [
            new ChoiceAttribute('primitive', ['string', 'number', 'true', 'false', 'null'], null),
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
            $this->primitive->setSelected(new RawRegionAttribute(
                new StructureAttribute(true, 'doubleQuote', '"'),
                new StructureAttribute(true, 'doubleQuote', '"'),
                $content,
                'string',
                null,
            ));
            return $this;
        }

        if ($type === PrimitiveType::Number) {
            if ($content === null) {
                throw new InvalidArgumentException('Content required for number.');
            }
            $this->primitive->setSelected(new RawRegionAttribute(null, null, $content, 'number', null));
            return $this;
        }

        if ($type === PrimitiveType::True) {
            $this->primitive->setSelected(new RawContentAttribute('true', 'true', null));
            return $this;
        }

        if ($type === PrimitiveType::False) {
            $this->primitive->setSelected(new RawContentAttribute('false', 'false', null));
            return $this;
        }

        if ($type === PrimitiveType::Null) {
            $this->primitive->setSelected(new RawContentAttribute('null', 'null', null));
            return $this;
        }

        throw new InvalidArgumentException('Unsupported type: ' . $type->value);
    }

    public function getPrimitiveType(): PrimitiveType|null
    {
        $attribute = $this->primitive->selected;
        return $attribute !== null ? PrimitiveType::from($attribute->name) : null;
    }

    public function getPrimitiveContent(): string|null
    {
        return $this->primitive->selected?->content;
    }
}
