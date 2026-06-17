<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Format;

use Closure;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;

class Formatter
{
    /**
     * @var array<Closure(NodeInterface $node, string $style):void>
     */
    public private(set) array $formatters = [];

    /**
     * @param callable(NodeInterface $node, string $style):void $formatter
     * @return self
     */
    public function addFormatter(callable $formatter): self
    {
        $this->formatters[] = Closure::fromCallable($formatter);

        return $this;
    }

    public function applyFormatters(NodeInterface $node, string $style): void
    {
        foreach ($this->formatters as $formatter) {
            $formatter($node, $style);
        }
    }
}
