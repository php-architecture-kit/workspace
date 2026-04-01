<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Event\Matching\Contract;

use PhpArchitecture\Parser\Processing\Context\MatchingContext;

interface MatchingEventListener
{
    public function handle(MatchingEvent $event, MatchingContext $context): void;
    public function priority(): int;
}
