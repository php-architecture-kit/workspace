<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Context;

use PhpArchitecture\Parser\Processing\Model\Matching\MatchedRegion;
use PhpArchitecture\Parser\Processing\Model\Matching\SequenceLibrary;

interface MatchingContext
{
    public function getOutput(): MatchedRegion;
    public function getSequenceLibrary(): SequenceLibrary;
}
