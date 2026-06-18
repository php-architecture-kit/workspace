<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Factory;

use PhpArchitecture\Parser\Foundation\Matching\Matcher;
use PhpArchitecture\Parser\Foundation\Parsing\Model\GroupNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\LeafNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;
use PhpArchitecture\Parser\Foundation\Parsing\NodeFactoryInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\ParsingContext;
use PhpArchitecture\Parser\Foundation\Parsing\Resolver\NodeTypeResolver;
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
        $nodeClass = $this->context->grammar()->nodeClassMap[$token->name] ?? LeafNode::class;
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
        $default = $this->shapeClassFor(NodeOrigin::Region, NodeTypeResolver::resolveNodeType($region));
        $nodeClass = $this->context->grammar()->nodeClassMap[$region->name] ?? $default;
        $node = new $nodeClass($region->name, NodeOrigin::Region, [], $parent, $region->meta, $region->tags);

        $this->context->nodeAttrFactory()->fillRegionBasedNodeWithAttributes($node, $region);

        return $node;
    }

    private function createNodeFromMatchedRegion(MatchedRegion $region, ?NodeInterface $parent = null): NodeInterface
    {
        $default = $this->shapeClassFor(NodeOrigin::Region, NodeTypeResolver::resolveNodeType($region));
        $nodeClass = $this->context->grammar()->nodeClassMap[$region->name] ?? $default;
        $node = new $nodeClass($region->name, NodeOrigin::Region, [], $parent, $region->meta, $region->tags);

        $this->context->nodeAttrFactory()->fillRegionBasedNodeWithAttributes($node, $region);

        return $node;
    }

    private function createNodeFromMatchedSequence(MatchedSequence $sequence, ?NodeInterface $parent = null): NodeInterface
    {
        $default = $this->shapeClassFor(NodeOrigin::Sequence, NodeTypeResolver::resolveNodeType($sequence));
        $nodeClass = $this->context->grammar()->nodeClassMap[$sequence->name] ?? $default;
        $node = new $nodeClass($sequence->name, NodeOrigin::Sequence, [], $parent, $sequence->meta, $sequence->tags);

        $this->context->nodeAttrFactory()->fillSequenceBasedNodeWithAttributes($node, $sequence);

        return $node;
    }

    /**
     * Default node class by shape, derived from (NodeOrigin × NodeType). A facade
     * registered in nodeClassMap overrides this; facades already extend the matching
     * shape. See docs/node-type-origin-cardinality.md (Table 2).
     *
     * @return class-string<NodeInterface>
     */
    private function shapeClassFor(NodeOrigin $origin, NodeType $nodeType): string
    {
        if ($origin === NodeOrigin::Token) {
            return LeafNode::class;
        }

        if ($nodeType === NodeType::Node) {
            return $origin === NodeOrigin::Sequence ? SequenceNode::class : GroupNode::class;
        }

        // Raw / Structure on a Region or Sequence collapse to a single attribute.
        return LeafNode::class;
    }
}
