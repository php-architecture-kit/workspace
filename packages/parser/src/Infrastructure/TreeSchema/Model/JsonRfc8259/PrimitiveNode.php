<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\JsonRfc8259;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

class PrimitiveNode extends Node
{
    /** @var ChoiceAttribute<RawRegionAttribute> */
    public ChoiceAttribute $primitive { get => $this->__get('primitive'); }
}
