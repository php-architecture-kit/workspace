<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition;

use Closure;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;

class FormatDefinition
{
    /**
     * @var array<Closure(NodeInterface $node, string $style):void>
     */
    public private(set) array $formatters = [];

    /**
     * @param array<callable(NodeInterface $node, string $style):void> $formatters
     */
    public function __construct(
        array $formatters = []
    ) {
        $this->formatters = array_map(
            static fn($formatter) => $formatter instanceof Closure ? $formatter : Closure::fromCallable($formatter),
            $formatters
        );
    }

    /**
     * @param callable(NodeInterface $node, string $style):void $formatter
     */
    public function addFormatter(callable $formatter): self
    {
        $this->formatters[] = $formatter instanceof Closure ? $formatter : Closure::fromCallable($formatter);

        return $this;
    }
}
