<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Parsing\Factory;

use PhpArchitecture\Parser\Parsing\Model\Node;
use PhpArchitecture\Parser\Parsing\Model\RawContent;
use PhpArchitecture\Parser\Parsing\Model\RegionRawContent;
use PhpArchitecture\Parser\Parsing\Model\Structure;
use PhpArchitecture\Parser\Parsing\NodeFactoryInterface;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedRegion;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequence;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequenceNode;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeInterface;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeType;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;

class NodeFactory implements NodeFactoryInterface
{
    public function fromToken(Token $token): NodeInterface
    {
        if ($token->hasTag(NodeType::Node->value)) {
            return new Node(
                $token->name,
                ['content' => $token->raw],
                [],
                $token->meta,
                $token->tags
            );
        }

        if ($token->hasTag(NodeType::Structure->value)) {
            return new Structure(
                $token->name,
                true,
                $token->raw,
                $token->meta,
                $token->tags
            );
        }

        return new RawContent(
            $token->name,
            $token->raw,
            $token->meta,
            $token->tags
        );
    }

    public function fromTokenRegion(TokenRegion $region): NodeInterface
    {
        if ($region->hasTag(NodeType::Node->value)) {
            return new Node(
                $region->name,
                [],
                $region->meta,
                $region->tags
            );
        }

        if ($region->hasTag(NodeType::Structure->value)) {
            return new Structure(
                $region->name,
                true,
                $region->__toString(),
                $region->meta,
                $region->tags
            );
        }

        $openerInstance = null;
        if (
            $region->getMeta(RegionRawContent::REGION_INCLUDES_STRUCTURE_OPENER_KEY, false)
            && $region->stream->first()->hasTag(NodeType::Structure->value)
        ) {
            $opener = $region->stream->first();
            $openerInstance = new Structure(
                $opener->name,
                true,
                $opener->raw,
                $opener->meta,
                $opener->tags
            );
            $region->stream->remove(0);
        }

        $closerInstance = null;
        if (
            $region->getMeta(RegionRawContent::REGION_INCLUDES_STRUCTURE_CLOSER_KEY, false)
            && $region->stream->last()->hasTag(NodeType::Structure->value)
        ) {
            $closer = $region->stream->last();
            $closerInstance = new Structure(
                $closer->name,
                true,
                $closer->raw,
                $closer->meta,
                $closer->tags
            );
            $region->stream->remove($region->stream->lastOffset());
        }

        return new RegionRawContent(
            $region->name,
            $region->__toString(),
            $openerInstance,
            $closerInstance,
            $region->meta,
            $region->tags,
        );
    }

    public function fromMatchedRegion(MatchedRegion $region): NodeInterface
    {
        if ($region->hasTag(NodeType::Node->value)) {
            return new Node(
                $region->name,
                [],
                $region->meta,
                $region->tags,
            );
        }

        if ($region->hasTag(NodeType::Structure->value)) {
            return new Structure(
                $region->name,
                true,
                $region->__toString(),
                $region->meta,
                $region->tags,
            );
        }

        $openerInstance = null;
        if (
            $region->getMeta(RegionRawContent::REGION_INCLUDES_STRUCTURE_OPENER_KEY, false)
            && $region->firstItem()->hasTag(NodeType::Structure->value)
        ) {
            $opener = $region->firstItem();
            $openerInstance = new Structure(
                $opener->name,
                true,
                $opener->raw,
                $opener->meta,
                $opener->tags
            );
            $region->removeItem(0);
        }

        $closerInstance = null;
        if (
            $region->getMeta(RegionRawContent::REGION_INCLUDES_STRUCTURE_CLOSER_KEY, false)
            && $region->lastItem()->hasTag(NodeType::Structure->value)
        ) {
            $closer = $region->lastItem();
            $closerInstance = new Structure(
                $closer->name,
                true,
                $closer->raw,
                $closer->meta,
                $closer->tags
            );
            $region->removeItem($region->lastIndex());
        }

        return new RegionRawContent(
            $region->name,
            $region->__toString(),
            $openerInstance,
            $closerInstance,
            $region->meta,
            $region->tags,
        );
    }

    public function fromMatchedSequence(MatchedSequence $matchedSequence): NodeInterface
    {
        if ($matchedSequence->hasTag(NodeType::Node->value)) {
            return new Node(
                $matchedSequence->name,
                [],
                $matchedSequence->meta,
                $matchedSequence->tags,
            );
        }

        if ($matchedSequence->hasTag(NodeType::Structure->value)) {
            return new Structure(
                $matchedSequence->name,
                true,
                $matchedSequence->__toString(),
                $matchedSequence->meta,
                $matchedSequence->tags,
            );
        }

        return new RawContent(
            $matchedSequence->name,
            $matchedSequence->__toString(),
            $matchedSequence->meta,
            $matchedSequence->tags,
        );
    }

    public function fromMatchedSequenceNode(MatchedSequenceNode $node): NodeInterface
    {
        if ($node->hasTag(NodeType::Node->value)) {
            return new Node(
                $node->name,
                [],
                $node->meta,
                $node->tags,
            );
        }

        if ($node->hasTag(NodeType::Structure->value)) {
            $nodeContent = $node->__toString();
            return new Structure(
                $node->name,
                !empty($node->items),
                $nodeContent === '' ? null : $nodeContent,
                $node->meta,
                $node->tags,
            );
        }

        return new RawContent(
            $node->name,
            $node->__toString(),
            $node->meta,
            $node->tags,
        );
    }
}
