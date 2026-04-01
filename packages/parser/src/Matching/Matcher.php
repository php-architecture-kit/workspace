<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Matching;

use PhpArchitecture\Parser\Processing\Context\MatchingContext;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedRegion;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequence;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;

class Matcher
{
    public function __construct(
        private readonly MatchingContext $context
    ) {}

    public function process(TokenRegion $region): MatchedRegion|MatchedSequence
    {
        
    }
}
