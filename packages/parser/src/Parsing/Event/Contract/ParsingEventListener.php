<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Parsing\Event\Contract;

use PhpArchitecture\Parser\Parsing\MatcherContext;

interface ParsingEventListener
{
    public function handle(ParsingEvent $event, MatcherContext $context): void;
    public function priority(): int;
}
