<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener;

interface RuleMatchedEventListener
{
    public function rule(): ?string;
}
