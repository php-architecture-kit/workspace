<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitmodules;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;

class DottedSectionHeaderNode extends Node
{


    public static function create(): self
    {
        return new self(
            name: 'dottedSectionHeader',
            origin: NodeOrigin::Sequence,
            attributes: [

            ],
            parent: null,
        );
    }
}
