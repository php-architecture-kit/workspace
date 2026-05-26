<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Factory;

use InvalidArgumentException;
use PhpArchitecture\Parser\Foundation\Matching\Matcher;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupedAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
use PhpArchitecture\Parser\Foundation\Parsing\NodeFactoryInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Resolver\NodeTypeResolver;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\ParsingContext;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedRegion;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequenceNode;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;

class NodeFactory implements NodeFactoryInterface
{
    public function __construct(
        private readonly ParsingContext $context,
    ) {}

    public function fromToken(Token $token, NodeInterface $parent): NodeInterface
    {
        $nodeType = NodeTypeResolver::resolveNodeType($token);

        return new Node(
            $token->name,
            [
                match ($nodeType) {
                    NodeType::Structure => new StructureAttribute($token->raw !== '', StructureAttribute::DEFAULT_NAME, $token->raw === '' ? null : $token->raw),
                    NodeType::Raw, NodeType::Node => new RawContentAttribute($token->raw),
                }
            ],
            $parent,
            $token->meta,
            $token->tags,
        );
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
        $node = new Node($region->name, [], $parent, $region->meta, $region->tags);

        $this->fillRegionBasedNodeWithAttributes($node, NodeTypeResolver::resolveNodeType($region), $region->stream->tokens);

        return $node;
    }

    private function createNodeFromMatchedRegion(MatchedRegion $region, ?NodeInterface $parent = null): NodeInterface
    {
        $node = new Node($region->name, [], $parent, $region->meta, $region->tags);

        $this->fillRegionBasedNodeWithAttributes($node, NodeTypeResolver::resolveNodeType($region), $region->items);

        return $node;
    }

    private function createNodeFromMatchedSequence(MatchedSequence $sequence, ?NodeInterface $parent = null): NodeInterface
    {
        $node = new Node($sequence->name, [], $parent, $sequence->meta, $sequence->tags);

        $this->fillSequenceBasedNodeWithAttributes($node, $sequence->items);

        return $node;
    }

    /** @param array<Token|TokenRegion|MatchedSequence> $items */
    private function fillRegionBasedNodeWithAttributes(NodeInterface $regionBasedNode, NodeType $regionNodeType, array $items): void
    {
        if ($regionNodeType === NodeType::Structure) {
            $content = implode('', array_map(static fn($item) => $item->__toString(), $items));
            $regionBasedNode->addAttribute(new StructureAttribute(
                !empty($sequenceNode->items),
                StructureAttribute::DEFAULT_NAME,
                $content === '' ? null : $content,
            ));

            return;
        }

        if ($regionNodeType === NodeType::Raw) {
            $content = implode('', array_map(static fn($item) => $item->__toString(), $items));
            $regionBasedNode->addAttribute(new RawContentAttribute(
                $content,
            ));

            return;
        }

        foreach ($items as $item) {
            $nodeType = NodeTypeResolver::resolveNodeType($item);

            match ($item::class) {
                Token::class => $this->context->nodeAttrFactory()->fromToken($item, $nodeType, $regionBasedNode),
                TokenRegion::class => $this->context->nodeAttrFactory()->fromTokenRegion($item, $nodeType, $regionBasedNode),
                MatchedSequence::class => $this->context->nodeAttrFactory()->fromMatchedSequence($item, $nodeType, $regionBasedNode),
                default => throw new InvalidArgumentException('Unknown item type'),
            };
        }
    }

    /** @param array<MatchedSequenceNode> $items */
    private function fillSequenceBasedNodeWithAttributes(NodeInterface $sequenceBasedNode, array $items): void
    {
        $groupedAttr = null;

        foreach ($items as $item) {
            if (!$item->hasTag(GroupedAttribute::TAG)) {
                $groupedAttr = null;
                $nodeType = NodeTypeResolver::resolveNodeType($item);
                $this->context->nodeAttrFactory()->fromMatchedSequenceNode($item, $nodeType, $sequenceBasedNode);
                continue;
            }

            if ($groupedAttr === null) {
                $groupedAttr = new GroupedAttribute($item->name, $sequenceBasedNode, [], $item->meta, $item->tags);
                $sequenceBasedNode->addAttribute($groupedAttr);
            }

            $nodeType = NodeTypeResolver::resolveNodeType($item);
            $this->context->nodeAttrFactory()->fromMatchedSequenceNode($item, $nodeType, $groupedAttr);
        }
    }
}
