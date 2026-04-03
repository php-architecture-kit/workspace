<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Event\Matching\Contract;

interface RemovableEventListener extends MatchingEventListener
{
    public function shouldBeRemoved(): bool;
}
