<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Factory;

use PhpArchitecture\Parser\Foundation\Matching\Matcher;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
use PhpArchitecture\Parser\Foundation\Parsing\NodeFactoryInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\ParsingContext;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedRegion;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequence;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;

class NodeFactory implements NodeFactoryInterface
{
    public function __construct(
        private readonly ParsingContext $context,
    ) {}

    public function fromToken(Token $token, NodeInterface $parent): NodeInterface
    {
        $nodeClass = $this->context->grammar()->nodeClassMap[$token->name] ?? Node::class;
        $node = new $nodeClass($token->name, NodeOrigin::Token, [], $parent, $token->meta, $token->tags);

        $this->context->nodeAttrFactory()->fillTokenBasedNodeWithAttributes($node, $token);

        return $node;
    }

    public function fromTokenRegion(TokenRegion $region, ?NodeInterface $parent): NodeInterface
    {
        $regionMatchingContext = $this->context->matchingContextForRegion($region);
        if (null === $regionMatchingContext) {
            return $this->createNodeFromTokenRegion($region, $parent);
        }

        $matcher = new Matcher($regionMatchingContext);
        $matchedSeqOrRegion = $matcher->process($region);

        if ($matchedSeqOrRegion instanceof MatchedRegion) {
            return $this->createNodeFromMatchedRegion($matchedSeqOrRegion, $parent);
        }

        return $this->createNodeFromMatchedSequence($matchedSeqOrRegion, $parent);
    }

    public function fromMatchedRegion(MatchedRegion $region, NodeInterface $parent): NodeInterface
    {
        return $this->createNodeFromMatchedRegion($region, $parent);
    }

    public function fromMatchedSequence(MatchedSequence $matchedSequence, NodeInterface $parent): NodeInterface
    {
        return $this->createNodeFromMatchedSequence($matchedSequence, $parent);
    }

    private function createNodeFromTokenRegion(TokenRegion $region, ?NodeInterface $parent = null): NodeInterface
    {
        $nodeClass = $this->context->grammar()->nodeClassMap[$region->name] ?? Node::class;
        $node = new $nodeClass($region->name, NodeOrigin::Region, [], $parent, $region->meta, $region->tags);

        $this->context->nodeAttrFactory()->fillRegionBasedNodeWithAttributes($node, $region);

        return $node;
    }

    private function createNodeFromMatchedRegion(MatchedRegion $region, ?NodeInterface $parent = null): NodeInterface
    {
        $nodeClass = $this->context->grammar()->nodeClassMap[$region->name] ?? Node::class;
        $node = new $nodeClass($region->name, NodeOrigin::Region, [], $parent, $region->meta, $region->tags);

        $this->context->nodeAttrFactory()->fillRegionBasedNodeWithAttributes($node, $region);

        return $node;
    }

    private function createNodeFromMatchedSequence(MatchedSequence $sequence, ?NodeInterface $parent = null): NodeInterface
    {
        $nodeClass = $this->context->grammar()->nodeClassMap[$sequence->name] ?? Node::class;
        $node = new $nodeClass($sequence->name, NodeOrigin::Sequence, [], $parent, $sequence->meta, $sequence->tags);

        $this->context->nodeAttrFactory()->fillSequenceBasedNodeWithAttributes($node, $sequence);

        return $node;
    }
}
