<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Tokenization\Model;

final readonly class Pattern
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $name,
        public readonly string $pattern,
        public readonly int $priority,
        public readonly array $tags,
    ) {}
}
