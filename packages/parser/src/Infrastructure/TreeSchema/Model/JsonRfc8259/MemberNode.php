<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\JsonRfc8259;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

class MemberNode extends Node
{
    public RawRegionAttribute $string { get => $this->__get('string'); }

    public GroupAttribute $trivia0 { get => $this->__get('trivia0'); }

    public StructureAttribute $colon { get => $this->__get('colon'); }

    /** @var GroupAttribute<InlineWsNode> */
    public GroupAttribute $trivia1 { get => $this->__get('trivia1'); }

    /** @var ChoiceAttribute<PrimitiveNode|ArrayNode|ObjectNode> */
    public ChoiceAttribute $value { get => $this->__get('value'); }
}
