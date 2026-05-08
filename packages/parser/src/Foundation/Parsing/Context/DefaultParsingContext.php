<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Context;

use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Foundation\Matching\Context\DefaultMatchingContext;
use PhpArchitecture\Parser\Foundation\Parsing\Factory\NodeAttrFactory;
use PhpArchitecture\Parser\Foundation\Parsing\Factory\NodeFactory;
use PhpArchitecture\Parser\Foundation\Parsing\NodeAttrFactoryInterface;
use PhpArchitecture\Parser\Foundation\Parsing\NodeFactoryInterface;
use PhpArchitecture\Parser\Foundation\Matching\Contract\MatchingContext;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\ParsingContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Foundation\Tokenization\Context\TokenizationContextCompiler;

class DefaultParsingContext implements ParsingContext
{
    private NodeInterface $rootNode;
    private TokenizationContext $tokenizationContext;

    public function __construct(
        private readonly CompiledGrammar $grammar,
        private readonly bool $rowColTracking = true,
        private ?NodeFactoryInterface $nodeFactory = null,
        private ?NodeAttrFactoryInterface $nodeAttrFactory = null,
    ) {
        $this->tokenizationContext = (new TokenizationContextCompiler())
            ->compile($grammar, $rowColTracking);
        $this->nodeFactory ??= new NodeFactory($this);
        $this->nodeAttrFactory ??= new NodeAttrFactory($this);
    }

    public function grammar(): CompiledGrammar
    {
        return $this->grammar;
    }

    public function nodeFactory(): NodeFactoryInterface
    {
        return $this->nodeFactory;
    }

    public function nodeAttrFactory(): NodeAttrFactoryInterface
    {
        return $this->nodeAttrFactory;
    }

    public function matchingContextForRegion(TokenRegion $region): ?MatchingContext
    {
        $compiledRegion = $this->grammar->regions[$region->name] ?? null;

        if ($compiledRegion === null) {
            return null;
        }

        if (!$compiledRegion->sequenceLibrary->rootSequence && empty($compiledRegion->sequenceLibrary->sequences)) {
            return null;
        }

        return new DefaultMatchingContext($region->name, $compiledRegion->sequenceLibrary);
    }

    public function tokenizationContext(): TokenizationContext
    {
        return $this->tokenizationContext;
    }
}
