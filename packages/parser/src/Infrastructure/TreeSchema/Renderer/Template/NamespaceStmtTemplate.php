<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Renderer\Template;

use Stringable;

final readonly class NamespaceStmtTemplate implements Stringable
{
    public function __construct(
        public string $namespace
    ) {}

    public function isFqcnDirectChild(string $fqcn): bool
    {
        $lastSlash = strrpos($fqcn, '\\');
        return $lastSlash !== false
            ? substr($fqcn, 0, $lastSlash) === $this->namespace
            : $this->namespace === '';
    }

    public function __toString(): string
    {
        return "namespace " . $this->namespace . ";";
    }
}
