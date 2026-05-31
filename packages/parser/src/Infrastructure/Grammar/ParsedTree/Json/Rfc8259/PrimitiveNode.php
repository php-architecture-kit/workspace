<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259;

use InvalidArgumentException;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
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
        $node = new self(
            name: 'primitive',
            attributes: [
                new ChoiceAttribute('primitive', ["false", "null", "true", "number", "string"], null),
            ],
            parent: null,
        );

        return $node;
    }

    // tutaj choice attribute nie zawiera node'ów, a atrybuty raw. Powinniśmy wziąć na to poprawkę
    // przy generowaniu metod. Dodatkowo ChoiceAttribute ma tutaj cardinality = 1, co wpływa na brak
    // metody remove

    // natomiast, skoro mamy tutaj choice of raws, to oznacza, że powinniśmy wygenerować enum {name}Type
    // i owe types zależą od tego jak zostały zadeklarowane, Rule tokenu powinno pamiętać w meta swoją
    // metodę kreacyjną.

    // dla uproszczenia generatora, każda z opcji ma swój własny if statement.

    // skoro istnieją opcje token/keyword to oznacza, że content jest niepotrzebny, ale skoro istnieją opcje
    // expression / raw region, to oznacza, że muszą móc przekazać content. Wynikowo argument content jest opcjonalny.

    public function setPrimitive(PrimitiveType $type, ?string $content = null): self
    {
        if ($type === PrimitiveType::False) {
            $this->primitive->setSelected(new RawContentAttribute("false", "false", null));

            return $this;
        }

        if ($type === PrimitiveType::Null) {
            $this->primitive->setSelected(new RawContentAttribute("null", "null", null));

            return $this;
        }

        if ($type === PrimitiveType::True) {
            $this->primitive->setSelected(new RawContentAttribute("true", "true", null));

            return $this;
        }

        if ($type === PrimitiveType::Number) {
            if ($content === null) {
                throw new InvalidArgumentException("Content must be provided for number type.");
            }
            $this->primitive->setSelected(
                new RawRegionAttribute(null, null, $content, 'number', null)
            );

            return $this;
        }

        if ($type === PrimitiveType::String) {
            if ($content === null) {
                throw new InvalidArgumentException("Content must be provided for string type.");
            }
            $this->primitive->setSelected(
                new RawRegionAttribute(
                    new StructureAttribute(true, 'doubleQuote', '"'),
                    new StructureAttribute(true, 'doubleQuote', '"'),
                    $content,
                    'string',
                    null
                )
            );

            return $this;
        }

        throw new InvalidArgumentException("Unsupported primitive type: " . $type->value);
    }

    public function getPrimitiveType(): ?PrimitiveType
    {
        /** @var ?RawContentAttribute|RawRegionAttribute $attribute */
        $attribute = $this->primitive->selected;
        if ($attribute === null) {
            return null;
        }

        return PrimitiveType::from($attribute->name);
    }

    public function getPrimitiveContent(): ?string
    {
        /** @var ?RawContentAttribute|RawRegionAttribute $attribute */
        $attribute = $this->primitive->selected;

        return $attribute?->content;
    }
}
