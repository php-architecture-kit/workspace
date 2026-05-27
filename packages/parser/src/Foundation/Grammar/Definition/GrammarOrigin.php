<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition;

readonly class GrammarOrigin
{
    public function __construct(
        public string $format,
        public ?string $variant,
    ) {}
}
