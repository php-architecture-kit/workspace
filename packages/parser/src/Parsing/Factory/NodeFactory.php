<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Parsing\Factory;

use PhpArchitecture\Parser\Parsing\NodeFactoryInterface;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeInterface;

use LogicException;
use PhpArchitecture\Parser\Parsing\Model\Node;
use PhpArchitecture\Parser\Parsing\Model\RawContent;
use PhpArchitecture\Parser\Parsing\Model\RegionRawContent;
use PhpArchitecture\Parser\Parsing\Model\Structure;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedRegion;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequence;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequenceNode;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeType;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;

class NodeFactory implements NodeFactoryInterface
{
    // /**
    //  * @param array<string,class-string<NodeInterface>> $tokenToNodeClassMap
    //  * @param array<string,class-string<NodeInterface>> $regionToNodeClassMap
    //  * @param array<string,class-string<NodeInterface>> $MatchedRegionToNodeClassMap
    //  * @param array<string,class-string<NodeInterface>> $sequenceToNodeClassMap
    //  * @param array<string,class-string<NodeInterface>> $sequenceNodeToNodeClassMap
    //  */
    // public function __construct(
    //     private readonly array $tokenToNodeClassMap,
    //     private readonly array $regionToNodeClassMap,
    //     private readonly array $matchedRegionToNodeClassMap,
    //     private readonly array $sequenceToNodeClassMap,
    //     private readonly array $sequenceNodeToNodeClassMap,
    // ) {}

    public function fromToken(Token $token): NodeInterface
    {
        if ($token->hasTag(NodeType::Node->value)) {
            return (new Node($token->name, ['content' => $token->raw]))
                ->addTag(...$token->tags)
                ->initMeta($token->getMetaAll());
        }

        if ($token->hasTag(NodeType::Structure->value)) {
            return (new Structure($token->name, true))
                ->addTag(...$token->tags)
                ->initMeta($token->getMetaAll());
        }

        return (new RawContent($token->name, $token->raw))
            ->addTag(...$token->tags)
            ->initMeta($token->getMetaAll());
    }

    public function fromTokenRegion(TokenRegion $region): NodeInterface
    {
        if ($region->hasTag(NodeType::Node->value)) {
            return (new Node($region->name, []))
                ->addTag(...$region->tags)
                ->initMeta($region->getMetaAll());
        }

        if ($region->hasTag(NodeType::Structure->value)) {
            return (new Structure($region->name, true))
                ->addTag(...$region->tags)
                ->initMeta($region->getMetaAll());
        }

        $openerPresent = false;
        if (
            $region->getMeta(RegionRawContent::REGION_INCLUDES_STRUCTURE_OPENER_KEY, false)
            && $region->stream->first()->hasTag(NodeType::Structure->value)
        ) {
            $openerPresent = true;
            $region->stream->remove(0);
        }

        $closerPresent = false;
        if (
            $region->getMeta(RegionRawContent::REGION_INCLUDES_STRUCTURE_CLOSER_KEY, false)
            && $region->stream->last()->hasTag(NodeType::Structure->value)
        ) {
            $closerPresent = true;
            $region->stream->remove($region->stream->lastOffset());
        }

        return (new RegionRawContent($region->name, $region->__toString(), $openerPresent, $closerPresent))
            ->addTag(...$region->tags)
            ->initMeta($region->getMetaAll());
    }

    public function fromMatchedRegion(MatchedRegion $region): NodeInterface
    {
        throw new LogicException("Not implemented");
    }

    public function fromMatchedSequence(MatchedSequence $matchedSequence): NodeInterface
    {
        throw new LogicException("Not implemented");
    }

    public function fromMatchedSequenceNode(MatchedSequenceNode $matchedSequenceNode): NodeInterface
    {
        throw new LogicException("Not implemented");
    }
}
