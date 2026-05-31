<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Contract;

use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsInterface;
use Stringable;

interface NodeInterface extends MetaInterface, Stringable, TagsInterface
{
    public function addAttribute(NodeAttributeInterface $attribute, Placement $placement = Placement::After, int $offset = -1): self;

    /** @return NodeAttributeInterface[] */
    public function getAttributes(): array;

    public function getName(): string;

    public function getParent(): null|NodeInterface;

    public function setParent(NodeInterface $parent): self;
}
