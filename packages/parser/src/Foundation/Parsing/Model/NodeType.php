<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model;

enum NodeType: string
{
    // technical
    case Tag = 'NodeType.Tag'; # spreads tagged alternatives directly into the sequence node
    case Skip = 'NodeType.Skip'; # skips eof/bof tokens but can be used for any rules that should be ignored

    // semantic
    case Node = 'NodeType.Node';
    case Raw = 'NodeType.Raw';
    case Structure = 'NodeType.Structure';
}
