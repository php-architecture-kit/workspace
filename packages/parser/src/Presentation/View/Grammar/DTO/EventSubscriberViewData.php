<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\Grammar\DTO;

final class EventSubscriberViewData
{
    public function __construct(
        public readonly string $eventShortName,
        public readonly string $listenerShortName,
        public readonly int $priority,
        public readonly ?string $onlyForRule,
    ) {}
}
