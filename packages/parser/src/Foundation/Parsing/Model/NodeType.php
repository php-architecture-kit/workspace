<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model;

enum NodeType: string
{
    case Node = 'NodeType.Node';
    case Raw = 'NodeType.Raw';
    case Structure = 'NodeType.Structure';
}
