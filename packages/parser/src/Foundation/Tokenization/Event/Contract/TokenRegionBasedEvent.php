<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract;

interface TokenRegionBasedEvent
{
    public function name(): string;
}
