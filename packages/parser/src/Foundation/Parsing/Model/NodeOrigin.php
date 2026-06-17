<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model;

enum NodeOrigin
{
    case Token;
    case Region;
    case Sequence;
}
