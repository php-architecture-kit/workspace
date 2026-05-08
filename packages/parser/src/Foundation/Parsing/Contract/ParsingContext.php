<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Contract;

use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Foundation\Matching\Contract\MatchingContext;
use PhpArchitecture\Parser\Foundation\Parsing\NodeAttrFactoryInterface;
use PhpArchitecture\Parser\Foundation\Parsing\NodeFactoryInterface;
use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;

interface ParsingContext
{
    public function grammar(): CompiledGrammar;
    public function nodeFactory(): NodeFactoryInterface;
    public function nodeAttrFactory(): NodeAttrFactoryInterface;

    public function matchingContextForRegion(TokenRegion $region): ?MatchingContext;
    public function tokenizationContext(): TokenizationContext;
}
