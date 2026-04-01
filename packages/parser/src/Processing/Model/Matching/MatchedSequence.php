<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Matching;

use PhpArchitecture\Parser\Shared\Meta\MetaTrait;

class MatchedSequence
{
    use MetaTrait;

    /**
     * @param MatchedSequenceNode[] $items
     */
    public function __construct(
        public private(set) string $name,
        public private(set) array $items,
    ) {}
}
