<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar;

class Grammar
{
    public function __construct(
        public readonly string $name,
        public readonly Region $global,
        public readonly ?string $variant = null,
    ) {}

    public function getRootRegion(): Region
    {
        return $this->global;
    }
}
