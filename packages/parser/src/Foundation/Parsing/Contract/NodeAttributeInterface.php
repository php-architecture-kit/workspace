<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Contract;

use Stringable;

interface NodeAttributeInterface extends Stringable
{
    public const DEFAULT_VALUE_KEY = 'defaultValue';

    public function getName(): string;
}
