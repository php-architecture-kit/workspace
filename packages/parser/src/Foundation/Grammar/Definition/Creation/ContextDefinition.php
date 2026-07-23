<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\Creation;

use Closure;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;

class ContextDefinition
{
    /**
     * @var array<Closure(NodeInterface $rootNode):void>
     */
    public private(set) array $initializers = [];

    /**
     * @param array<callable(NodeInterface $rootNode):void> $initializers
     */
    public function __construct(
        array $initializers = [],
    ) {
        $this->initializers = array_map(
            static fn($initializer) => $initializer instanceof Closure ? $initializer : Closure::fromCallable($initializer),
            $initializers,
        );
    }

    /**
     * @param callable(NodeInterface $rootNode):void $initializer
     */
    public function addContextInitializer(callable $initializer, bool $first = false): self
    {
        if ($first) {
            array_unshift($this->initializers, $initializer instanceof Closure ? $initializer : Closure::fromCallable($initializer));
            return $this;
        }

        $this->initializers[] = $initializer instanceof Closure ? $initializer : Closure::fromCallable($initializer);

        return $this;
    }
}
