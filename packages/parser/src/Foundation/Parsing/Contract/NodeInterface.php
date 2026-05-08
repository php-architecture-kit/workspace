<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Contract;

use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use Stringable;

interface NodeInterface extends MetaInterface, Stringable
{
    public function addAttribute(NodeAttributeInterface $attribute, AttributePlacement $placement = AttributePlacement::After, int $offset = -1): self;

    public function withParent(NodeInterface $parent): self;
}
