<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\JsonRfc8259;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupedAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

class ObjectNode extends Node
{
    public StructureAttribute $beginObject { get => $this->__get('beginObject'); }

    /** @var GroupAttribute<TrailingWsNode|LeadingWsNode> */
    public GroupAttribute $trivia0 { get => $this->__get('trivia0'); }

    /** @var GroupedAttribute<MemberNode|StructureAttribute|TrailingWsNode|LeadingWsNode> */
    public GroupedAttribute $members { get => $this->__get('members'); }

    /** @var GroupAttribute<TrailingWsNode|LeadingWsNode> */
    public GroupAttribute $trivia1 { get => $this->__get('trivia1'); }

    public StructureAttribute $endObject { get => $this->__get('endObject'); }
}
