<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Matching;

use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;
use PhpArchitecture\Parser\Shared\Meta\MetaTrait;

class MatchedRegion
{
    use MetaTrait;

    /**
     * @param array<Token|TokenRegion|MatchedSequence> $items
     */
    public function __construct(
        public private(set) string $name,
        public private(set) array $items,
    ) {}
}
