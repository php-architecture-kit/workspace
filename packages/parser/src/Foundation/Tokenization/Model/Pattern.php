<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Tokenization\Model;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;

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
        public readonly ?GrammarOrigin $origin = null,
    ) {}
}
