<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\JsonRfc8259;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupedAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

class ArrayNode extends Node
{
    public StructureAttribute $beginArray { get => $this->__get('beginArray'); }

    /** @var GroupAttribute<TrailingWsNode|LeadingWsNode> */
    public GroupAttribute $trivia0 { get => $this->__get('trivia0'); }

    /** @var GroupedAttribute<ChoiceAttribute<PrimitiveNode|ObjectNode|ArrayNode>|StructureAttribute|TrailingWsNode|LeadingWsNode> */
    public GroupedAttribute $items { get => $this->__get('items'); }

    /** @var GroupAttribute<TrailingWsNode|LeadingWsNode> */
    public GroupAttribute $trivia1 { get => $this->__get('trivia1'); }

    public StructureAttribute $endArray { get => $this->__get('endArray'); }
}
