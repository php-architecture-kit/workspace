<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Context;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;

class NodeContext
{
    /** @var array<string,mixed> */
    public array $nodeContext = [];

    public function __construct(
        public readonly NodeInterface $node,
    ) {}
}
