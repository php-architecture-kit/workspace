<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract;

interface RemovableEventListener
{
    public function shouldBeRemoved(): bool;
}
