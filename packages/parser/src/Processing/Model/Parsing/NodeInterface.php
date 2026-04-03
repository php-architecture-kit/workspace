<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Parsing;

use Stringable;

interface NodeInterface extends Stringable
{
    public const DEFAULT_VALUE_KEY = 'defaultValue';
}
