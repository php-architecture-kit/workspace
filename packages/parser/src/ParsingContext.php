<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser;

use PhpArchitecture\Parser\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Processing\Context\MatchingContext;
use PhpArchitecture\Parser\Processing\Context\TokenizationContext;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeFactoryInterface;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeInterface;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;

interface ParsingContext
{
    public function addNode(?NodeInterface $parent, NodeInterface $node): void;
    public function getOutput(): NodeInterface;

    public function grammar(): CompiledGrammar;
    public function nodeFactory(): NodeFactoryInterface;

    public function matchingContextForRegion(TokenRegion $region): ?MatchingContext;
    public function tokenizationContext(): TokenizationContext;
}
