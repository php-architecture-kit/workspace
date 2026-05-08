<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\CompiledGrammar\DTO;

final class CompiledEventSubscriberViewData
{
    public function __construct(
        public readonly string $eventShortName,
        public readonly string $listenerShortName,
        public readonly string $details,
        public readonly int $priority,
        public readonly ?string $onlyForRule,
    ) {}
}
