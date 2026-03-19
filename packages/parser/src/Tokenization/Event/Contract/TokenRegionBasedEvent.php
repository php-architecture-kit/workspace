<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization\Event\Contract;

interface TokenRegionBasedEvent
{
    public function name(): string;
}
