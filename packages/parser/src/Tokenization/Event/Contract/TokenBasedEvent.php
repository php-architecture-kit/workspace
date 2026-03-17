<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization\Event\Contract;

interface TokenBasedEvent
{
    public function name(): string;
}
