<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Matching\Event\Contract;

interface RemovableEventListener extends MatchingEventListener
{
    public function shouldBeRemoved(): bool;
}
