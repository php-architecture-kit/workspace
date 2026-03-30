<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\EventListener;

interface RuleMatchedEventListener
{
    public function rule(): ?string;
}
