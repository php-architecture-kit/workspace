<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Parsing\Context;

use PhpArchitecture\Parser\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Matching\Context\DefaultMatcherContext;
use PhpArchitecture\Parser\Parsing\Factory\NodeFactory;
use PhpArchitecture\Parser\Parsing\Model\Node;
use PhpArchitecture\Parser\Parsing\NodeFactoryInterface;
use PhpArchitecture\Parser\Processing\Context\MatchingContext;
use PhpArchitecture\Parser\Processing\Context\ParsingContext;
use PhpArchitecture\Parser\Processing\Context\TokenizationContext;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeInterface;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;
use PhpArchitecture\Parser\Tokenization\Context\TokenizationContextCompiler;

class DefaultParsingContext implements ParsingContext
{
    private NodeInterface $rootNode;
    private TokenizationContext $tokenizationContext;

    public function __construct(
        private readonly CompiledGrammar $grammar,
        private readonly NodeFactoryInterface $nodeFactory = new NodeFactory(),
        private readonly bool $rowColTracking = true,
    ) {
        $this->tokenizationContext = (new TokenizationContextCompiler())
            ->compile($grammar, $rowColTracking);
    }

    public function addNode(?NodeInterface $parent, NodeInterface $node): void
    {
        if ($parent === null) {
            $this->rootNode = $node;
            return;
        }

        assert($parent instanceof Node);
        $parent->attributes[] = $node;
    }

    public function getOutput(): NodeInterface
    {
        return $this->rootNode;
    }

    public function grammar(): CompiledGrammar
    {
        return $this->grammar;
    }

    public function nodeFactory(): NodeFactoryInterface
    {
        return $this->nodeFactory;
    }

    public function matchingContextForRegion(TokenRegion $region): ?MatchingContext
    {
        $compiledRegion = $this->grammar->regions[$region->name] ?? null;
        
        if ($compiledRegion === null) {
            return null;
        }
        
        return new DefaultMatcherContext($region->name, $compiledRegion->sequenceLibrary);
    }

    public function tokenizationContext(): TokenizationContext
    {
        return $this->tokenizationContext;
    }
}
