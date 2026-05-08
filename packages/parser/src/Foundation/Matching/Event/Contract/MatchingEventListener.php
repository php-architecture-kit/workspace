<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Matching\Event\Contract;

use PhpArchitecture\Parser\Foundation\Matching\Contract\MatchingContext;

interface MatchingEventListener
{
    public function handle(MatchingEvent $event, MatchingContext $context): void;
    public function priority(): int;
}
