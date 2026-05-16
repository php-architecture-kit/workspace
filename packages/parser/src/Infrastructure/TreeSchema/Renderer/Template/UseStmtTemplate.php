<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Renderer\Template;

use Stringable;

final readonly class UseStmtTemplate implements Stringable
{
    public function __construct(
        public string $fqcn
    ) {}

    public function __toString(): string
    {
        return "use " . $this->fqcn . ";";
    }
}
