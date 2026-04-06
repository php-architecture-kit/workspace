<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Context;

use PhpArchitecture\Parser\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Processing\Context\MatchingContext;
use PhpArchitecture\Parser\Processing\Context\TokenizationContext;
use PhpArchitecture\Parser\Parsing\NodeAttrFactoryInterface;
use PhpArchitecture\Parser\Parsing\NodeFactoryInterface;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;

interface ParsingContext
{
    public function grammar(): CompiledGrammar;
    public function nodeFactory(): NodeFactoryInterface;
    public function nodeAttrFactory(): NodeAttrFactoryInterface;

    public function matchingContextForRegion(TokenRegion $region): ?MatchingContext;
    public function tokenizationContext(): TokenizationContext;
}
