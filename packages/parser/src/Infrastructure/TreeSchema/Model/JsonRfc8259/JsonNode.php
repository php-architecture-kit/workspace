<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\JsonRfc8259;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

class JsonNode extends Node
{
    /** @var GroupAttribute<LeadingWsNode|TrailingWsNode|EmptyLineNode> */
    public GroupAttribute $trivia0 { get => $this->__get('trivia0'); }

    /** @var ChoiceAttribute<ObjectNode|ArrayNode|PrimitiveNode> */
    public ChoiceAttribute $value { get => $this->__get('value'); }

    /** @var GroupAttribute<TrailingWsNode> */
    public GroupAttribute $trivia1 { get => $this->__get('trivia1'); }
}
