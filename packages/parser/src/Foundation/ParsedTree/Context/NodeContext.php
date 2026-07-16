<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\ParsedTree\Context;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;

class NodeContext
{
    public function __construct(
        public readonly NodeInterface $node,
        public readonly FormattingContext $formatting = new FormattingContext(),
    ) {}
}
