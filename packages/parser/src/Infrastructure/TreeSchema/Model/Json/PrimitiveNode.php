<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json;

use LogicException;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

// enum dla wariantów z Rule::choice
enum PrimitiveType: string
{
    case String = "string";
    case Number = "number";
    case True = "true";
    case False = "false";
    case Null = "null";
}

// stworzony z
//   └─ ChoiceAttribute: item (choices: array|object|primitive) [tags: GroupedAttribute]
//     └─ NodeAttribute: primitive [meta: {"nodeType":"Node"}] [tags: value]
//       └─ Node: primitive [meta: {"nodeType":"Node"}] [tags: value]
//         └─ RawRegionAttribute: string = "\"@phpunit --do-not-cache-result --log-junit var\/co..." [meta: {"startedBy":"\""}]
// primitive to Rule::choice, wszystkie jego children to Raw
class PrimitiveNode extends Node
{
    public static function createTrue(string $raw, PrimitiveType $type, ?NodeInterface $parent = null): self
    {
        if (in_array($type, $type->keywords())) {
            return new self(
                name: $type->value,
                attributes: [
                    new RawContentAttribute($raw),
                ],
                parent: $parent,
            );
        }

        throw new LogicException("Unsupported primitive type: " . $type->value);
    }
}
