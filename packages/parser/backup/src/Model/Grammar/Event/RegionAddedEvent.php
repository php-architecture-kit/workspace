<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Model\Grammar\Event;

use PhpArchitecture\Parser\Event\EventInterface;
use PhpArchitecture\Parser\Model\Grammar\Region;

final class RegionAddedEvent implements EventInterface, GrammarEventInterface
{
    public function __construct(
        public readonly Region $region,
    ) {}
}
