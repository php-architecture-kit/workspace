<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Matching\Event\Contract;

use PhpArchitecture\Parser\Matching\MatcherContext;

interface ParsingEventListener
{
    public function handle(ParsingEvent $event, MatcherContext $context): void;
    public function priority(): int;
}
