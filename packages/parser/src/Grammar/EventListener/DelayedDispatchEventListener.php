<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\EventListener;

interface DelayedDispatchEventListener
{
    public function triggerEvent(): ?string;
}
