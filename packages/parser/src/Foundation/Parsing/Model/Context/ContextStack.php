<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Context;

use ArrayObject;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Defaults;

/**
 * Each node has its own context stack which contains references to ascendant contexts
 */
class ContextStack
{
    public const STYLE = 'style';

    /**
     * @param NodeContext[] $stack
     * @param ArrayObject<string,mixed> $treeContext
     */
    public function __construct(
        public readonly array $stack = [],
        public ArrayObject $treeContext = new ArrayObject([self::STYLE => Defaults::DEFAULT_STYLE]),
    ) {}

    public function push(NodeContext $context): static
    {
        return new static([...$this->stack, $context], $this->treeContext);
    }
}
