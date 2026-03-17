<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization\Model;

final class Pattern
{
    public function __construct(
        public readonly string $name,
        public readonly string $pattern,
        public readonly int $priority,
    ) {}
}
