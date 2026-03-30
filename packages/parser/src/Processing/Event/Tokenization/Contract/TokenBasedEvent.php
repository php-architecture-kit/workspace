<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Event\Tokenization\Contract;

interface TokenBasedEvent
{
    public function name(): string;
}
