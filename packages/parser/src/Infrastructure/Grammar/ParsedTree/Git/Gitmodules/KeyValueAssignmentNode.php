<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitmodules;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;

class KeyValueAssignmentNode extends Node
{


    public static function create(): self
    {
        return new self(
            name: 'keyValueAssignment',
            origin: NodeOrigin::Sequence,
            attributes: [

            ],
            parent: null,
        );
    }
}
